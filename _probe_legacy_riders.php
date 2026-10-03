<?php
$root = __DIR__;
$log = $root.'\_SAFE_LAUNCHER.log';
function println(string $m):void { global $log; @file_put_contents($log, @date('[H:i:s] ').$m.PHP_EOL, FILE_APPEND); }
// MariaDB must be alive, else start
if (!($s=@fsockopen('127.0.0.1',3306,$_,$_,0.5))) {
    println('MariaDB down — restarting via START /B');
    $cmd = 'START "" /D "C:\xampp\mysql\bin" /MIN /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --basedir=C:\xampp\mysql --datadir="C:\xampp\mysql\data" --port=3306 --bind-address=127.0.0.1 --log-error="'.sys_get_temp_dir().'\mariadb-probe.err"';
    $ph = popen($cmd,'r'); if ($ph) pclose($ph);
    $end = time()+22; while (time()<$end) { $s2=@fsockopen('127.0.0.1',3306,$_,$_,0.5); if ($s2) {fclose($s2); break;} usleep(300000); }
}
$mysqli = @new mysqli('127.0.0.1','root','','motobook_admin',3306);
if ($mysqli->connect_errno) { echo "CONNECT FAIL: ".$mysqli->connect_error; exit; }
$mysqli->set_charset('utf8mb4');
$cols = $mysqli->query("SHOW COLUMNS FROM `riders`");
$present = [];
while ($row = $cols->fetch_assoc()) { $present[strtolower($row['Field'])] = $row['Type']; }
println('Legacy riders columns ('.count($present).'):');
foreach ($present as $n=>$t) println('  '.$n.' '.$t);
// Count existing rows
$row = $mysqli->query("SELECT COUNT(*) c FROM riders")->fetch_assoc();
println("Legacy riders total rows: ".$row['c']);
// Show any sample row with rider_code RDR-0001 or email juan.rider
$s = $mysqli->query("SELECT id,rider_code,email,".implode(',',array_keys($present))." FROM riders WHERE rider_code='RDR-0001' OR email='juan.rider@motobook.com' LIMIT 1");
if ($s && $s->num_rows) {
    println('Existing RDR-0001/juan.rider row:');
    println(json_encode($s->fetch_assoc(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
} else {
    println('No RDR-0001 or juan.rider row exists yet.');
}
$mysqli->close();
