<?php
/**
 * SAFE COMBINED LAUNCHER (mysql starts first, then operations happen within 1 process)
 * Output goes to _SAFE_LAUNCHER.log in project root
 */
declare(strict_types=1);
$root = __DIR__;
$log = $root.'\_SAFE_LAUNCHER.log';
@unlink($log);
function println(string $m):void { global $log; @file_put_contents($log, @date('[H:i:s] ').$m.PHP_EOL, FILE_APPEND); }

// Step 1: start MariaDB first and detach it via START /MIN /B so it doesn't die with our php.exe
println('Step1: launching MariaDB 3306...');
$mariaBin = 'C:\xampp\mysql\bin\mysqld.exe';
$mariaIni = file_exists('C:\xampp\mysql\bin\my.ini') ? 'C:\xampp\mysql\bin\my.ini' : 'C:\xampp\mysql\my.ini';
$mariaData = 'C:\xampp\mysql\data';
$mariaErr = sys_get_temp_dir().'\mariadb-launcher.err';
$cmd =
    'START "mariadb-motobook" /D "C:\xampp\mysql\bin" /MIN /B ' .
    '"'.$mariaBin.'" '.
    '--defaults-file="'.$mariaIni.'" '.
    '--basedir=C:\xampp\mysql '.
    '--datadir="'.$mariaData.'" '.
    '--port=3306 '.
    '--bind-address=127.0.0.1 '.
    '--log-error="'.$mariaErr.'"';
$ph = popen($cmd,'r'); if ($ph) pclose($ph);
$end = time()+25; $mariaUp=false;
while (time()<$end) {
    $s=@fsockopen('127.0.0.1',3306,$_,$_,0.5); if ($s) { fclose($s); $mariaUp=true; break; }
    usleep(300000);
}
println('Step1 MariaDB: '.($mariaUp?'UP':'DOWN').' err file: '.($mariaErr));
if (!$mariaUp) { println('DB error log: '.@file_get_contents($mariaErr)); exit(1); }
// Extra wait 2s for stability
sleep(2);

// Step 2: connect
println('Step2: connect DB');
$mysqli = @new mysqli('127.0.0.1','root','','motobook_admin',3306);
if ($mysqli->connect_errno) { println('CONNECT FAIL: '.$mysqli->connect_error); exit(2); }
$mysqli->set_charset('utf8mb4');
println('Step2: connected OK');

