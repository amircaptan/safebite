<?php
header("Content-Type: application/json");
require "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok"=>false,"error"=>"Method not allowed"]);
  exit;
}

$payload = json_decode(file_get_contents("php://input"), true);
if (!$payload) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"Invalid JSON"]);
  exit;
}

$email = strtolower(trim($payload["email"] ?? ""));
$pass  = (string)($payload["pass"] ?? "");

if ($email === "" || $pass === "") {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"Email and password required"]);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT id, full_name, age, email, password_hash FROM users WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if (!$user || !password_verify($pass, $user["password_hash"])) {
    http_response_code(401);
    echo json_encode(["ok"=>false,"error"=>"Incorrect email or password"]);
    exit;
  }

  echo json_encode(["ok"=>true,"userId"=>(int)$user["id"]]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"Server error"]);
}
