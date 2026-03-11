<?php
header("Content-Type: application/json");
require "db.php";

function norm_list($arr){
  if (!is_array($arr)) return [];
  $out = [];
  foreach ($arr as $v){
    $v = trim(strtolower((string)$v));
    if ($v !== "" && !in_array($v, $out, true)) $out[] = $v;
  }
  sort($out);
  return $out;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
  $userId = (int)($_GET["userId"] ?? 0);
  if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Missing userId"]);
    exit;
  }

  try {
    $u = $pdo->prepare("SELECT id, full_name, age, email FROM users WHERE id = ? LIMIT 1");
    $u->execute([$userId]);
    $user = $u->fetch();
    if (!$user) {
      http_response_code(404);
      echo json_encode(["ok"=>false,"error"=>"User not found"]);
      exit;
    }

    $items = $pdo->prepare("SELECT item_type, item_value FROM user_dietary_items WHERE user_id = ? ORDER BY item_value ASC");
    $items->execute([$userId]);
    $rows = $items->fetchAll();

    $allergies = [];
    $intolerances = [];
    $preferences = [];
    foreach ($rows as $r) {
      if ($r["item_type"] === "allergy") $allergies[] = $r["item_value"];
      if ($r["item_type"] === "intolerance") $intolerances[] = $r["item_value"];
      if ($r["item_type"] === "preference") $preferences[] = $r["item_value"];
    }

    $noteQ = $pdo->prepare("SELECT note_text FROM user_notes WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $noteQ->execute([$userId]);
    $noteRow = $noteQ->fetch();
    $notes = $noteRow ? $noteRow["note_text"] : "";

    echo json_encode([
      "ok"=>true,
      "profile"=>[
        "id"=>(int)$user["id"],
        "name"=>$user["full_name"],
        "age"=>$user["age"] === null ? null : (int)$user["age"],
        "email"=>$user["email"],
        "severity"=>"standard", // kept client-side for logic; optional to persist later
        "allergies"=>$allergies,
        "intolerances"=>$intolerances,
        "preferences"=>$preferences,
        "notes"=>$notes
      ]
    ]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"Server error"]);
  }
  exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $payload = json_decode(file_get_contents("php://input"), true);
  if (!$payload) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Invalid JSON"]);
    exit;
  }

  $userId = (int)($payload["userId"] ?? 0);
  if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Missing userId"]);
    exit;
  }

  $name = trim($payload["name"] ?? "");
  $age  = $payload["age"] ?? null;
  $notes = trim($payload["notes"] ?? "");
  $severity = (($payload["severity"] ?? "standard") === "strict") ? "strict" : "standard";

  $allergies = norm_list($payload["allergies"] ?? []);
  $intolerances = norm_list($payload["intolerances"] ?? []);
  $preferences = norm_list($payload["preferences"] ?? []);

  if ($name === "") {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Name required"]);
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

  try {
    $pdo->beginTransaction();

    $up = $pdo->prepare("UPDATE users SET full_name = ?, age = ? WHERE id = ?");
    $up->execute([$name, $ageVal, $userId]);

    // replace dietary items
    $pdo->prepare("DELETE FROM user_dietary_items WHERE user_id = ?")->execute([$userId]);

    $ins = $pdo->prepare("INSERT INTO user_dietary_items (user_id, item_type, item_value, severity) VALUES (?, ?, ?, ?)");
    foreach ($allergies as $v) {
      $sev = ($severity === "strict") ? "severe" : "moderate";
      $ins->execute([$userId, "allergy", $v, $sev]);
    }
    foreach ($intolerances as $v) $ins->execute([$userId, "intolerance", $v, null]);
    foreach ($preferences as $v) $ins->execute([$userId, "preference", $v, null]);

    // replace notes (keep only latest)
    $pdo->prepare("DELETE FROM user_notes WHERE user_id = ?")->execute([$userId]);
    if ($notes !== "") {
      $pdo->prepare("INSERT INTO user_notes (user_id, note_text) VALUES (?, ?)")->execute([$userId, $notes]);
    }

    $pdo->commit();
    echo json_encode(["ok"=>true]);
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"Server error"]);
  }
  exit;
}

http_response_code(405);
echo json_encode(["ok"=>false,"error"=>"Method not allowed"]);