// Step 3: add missing cols to legacy riders (SHOW COLUMNS guard)
println('Step3: add missing cols to legacy riders...');
$wantCols = [
    'name'                  => "VARCHAR(120) DEFAULT NULL COMMENT 'Alias for compatibility with new A-rider schema; same value as full_name.'",
    'password_hash'         => "VARCHAR(255) DEFAULT NULL COMMENT 'Bcrypt hash for new A-rider password_verify(); mirrors legacy password column when set.'",
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
$a=0; $sk=0;
foreach ($wantCols as $n=>$d) {
    $like=$mysqli->real_escape_string($n);
    $r=$mysqli->query("SHOW COLUMNS FROM `riders` LIKE '$like'");
    if ($r && $r->num_rows>0) { $sk++; continue; }
    if (@$mysqli->query("ALTER TABLE `riders` ADD COLUMN `$n` $d")) $a++;
    else $sk++;
}
// Post-col: keep name/password_hash in sync (name=full_name, password_hash=password for any existing legacy plain/hashed rows)
$hasFull = $mysqli->query("SHOW COLUMNS FROM `riders` LIKE 'full_name'");
$hasName  = $mysqli->query("SHOW COLUMNS FROM `riders` LIKE 'name'");
if ($hasFull && $hasFull->num_rows>0 && $hasName && $hasName->num_rows>0) {
    @$mysqli->query("UPDATE riders SET name = full_name WHERE name IS NULL OR name = ''");
    println('Step3: synced name = full_name for existing rows');
}
$hasPw = $mysqli->query("SHOW COLUMNS FROM `riders` LIKE 'password'");
$hasPh  = $mysqli->query("SHOW COLUMNS FROM `riders` LIKE 'password_hash'");
if ($hasPw && $hasPw->num_rows>0 && $hasPh && $hasPh->num_rows>0) {
    @$mysqli->query("UPDATE riders SET password_hash = password WHERE password_hash IS NULL OR password_hash = ''");
    println('Step3: synced password_hash = password for existing rows');
}
println("Step3: added=$a skipped=$sk");

// Step 4: ensure all 6 rider tables exist
println('Step4: ensure 6 rider tables...');
function hasTable(mysqli $db, string $name): bool {
    $r = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($name)."'");
    return $r && $r->num_rows>0;
}
// Create tables only if missing, with FK dependency order riders -> rider_orders -> others
$needTables = [
    'riders' => "CREATE TABLE riders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_code VARCHAR(24) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(24) DEFAULT NULL, password_hash VARCHAR(255) NOT NULL, vehicle_plate VARCHAR(32) DEFAULT NULL,
  vehicle_type ENUM('MOTORCYCLE','EBIKE','CAR','VAN') DEFAULT 'MOTORCYCLE',
  city VARCHAR(80) DEFAULT NULL, status ENUM('ACTIVE','INACTIVE','ON_SHIFT','OFFLINE','SUSPENDED') DEFAULT 'OFFLINE',
  gcash_mobile_number VARCHAR(20) DEFAULT NULL, gcash_account_name VARCHAR(80) DEFAULT NULL,
  gcash_qr_data_uri MEDIUMTEXT DEFAULT NULL, fcm_push_token VARCHAR(255) DEFAULT NULL, fcm_push_sub_json JSON DEFAULT NULL,
  duty_today_payout DECIMAL(10,2) NOT NULL DEFAULT 0.00, completed_today INT UNSIGNED NOT NULL DEFAULT 0,
  acceptance_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00, active_hours_today DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  current_shift_started_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_riders_status (status), INDEX idx_riders_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'rider_orders' => "CREATE TABLE rider_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, rider_id INT UNSIGNED DEFAULT NULL, order_code VARCHAR(32) NOT NULL UNIQUE,
  merchant_order_id VARCHAR(32) DEFAULT NULL, source_store_id INT UNSIGNED DEFAULT NULL, shared_order_id INT UNSIGNED DEFAULT NULL,
  source_system ENUM('MOTOBOOK_POS','GRAB','FOODPANDA','MCDELIVERY','INHOUSE') DEFAULT 'INHOUSE',
  merchant_name VARCHAR(160) NOT NULL, merchant_lat DECIMAL(11,8) DEFAULT NULL, merchant_lng DECIMAL(11,8) DEFAULT NULL,
  merchant_address VARCHAR(255) NOT NULL,
  dropoff_name VARCHAR(160) DEFAULT NULL, dropoff_lat DECIMAL(11,8) DEFAULT NULL, dropoff_lng DECIMAL(11,8) DEFAULT NULL,
  dropoff_address VARCHAR(255) NOT NULL, dropoff_phone VARCHAR(24) DEFAULT NULL,
  total_distance_km DECIMAL(6,2) NOT NULL DEFAULT 0.00, estimated_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  payout_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, tip_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  cod_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method ENUM('COD','GCASH','MAYA','GRABPAY','SHOPEEPAY','CARD','BANK_TRANSFER','PREPAID') DEFAULT 'COD',
  payment_reference VARCHAR(64) DEFAULT NULL, payment_status ENUM('UNPAID','PENDING_VERIFICATION','PAID') DEFAULT 'UNPAID',
  items_json JSON DEFAULT NULL, special_notes VARCHAR(255) DEFAULT NULL,
  state ENUM('OFFER_RECEIVED','OFFER_EXPIRED','OFFER_REJECTED','ACCEPTED','NAVIGATING_TO_PICKUP','ARRIVED_AT_PICKUP','ORDER_VERIFIED','NAVIGATING_TO_DROP_OFF','ARRIVED_AT_DROP_OFF','PROOF_SUBMITTED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'OFFER_RECEIVED',
  offer_received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, offer_expires_at DATETIME DEFAULT NULL,
  accepted_at DATETIME DEFAULT NULL, pickup_arrived_at DATETIME DEFAULT NULL, verified_at DATETIME DEFAULT NULL,
  dropoff_arrived_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, incident_reported VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ro_rider_state (rider_id, state), INDEX idx_ro_state (state), INDEX idx_ro_code (order_code),
  INDEX idx_ro_shared (shared_order_id), INDEX idx_ro_payment (payment_method, payment_status),
  UNIQUE INDEX uq_ro_shared (shared_order_id), INDEX idx_ro_completed_date (rider_id, state, completed_at),
  CONSTRAINT fk_ro_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'rider_location_logs' => "CREATE TABLE rider_location_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, rider_id INT UNSIGNED NOT NULL, rider_order_id INT UNSIGNED DEFAULT NULL,
  lat DECIMAL(11,8) NOT NULL, lng DECIMAL(11,8) NOT NULL, heading DECIMAL(5,2) DEFAULT NULL,
  speed_kmh DECIMAL(6,2) DEFAULT NULL, accuracy_m DECIMAL(6,2) DEFAULT NULL,
  motion_state ENUM('MOVING','IDLE','STOPPED') DEFAULT 'IDLE',
  is_offline_batch TINYINT(1) NOT NULL DEFAULT 0,
  recorded_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  INDEX idx_rll_rider_time (rider_id, recorded_at), INDEX idx_rll_order (rider_order_id),
  CONSTRAINT fk_rll_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
  CONSTRAINT fk_rll_order FOREIGN KEY (rider_order_id) REFERENCES rider_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'rider_pod_records' => "CREATE TABLE rider_pod_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, rider_order_id INT UNSIGNED NOT NULL UNIQUE, rider_id INT UNSIGNED NOT NULL,
  proof_image_path VARCHAR(255) DEFAULT NULL, proof_image_lat DECIMAL(11,8) DEFAULT NULL,
  proof_image_lng DECIMAL(11,8) DEFAULT NULL, proof_captured_at DATETIME(3) DEFAULT NULL,
  cod_collected_amt DECIMAL(10,2) NOT NULL DEFAULT 0.00, cod_change_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  cod_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  signature_path VARCHAR(255) DEFAULT NULL, signature_name VARCHAR(120) DEFAULT NULL,
  dropoff_notes VARCHAR(255) DEFAULT NULL,
  handoff_mode ENUM('DIRECT','GATE_LEAVE','NEIGHBOR','LOCKER') DEFAULT 'DIRECT',
  payment_method ENUM('COD','GCASH','MAYA','GRABPAY','SHOPEEPAY','CARD','BANK_TRANSFER','PREPAID') DEFAULT 'COD',
  payment_reference VARCHAR(64) DEFAULT NULL, payment_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  payment_proof_image_path VARCHAR(255) DEFAULT NULL, payment_proof_image_lat DECIMAL(11,8) DEFAULT NULL,
  payment_proof_image_lng DECIMAL(11,8) DEFAULT NULL, payment_captured_at DATETIME(3) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pod_order FOREIGN KEY (rider_order_id) REFERENCES rider_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_pod_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'rider_order_incidents' => "CREATE TABLE rider_order_incidents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, rider_id INT UNSIGNED NOT NULL, rider_order_id INT UNSIGNED DEFAULT NULL,
  incident_code VARCHAR(40) NOT NULL, detail VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_roi_rider (rider_id), INDEX idx_roi_order (rider_order_id),
  CONSTRAINT fk_roi_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
  CONSTRAINT fk_roi_order FOREIGN KEY (rider_order_id) REFERENCES rider_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'rider_deposits' => "CREATE TABLE rider_deposits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, rider_id INT UNSIGNED NOT NULL, deposit_date DATE NOT NULL,
  orders_completed_count INT UNSIGNED NOT NULL DEFAULT 0,
  cash_cod_collected_php DECIMAL(12,2) NOT NULL DEFAULT 0.00, online_confirmed_php DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_expected_php DECIMAL(12,2) NOT NULL DEFAULT 0.00, deposited_cash_php DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  deposit_note VARCHAR(255) DEFAULT NULL, deposit_slip_image_path VARCHAR(255) DEFAULT NULL,
  deposit_slip_lat DECIMAL(11,8) DEFAULT NULL, deposit_slip_lng DECIMAL(11,8) DEFAULT NULL,
  deposit_slip_captured_at DATETIME(3) DEFAULT NULL,
  deposit_status ENUM('PENDING','VERIFIED','SHORT','OVERAGE','REJECTED') DEFAULT 'PENDING',
  verified_by_staff_id INT UNSIGNED DEFAULT NULL, verified_at DATETIME DEFAULT NULL,
  shift_started_at DATETIME DEFAULT NULL, shift_ended_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE INDEX uq_rd_rider_date (rider_id, deposit_date),
  INDEX idx_rd_status (deposit_status), INDEX idx_rd_date (deposit_date),
  CONSTRAINT fk_rd_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
$okT=0; $skipT=0; $failT=0;
foreach ($needTables as $n=>$sql) {
    if (hasTable($mysqli,$n)) { $skipT++; continue; }
    if (@$mysqli->query($sql)) $okT++;
    else { $failT++; println('  FAIL table '.$n.': '.$mysqli->error); }
}
println("Step4 tables: created=$okT skipped=$skipT failed=$failT");

// Step 5: seed RDR-0001 + 2 demo orders (small bind params, NO send_long_data)
println('Step5: seed RDR-0001 + 2 demo orders...');
$pw = 'Motobook200409';
$hash = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>10]);
// Use multi-query approach (execute via raw query not prepared) to avoid bind_param hang issues
$riderCode = 'RDR-0001';
$name = 'Juan Dela Cruz';
$email = 'juan.rider@motobook.com';
$phone = '639170000001';
$plate = 'UHZ-4201';
$vtype = 'MOTORCYCLE';
$city = 'Makati City';
$status = 'active'; // lowercase enum for legacy riders table
$gNum = '09170000001';
$gName = 'JUAN DELA CRUZ';
$dp = 1450.00;
$ct = 12;
$ar = 98.00;
$ah = 5.20;
$e = fn(string $s) => "'".$mysqli->real_escape_string($s)."'";

