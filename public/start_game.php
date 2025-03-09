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
$team_questions = json_decode(file_get_contents(__DIR__ . '/../data/team_questions.json'), true);
$group_questions = ($group_id) ? json_decode(file_get_contents(__DIR__ . "/../data/group_questions_{$group_id}.json"), true) : [];

/**
 * Returns all unanswered questions for a given set.
 * A question is considered answered if there is a record in UserQuestions where
 * category = $desiredCategory and answered <> 0.
 */
function getAllUnansweredQuestions($pdo, $user_id, $questions, $desiredCategory) {
    $unanswered = [];
    foreach ($questions["questionDefinition"]["questions"] as $q) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM UserQuestions WHERE user_id = ? AND question_id = ? AND category = ? AND answered <> 0");
        $stmt->execute([$user_id, $q['id'], $desiredCategory]);
        $count = $stmt->fetchColumn();
        if (!$count) {
            $unanswered[] = $q;
        }
    }
    return $unanswered;
}

/**
 * Randomly selects one question from the provided array and tags it with the desired category.
 */
function selectOneRandom($questions, $desiredCategory) {
    if (!empty($questions)) {
        $q = $questions[array_rand($questions)];
        $q['selected_category'] = $desiredCategory;
        return [$q];
    }
    return [];
}

/**
 * Randomly selects $num questions from the provided array and tags them with the desired category.
 */
function selectRandomMultiple($questions, $num, $desiredCategory) {
    $selected = [];
    if (!empty($questions)) {
        if (count($questions) > $num) {
            $keys = array_rand($questions, $num);
            if (!is_array($keys)) {
                $keys = [$keys];
            }
            foreach ($keys as $key) {
                $q = $questions[$key];
                $q['selected_category'] = $desiredCategory;
                $selected[] = $q;
            }
        } else {
            foreach ($questions as $q) {
                $q['selected_category'] = $desiredCategory;
                $selected[] = $q;
            }
        }
    }
    return $selected;
}

// ----- Selection for Department, Team, and Group -----
// Filter unanswered questions for each set and select one random question if available.
$selected_department = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $department_questions, "department"), "department");
$selected_team = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $team_questions, "team"), "team");
$selected_group = [];
if (!empty($group_questions)) {
    $selected_group = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $group_questions, "group"), "group");
}

// Count the number of questions selected from non-general sets.
$others_count = count($selected_department) + count($selected_team) + count($selected_group);

// ----- Selection for General Questions -----
// Filter unanswered general questions.
$general_unanswered = getAllUnansweredQuestions($pdo, $user_id, $general_questions, "general");
// Determine how many general questions are needed:
// If no other questions, select 5 general questions.
// Otherwise, select (5 - others_count) but enforce a minimum of 2.
if ($others_count === 0) {
    $general_needed = 5;
} else {
    $general_needed = 5 - $others_count;
    if ($general_needed < 2) {
        $general_needed = 2;
    }
}
$selected_general = selectRandomMultiple($general_unanswered, $general_needed, "general");

// Combine all selected questions.
$final_questions = array_merge($selected_department, $selected_team, $selected_group, $selected_general);

// ----- Insertion into UserQuestions -----
// Insert each selected question into UserQuestions with answered = 0 (not yet answered).
foreach ($final_questions as $q) {
    // Use the explicitly tagged category.
    $stmt = $pdo->prepare("INSERT INTO UserQuestions (user_id, question_id, category, answered) VALUES (?, ?, ?, 0)");
    $stmt->execute([$user_id, $q['id'], $q['selected_category']]);
}

// ----- Prepare Session Data -----
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
