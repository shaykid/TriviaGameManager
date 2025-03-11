<?php
require __DIR__ . '/../config/db_connection.php';

// Retrieve required GET parameters
$contact_id = isset($_GET['contact_id']) ? $_GET['contact_id'] : "";
$chat_id = isset($_GET['chat_id']) ? $_GET['chat_id'] : "";
$action_script_id = isset($_GET['action_script_id']) ? intval($_GET['action_script_id']) : 0;

if (empty($contact_id) || empty($chat_id) || $action_script_id === 0) {
    die(json_encode(["status" => "error", "message" => "Missing required parameters."]));
}

// Insert a new session record with an empty JSON object as data_json
$stmt = $pdo->prepare("INSERT INTO AllSessions (action_script_id, chat_id, contact_id, data_json) VALUES (?, ?, ?, '{}')");
$stmt->execute([$action_script_id, $chat_id, $contact_id]);
$session_id = $pdo->lastInsertId();

// Return the new session_id in a JSON response
echo json_encode(["status" => "success", "session_id" => $session_id], JSON_UNESCAPED_UNICODE);