// First check existence by email/rider_code
$exists = $mysqli->query("SELECT id,rider_code,full_name,name,password,password_hash FROM riders WHERE rider_code={$e($riderCode)} OR email={$e($email)} LIMIT 1")->fetch_assoc() ?: null;

if (!$exists) {
    // Insert new rider via columns that DEFINITELY exist in legacy riders table + new cols added above (name/password_hash).
    // Build column list dynamically based on SHOW COLUMNS so this works regardless of schema version.
    $colsRaw = $mysqli->query("SHOW COLUMNS FROM `riders`");
    $avail = [];
    while ($row = $colsRaw->fetch_assoc()) $avail[strtolower($row['Field'])] = true;
    // Build (col1,col2,col3) VALUES (v1,v2,v3) pairs, only inserting columns that physically exist
    $vals = [
        'rider_code'            => $e($riderCode),
        'full_name'             => $e($name),
        'name'                  => isset($avail['name']) ? $e($name) : null,
        'email'                 => $e($email),
        'phone'                 => $e($phone),
        'contact_number'        => isset($avail['contact_number']) ? $e($phone) : null,
        'password'              => isset($avail['password'])       ? $e($hash) : null,
        'password_hash'         => isset($avail['password_hash'])  ? $e($hash) : null,
        'vehicle_type'          => $e($vtype),
        'vehicle_plate'         => $e($plate),
        'plate_number'          => isset($avail['plate_number'])   ? $e($plate) : null,
        'city'                  => isset($avail['city'])           ? $e($city)  : null,
        'status'                => $e($status),
        'gcash_mobile_number'   => isset($avail['gcash_mobile_number']) ? $e($gNum) : null,
        'gcash_account_name'    => isset($avail['gcash_account_name'])  ? $e($gName) : null,
        'duty_today_payout'     => isset($avail['duty_today_payout'])   ? (string)$dp : null,
        'completed_today'       => isset($avail['completed_today'])     ? (string)$ct : null,
        'acceptance_rate'       => isset($avail['acceptance_rate'])     ? (string)$ar : null,
        'active_hours_today'    => isset($avail['active_hours_today'])  ? (string)$ah : null,
    ];
    $colList = []; $valList = [];
    foreach ($vals as $col=>$v) {
        if ($v === null) continue;
        $colList[] = "`$col`";
        $valList[] = $v;
    }
    $insertSQL = 'INSERT INTO riders ('.implode(',', $colList).') VALUES ('.implode(',', $valList).')';
    $ok = @$mysqli->query($insertSQL);
    println('Step5: INSERT RDR-0001 via legacy cols -> '.($ok?'OK':'FAIL: '.$mysqli->error));
} else {
    println('Step5: RDR-0001/juan.rider already exists (id='.((int)$exists['id']).'), updating password hash to bcrypt for '.$pw);
    // Update with same COALESCE logic to keep legacy/new cols in sync
    $updates = [];
    $updates[] = "full_name={$e($name)}";
    $updates[] = "name={$e($name)}";
    $updates[] = "password={$e($hash)}";
    $updates[] = "password_hash={$e($hash)}";
    $updates[] = "rider_code=COALESCE(rider_code,{$e($riderCode)})";
    $updates[] = "phone=COALESCE(NULLIF(phone,''),{$e($phone)})";
    $updates[] = "gcash_mobile_number=COALESCE(NULLIF(gcash_mobile_number,''),{$e($gNum)})";
    $updates[] = "gcash_account_name=COALESCE(NULLIF(gcash_account_name,''),{$e($gName)})";
    $updates[] = "status=IF(status IN ('active','inactive','on_duty','suspended','ACTIVE','INACTIVE','ON_SHIFT','OFFLINE','SUSPENDED'),status,'active')";
    $sql = 'UPDATE riders SET '.implode(', ', $updates).' WHERE id='.(int)$exists['id'].' LIMIT 1';
    @$mysqli->query($sql);
}
// Verify password match
$row = $mysqli->query("SELECT id,password,password_hash FROM riders WHERE rider_code={$e($riderCode)} LIMIT 1")->fetch_assoc() ?: null;
$rid = 0; $pv = 'NO ROW';
if ($row) {
    $rid = (int)$row['id'];
    $cmp = (string)($row['password_hash'] ?: $row['password']);
    $pv = password_verify($pw, $cmp) ? 'MATCH' : 'MISMATCH';
}
println("Step5: rid=$rid pw=$pv (expect MATCH for $pw)");

