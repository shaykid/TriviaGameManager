<?php
require '../config/db_connection.php';

$data = json_decode(file_get_contents("php://input"), true);
$question_text = $data['question_text'];
$category = $data['category'];

$stmt = $pdo->prepare("INSERT INTO UserQuestions (question_id, category, answered) VALUES (UUID(), ?, FALSE)");
$stmt->execute([$category]);

echo json_encode(["status" => "success", "message" => "Question added"]);
?>
