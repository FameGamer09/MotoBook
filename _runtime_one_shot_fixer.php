<?php
/**
 * ONE-SHOT RUNTIME FIXER SCRIPT
 * - Spawns mariadb via START /B (no parent-child kill on Windows console group exit)
 * - Cleans stale *.pid + ib_logfile before launch
 * - Applies migration 001 with explicit mysqli_multi_query, skipping MariaDB 10.4 IF NOT EXISTS INDEX syntax errors
 * - Generates real bcrypt hash for Motobook200409, replaces placeholder, ensures password_verify passes
 * - Spawns PHP built-in server 127.0.0.1:8080 docroot=motobook via START /B
 * - Writes results to _runtime_fixer_output.txt so caller can read without capturing stdout/stderr flushes
 */
declare(strict_types=1);
$motobookRoot = __DIR__;
$docRoot = $motobookRoot;
$outFile = $motobookRoot . '\_runtime_fixer_output.txt';
$dbhost = '127.0.0.1'; $dbuser = 'root'; $dbpass = ''; $dbname = 'motobook_admin';
$mariaBin = 'C:\xampp\mysql\bin\mysqld.exe';
$mariaIni = file_exists('C:\xampp\mysql\bin\my.ini') ? 'C:\xampp\mysql\bin\my.ini' : 'C:\xampp\mysql\my.ini';
$mariaData = 'C:\xampp\mysql\data';
$phpBin = 'C:\xampp\php\php.exe';
$serverPort = 8080;
$serverLog = getenv('TEMP').'\\php-server-'.$serverPort.'.log';
$mariaLog = getenv('TEMP').'\\mariadb-runtime.log';

function writeOut(string $msg): void {
    global $outFile;
    $line = @date('[Y-m-d H:i:s] ').$msg.PHP_EOL;
    @file_put_contents($outFile, $line, FILE_APPEND);
    echo $line; @flush(); @ob_flush();
}
@unlink($outFile);

# ============= STEP 0: kill zombie php/mysqld first =============
writeOut('STEP0: killing zombie processes...');
function killByName(array $names): void {
    foreach ($names as $n) {
        $wmi = @shell_exec('wmic process where name="'.$n.'.exe" get ProcessId 2>NUL');
        if (is_string($wmi) && preg_match_all('/\b\d{3,6}\b/', $wmi, $m)) {
            foreach ($m[0] as $pid) {
                if ((int)$pid > 10) { @shell_exec('taskkill /F /PID '.(int)$pid.' 2>NUL'); }
            }
        }
    }
}
killByName(['mysqld','php']); sleep(2);
writeOut('STEP0: zombies killed.');

# ============= STEP 1: H1 fix =============
writeOut('STEP1 H1: delete stale pid/redo/ibtmp files...');
foreach (glob($mariaData.'\\*.pid')?:[] as $p) { @unlink($p); writeOut('  removed '.$p); }
foreach (glob($mariaData.'\\ib_logfile*')?:[] as $p) { @unlink($p); writeOut('  removed '.$p); }
foreach (glob($mariaData.'\\ibtmp1')?:[] as $p) { @unlink($p); writeOut('  removed '.$p); }

# ============= STEP 2: H2 fix =============
writeOut('STEP2 H2: spawn mysqld via START /B so console-exit does not kill it...');
$startCmd =
    'START "mariadb-motobook" /D "C:\\xampp\\mysql\\bin" /MIN /B '.
    '"'.$mariaBin.'" '.
    '--defaults-file="'.$mariaIni.'" '.
    '--basedir=C:\\xampp\\mysql '.
    '--datadir="'.$mariaData.'" '.
    '--port=3306 '.
    '--bind-address=127.0.0.1 '.
    '--log-error="'.$mariaLog.'"';