// Orders via simple insert IGNORE strings, no bind, no send_long_data
if ($rid>0) {
    $ij1 = json_encode([['name'=>'1x Paa Large with Extra Rice','qty'=>1,'special_instructions'=>'NO MAYO, LESS ICE'],['name'=>'1x Coke Zero','qty'=>1,'special_instructions'=>'No Ice']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $ij2 = json_encode([['name'=>'2pc Chicken Inasal Unli Rice','qty'=>1,'special_instructions'=>'Extra soy sauce'],['name'=>'1x Halo-Halo Special','qty'=>1,'special_instructions'=>'Hold leche flan']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $sqls = [];
    $sqls[] = "INSERT IGNORE INTO rider_orders (rider_id,order_code,merchant_order_id,source_system,merchant_name,merchant_lat,merchant_lng,merchant_address,dropoff_name,dropoff_lat,dropoff_lng,dropoff_address,dropoff_phone,total_distance_km,estimated_minutes,payout_amount,tip_amount,cod_amount,payment_method,payment_reference,payment_status,items_json,special_notes,state,offer_expires_at) VALUES($rid,'MB-88491','JJ-7K2A','INHOUSE','Jollibee - Uptown Mall Branch',14.556477,121.055092,'3rd Level, Uptown Mall, 36th St., Taguig','Ms. Santos',14.574321,121.083912,'Block 4, Lot 12, Santa Rosa Village','639990000001',4.20,18,105.00,20.00,680.00,'COD',NULL,'UNPAID',{$e($ij1)},'Leave at gate if no answer within 2 minutes','OFFER_RECEIVED',DATE_ADD(NOW(),INTERVAL 30 SECOND))";
    $sqls[] = "INSERT IGNORE INTO rider_orders (rider_id,order_code,merchant_order_id,source_system,merchant_name,merchant_lat,merchant_lng,merchant_address,dropoff_name,dropoff_lat,dropoff_lng,dropoff_address,dropoff_phone,total_distance_km,estimated_minutes,payout_amount,tip_amount,cod_amount,payment_method,payment_reference,payment_status,items_json,special_notes,state,offer_expires_at) VALUES($rid,'MB-88492','MI-X1P3','INHOUSE','Mang Inasal - Market Market',14.549122,121.049921,'Market! Market!, McKinley Pkwy, Taguig','Mr. Reyes',14.559874,121.051222,'Unit 1702, Avida Towers 34th','639180000002',2.10,10,75.00,0.00,0.00,'GCASH','G-884120912','PENDING_VERIFICATION',{$e($ij2)},'','OFFER_RECEIVED',DATE_ADD(NOW(),INTERVAL 60 SECOND))";
    foreach ($sqls as $s) {
        $ok = @$mysqli->query($s);
        $aff = $mysqli->affected_rows;
        println('  Order insert '.($ok?'OK':'FAIL:'.$mysqli->error).' affected_rows='.$aff);
    }
}
$cnt = $mysqli->query("SELECT COUNT(*) c FROM rider_orders WHERE rider_id=$rid")->fetch_assoc()['c'] ?? 0;
println("Step5: demo orders count for RDR-0001 = $cnt (expect >=2)");
$mysqli->close();
println('Step5: seed complete, DB connection closed (do NOT kill mariadb now — it stays running on system)');

// Step 6: start PHP built-in server on 8080 detached
println('Step6: start PHP server 127.0.0.1:8080...');
$phpBin = 'C:\xampp\php\php.exe';
$phpCmd =
    'START "php-motobook" /D "'.dirname($phpBin).'" /MIN /B "'.$phpBin.'" '.
    '-S 127.0.0.1:8080 -t "'.$root.'"';
$ph = popen($phpCmd,'r'); if ($ph) pclose($ph);
$end = time()+15; $phpUp=false;
while (time()<$end) {
    $s=@fsockopen('127.0.0.1',8080,$_,$_,0.5); if ($s) { fclose($s); $phpUp=true; break; }
    usleep(300000);
}
println('Step6: PHP 8080: '.($phpUp?'UP':'DOWN'));
if ($phpUp) {
    $ctx = stream_context_create(['http'=>['timeout'=>8,'ignore_errors'=>true]]);
    $html = @file_get_contents('http://127.0.0.1:8080/login.php', false, $ctx);
    println('login.php render: bytes='.strlen((string)$html).' hasForm='.(is_string($html) && stripos($html,'<form')!==false?'YES':'NO'));
}
println('==============================================');
println('ALL STEPS OK');
println('Login URL    : http://127.0.0.1:8080/login.php');
println('Rider Email  : juan.rider@motobook.com');
println('Rider Pass   : Motobook200409');
println('Post SSO URL : http://127.0.0.1:8080/A-rider/');
println('==============================================');
exit(0);
