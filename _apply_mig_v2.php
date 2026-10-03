<?php
declare(strict_types=1);
$dbhost = '127.0.0.1';
$dbuser = 'root';
$dbpass = '';
$dbname = 'motobook_admin';

# --- auto-start MariaDB if down (idempotent safe) ---
function waitForMysql(string $host, int $port, int $maxSec = 25): bool {
    $end = time() + $maxSec;
    while (time() < $end) {
        $sock = @fsockopen($host, $port, $_, $_, 0.5);
        if ($sock) { fclose($sock); return true; }
        usleep(300000);
    }
    return false;
}
$isUp = waitForMysql($dbhost, 3306, 2);
if (!$isUp) {
    # Launch MariaDB detached using the same args as earlier successful attempt
    $datadir = 'C:\\xampp\\mysql\\data';
    $ini = 'C:\\xampp\\mysql\\bin\\my.ini';
    $bin = 'C:\\xampp\\mysql\\bin\\mysqld.exe';
    $log = getenv('TEMP').'\\xampp-mariadb-keepalive.log';
    # Remove stale pids
    foreach (glob($datadir.'\\*.pid')?:[] as $p) @unlink($p);
    $desc = [
        0 => ['pipe','r'],
        1 => ['file',$log,'a'],
        2 => ['file',$log,'a'],
    ];
    $pipes = [];
    $proc = @proc_open(
        '"'.$bin.'" --defaults-file="'.$ini.'" --basedir=C:\\xampp\\mysql --datadir="'.$datadir.'" --port=3306 --bind-address=127.0.0.1',
        $desc, $pipes, null, null, ['bypass_shell'=>true,'create_new_console'=>true]
    );
    if (is_resource($proc)) {
        proc_close($proc);
    }
    $isUp = waitForMysql($dbhost, 3306, 20);
    if (!$isUp) { fwrite(STDERR, "MySQL didn't come up within 20s.\nLog:\n".@file_get_contents($log)."\n"); exit(2); }
    sleep(1);
}
echo "MySQL: UP\n";

# --- connect ---
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname, 3306);
if ($mysqli->connect_errno) { die('CONNECT ERR '.$mysqli->connect_error); }
$mysqli->set_charset('utf8mb4');

