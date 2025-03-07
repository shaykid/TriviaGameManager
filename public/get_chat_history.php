<?php
require '../config/db_connection.php';

$user_id = $_GET['user_id'];
$stmt = $pdo->prepare("SELECT chat_id, session_id, message_text, timestamp FROM ChatLogs WHERE user_id = ? ORDER BY timestamp DESC");
$stmt->execute([$user_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["status" => "success", "chat_history" => $results]);
?>
