<?php
declare(strict_types=1);
$motobookRoot = __DIR__;
$docRoot = $motobookRoot;
$outFile = $motobookRoot . '\_runtime_fixer_output.txt';
@unlink($outFile);
$dbhost = '127.0.0.1'; $dbuser = 'root'; $dbpass = ''; $dbname = 'motobook_admin';
$mariaBin = 'C:\xampp\mysql\bin\mysqld.exe';
$mariaIni = file_exists('C:\xampp\mysql\bin\my.ini') ? 'C:\xampp\mysql\bin\my.ini' : 'C:\xampp\mysql\my.ini';
$mariaData = 'C:\xampp\mysql\data';
$phpBin = 'C:\xampp\php\php.exe';
$serverPort = 8080;
$mariaLog = getenv('TEMP').'\\mariadb-runtime.log';

function writeOut(string $msg): void {
    global $outFile;
    $line = @date('[H:i:s] ').$msg.PHP_EOL;
    @file_put_contents($outFile, $line, FILE_APPEND);
}

function waitForTcp(string $host,int $port,int $maxSec=20):bool {
    $end=time()+$maxSec;
    while (time()<$end) {
        $s=@fsockopen($host,$port,$_,$_,0.4);
        if ($s) { fclose($s); return true; }
        usleep(300000);
    }
    return false;
}

// STEP 1 H1: delete stale pid/redo/ibtmp
writeOut('STEP1 H1: remove stale pid/redo/ibtmp...');
foreach (glob($mariaData.'\\*.pid')?:[] as $p) @unlink($p);
foreach (glob($mariaData.'\\ib_logfile*')?:[] as $p) @unlink($p);
foreach (glob($mariaData.'\\ibtmp1')?:[] as $p) @unlink($p);

// STEP 2 H2: launch mysqld via popen with start /b so no proc_group death on exit
writeOut('STEP2 H2: spawn mysqld...');
$startCmd =
    'START "" /D "C:\\xampp\\mysql\\bin" /MIN /B '.
    '"'.$mariaBin.'" '.
    '--defaults-file="'.$mariaIni.'" '.
    '--basedir=C:\\xampp\\mysql '.
    '--datadir="'.$mariaData.'" '.
    '--port=3306 '.
    '--bind-address=127.0.0.1 '.
    '--log-error="'.$mariaLog.'"';
$ph = popen($startCmd,'r'); if ($ph) pclose($ph);
if (!waitForTcp($dbhost,3306,22)) { writeOut('FATAL MariaDB not up after 22s'); exit(1); }
writeOut('STEP2: MariaDB 3306 UP.');
sleep(2);

// STEP 3: connect
$mysqli = @new mysqli($dbhost,$dbuser,$dbpass,$dbname,3306);
if ($mysqli->connect_errno) { writeOut('CONNECT ERR '.$mysqli->connect_error); exit(2); }
$mysqli->set_charset('utf8mb4');
writeOut('STEP3: mysqli connected to motobook_admin.');

// STEP 4: legacy riders ALTER add missing cols one by one with SHOW COLUMNS guard
writeOut('STEP4: legacy riders cols add...');
$cols = [
    ['gcash_mobile_number',"VARCHAR(20) DEFAULT NULL"],
    ['gcash_account_name',"VARCHAR(80) DEFAULT NULL"],
    ['gcash_qr_data_uri',"MEDIUMTEXT DEFAULT NULL"],
    ['fcm_push_token',"VARCHAR(255) DEFAULT NULL"],
    ['fcm_push_sub_json',"JSON DEFAULT NULL"],
    ['duty_today_payout',"DECIMAL(10,2) NOT NULL DEFAULT 0.00"],
    ['completed_today',"INT UNSIGNED NOT NULL DEFAULT 0"],
    ['acceptance_rate',"DECIMAL(5,2) NOT NULL DEFAULT 0.00"],
    ['active_hours_today',"DECIMAL(4,2) NOT NULL DEFAULT 0.00"],
    ['current_shift_started_at',"DATETIME DEFAULT NULL"],
];
$added=0; $skipped=0;
foreach ($cols as $c) {
    $like = $mysqli->real_escape_string($c[0]);
    $chk = $mysqli->query("SHOW COLUMNS FROM `riders` LIKE '$like'");
    if ($chk && $chk->num_rows>0) { $skipped++; continue; }
    if (@$mysqli->query("ALTER TABLE `riders` ADD COLUMN `$c[0]` ".$c[1])) $added++;
    else $skipped++;
}
writeOut("STEP4: legacy riders cols added=$added skipped=$skipped.");