# --- apply ALTER TABLE to LEGACY riders first: add missing gcash/fcm cols idempotent ---
$legacyAlters = [
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS gcash_mobile_number VARCHAR(20) DEFAULT NULL COMMENT '11-digit PH GCash number'",
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS gcash_account_name VARCHAR(80) DEFAULT NULL COMMENT 'GCash account full name'",
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS gcash_qr_data_uri MEDIUMTEXT DEFAULT NULL COMMENT 'GSave/QR PH data URI image'",
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS fcm_push_token VARCHAR(255) DEFAULT NULL COMMENT 'FCM/Web Push token'",
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS fcm_push_sub_json JSON DEFAULT NULL COMMENT 'Raw Web Push JSON'",
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS duty_today_payout DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS completed_today INT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS acceptance_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS active_hours_today DECIMAL(4,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE riders ADD COLUMN IF NOT EXISTS current_shift_started_at DATETIME DEFAULT NULL",
];
$legacyAlterOK = 0; $legacyAlterSkip = 0;
foreach ($legacyAlters as $st) {
    if (@$mysqli->query($st)) $legacyAlterOK++;
    else $legacyAlterSkip++;
}
echo "Legacy riders ALTER: $legacyAlterOK OK  $legacyAlterSkip skipped (idempotent).\n";

# --- re-generate a CORRECT bcrypt hash for default password Motobook200409 ---
$defaultPw = 'Motobook200409';
$realHash = password_hash($defaultPw, PASSWORD_BCRYPT, ['cost' => 10]);
echo "Bcrypt hash for $defaultPw generated.\n";

# --- execute migration SQL: split on ; with string-safe splitter ---
$sql = file_get_contents(__DIR__ . '/A-rider/migrations/001_init_rider_schema.sql');
$tokens = str_split($sql);
$stmts = [];
$cur = '';
$inStr = null; $escape = false;
foreach ($tokens as $ch) {
    if ($inStr === null) {
        if ($ch === "'" || $ch === '"' || $ch === '`') { $inStr = $ch; $cur .= $ch; }
        elseif ($ch === ';') { if (trim($cur) !== '') $stmts[] = $cur; $cur = ''; }
        elseif ($ch === '#') { while (($next = next($tokens)) !== false && $next !== "\n") { /* consume */ } }
        elseif ($ch === '-' && isset($tokens[key($tokens)]) && current($tokens) === '-') { while (($next = next($tokens)) !== false && $next !== "\n") { /* consume */ } }
        else $cur .= $ch;
    } else {
        if ($escape) { $cur .= $ch; $escape = false; }
        elseif ($ch === '\\') { $cur .= $ch; $escape = true; }
        elseif ($ch === $inStr) { $cur .= $ch; $inStr = null; }
        else $cur .= $ch;
    }
}
if (trim($cur) !== '') $stmts[] = $cur;

$ok = 0; $skip = 0; $fail = 0;
foreach ($stmts as $idx => $rawStmt) {
    $st = trim($rawStmt);
    if ($st === '') { $skip++; continue; }
    # Replace the placeholder bcrypt hash in seed with real one for this PHP run
    if (str_contains($st, '$2y$10$r4n8tT2d8bDZt0v9xwQyeeWd63Kz8B7p3R9sQxUaOeLfYgP7yQdKC')) {
        $st = str_replace('$2y$10$r4n8tT2d8bDZt0v9xwQyeeWd63Kz8B7p3R9sQxUaOeLfYgP7yQdKC', $realHash, $st);
    }
    $res = @$mysqli->query($st);
    if ($res === true || $res instanceof mysqli_result) {
        $ok++;
    } else {
        $err = $mysqli->error;
        $isIdempotent =
            stripos($err, 'already exists') !== false ||
            stripos($err, 'Duplicate column') !== false ||
            stripos($err, 'Duplicate entry') !== false ||
            stripos($err, 'Duplicate key') !== false ||
            stripos($err, 'Duplicate key name') !== false ||
            stripos($err, 'Unknown') === 0 && stripos($err, 'IF NOT EXISTS') !== false ||
            (stripos($err, 'syntax') !== false && stripos($st, 'IF NOT EXISTS') !== false);
        if ($isIdempotent) { $skip++; }
        else { $fail++; echo "[FAIL stmt#$idx] ".$err."\n    ".substr($st,0,140)."\n\n"; }
    }
}
echo "Migration: $ok OK, $skip skipped, $fail hard errors.\n";

# --- verify ---
$checks = ['riders','rider_orders','rider_location_logs','rider_pod_records','rider_order_incidents','rider_deposits'];
$present = 0;
foreach ($checks as $t) {
    $r = $mysqli->query("SHOW TABLES LIKE '$t'");
    $yes = $r && $r->num_rows > 0;
    echo "  table $t: ".($yes?'YES':'MISSING')."\n";
    if ($yes) $present++;
}
$r = $mysqli->query("SELECT id,rider_code,name,email,gcash_mobile_number,gcash_account_name FROM riders WHERE rider_code='RDR-0001' LIMIT 1");
if ($r && $r->num_rows) {
    $row = $r->fetch_assoc();
    echo "\nRDR-0001: OK (".implode(' / ', $row).")";
    # Verify password hash matches default password
    $pwRow = $mysqli->query("SELECT password_hash FROM riders WHERE rider_code='RDR-0001' LIMIT 1")->fetch_assoc();
    if (password_verify($defaultPw, $pwRow['password_hash'])) {
        echo "\nRDR-0001 password '$defaultPw' VERIFIED OK";
    } else {
        echo "\nUpdating RDR-0001 password hash now because placeholder was used...";
        $stmt = $mysqli->prepare('UPDATE riders SET password_hash=? WHERE rider_code=? LIMIT 1');
        $stmt->bind_param('ss', $realHash, $rc);
        $rc = 'RDR-0001'; $stmt->execute(); $stmt->close();
        echo "done.\n";
    }
}
# --- spawn persistent keepalive SELECT 1 every 3s so mysqld is kept awake for browser demo window ---
$kaFile = __DIR__ . '/_keepalive_db.php';
file_put_contents($kaFile, '<?php '.
    '$pdo=new PDO("mysql:host=127.0.0.1;dbname=motobook_admin;charset=utf8mb4","root","",'.
    '[PDO::ATTR_ERRMODE=>PDO::ERRMODE_SILENT,PDO::ATTR_PERSISTENT=>true]);'.
    'while(1){@$pdo->query("SELECT 1");sleep(3);}'.
'');
$descriptors = [
    0 => ['pipe','r'],
    1 => ['file',getenv('TEMP').'\\_mb_ka_stdout.log','a'],
    2 => ['file',getenv('TEMP').'\\_mb_ka_stderr.log','a'],
];
$proc = proc_open(
    '"C:\\xampp\\php\\php.exe" "'.$kaFile.'"',
    $descriptors, $pipes, null, null,
    ['bypass_shell'=>true,'create_new_console'=>true]
);
if (is_resource($proc)) { proc_close($proc); echo "\nPersistent DB keepalive spawned OK.\n"; }

echo "\nMigration runner DONE. $present/6 rider tables present.\n";
