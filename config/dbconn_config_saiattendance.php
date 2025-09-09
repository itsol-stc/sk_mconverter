<?php
// DB接続情報
$dsn_sai = "pgsql:host=127.0.0.1;port=5432;dbname=SaiAttendance";
$user_sai = "stcusermosp";
$pass_sai = "stcpassmosp";

try {
    $pdo_sai = new PDO($dsn_sai, $user_sai, $pass_sai, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}