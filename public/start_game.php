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

/**
 * Filter out any questions that the user has already answered.
 * We consider a question "answered" if there is a record in UserQuestions
 * where answered <> 0.
 */
function getAllUnansweredQuestions($pdo, $user_id, $questions, $category) {
    $unanswered = [];
    foreach ($questions["questionDefinition"]["questions"] as $q) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM UserQuestions WHERE user_id = ? AND question_id = ? AND category = ? AND answered <> 0");
        $stmt->execute([$user_id, $q['id'], $category]);
        $count = $stmt->fetchColumn();
        if (!$count) {  // if count is zero, question is unanswered
            $unanswered[] = $q;
        }
    }
    return $unanswered;
}

/**
 * Randomly select one question from the filtered unanswered questions array.
 */
function selectOneRandom($questions) {
    if (!empty($questions)) {
        return [$questions[array_rand($questions)]];
    }
    return [];
}

// For department, team, and group sets: filter unanswered and select one random question (if available)
$selected_department = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $department_questions, "department"));
$selected_team = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $team_questions, "team"));
$selected_group = [];
if (!empty($group_questions)) {
    $selected_group = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $group_questions, "group"));
}

// Count questions selected from these sets
$others_count = count($selected_department) + count($selected_team) + count($selected_group);

// For general questions: filter unanswered and then randomly select as many as needed
$general_unanswered = getAllUnansweredQuestions($pdo, $user_id, $general_questions, "general");

// Calculate how many general questions are needed:
// Total must be 5; if others are present, general questions = 5 - others_count.
// If no other questions, select 5. Also enforce a minimum of 2 general questions when others exist.
if ($others_count === 0) {
    $general_needed = 5;
} else {
    $general_needed = 5 - $others_count;
    if ($general_needed < 2) {
        $general_needed = 2;
    }
}

// Randomly select required number of general questions from the filtered array
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

// Combine all selected questions into the final set
$final_questions = array_merge($selected_department, $selected_team, $selected_group, $selected_general);

// Insert each selected question into UserQuestions table (with answered = FALSE)
foreach ($final_questions as $q) {
    $stmt = $pdo->prepare("INSERT INTO UserQuestions (user_id, question_id, category, answered) VALUES (?, ?, ?, FALSE)");
    $stmt->execute([$user_id, $q['id'], $q['question_theme']]);
}

// Prepare the JSON structure for the session
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

// Insert or update session data in AllSessions (including action_script_id)
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
