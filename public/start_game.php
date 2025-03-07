<?php
// Include central database connection
require __DIR__ . '/../config/db_connection.php';

// $pdo is now ready to use.

// Retrieve parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;

if ($user_id === 0) {
    die(json_encode(["status" => "error", "message" => "Invalid user ID"]));
}

// Load Questions from JSON Files
$general_questions = json_decode(file_get_contents(__DIR__ . '/../resources/questions/general_questions.json'), true);
$department_questions = json_decode(file_get_contents(__DIR__ . '/../resources/questions/department_questions.json'), true);
$team_questions = json_decode(file_get_contents(__DIR__ . '/../resources/questions/team_questions.json'), true);
$group_questions = ($group_id) ? json_decode(file_get_contents(__DIR__ . "/../resources/questions/group_{$group_id}_questions.json"), true) : [];

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

// Store Selected Questions in Database
foreach ($final_questions as $q) {
    $stmt = $pdo->prepare("INSERT INTO UserQuestions (user_id, question_id, category, answered) VALUES (?, ?, ?, FALSE)");
    $stmt->execute([$user_id, $q['id'], $q['type']]);
}

// Prepare JSON structure
$formatted_questions = [
    "state" => [
        "step" => "start",
        "session_id" => $user_id,
        "scriptAnswers" => new stdClass(),
        "currentQuestion" => 0
    ],
    "questionDefinition" => [
        "surveyTitle" => "שאלות טריוויה ומשחקי חברה",
        "questions" => $final_questions
    ]
];

// Save session to `AllSessions` table
$stmt = $pdo->prepare("
    INSERT INTO AllSessions (session_id, chat_id, contact_id, data_json)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE data_json = VALUES(data_json), last_update_time = CURRENT_TIMESTAMP
");

$chat_id = "chat_id_placeholder"; // Replace with actual chat_id if applicable
$contact_id = "contact_id_placeholder"; // Replace with actual contact_id if applicable

$stmt->execute([
    $user_id, 
    $chat_id, 
    $contact_id, 
    json_encode($formatted_questions, JSON_UNESCAPED_UNICODE)
]);

// Output final JSON
echo json_encode(["status" => "success", "data" => $formatted_questions], JSON_UNESCAPED_UNICODE);
