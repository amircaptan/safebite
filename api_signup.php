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

$name = trim($payload["name"] ?? "");
$email = strtolower(trim($payload["email"] ?? ""));
$pass  = (string)($payload["pass"] ?? "");
$age   = $payload["age"] ?? null;
$severity = (($payload["severity"] ?? "standard") === "strict") ? "strict" : "standard";
$notes = trim($payload["notes"] ?? "");

$allergies = is_array($payload["allergies"] ?? null) ? $payload["allergies"] : [];
$intolerances = is_array($payload["intolerances"] ?? null) ? $payload["intolerances"] : [];
$preferences = is_array($payload["preferences"] ?? null) ? $payload["preferences"] : [];

if ($name === "" || $email === "" || strlen($pass) < 4) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"Name, email, and password are required (min 4 chars)."]);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"Invalid email"]);
  exit;
}

$ageVal = null;
if ($age !== null && $age !== "") {
  $ageInt = (int)$age;
  if ($ageInt < 0 || $ageInt > 120) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Invalid age"]);
    exit;
  }
  $ageVal = $ageInt;
}

$norm = function($arr){
  $out = [];
  foreach ($arr as $v) {
    $v = trim(strtolower((string)$v));
    if ($v !== "" && !in_array($v, $out, true)) $out[] = $v;
  }
  sort($out);
  return $out;
};

try {
  // unique email check
  $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
  $check->execute([$email]);
  if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(["ok"=>false,"error"=>"Email already registered"]);
    exit;
  }

  $pdo->beginTransaction();

  $stmt = $pdo->prepare("INSERT INTO users (full_name, age, email, password_hash) VALUES (?, ?, ?, ?)");
  $stmt->execute([$name, $ageVal, $email, password_hash($pass, PASSWORD_DEFAULT)]);
  $userId = (int)$pdo->lastInsertId();

  if ($notes !== "") {
    $n = $pdo->prepare("INSERT INTO user_notes (user_id, note_text) VALUES (?, ?)");
    $n->execute([$userId, $notes]);
  }

  $ins = $pdo->prepare("INSERT INTO user_dietary_items (user_id, item_type, item_value, severity) VALUES (?, ?, ?, ?)");

  foreach ($norm($allergies) as $v) {
    // store severity for allergies as mild/moderate/severe (simple mapping)
    $sev = ($severity === "strict") ? "severe" : "moderate";
    $ins->execute([$userId, "allergy", $v, $sev]);
  }
  foreach ($norm($intolerances) as $v) {
    $ins->execute([$userId, "intolerance", $v, null]);
  }
  foreach ($norm($preferences) as $v) {
    $ins->execute([$userId, "preference", $v, null]);
  }

  $pdo->commit();

  echo json_encode(["ok"=>true,"userId"=>$userId]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"Server error"]);
}
