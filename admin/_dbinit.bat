@echo off
cd /d c:\xampp\mysql\bin
mysql.exe -u root -h 127.0.0.1 -P 3306 motobook_admin < c:\xampp\htdocs\IM-101\motobook\admin\_tmp_init.sql
echo INIT_OK
mysql.exe -u root -h 127.0.0.1 -P 3306 motobook_admin < c:\xampp\htdocs\IM-101\motobook\admin\database\schema.sql
echo SCHEMA_OK
mysql.exe -u root -h 127.0.0.1 -P 3306 motobook_admin < c:\xampp\htdocs\IM-101\motobook\admin\database\seed.sql
echo SEED_OK
mysql.exe -u root -h 127.0.0.1 -P 3306 motobook_admin < c:\xampp\htdocs\IM-101\motobook\admin\database\operations.sql
echo OPS_OK
echo Done.
