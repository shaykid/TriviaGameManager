<?php
require '../config/db_connection.php';

function startGame($pdo, $user_id, $group_id = null) {
    // שליפת שאלות מתאימות שלא נענו
    $stmt = $pdo->prepare("
        CALL GetUnansweredQuestions(?, ?)
    ");
    $stmt->execute([$user_id, $group_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // החזרת שאלות למשתמש
    echo json_encode(["status" => "success", "questions" => $questions]);
}
?>

