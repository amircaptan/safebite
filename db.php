<?php
$host = getenv("DB_HOST") ?: "localhost";
$db   = getenv("DB_NAME") ?: "safebite";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "";

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
  );
} catch (PDOException $e) {
  http_response_code(500);
  exit("DB connection failed.");
}