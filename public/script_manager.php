<?php
require '../config/db_connection.php';

// קבלת הפרמטרים מה-URL
$command = isset($_GET['command']) ? $_GET['command'] : '';

switch ($command) {
    case 'update_score':
        updateUserScore($pdo, $_GET['user_id'], $_GET['correct_answers']);
        break;

    case 'log_chat_message':
        logChatMessage($pdo, $_GET['chat_id'], $_GET['user_id'], $_GET['session_id'], $_GET['message_text']);
        break;

    case 'get_chat_history':
        getUserChatHistory($pdo, $_GET['user_id']);
        break;

    case 'get_unanswered_questions':
        getUnansweredQuestions($pdo, $_GET['user_id'], $_GET['group_id'] ?? null);
        break;

    case 'clean_old_data':
        cleanOldData($pdo);
        break;

    case 'generate_daily_report':
        generateDailyReport($pdo);
        break;

    case 'translate_question':
        translateQuestion($_GET['question_text'], $_GET['language']);
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid command"]);
        break;
}

// 1️⃣ עדכון ניקוד לאחר תשובה
function updateUserScore($pdo, $user_id, $correct_answers) {
    $stmt = $pdo->prepare("CALL UpdateUserScore(?, ?)");
    $stmt->execute([$user_id, $correct_answers]);
    echo json_encode(["status" => "success", "message" => "Score updated"]);
}

// 2️⃣ רישום הודעה בצ'אט
function logChatMessage($pdo, $chat_id, $user_id, $session_id, $message_text) {
    $stmt = $pdo->prepare("CALL LogChatMessage(?, ?, ?, ?)");
    $stmt->execute([$chat_id, $user_id, $session_id, $message_text]);
    echo json_encode(["status" => "success", "message" => "Chat message logged"]);
}

// 3️⃣ שליפת נתוני צ'אט של משתמש
function getUserChatHistory($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT chat_id, session_id, message_text, timestamp FROM ChatLogs WHERE user_id = ? ORDER BY timestamp DESC");
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["status" => "success", "chat_history" => $results]);
}

// 4️⃣ שליפת שאלות שטרם נענו
function getUnansweredQuestions($pdo, $user_id, $group_id = null) {
    $stmt = $pdo->prepare("CALL GetUnansweredQuestions(?, ?)");
    $stmt->execute([$user_id, $group_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["status" => "success", "questions" => $questions]);
}

// 5️⃣ ניקוי יומי של נתונים ישנים (שאלות שנענו לפני 30 יום)
function cleanOldData($pdo) {
    $stmt = $pdo->query("DELETE FROM UserQuestions WHERE answered = TRUE AND timestamp < NOW() - INTERVAL 30 DAY");
    echo json_encode(["status" => "success", "message" => "Old data cleaned"]);
}

// 6️⃣ שליחת דוח יומי למנהל
function generateDailyReport($pdo) {
    $stmt = $pdo->query("SELECT * FROM UserScores WHERE last_updated >= NOW() - INTERVAL 1 DAY");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $report = "Daily Report - User Scores:\n";
    foreach ($results as $row) {
        $report .= "User ID: {$row['user_id']} - Score: {$row['total_score']}\n";
    }

    sendMessageToUser(123456789, $report); // שליחת הדוח למנהל
    echo json_encode(["status" => "success", "message" => "Daily report sent"]);
}

// 7️⃣ שליחת הודעה לבוט
function sendMessageToUser($chat_id, $message) {
    $api_url = "https://api.telegram.org/botYOUR_BOT_TOKEN/sendMessage";
    $params = ["chat_id" => $chat_id, "text" => $message];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// 8️⃣ שליחת שאלה לתרגום
function translateQuestion($question_text, $target_language) {
    $api_key = "YOUR_OPENAI_API_KEY";
    $url = "https://api.openai.com/v1/chat/completions";

    $headers = ["Content-Type: application/json", "Authorization: Bearer $api_key"];
    $data = json_encode([
        "model" => "gpt-4",
        "messages" => [["role" => "system", "content" => "Translate this: $question_text to $target_language"]],
        "temperature" => 0.7
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    echo json_encode(["status" => "success", "translated_text" => $result['choices'][0]['message']['content'] ?? "Translation failed"]);
}
?>