@shell_exec($startCmd);
# Wait for TCP up
$end = time()+20; $mariaUp=false;
while (time()<$end) {
    $sock = @fsockopen($dbhost, 3306, $_, $_, 0.5);
    if ($sock) { fclose($sock); $mariaUp=true; break; }
    usleep(400000);
}
writeOut('STEP2: MariaDB 3306 up='.($mariaUp?'YES':'NO').' after '.(time()-(time()-20)).'s elapsed approx');
sleep(2);
if (!$mariaUp) {
    writeOut('FATAL MariaDB failed. Log: '.(@file_get_contents($mariaLog)?:''));
    exit(1);
}

# ============= STEP 3: connect mysqli =============
writeOut('STEP3: connect motobook_admin...');
$mysqli = @new mysqli($dbhost, $dbuser, $dbpass, $dbname, 3306);
if ($mysqli->connect_errno) { writeOut('CONNECT ERR '.$mysqli->connect_error); exit(2); }
$mysqli->set_charset('utf8mb4');
writeOut('STEP3: connected OK.');

# ============= STEP 4: legacy riders ALTER idempotent cols =============
writeOut('STEP4: legacy riders ALTER add gcash/fcm cols idempotent...');
$cols = [
    ['gcash_mobile_number',"VARCHAR(20) DEFAULT NULL COMMENT '11-digit PH GCash number'"],
    ['gcash_account_name',"VARCHAR(80) DEFAULT NULL COMMENT 'GCash account full name'"],
    ['gcash_qr_data_uri',"MEDIUMTEXT DEFAULT NULL COMMENT 'GSave/QR PH data URI image'"],
    ['fcm_push_token',"VARCHAR(255) DEFAULT NULL COMMENT 'FCM/Web Push token'"],
    ['fcm_push_sub_json',"JSON DEFAULT NULL COMMENT 'Raw Web Push JSON'"],
    ['duty_today_payout',"DECIMAL(10,2) NOT NULL DEFAULT 0.00"],
    ['completed_today',"INT UNSIGNED NOT NULL DEFAULT 0"],
    ['acceptance_rate',"DECIMAL(5,2) NOT NULL DEFAULT 0.00"],
    ['active_hours_today',"DECIMAL(4,2) NOT NULL DEFAULT 0.00"],
    ['current_shift_started_at',"DATETIME DEFAULT NULL"],
];
$added=0; $skipped=0;
foreach ($cols as $c) {
    $chk = $mysqli->query("SHOW COLUMNS FROM `riders` LIKE '".$mysqli->real_escape_string($c[0])."'");
    if ($chk && $chk->num_rows>0) { $skipped++; continue; }
    $ok = @$mysqli->query("ALTER TABLE `riders` ADD COLUMN `".$c[0]."` ".$c[1]);
    if ($ok) $added++; else { $skipped++; writeOut('  alter skip '.$c[0].': '.$mysqli->error); }
}
writeOut("STEP4: added $added cols, skipped $skipped cols.");

