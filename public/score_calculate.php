<?php
require __DIR__ . '/../config/db_connection.php';

// Get session_id from curl GET parameters
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
if ($session_id <= 0) {
    die(json_encode(["status" => "error", "message" => "Invalid session ID"]));
}

// Retrieve session record from AllSessions to get contact_id and chat_id
$stmt = $pdo->prepare("SELECT contact_id, chat_id FROM AllSessions WHERE session_id = ?");
$stmt->execute([$session_id]);
$sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sessionData) {
    die(json_encode(["status" => "error", "message" => "Session not found"]));
}
$contact_id = $sessionData['contact_id']; // This is our user_id
$chat_id = $sessionData['chat_id'];

// Calculate the session score from UserQuestions for this session and user.
// For each question, if the "answered" field equals the "correct_answer", score 1 point.
$stmt = $pdo->prepare("SELECT SUM(CASE WHEN answered = correct_answer THEN 1 ELSE 0 END) AS session_score FROM UserQuestions WHERE session_id = ? AND user_id = ?");
$stmt->execute([$session_id, $contact_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$session_score = ($result && $result['session_score'] !== null) ? intval($result['session_score']) : 0;

// Update the userScores table:
// - If a record exists for this user, add the new session score to the existing total score.
// - If no record exists, create one with the session score.
$stmt = $pdo->prepare("SELECT total_score FROM UserScores WHERE user_id = ?");
$stmt->execute([$contact_id]);
$userScoreData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($userScoreData) {
    $new_total_score = intval($userScoreData['total_score']) + $session_score;
    $stmt = $pdo->prepare("UPDATE UserScores SET total_score = ? WHERE user_id = ?");
    $stmt->execute([$new_total_score, $contact_id]);
} else {
    $new_total_score = $session_score;
    $stmt = $pdo->prepare("INSERT INTO UserScores (user_id, total_score) VALUES (?, ?)");
    $stmt->execute([$contact_id, $new_total_score]);
}

// Return a JSON response with the calculated scores.
echo json_encode([
    "status" => "success",
    "session_id" => $session_id,
    "session_score" => $session_score,
    "total_score" => $new_total_score
], JSON_UNESCAPED_UNICODE);
?>
