<?php
echo "PHP_VERSION=".PHP_VERSION."\n";
echo "PDO_DRIVERS=".implode(',', PDO::getAvailableDrivers())."\n";
echo "MYSQLI=".(extension_loaded('mysqli')?'YES':'NO')."\n";
echo "SQLITE3=".(extension_loaded('sqlite3')?'YES':'NO')."\n";
echo "BCRYPT=".(defined('PASSWORD_BCRYPT')?'YES':'NO')."\n";
echo "CURL=".(extension_loaded('curl')?'YES':'NO')."\n";
echo "GD=".(extension_loaded('gd')?'YES':'NO')."\n";
// Test in-memory sqlite works
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE t(id INT)'); $pdo->exec('INSERT INTO t VALUES(1),(2)');
$c = $pdo->query('SELECT COUNT(*) FROM t')->fetchColumn();
echo "SQLITE_INMEMORY_ROWS=$c\n";
// Test bcrypt
$h = password_hash('Motobook200409', PASSWORD_BCRYPT, ['cost'=>10]);
echo "BCRYPT_LEN=".strlen($h)."  VERIFY=".(password_verify('Motobook200409',$h)?'OK':'FAIL')."\n";