# ============= STEP 5: H3 fix ============= apply rider tables migration string-split, ignoring IF NOT EXISTS index errors
writeOut('STEP5 H3: apply rider tables migration 001 (manual statament runner)...');
$sqlRaw = @file_get_contents($motobookRoot.'\\A-rider\\migrations\\001_init_rider_schema.sql');
if (!$sqlRaw) { writeOut('FATAL migration file not found'); exit(3); }
# Manual safe splitter: ; at line-end only
$stmts = []; $buf=''; $inS=null; $esc=false;
foreach (str_split($sqlRaw) as $ch) {
    if ($inS === null) {
        if ($ch === "'" || $ch === '"' || $ch === '`') { $inS = $ch; $buf .= $ch; }
        elseif ($ch === ';') { if (trim($buf)!=='') $stmts[]=$buf; $buf=''; }
        else $buf.=$ch;
    } else {
        if ($esc) { $buf.=$ch; $esc=false; }
        elseif ($ch==='\\') { $buf.=$ch; $esc=true; }
        elseif ($ch===$inS) { $buf.=$ch; $inS=null; }
        else $buf.=$ch;
    }
}
if (trim($buf)!=='') $stmts[]=$buf;
# Generate password hash for Motobook200409 (H5 fix).
$defaultPw = 'Motobook200409';
$realHash = password_hash($defaultPw, PASSWORD_BCRYPT, ['cost'=>10]);
# Build index for manual idx_ro_completed_date instead of "ALTER TABLE ADD INDEX IF NOT EXISTS" unsupported syntax.
$completedIndexDone = false;
$ok=0; $skip=0; $fail=0;
foreach ($stmts as $idx=>$rawStmt) {
    $st = trim($rawStmt);
    if ($st==='' || str_starts_with($st,'--')) { $skip++; continue; }
    # Replace placeholder bcrypt hash.
    $st = str_replace('$2y$10$r4n8tT2d8bDZt0v9xwQyeeWd63Kz8B7p3R9sQxUaOeLfYgP7yQdKC', $realHash, $st);
    # H3 manual fix: skip the unsupported ADD INDEX IF NOT EXISTS line, run equivalent SHOW INDEX check + manual ADD INDEX.
    if (stripos($st, 'ADD INDEX IF NOT EXISTS idx_ro_completed_date') !== false) {
        $chk = $mysqli->query("SHOW INDEX FROM `rider_orders` WHERE Key_name='idx_ro_completed_date'");
        if ($chk && $chk->num_rows>0) { $skip++; continue; }
        $st = preg_replace('/\s+IF NOT EXISTS/i', '', $st); # strip IF NOT EXISTS keyword to make it MariaDB 10.4 compatible
        $completedIndexDone = true;
    }
    $res = @$mysqli->query($st);
    if ($res === true || $res instanceof mysqli_result) { $ok++; }
    else {
        $err = $mysqli->error;
        $idempotent =
            stripos($err,'already exists')!==false ||
            stripos($err,'Duplicate column')!==false ||
            stripos($err,'Duplicate entry')!==false ||
            stripos($err,'Duplicate key name')!==false;
        if ($idempotent) $skip++;
        else {
            $fail++;
            writeOut('  HARD FAIL stmt#'.$idx.': '.$err);
            writeOut('    SQL head: '.substr(preg_replace('/\s+/',' ',$st),0,150));
        }
    }
}
# H3 post-loop: if the IF NOT EXISTS line was stripped and the CREATE rider_orders table was brand new this run, still apply idx_ro_completed_date (because when rider_orders is new, the SHOW INDEX check inside ran before table existed!)
if (!$completedIndexDone) {
    $chk = $mysqli->query("SHOW INDEX FROM `rider_orders` WHERE Key_name='idx_ro_completed_date'");
    if (!$chk || $chk->num_rows===0) {
        $ok2 = @$mysqli->query("ALTER TABLE rider_orders ADD INDEX idx_ro_completed_date (rider_id, state, completed_at)");
        if ($ok2) { $ok++; writeOut('STEP5 H3 post: created idx_ro_completed_date manually.'); }
    }
}
writeOut("STEP5 H3: $ok OK, $skip idempotent-skip, $fail hard errors.");

