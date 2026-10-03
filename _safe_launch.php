<?php
/**
 * SAFE MOTOBOOK RIDER DEMO LAUNCHER
 * - NO deletes of ib_logfiles, pid files, or user data
 * - Uses Windows "START /MIN /B" to detach MariaDB + PHP server so they don't die on exit
 * - Uses SHOW COLUMNS / SHOW INDEX guards before every ALTER/CREATE to be idempotent
 * - Writes all progress to _safe_launch.log
 */
declare(strict_types=1);
$root = __DIR__;
$logFile = $root . DIRECTORY_SEPARATOR . '_safe_launch.log';
@unlink($logFile);

function println(string $msg): void {
    global $logFile;
    $line = @date('[H:i:s] ') . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function waitTcp(string $host, int $port, int $max = 20): bool {
    $end = time() + $max;
    while (time() < $end) {
        $s = @fsockopen($host, $port, $_, $_, 0.5);
        if ($s) { fclose($s); return true; }
        usleep(300000);
    }
    return false;
}

// ---------------------------
// STEP 1: start MariaDB (safe)
// ---------------------------
println('STEP 1: start MariaDB on 3306 using START /B (no PS args)...');
$mariaBin = 'C:\xampp\mysql\bin\mysqld.exe';
$mariaIni = file_exists('C:\xampp\mysql\bin\my.ini') ? 'C:\xampp\mysql\bin\my.ini' : 'C:\xampp\mysql\my.ini';
$mariaData = 'C:\xampp\mysql\data';
$mariaErr = getenv('TEMP') . '\mariadb-safe-launch.err';
$cmd =
    'START "" /D "C:\xampp\mysql\bin" /MIN /B ' .
    '"' . $mariaBin . '" ' .
    '--defaults-file="' . $mariaIni . '" ' .
    '--basedir=C:\xampp\mysql ' .
    '--datadir="' . $mariaData . '" ' .
    '--port=3306 ' .
    '--bind-address=127.0.0.1 ' .
    '--log-error="' . $mariaErr . '"';
// Use pclose/popen so shell START runs and returns immediately.
$ph = popen($cmd, 'r'); if ($ph) pclose($ph);
$up = waitTcp('127.0.0.1', 3306, 25);
println('MariaDB TCP after wait: ' . ($up ? 'UP' : 'DOWN'));
if (!$up) {
    println('ERROR: MariaDB failed to start. Last err log:');
    println(@file_get_contents($mariaErr) ?: '(empty)');
    exit(1);
}
sleep(2);

// ---------------------------
// STEP 2: connect motobook_admin
// ---------------------------
println('STEP 2: connect motobook_admin mysqli...');
$mysqli = @new mysqli('127.0.0.1', 'root', '', 'motobook_admin', 3306);
if ($mysqli->connect_errno) {
    println('CONNECT FAIL: ' . $mysqli->connect_error);
    exit(2);
}
$mysqli->set_charset('utf8mb4');
println('Connected OK.');

// ---------------------------
// STEP 3: LEGACY RIDERS — ADD MISSING COLS VIA SHOW COLUMNS GUARD
// ---------------------------
println('STEP 3: add missing gcash/fcm cols to legacy riders (SHOW COLUMNS guards)...');
$wantCols = [
    'gcash_mobile_number'   => "VARCHAR(20) DEFAULT NULL",
    'gcash_account_name'    => "VARCHAR(80) DEFAULT NULL",
    'gcash_qr_data_uri'     => "MEDIUMTEXT DEFAULT NULL",
    'fcm_push_token'        => "VARCHAR(255) DEFAULT NULL",
    'fcm_push_sub_json'     => "JSON DEFAULT NULL",
    'duty_today_payout'     => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    'completed_today'       => "INT UNSIGNED NOT NULL DEFAULT 0",
    'acceptance_rate'       => "DECIMAL(5,2) NOT NULL DEFAULT 0.00",
    'active_hours_today'    => "DECIMAL(4,2) NOT NULL DEFAULT 0.00",
    'current_shift_started_at' => "DATETIME DEFAULT NULL",
];
$added = 0; $skip = 0;
foreach ($wantCols as $name => $def) {
    $like = $mysqli->real_escape_string($name);
    $r = $mysqli->query("SHOW COLUMNS FROM `riders` LIKE '$like'");
    if ($r && $r->num_rows > 0) { $skip++; continue; }
    if (@$mysqli->query("ALTER TABLE `riders` ADD COLUMN `$name` $def")) $added++;
    else $skip++;
}
println("  added=$added  skipped=$skip");

// ---------------------------
// STEP 4: create rider tables from migration 001, using SHOW TABLES guards
// ---------------------------
println('STEP 4: create rider tables via migration 001...');
$tables = [
    'riders' => /** @lang MariaDB */ <<<'SQL'
CREATE TABLE riders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_code VARCHAR(24) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL, email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(24) DEFAULT NULL, password_hash VARCHAR(255) NOT NULL,
  vehicle_plate VARCHAR(32) DEFAULT NULL,
  vehicle_type ENUM('MOTORCYCLE','EBIKE','CAR','VAN') DEFAULT 'MOTORCYCLE',
  city VARCHAR(80) DEFAULT NULL,
  status ENUM('ACTIVE','INACTIVE','ON_SHIFT','OFFLINE','SUSPENDED') DEFAULT 'OFFLINE',
  gcash_mobile_number VARCHAR(20) DEFAULT NULL, gcash_account_name VARCHAR(80) DEFAULT NULL,
  gcash_qr_data_uri MEDIUMTEXT DEFAULT NULL, fcm_push_token VARCHAR(255) DEFAULT NULL,
  fcm_push_sub_json JSON DEFAULT NULL,
  duty_today_payout DECIMAL(10,2) NOT NULL DEFAULT 0.00, completed_today INT UNSIGNED NOT NULL DEFAULT 0,
  acceptance_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00, active_hours_today DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  current_shift_started_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_riders_status (status), INDEX idx_riders_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    ,
    'rider_orders' => /** @lang MariaDB */ <<<'SQL'
CREATE TABLE rider_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_id INT UNSIGNED DEFAULT NULL, order_code VARCHAR(32) NOT NULL UNIQUE,
  merchant_order_id VARCHAR(32) DEFAULT NULL, source_store_id INT UNSIGNED DEFAULT NULL,
  shared_order_id INT UNSIGNED DEFAULT NULL,
  source_system ENUM('MOTOBOOK_POS','GRAB','FOODPANDA','MCDELIVERY','INHOUSE') DEFAULT 'INHOUSE',
  merchant_name VARCHAR(160) NOT NULL, merchant_lat DECIMAL(11,8) DEFAULT NULL, merchant_lng DECIMAL(11,8) DEFAULT NULL,
  merchant_address VARCHAR(255) NOT NULL,
  dropoff_name VARCHAR(160) DEFAULT NULL, dropoff_lat DECIMAL(11,8) DEFAULT NULL, dropoff_lng DECIMAL(11,8) DEFAULT NULL,
  dropoff_address VARCHAR(255) NOT NULL, dropoff_phone VARCHAR(24) DEFAULT NULL,
  total_distance_km DECIMAL(6,2) NOT NULL DEFAULT 0.00, estimated_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  payout_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, tip_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  cod_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method ENUM('COD','GCASH','MAYA','GRABPAY','SHOPEEPAY','CARD','BANK_TRANSFER','PREPAID') DEFAULT 'COD',
  payment_reference VARCHAR(64) DEFAULT NULL,
  payment_status ENUM('UNPAID','PENDING_VERIFICATION','PAID') DEFAULT 'UNPAID',
  items_json JSON DEFAULT NULL, special_notes VARCHAR(255) DEFAULT NULL,
  state ENUM('OFFER_RECEIVED','OFFER_EXPIRED','OFFER_REJECTED','ACCEPTED','NAVIGATING_TO_PICKUP','ARRIVED_AT_PICKUP','ORDER_VERIFIED','NAVIGATING_TO_DROP_OFF','ARRIVED_AT_DROP_OFF','PROOF_SUBMITTED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'OFFER_RECEIVED',
  offer_received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, offer_expires_at DATETIME DEFAULT NULL,
  accepted_at DATETIME DEFAULT NULL, pickup_arrived_at DATETIME DEFAULT NULL, verified_at DATETIME DEFAULT NULL,
  dropoff_arrived_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL,
  incident_reported VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ro_rider_state (rider_id, state), INDEX idx_ro_state (state), INDEX idx_ro_code (order_code),
  INDEX idx_ro_shared (shared_order_id), INDEX idx_ro_payment (payment_method, payment_status),
  UNIQUE INDEX uq_ro_shared (shared_order_id),
  INDEX idx_ro_completed_date (rider_id, state, completed_at),
  CONSTRAINT fk_ro_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    ,
    'rider_location_logs' => /** @lang MariaDB */ <<<'SQL'
CREATE TABLE rider_location_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_id INT UNSIGNED NOT NULL, rider_order_id INT UNSIGNED DEFAULT NULL,
  lat DECIMAL(11,8) NOT NULL, lng DECIMAL(11,8) NOT NULL,
  heading DECIMAL(5,2) DEFAULT NULL, speed_kmh DECIMAL(6,2) DEFAULT NULL, accuracy_m DECIMAL(6,2) DEFAULT NULL,
  motion_state ENUM('MOVING','IDLE','STOPPED') DEFAULT 'IDLE',
  is_offline_batch TINYINT(1) NOT NULL DEFAULT 0,
  recorded_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  INDEX idx_rll_rider_time (rider_id, recorded_at), INDEX idx_rll_order (rider_order_id),
  CONSTRAINT fk_rll_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
  CONSTRAINT fk_rll_order FOREIGN KEY (rider_order_id) REFERENCES rider_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    ,
    'rider_pod_records' => /** @lang MariaDB */ <<<'SQL'
CREATE TABLE rider_pod_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_order_id INT UNSIGNED NOT NULL UNIQUE, rider_id INT UNSIGNED NOT NULL,
  proof_image_path VARCHAR(255) DEFAULT NULL, proof_image_lat DECIMAL(11,8) DEFAULT NULL,
  proof_image_lng DECIMAL(11,8) DEFAULT NULL, proof_captured_at DATETIME(3) DEFAULT NULL,
  cod_collected_amt DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  cod_change_due DECIMAL(10,2) NOT NULL DEFAULT 0.00, cod_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  signature_path VARCHAR(255) DEFAULT NULL, signature_name VARCHAR(120) DEFAULT NULL,
  dropoff_notes VARCHAR(255) DEFAULT NULL,
  handoff_mode ENUM('DIRECT','GATE_LEAVE','NEIGHBOR','LOCKER') DEFAULT 'DIRECT',
  payment_method ENUM('COD','GCASH','MAYA','GRABPAY','SHOPEEPAY','CARD','BANK_TRANSFER','PREPAID') DEFAULT 'COD',
  payment_reference VARCHAR(64) DEFAULT NULL, payment_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  payment_proof_image_path VARCHAR(255) DEFAULT NULL, payment_proof_image_lat DECIMAL(11,8) DEFAULT NULL,
  payment_proof_image_lng DECIMAL(11,8) DEFAULT NULL, payment_captured_at DATETIME(3) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pod_order FOREIGN KEY (rider_order_id) REFERENCES rider_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_pod_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    ,
    'rider_order_incidents' => /** @lang MariaDB */ <<<'SQL'
CREATE TABLE rider_order_incidents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_id INT UNSIGNED NOT NULL, rider_order_id INT UNSIGNED DEFAULT NULL,
  incident_code VARCHAR(40) NOT NULL, detail VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_roi_rider (rider_id), INDEX idx_roi_order (rider_order_id),
  CONSTRAINT fk_roi_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
  CONSTRAINT fk_roi_order FOREIGN KEY (rider_order_id) REFERENCES rider_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    ,
    'rider_deposits' => /** @lang MariaDB */ <<<'SQL'
CREATE TABLE rider_deposits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_id INT UNSIGNED NOT NULL, deposit_date DATE NOT NULL,
  orders_completed_count INT UNSIGNED NOT NULL DEFAULT 0,
  cash_cod_collected_php DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  online_confirmed_php DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_expected_php DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  deposited_cash_php DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  deposit_note VARCHAR(255) DEFAULT NULL, deposit_slip_image_path VARCHAR(255) DEFAULT NULL,
  deposit_slip_lat DECIMAL(11,8) DEFAULT NULL, deposit_slip_lng DECIMAL(11,8) DEFAULT NULL,
  deposit_slip_captured_at DATETIME(3) DEFAULT NULL,
  deposit_status ENUM('PENDING','VERIFIED','SHORT','OVERAGE','REJECTED') DEFAULT 'PENDING',
  verified_by_staff_id INT UNSIGNED DEFAULT NULL, verified_at DATETIME DEFAULT NULL,
  shift_started_at DATETIME DEFAULT NULL, shift_ended_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE INDEX uq_rd_rider_date (rider_id, deposit_date),
  INDEX idx_rd_status (deposit_status), INDEX idx_rd_date (deposit_date),
  CONSTRAINT fk_rd_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
];
$okTbl = 0; $skipTbl = 0; $failTbl = 0;
foreach ($tables as $name => $sql) {
    $r = $mysqli->query("SHOW TABLES LIKE '$name'");
    if ($r && $r->num_rows > 0) { $skipTbl++; continue; }
    if (@$mysqli->query($sql)) $okTbl++;
    else { $failTbl++; println('  create '.$name.' FAILED: '.$mysqli->error); }
}
println("  tables: created=$okTbl  skipped=$skipTbl  failed=$failTbl");

// ---------------------------
// STEP 5: Seed RDR-0001 + 2 demo orders (with real bcrypt hash)
// ---------------------------
println('STEP 5: seed demo data RDR-0001 + MB-88491/MB-88492 (INSERT IGNORE)...');
$pw = 'Motobook200409';
$hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 10]);
$insRider = $mysqli->prepare(
    "INSERT IGNORE INTO riders (rider_code,name,email,phone,password_hash,vehicle_plate,vehicle_type,city,status,".
    "gcash_mobile_number,gcash_account_name,duty_today_payout,completed_today,acceptance_rate,active_hours_today)".
    " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$rc='RDR-0001'; $nm='Juan Dela Cruz'; $em='juan.rider@motobook.com'; $ph='639170000001';
$vp='UHZ-4201'; $vt='MOTORCYCLE'; $ct='Makati City'; $st='OFFLINE'; $gm='09170000001'; $gn='JUAN DELA CRUZ';
$dp = 1450.00; $co = 12; $ar = 98.00; $ah = 5.20;
$insRider->bind_param('sssssssssssdidd',$rc,$nm,$em,$ph,$hash,$vp,$vt,$ct,$st,$gm,$gn,$dp,$co,$ar,$ah);
$insRider->execute(); $insRider->close();

// Force password hash overwrite every run so the placeholder never causes login fail
$updPw = $mysqli->prepare('UPDATE riders SET password_hash=? WHERE rider_code=? LIMIT 1');
$rc2='RDR-0001'; $updPw->bind_param('ss',$hash,$rc2); $updPw->execute(); $updPw->close();
println('RDR-0001 hash overwritten with real bcrypt for ' . $pw);

// Get rider id for order seed
$rid = (int)($mysqli->query("SELECT id FROM riders WHERE rider_code='RDR-0001' LIMIT 1")->fetch_assoc()['id'] ?? 0);
if ($rid > 0) {
    $o1 = $mysqli->prepare(
        "INSERT IGNORE INTO rider_orders (rider_id,order_code,merchant_order_id,source_system,".
        "merchant_name,merchant_lat,merchant_lng,merchant_address,".
        "dropoff_name,dropoff_lat,dropoff_lng,dropoff_address,dropoff_phone,".
        "total_distance_km,estimated_minutes,payout_amount,tip_amount,cod_amount,".
        "payment_method,payment_reference,payment_status,items_json,special_notes,state,offer_expires_at)".
        " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 SECOND))"
    );
    $oc='MB-88491'; $moid='JJ-7K2A'; $ss='INHOUSE';
    $mn='Jollibee - Uptown Mall Branch'; $mlat=14.556477; $mlng=121.055092; $ma='3rd Level, Uptown Mall, 36th St., Taguig';
    $dn='Ms. Santos'; $dlat=14.574321; $dlng=121.083912; $da='Block 4, Lot 12, Santa Rosa Village'; $dp2='639990000001';
    $td=4.20; $em2=18; $pa=105.00; $ta=20.00; $ca=680.00; $pm='COD'; $pref=null; $ps='UNPAID';
    $ij = json_encode([['name'=>'1x Paa Large with Extra Rice','qty'=>1,'special_instructions'=>'NO MAYO, LESS ICE'],['name'=>'1x Coke Zero','qty'=>1,'special_instructions'=>'No Ice']]);
    $sn = 'Leave at gate if no answer within 2 minutes'; $state = 'OFFER_RECEIVED';
    $null = null;
    $o1->bind_param('isssssdssssssdddssdssbssss',$rid,$oc,$moid,$ss,$mn,$mlat,$mlng,$ma,$dn,$dlat,$dlng,$da,$dp2,$td,$em2,$pa,$ta,$ca,$pm,$null,$ps,$ij,$sn,$state);
    // send blob
    $o1->send_long_data(20, (string)$pref);
    $o1->send_long_data(21, (string)$ij);
    $o1->execute(); $o1->close();

    $o2 = $mysqli->prepare(
        "INSERT IGNORE INTO rider_orders (rider_id,order_code,merchant_order_id,source_system,".
        "merchant_name,merchant_lat,merchant_lng,merchant_address,".
        "dropoff_name,dropoff_lat,dropoff_lng,dropoff_address,dropoff_phone,".
        "total_distance_km,estimated_minutes,payout_amount,tip_amount,cod_amount,".
        "payment_method,payment_reference,payment_status,items_json,state,offer_expires_at)".
        " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 60 SECOND))"
    );
    $oc='MB-88492'; $moid='MI-X1P3';
    $mn='Mang Inasal - Market Market'; $mlat=14.549122; $mlng=121.049921; $ma='Market! Market!, McKinley Pkwy, Taguig';
    $dn='Mr. Reyes'; $dlat=14.559874; $dlng=121.051222; $da='Unit 1702, Avida Towers 34th'; $dp2='639180000002';
    $td=2.10; $em2=10; $pa=75.00; $ta=0.00; $ca=0.00; $pm='GCASH'; $pref='G-884120912'; $ps='PENDING_VERIFICATION';
    $ij = json_encode([['name'=>'2pc Chicken Inasal Unli Rice','qty'=>1,'special_instructions'=>'Extra soy sauce'],['name'=>'1x Halo-Halo Special','qty'=>1,'special_instructions'=>'Hold leche flan']]);
    $state = 'OFFER_RECEIVED';
    $o2->bind_param('isssssdssssssdddssdssbsss',$rid,$oc,$moid,$ss,$mn,$mlat,$mlng,$ma,$dn,$dlat,$dlng,$da,$dp2,$td,$em2,$pa,$ta,$ca,$pm,$pref,$ps,$ij,$state);
    $o2->send_long_data(21, (string)$ij);
    $o2->execute(); $o2->close();
    println('RDR-0001 demo orders seeded: OK');
}

// Verify password
$check = $mysqli->query("SELECT password_hash FROM riders WHERE rider_code='RDR-0001' LIMIT 1")->fetch_assoc()['password_hash'] ?? '';
$pv = $check ? (password_verify($pw,$check) ? 'MATCH' : 'MISMATCH') : 'NOROW';
println("Password verify for RDR-0001/Motobook200409: $pv");

// ---------------------------
// STEP 6: Start PHP built-in server on 8080
// ---------------------------
println('STEP 6: start PHP -S 127.0.0.1:8080 docroot motobook...');
$phpBin = 'C:\xampp\php\php.exe';
$cmd2 =
    'START "" /D "'.dirname($phpBin).'" /MIN /B ' .
    '"'.$phpBin.'" -S 127.0.0.1:8080 -t "'.$root.'"';
$ph2 = popen($cmd2,'r'); if ($ph2) pclose($ph2);
$phpUp = waitTcp('127.0.0.1', 8080, 12);
println('PHP server 8080: ' . ($phpUp ? 'UP' : 'DOWN'));

if ($phpUp) {
    // Test login page renders
    $ctx = stream_context_create(['http'=>['timeout'=>8,'ignore_errors'=>true]]);
    $h = @file_get_contents('http://127.0.0.1:8080/login.php', false, $ctx);
    $len = strlen((string)$h);
    $okForm = (is_string($h) && stripos($h,'<form')!==false && stripos($h,'email')!==false);
    println("login.php render: bytes=$len hasForm=$okForm");
}

// Final summary
println('==============================================');
println('SAFE LAUNCH COMPLETE — open browser to:');
println('  Login URL: http://127.0.0.1:8080/login.php');
println('  Rider email: juan.rider@motobook.com');
println('  Rider pass : Motobook200409');
println('  After SSO: redirects to http://127.0.0.1:8080/A-rider/');
println('==============================================');
exit(0);
