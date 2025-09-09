<?php
// DB接続情報
$dsn_mosp = "pgsql:host=127.0.0.1;port=5432;dbname=stcmospv4";
$user_mosp = "stcusermosp";
$pass_mosp = "stcpassmosp";

try {
    $pdo_mosp = new PDO($dsn_mosp, $user_mosp, $pass_mosp, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}