DELIMITER //
CREATE PROCEDURE GetUnansweredQuestions(IN p_user_id INT, IN p_group_id INT)
BEGIN
    SELECT q.*
    FROM Questions q
    LEFT JOIN UserQuestions uq ON q.question_id = uq.question_id AND uq.user_id = p_user_id
    WHERE uq.question_id IS NULL
    ORDER BY q.difficulty ASC
    LIMIT 5;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE UpdateUserScore(IN p_user_id INT, IN p_correct_answers INT)
BEGIN
    DECLARE base_score FLOAT;
    SET base_score = IF(p_correct_answers >= 3, 1, p_correct_answers * 0.333);
    
    INSERT INTO UserScores (user_id, total_score) 
    VALUES (p_user_id, base_score) 
    ON DUPLICATE KEY UPDATE total_score = total_score + base_score;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE LogChatMessage(IN p_chat_id INT, IN p_user_id INT, IN p_session_id INT, IN p_message_text TEXT)
BEGIN
    INSERT INTO ChatLogs (chat_id, user_id, session_id, message_text) VALUES (p_chat_id, p_user_id, p_session_id, p_message_text);
END //
DELIMITER ;

<?php
require '../config/db_connection.php';

$command = isset($_GET['command']) ? $_GET['command'] : '';

switch ($command) {
    case 'update_score':
        updateUserScore($pdo, $_GET['user_id'], $_GET['correct_answers']);
        break;

    case 'log_chat_message':
        logChatMessage($pdo, $_GET['chat_id'], $_GET['user_id'], $_GET['session_id'], $_GET['message_text']);
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid command"]);
        break;
}

// פונקציה לעדכון ניקוד
function updateUserScore($pdo, $user_id, $correct_answers) {
    $stmt = $pdo->prepare("CALL UpdateUserScore(?, ?)");
    $stmt->execute([$user_id, $correct_answers]);
    echo json_encode(["status" => "success", "message" => "Score updated"]);
}

// פונקציה ללוג הודעות
function logChatMessage($pdo, $chat_id, $user_id, $session_id, $message_text) {
    $stmt = $pdo->prepare("CALL LogChatMessage(?, ?, ?, ?)");
    $stmt->execute([$chat_id, $user_id, $session_id, $message_text]);
    echo json_encode(["status" => "success", "message" => "Chat message logged"]);
}
?>




