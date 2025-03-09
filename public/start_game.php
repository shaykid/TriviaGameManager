<?php
require __DIR__ . '/../config/db_connection.php';

// Retrieve parameters safely
$contact_id = isset($_GET['contact_id']) ? $_GET['contact_id'] : "";
if (empty($contact_id)) {
    die(json_encode(["status" => "error", "message" => "Invalid contact ID"]));
}
$chat_id = isset($_GET['chat_id']) ? $_GET['chat_id'] : "chat_id_placeholder";
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
$action_script_id = isset($_GET['action_script_id']) ? intval($_GET['action_script_id']) : 0;

// Use contact_id as the user identifier
$user_id = $contact_id;

// Determine the new session_id: add 2 to the last session_id for this chat/contact
$stmt = $pdo->prepare("SELECT MAX(session_id) as max_session FROM AllSessions WHERE chat_id = ? AND contact_id = ?");
$stmt->execute([$chat_id, $contact_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$session_id = ($row && $row['max_session'] !== null) ? $row['max_session'] + 2 : 4000;

// Load JSON files (located in /data)
$general_questions = json_decode(file_get_contents(__DIR__ . '/../data/general_questions.json'), true);
$department_questions = json_decode(file_get_contents(__DIR__ . '/../data/department_questions.json'), true);
$team_questions = json_decode(file_get_contents(__DIR__ . "/../data/team_questions.json"), true);
$group_questions = ($group_id) ? json_decode(file_get_contents(__DIR__ . "/../data/group_questions_{$group_id}.json"), true) : [];

// Updated function: returns all unanswered questions based on UserQuestions
function getAllUnansweredQuestions($pdo, $user_id, $questions, $category) {
    $unanswered = [];
    foreach ($questions["questionDefinition"]["questions"] as $q) {
        // Count the question as answered if there's a record where answered <> 0
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM UserQuestions WHERE user_id = ? AND question_id = ? AND category = ? AND answered = 0");
        $stmt->execute([$user_id, $q['id'], $category]);
        $already_answered = $stmt->fetchColumn();
        if (!$already_answered) {
            $unanswered[] = $q;
        }
    }
    return $unanswered;
}

// For department, team, and group sets: select one random unanswered question (if available)
$selected_department = [];
$dept_unanswered = getAllUnansweredQuestions($pdo, $user_id, $department_questions, "department");
if (!empty($dept_unanswered)) {
    $selected_department[] = $dept_unanswered[array_rand($dept_unanswered)];
}

$selected_team = [];
$team_unanswered = getAllUnansweredQuestions($pdo, $user_id, $team_questions, "team");
if (!empty($team_unanswered)) {
    $selected_team[] = $team_unanswered[array_rand($team_unanswered)];
}

$selected_group = [];
if (!empty($group_questions)) {
    $group_unanswered = getAllUnansweredQuestions($pdo, $user_id, $group_questions, "group");
    if (!empty($group_unanswered)) {
        $selected_group[] = $group_unanswered[array_rand($group_unanswered)];
    }
}

// Count how many questions we got from other sets
$others_count = count($selected_department) + count($selected_team) + count($selected_group);

// Determine how many general questions are needed
$general_needed = 5 - $others_count;
if ($others_count === 0) {
    $general_needed = 5;
} elseif ($general_needed < 2) {
    // Ensure at least 2 general questions if any others were selected
    $general_needed = 2;
}

// Select random unanswered general questions
$general_unanswered = getAllUnansweredQuestions($pdo, $user_id, $general_questions, "general");
$selected_general = [];
if (!empty($general_unanswered)) {
    if (count($general_unanswered) > $general_needed) {
        $keys = array_rand($general_unanswered, $general_needed);
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        foreach ($keys as $key) {
            $selected_general[] = $general_unanswered[$key];
        }
    } else {
        $selected_general = $general_unanswered;
    }
}

// Combine all selected questions
$final_questions = array_merge($selected_department, $selected_team, $selected_group, $selected_general);

// Insert each selected question into UserQuestions (with answered = FALSE)
// (Optionally, you can check for duplicates before inserting)
foreach ($final_questions as $q) {
    $stmt = $pdo->prepare("INSERT INTO UserQuestions (user_id, question_id, category, answered) VALUES (?, ?, ?, FALSE)");
    $stmt->execute([$user_id, $q['id'], $q['question_theme']]);
}

// Prepare the session JSON structure
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

// Insert or update the session data in AllSessions (including action_script_id)
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
