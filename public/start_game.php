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
 * A question is considered "used" if a record exists in UserQuestions for that user, question ID,
 * and fixed category (regardless of the answered value).
 */
function getAllUnansweredQuestions($pdo, $user_id, $questions, $fixedCategory) {
    $unanswered = [];
    foreach ($questions["questionDefinition"]["questions"] as $q) {
        $stmt = $pdo->prepare("SELECT answered FROM UserQuestions WHERE user_id = ? AND question_id = ? AND category = ?");
        $stmt->execute([$user_id, $q['id'], $fixedCategory]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $isUnanswered = true;
        foreach ($rows as $row) {
            if ($row['answered'] != 0) { // If any record is answered (non-zero), mark as answered.
                $isUnanswered = false;
                break;
            }
        }
        if ($isUnanswered) {
            $unanswered[] = $q;
        }
    }
    return $unanswered;
}


/**
 * Randomly select one question from the provided array and tag it with the fixed category.
 */
function selectOneRandom($questions, $fixedCategory) {
    if (!empty($questions)) {
        $q = $questions[array_rand($questions)];
        // Override any JSON category with the fixed value
        $q['selected_category'] = $fixedCategory;
        return [$q];
    }
    return [];
}

/**
 * Randomly select $num questions from the provided array and tag each with the fixed category.
 */
function selectRandomMultiple($questions, $num, $fixedCategory) {
    $selected = [];
    if (!empty($questions)) {
        if (count($questions) > $num) {
            $keys = array_rand($questions, $num);
            if (!is_array($keys)) {
                $keys = [$keys];
            }
            foreach ($keys as $key) {
                $q = $questions[$key];
                $q['selected_category'] = $fixedCategory;
                $selected[] = $q;
            }
        } else {
            foreach ($questions as $q) {
                $q['selected_category'] = $fixedCategory;
                $selected[] = $q;
            }
        }
    }
    return $selected;
}

// ---- For Department, Team, and Group Questions ----
// Filter unanswered questions and select one random question from each set (if available).
$selected_department = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $department_questions, "department"), "department");
$selected_team = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $team_questions, "team"), "team");
$selected_group = [];
if (!empty($group_questions)) {
    $selected_group = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $group_questions, "group"), "group");
}

// Count how many questions are selected from these non-general sets.
$others_count = count($selected_department) + count($selected_team) + count($selected_group);

// ---- For General Questions ----
// Filter unanswered general questions.
$general_unanswered = getAllUnansweredQuestions($pdo, $user_id, $general_questions, "general");
// Determine how many general questions are needed.
// If no other questions, select 5; otherwise, select (5 - others_count) with a minimum of 2.
if ($others_count === 0) {
    $general_needed = 5;
} else {
    $general_needed = 5 - $others_count;
    if ($general_needed < 2) {
        $general_needed = 2;
    }
}
$selected_general = selectRandomMultiple($general_unanswered, $general_needed, "general");

// ---- Combine All Selected Questions ----
$final_questions = array_merge($selected_department, $selected_team, $selected_group, $selected_general);

// ---- Insert Selected Questions into UserQuestions ----
// This ensures that a question already presented won't be selected again.
foreach ($final_questions as $q) {
    $stmt = $pdo->prepare("INSERT INTO UserQuestions (user_id, question_id, category, answered) VALUES (?, ?, ?, 0)");
    $stmt->execute([$user_id, $q['id'], $q['selected_category']]);
}

// ---- Prepare the Session JSON Structure ----
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

// ---- Insert or Update Session Data in AllSessions (including action_script_id) ----
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