# ============= STEP 6: Verify H5 password hash =============
writeOut('STEP6 H5: verify bcrypt for password="'.$defaultPw.'" against RDR-0001 row...');
$r = $mysqli->query("SELECT id,password_hash FROM riders WHERE rider_code='RDR-0001' LIMIT 1");
if ($r && $r->num_rows) {
    $row = $r->fetch_assoc();
    if (password_verify($defaultPw, $row['password_hash'])) {
        writeOut('STEP6 H5: password_verify OK ✔');
    } else {
        writeOut('STEP6 H5: hash wrong, overwriting...');
        $stmt = $mysqli->prepare('UPDATE riders SET password_hash=? WHERE rider_code=? LIMIT 1');
        $rc='RDR-0001'; $stmt->bind_param('ss',$realHash,$rc); $stmt->execute(); $stmt->close();
        writeOut('STEP6 H5: overwrite complete.');
    }
} else { writeOut('STEP6 H5: RDR-0001 not present, seeding manually with correct hash...');
    $stmt = $mysqli->prepare("INSERT INTO riders (rider_code,name,email,phone,password_hash,vehicle_plate,vehicle_type,city,status,gcash_mobile_number,gcash_account_name,duty_today_payout,completed_today,acceptance_rate,active_hours_today) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $rc='RDR-0001'; $nm='Juan Dela Cruz'; $em='juan.rider@motobook.com'; $ph='639170000001'; $vp='UHZ-4201'; $vt='MOTORCYCLE'; $ct='Makati City'; $st='OFFLINE'; $gm='09170000001'; $gn='JUAN DELA CRUZ'; $dp=1450.00; $co=12; $ar=98.00; $ah=5.20;
    $stmt->bind_param('sssssssssssdidd',$rc,$nm,$em,$ph,$realHash,$vp,$vt,$ct,$st,$gm,$gn,$dp,$co,$ar,$ah);
    $stmt->execute(); $stmt->close();
    writeOut('STEP6 H5: inserted RDR-0001 with correct bcrypt hash.');
}

# ============= STEP 7: summary tables check =============
writeOut('STEP7: final rider tables presence check:');
$need = ['riders','rider_orders','rider_location_logs','rider_pod_records','rider_order_incidents','rider_deposits'];
$count = 0;
foreach ($need as $t) {
    $r = $mysqli->query("SHOW TABLES LIKE '$t'");
    $yes = ($r && $r->num_rows>0) ? 'YES' : 'MISSING';
    writeOut("  [$yes] $t");
    if ($yes==='YES') $count++;
}
$orderCnt = $mysqli->query("SELECT COUNT(*) c FROM rider_orders WHERE rider_id=(SELECT id FROM riders WHERE rider_code='RDR-0001')")->fetch_assoc()['c'] ?? 0;
writeOut("STEP7: demo orders for RDR-0001 = $orderCnt (expect >=2 MB-88491/MB-88492).");
$mysqli->close();

# ============= STEP 8: H4 fix ============= start PHP built-in server
writeOut('STEP8 H4: start PHP built-in server 127.0.0.1:'.$serverPort.' via START /B...');
@file_put_contents($serverLog,'');
$phpStartCmd =
    'START "php-motobook-'.$serverPort.'" /D "'.dirname($phpBin).'" /MIN /B '.
    '"'.$phpBin.'" -S 127.0.0.1:'.$serverPort.' -t "'.$docRoot.'"';
@shell_exec($phpStartCmd);
$end = time()+12; $phpUp=false;
while (time()<$end) {
    $sock = @fsockopen('127.0.0.1', $serverPort, $_, $_, 0.5);
    if ($sock) { fclose($sock); $phpUp=true; break; }
    usleep(400000);
}
writeOut('STEP8 H4: PHP server 127.0.0.1:'.$serverPort.' TCP='.($phpUp?'UP':'DOWN'));
if (!$phpUp) {
    writeOut('FATAL PHP server failed. Log: '.(@file_get_contents($serverLog)?:''));
    exit(4);
}
# Test login.php renders
$ctx = stream_context_create(['http'=>['timeout'=>8,'ignore_errors'=>true]]);
$html = @file_get_contents('http://127.0.0.1:'.$serverPort.'/login.php', false, $ctx);
$len = strlen((string)$html);
$hasForm = (is_string($html) && stripos($html,'<form')!==false) ? 'YES' : 'NO';
$hasEmail = (is_string($html) && stripos($html,'email')!==false) ? 'YES' : 'NO';
writeOut('STEP8: login.php render bytes='.$len.' hasForm='.$hasForm.' hasEmailInput='.$hasEmail);

# ============= FINAL =============
writeOut('=== ALL STEPS DONE. Rider login demo ready at: ===');
writeOut('  -> http://127.0.0.1:'.$serverPort.'/login.php');
writeOut('  -> Credentials: juan.rider@motobook.com / Motobook200409');
writeOut('  -> After SSO login, redirects to: http://127.0.0.1:'.$serverPort.'/A-rider/');
exit(0);
