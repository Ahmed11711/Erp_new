<?php
$hosts = ['127.0.0.1', 'localhost'];
$user = 'root';
$pass = '';
$db = 'newerp';

foreach ($hosts as $host) {
    echo "Testing connection to $host...\n";
    try {
        $pdo = new PDO("mysql:host=$host;port=3306;dbname=$db", $user, $pass);
        echo "SUCCESS: Connected to $host\n";
    } catch (PDOException $e) {
        echo "FAILED: Could not connect to $host. Error: " . $e->getMessage() . "\n";
    }
    echo "------------------------------------------------\n";
}
