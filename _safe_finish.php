<?php
declare(strict_types=1);
$root = __DIR__;
$log = $root.'\_safe_launch.log';
function println(string $m):void {
    global $log; @file_put_contents($log, @date('[H:i:s] ').$m.PHP_EOL, FILE_APPEND);
}
$mysqli = @new mysqli('127.0.0.1','root','','motobook_admin',3306);
if ($mysqli->connect_errno) { println('DB FAIL '.$mysqli->connect_error); exit(1); }
$mysqli->set_charset('utf8mb4');
println('DB connected OK');

// Force overwrite RDR-0001 password hash to Motobook200409
$pw = 'Motobook200409';
$hash = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>10]);
$stmt = $mysqli->prepare('UPDATE riders SET password_hash=?, gcash_mobile_number=IFNULL(gcash_mobile_number,?), gcash_account_name=IFNULL(gcash_account_name,?), rider_code=IFNULL(rider_code,?) WHERE email=? LIMIT 1');
$gm='09170000001'; $gn='JUAN DELA CRUZ'; $rc='RDR-0001'; $em='juan.rider@motobook.com';
$stmt->bind_param('sssss',$hash,$gm,$gn,$rc,$em);
$stmt->execute();
if ($stmt->affected_rows === 0) {
    // If not exist by email, insert it
    $ins = $mysqli->prepare("INSERT INTO riders (rider_code,name,email,phone,password_hash,vehicle_plate,vehicle_type,city,status,gcash_mobile_number,gcash_account_name,duty_today_payout,completed_today,acceptance_rate,active_hours_today) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $nm='Juan Dela Cruz'; $ph='639170000001'; $vp='UHZ-4201'; $vt='MOTORCYCLE'; $ct='Makati City'; $st='OFFLINE';
    $dp=1450.00; $co=12; $ar=98.00; $ah=5.20;
    $ins->bind_param('sssssssssssdidd',$rc,$nm,$em,$ph,$hash,$vp,$vt,$ct,$st,$gm,$gn,$dp,$co,$ar,$ah);
    $ins->execute(); $ins->close();
    println('Inserted RDR-0001 fresh (email not found)');
} else println('Updated existing RDR-0001 hash + gcash defaults');
$stmt->close();

// Verify password OK
$row = $mysqli->query("SELECT id,email,password_hash FROM riders WHERE rider_code='RDR-0001' LIMIT 1")->fetch_assoc();
$pv = password_verify($pw, $row['password_hash'] ?? '') ? 'MATCH' : 'MISMATCH';
$rid = (int)($row['id'] ?? 0);
println("verify RDR-0001/$pw: $pv  id=$rid");

// Seed demo orders MB-88491 (COD) and MB-88492 (GCASH)
function seedOrder(mysqli $db, int $rid, array $data): void {
    $exist = $db->query("SELECT id FROM rider_orders WHERE order_code='".$db->real_escape_string($data['code'])."' LIMIT 1");
    if ($exist && $exist->num_rows>0) { println("  skip {$data['code']} already exists"); return; }
    $prep = $db->prepare(
        "INSERT INTO rider_orders (rider_id,order_code,merchant_order_id,source_system,".
        "merchant_name,merchant_lat,merchant_lng,merchant_address,".
        "dropoff_name,dropoff_lat,dropoff_lng,dropoff_address,dropoff_phone,".
        "total_distance_km,estimated_minutes,payout_amount,tip_amount,cod_amount,".
        "payment_method,payment_reference,payment_status,items_json,special_notes,state,offer_expires_at)".
        " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? SECOND))"
    );
    $exp = $data['exp_sec'] ?? 30;
    $items = json_encode($data['items'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $note = $data['note'] ?? '';
    // payment_reference might be null
    $pref = $data['payment_ref'] ?? null;
    $prep->bind_param(
        'isssssdssssssiddddsdssssssi',
        $rid, $data['code'], $data['moid'], $data['system'],
        $data['mname'], $data['mlat'], $data['mlng'], $data['maddr'],
        $data['dname'], $data['dlat'], $data['dlng'], $data['daddr'], $data['dphone'],
        $data['dist'], $data['emin'], $data['payout'], $data['tip'], $data['cod'],
        $data['pmethod'], $pref, $data['pstatus'], $items, $note, $data['state'], $exp
    );
    $prep->execute();
    if ($prep->errno) println("  FAIL {$data['code']}: ".$prep->error);
    else println("  OK seed {$data['code']} ({$data['pmethod']})");
    $prep->close();
}
if ($rid>0) {
    seedOrder($mysqli, $rid, [
        'code'=>'MB-88491','moid'=>'JJ-7K2A','system'=>'INHOUSE',
        'mname'=>'Jollibee - Uptown Mall Branch','mlat'=>14.556477,'mlng'=>121.055092,'maddr'=>'3rd Level, Uptown Mall, 36th St., Taguig',
        'dname'=>'Ms. Santos','dlat'=>14.574321,'dlng'=>121.083912,'daddr'=>'Block 4, Lot 12, Santa Rosa Village','dphone'=>'639990000001',
        'dist'=>4.20,'emin'=>18,'payout'=>105.00,'tip'=>20.00,'cod'=>680.00,
        'pmethod'=>'COD','payment_ref'=>null,'pstatus'=>'UNPAID',
        'items'=>[['name'=>'1x Paa Large with Extra Rice','qty'=>1,'special_instructions'=>'NO MAYO, LESS ICE'],['name'=>'1x Coke Zero','qty'=>1,'special_instructions'=>'No Ice']],
        'note'=>'Leave at gate if no answer within 2 minutes','state'=>'OFFER_RECEIVED','exp_sec'=>30,
    ]);
    seedOrder($mysqli, $rid, [
        'code'=>'MB-88492','moid'=>'MI-X1P3','system'=>'INHOUSE',
        'mname'=>'Mang Inasal - Market Market','mlat'=>14.549122,'mlng'=>121.049921,'maddr'=>'Market! Market!, McKinley Pkwy, Taguig',
        'dname'=>'Mr. Reyes','dlat'=>14.559874,'dlng'=>121.051222,'daddr'=>'Unit 1702, Avida Towers 34th','dphone'=>'639180000002',
        'dist'=>2.10,'emin'=>10,'payout'=>75.00,'tip'=>0.00,'cod'=>0.00,
        'pmethod'=>'GCASH','payment_ref'=>'G-884120912','pstatus'=>'PENDING_VERIFICATION',
        'items'=>[['name'=>'2pc Chicken Inasal Unli Rice','qty'=>1,'special_instructions'=>'Extra soy sauce'],['name'=>'1x Halo-Halo Special','qty'=>1,'special_instructions'=>'Hold leche flan']],
        'note'=>'','state'=>'OFFER_RECEIVED','exp_sec'=>60,
    ]);
}
$mysqli->close();

// Start PHP server on 8080
println('Starting PHP server 127.0.0.1:8080 docroot motobook...');
$php = 'C:\xampp\php\php.exe';
$cmd = 'START "" /D "'.dirname($php).'" /MIN /B "'.$php.'" -S 127.0.0.1:8080 -t "'.$root.'"';
$ph = popen($cmd,'r'); if ($ph) pclose($ph);
$end = time()+15; $up = false;
while (time()<$end) {
    $s=@fsockopen('127.0.0.1',8080,$_,$_,0.5); if ($s) { fclose($s); $up=true; break; }
    usleep(300000);
}
println('PHP 8080: '.($up?'UP':'DOWN'));
if ($up) {
    $ctx = stream_context_create(['http'=>['timeout'=>8,'ignore_errors'=>true]]);
    $html = @file_get_contents('http://127.0.0.1:8080/login.php',false,$ctx);
    println('login.php: bytes='.strlen((string)$html).' hasForm='.(is_string($html) && stripos($html,'<form')!==false?'YES':'NO'));
}
println('DONE. Login at http://127.0.0.1:8080/login.php  juan.rider@motobook.com / Motobook200409');
