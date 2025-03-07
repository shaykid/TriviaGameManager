<?php
// Database Connection
$config = json_decode(file_get_contents("db_connection.json"), true);

try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8", $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]));
}

// Get User ID from URL
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : null; // Optional

if ($user_id === 0) {
    die(json_encode(["status" => "error", "message" => "Invalid user ID"]));
}

// Load Questions from JSON Files
$general_questions = json_decode(file_get_contents("general_questions.json"), true);
$team_questions = json_decode(file_get_contents("team_questions.json"), true);
$department_questions = json_decode(file_get_contents("department_questions.json"), true);
$group_questions = ($group_id) ? json_decode(file_get_contents("group_questions_{$group_id}.json"), true) : [];

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

// Select 3 General Questions
$selected_general = getUnansweredQuestions($pdo, $user_id, $general_questions, "general", 3);

// Select 1 Team Question
$selected_team = getUnansweredQuestions($pdo, $user_id, $team_questions, "team", 1);

// Select 1 Department Question
$selected_department = getUnansweredQuestions($pdo, $user_id, $department_questions, "department", 1);

// Select 1 Group Question (if applicable)
$selected_group = ($group_questions) ? getUnansweredQuestions($pdo, $user_id, $group_questions, "group", 1) : [];

// Combine Selected Questions
$final_questions = array_merge($selected_general, $selected_team, $selected_department, $selected_group);

// Store Selected Questions in Database
foreach ($final_questions as $q) {
    $stmt = $pdo->prepare("INSERT INTO UserQuestions (user_id, question_id, category, answered) VALUES (?, ?, ?, FALSE)");
    $stmt->execute([$user_id, $q['id'], $q['question_theme']]);
}

// Format questions in the required JSON structure
$formatted_questions = [
    "questionDefinition" => [
        "surveyTitle" => "שאלות טריוויה ומשחקי חברה",
        "questions" => $final_questions
    ]
];

// Output Questions
echo json_encode(["status" => "success", "data" => $formatted_questions], JSON_UNESCAPED_UNICODE);
?>