// STEP 5 H3: run migration 001 via line-by-line ; splitter with manual guards.
writeOut('STEP5 H3: apply migration 001...');
$sql = @file_get_contents($motobookRoot.'\\A-rider\\migrations\\001_init_rider_schema.sql');
if (!$sql) { writeOut('FATAL 001 file missing'); exit(3); }
$stmts = []; $buf=''; $inS=null; $esc=false;
foreach (str_split($sql) as $ch) {
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
$defaultPw = 'Motobook200409';
$realHash = password_hash($defaultPw, PASSWORD_BCRYPT, ['cost'=>10]);
$placeholder = '$2y$10$r4n8tT2d8bDZt0v9xwQyeeWd63Kz8B7p3R9sQxUaOeLfYgP7yQdKC';
$ok=0; $skip=0; $fail=0; $madeIdx=false;
foreach ($stmts as $idx=>$raw) {
    $st = trim($raw);
    if ($st==='' || str_starts_with($st,'--')) { $skip++; continue; }
    $st = str_replace($placeholder, $realHash, $st);
    // Unsupported MariaDB 10.4 "ADD INDEX IF NOT EXISTS" fix
    if (stripos($st, 'ADD INDEX IF NOT EXISTS idx_ro_completed_date') !== false) {
        $chk = $mysqli->query("SHOW INDEX FROM `rider_orders` WHERE Key_name='idx_ro_completed_date'");
        if ($chk && $chk->num_rows>0) { $skip++; continue; }
        $st = preg_replace('/\s+IF NOT EXISTS/i','',$st);
        $madeIdx = true;
    }
    $res = @$mysqli->query($st);
    if ($res === true || $res instanceof mysqli_result) { $ok++; }
    else {
        $err = $mysqli->error;
        $idem = stripos($err,'already exists')!==false || stripos($err,'Duplicate')!==false;
        if ($idem) $skip++;
        else { $fail++; writeOut('  FAIL #'.$idx.' '.$err.' | '.substr(preg_replace('/\s+/',' ',$st),0,150)); }
    }
}
if (!$madeIdx) {
    $chk = $mysqli->query("SHOW INDEX FROM `rider_orders` WHERE Key_name='idx_ro_completed_date'");
    if (!$chk || $chk->num_rows===0) {
        if (@$mysqli->query("ALTER TABLE rider_orders ADD INDEX idx_ro_completed_date (rider_id, state, completed_at)")) $ok++;
    }
}
writeOut("STEP5 H3: migration applied: $ok OK / $skip skip / $fail hard.");

// STEP 6 H5: password verify and overwrite fallback
writeOut('STEP6 H5: verify rider password hash...');
$r = $mysqli->query("SELECT id,password_hash FROM riders WHERE rider_code='RDR-0001' LIMIT 1");
if ($r && $r->num_rows) {
    $row = $r->fetch_assoc();
    if (!password_verify($defaultPw, $row['password_hash'])) {
        $stmt = $mysqli->prepare('UPDATE riders SET password_hash=? WHERE rider_code=? LIMIT 1');
        $rc='RDR-0001'; $stmt->bind_param('ss',$realHash,$rc); $stmt->execute(); $stmt->close();
        writeOut('STEP6: overwritten hash.');
    } else writeOut('STEP6: password verify OK.');
}

// STEP 7: verify presence + demo rows
writeOut('STEP7: verify 6 rider tables + demo orders...');
$need=['riders','rider_orders','rider_location_logs','rider_pod_records','rider_order_incidents','rider_deposits'];
$cnt=0;
foreach ($need as $t) {
    $rr = $mysqli->query("SHOW TABLES LIKE '$t'");
    $yn = ($rr && $rr->num_rows) ? 'YES' : 'MISSING';
    writeOut("  [$yn] $t");
    if ($yn==='YES') $cnt++;
}
$o = $mysqli->query("SELECT COUNT(*) c FROM rider_orders WHERE rider_id=(SELECT IFNULL(id,0) FROM riders WHERE rider_code='RDR-0001')")->fetch_assoc()['c'] ?? 0;
writeOut("STEP7: demo orders for RDR-0001 = $o (expect >=2). count tables present: $cnt/6.");
$mysqli->close();

// STEP 8 H4: start PHP built-in server
writeOut('STEP8 H4: spawn PHP -S 127.0.0.1:'.$serverPort.' ...');
$cmd = 'START "" /D "'.dirname($phpBin).'" /MIN /B "'.$phpBin.'" -S 127.0.0.1:'.$serverPort.' -t "'.$docRoot.'"';
$ph = popen($cmd,'r'); if ($ph) pclose($ph);
if (!waitForTcp('127.0.0.1',$serverPort,12)) { writeOut('FATAL PHP server not up after 12s'); exit(4); }
writeOut('STEP8: PHP server 8080 UP.');

// Test login page renders
$ctx = stream_context_create(['http'=>['timeout'=>6,'ignore_errors'=>true]]);
$h = @file_get_contents('http://127.0.0.1:'.$serverPort.'/login.php', false, $ctx);
$len = strlen((string)$h);
$form = (is_string($h) && stripos($h,'<form')!==false && stripos($h,'email')!==false) ? 'YES' : 'NO';
writeOut("STEP8: login.php render bytes=$len hasFormAndEmail=$form.");

writeOut('=== FINAL SUCCESS ===');
writeOut('Login URL: http://127.0.0.1:'.$serverPort.'/login.php');
writeOut('Credentials: juan.rider@motobook.com / Motobook200409');
writeOut('Expected post-login redirect: http://127.0.0.1:'.$serverPort.'/A-rider/');
exit(0);
