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
$previousSessionID = isset($_GET['previousSessionID']) ? $_GET['previousSessionID'] : null;

// Use contact_id as the user identifier
$user_id = $contact_id;

// Load JSON files (located in /data)
$general_questions = json_decode(file_get_contents(__DIR__ . '/../data/general_questions.json'), true);
$department_questions = json_decode(file_get_contents(__DIR__ . '/../data/department_questions.json'), true);
$team_questions = json_decode(file_get_contents(__DIR__ . '/../data/team_questions.json'), true);
$group_questions = ($group_id) ? json_decode(file_get_contents(__DIR__ . "/../data/group_questions_{$group_id}.json"), true) : [];

/**
 * Returns all unanswered questions for a given set.
 * A question is considered unanswered if either no record exists in UserQuestions for that user,
 * question ID, and fixed category, or if a record exists with answered == 0.
 */
function getAllUnansweredQuestions($pdo, $user_id, $questions, $fixedCategory) {
    $unanswered = [];
    foreach ($questions["questionDefinition"]["questions"] as $q) {
        $stmt = $pdo->prepare("SELECT answered FROM UserQuestions WHERE user_id = ? AND question_id = ? AND category = ?");
        $stmt->execute([$user_id, $q['id'], $fixedCategory]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $isUnanswered = true;
        foreach ($rows as $row) {
            if ($row['answered'] != 0) { // if any record has answered nonzero, question is answered
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
$selected_department = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $department_questions, "department"), "department");
$selected_team = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $team_questions, "team"), "team");
$selected_group = [];
if (!empty($group_questions)) {
    $selected_group = selectOneRandom(getAllUnansweredQuestions($pdo, $user_id, $group_questions, "group"), "group");
}
$others_count = count($selected_department) + count($selected_team) + count($selected_group);

// ---- For General Questions ----
$general_unanswered = getAllUnansweredQuestions($pdo, $user_id, $general_questions, "general");
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
// Also determine the correct answer by matching BestAnswer text with displayValue of options.
foreach ($final_questions as $q) {
    $correct_option = null;
    if (isset($q['BestAnswer']) && isset($q['options']) && is_array($q['options'])) {
        foreach ($q['options'] as $option) {
            if (strpos($option['displayValue'], $q['BestAnswer']) !== false) {
                $correct_option = $option['returnValue'];
                break;
            }
        }
    }
    $stmt = $pdo->prepare("INSERT INTO UserQuestions (user_id, session_id, question_id, category, answered, correct_answer) VALUES (?, ?, ?, ?, 0, ?)");
    $stmt->execute([$user_id, /*new session_id*/ 0, $q['id'], $q['selected_category'], $correct_option]);
    // We'll update the session_id below once we have it.
}

// ---- Create a New Session Record Using Auto-Increment ----
// Insert a temporary record with a dummy data_json to get a new session_id.
$stmt = $pdo->prepare("INSERT INTO AllSessions (action_script_id, chat_id, contact_id, data_json) VALUES (?, ?, ?, '{}')");
$stmt->execute([$action_script_id, $chat_id, $contact_id]);
$session_id = $pdo->lastInsertId();

// ---- Update the Session JSON Structure with session_id and previousSessionID ----
$formatted_questions = [
    "state" => [
        "step" => "start",
        "session_id" => $session_id,
        "previousSessionID" => $previousSessionID,
        "scriptAnswers" => new stdClass(),
        "currentQuestion" => 0
    ],
    "questionDefinition" => [
        "surveyTitle" => "שאלות טריוויה ומשחקי חברה",
        "questions" => $final_questions
    ]
];

// ---- Update the AllSessions record with the full session data ----
$stmt = $pdo->prepare("UPDATE AllSessions SET data_json = ? WHERE session_id = ?");
$stmt->execute([json_encode($formatted_questions, JSON_UNESCAPED_UNICODE), $session_id]);

// ---- Update UserQuestions with the new session_id ----
$stmt = $pdo->prepare("UPDATE UserQuestions SET session_id = ? WHERE user_id = ? AND session_id = 0");
$stmt->execute([$session_id, $user_id]);

echo json_encode(["status" => "success", "data" => $formatted_questions], JSON_UNESCAPED_UNICODE);
