<?php
require __DIR__ . '/../config/db_connection.php';

// Retrieve parameters safely from GET
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
$chat_id = isset($_GET['chat_id']) ? $_GET['chat_id'] : "chat_id_placeholder";
$contact_id = isset($_GET['contact_id']) ? $_GET['contact_id'] : "contact_id_placeholder";
$action_script_id = isset($_GET['action_script_id']) ? intval($_GET['action_script_id']) : 0;

if ($user_id === 0) {
    die(json_encode(["status" => "error", "message" => "Invalid user ID"]));
}

// Determine the new session_id: add 2 to the last session_id for this chat/contact
$stmt = $pdo->prepare("SELECT MAX(session_id) as max_session FROM AllSessions WHERE chat_id = ? AND contact_id = ?");
$stmt->execute([$chat_id, $contact_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$session_id = ($row && $row['max_session'] !== null) ? $row['max_session'] + 2 : 4000;

// Load JSON files correctly (located in /data)
$general_questions = json_decode(file_get_contents(__DIR__ . '/../data/general_questions.json'), true);
$department_questions = json_decode(file_get_contents(__DIR__ . '/../data/department_questions.json'), true);
$team_questions = json_decode(file_get_contents(__DIR__ . "/../data/team_questions.json"), true);
$group_questions = ($group_id) ? json_decode(file_get_contents(__DIR__ . "/../data/group_questions_{$group_id}.json"), true) : [];

// Function to Fetch Unanswered Questions
function getUnansweredQuestions($pdo, $user_id, $questions, $category, $limit) {
    $unanswered = [];
    foreach ($questions["questionDefinition"]["questions"] as $q) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM UserQuestions WHERE user_id = ? AND question_id = ? AND category = ?");
        $stmt->execute([$user_id, $q['id'], $category]);
        $already_answered = $stmt->fetchColumn();
        if (!$already_answered) {
            $unanswered[] = $q;
        }
        if (count($unanswered) >= $limit) break;
    }
    return $unanswered;
}

// Select questions
$selected_general = getUnansweredQuestions($pdo, $user_id, $general_questions, "general", 3);
$selected_team = getUnansweredQuestions($pdo, $user_id, $team_questions, "team", 1);
$selected_department = getUnansweredQuestions($pdo, $user_id, $department_questions, "department", 1);
$selected_group = ($group_questions) ? getUnansweredQuestions($pdo, $user_id, $group_questions, "group", 1) : [];

// Combine Selected Questions
$final_questions = array_merge($selected_general, $selected_team, $selected_department, $selected_group);

// Store Selected Questions in UserQuestions table
foreach ($final_questions as $q) {
    $stmt = $pdo->prepare("INSERT INTO UserQuestions (user_id, question_id, category, answered) VALUES (?, ?, ?, FALSE)");
    $stmt->execute([$user_id, $q['id'], $q['question_theme']]);
}

// Prepare JSON structure to be stored
$formatted_questions = [
    "state" => [
        "step" => "start",
        "session_id" => $session_id,
        "scriptAnswers" => new stdClass(),
        "currentQuestion" => 0
    ],
    "questionDefinition" => [
        "surveyTitle" => "שאלות טריוויה ומשחקי חברה",
        "questions" => $final_questions
    ]
];

// Insert or update session data in AllSessions table, including action_script_id
$stmt = $pdo->prepare("
    INSERT INTO AllSessions (session_id, action_script_id, chat_id, contact_id, data_json)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE data_json = VALUES(data_json), last_update_time = CURRENT_TIMESTAMP
");

$stmt->execute([
    $session_id, 
    $action_script_id,
    $chat_id, 
    $contact_id, 
    json_encode($formatted_questions, JSON_UNESCAPED_UNICODE)
]);

echo json_encode(["status" => "success", "data" => $formatted_questions], JSON_UNESCAPED_UNICODE);
