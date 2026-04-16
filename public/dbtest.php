<?php

$host = 'sql106.infinityfree.com';
$db   = 'if0_41653669_hamzalhamza';
$user = 'if0_41653669';
$pass = 'ORCWxk7Aeyw7j';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    echo "DB OK";
} catch (Throwable $e) {
    echo "DB ERROR: " . $e->getMessage();
}
