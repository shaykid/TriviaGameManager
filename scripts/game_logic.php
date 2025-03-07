<?php
require '../config/db_connection.php';

function getUnansweredQuestions($pdo, $user_id, $questions_json, $category, $limit = 1) {
    $unanswered = [];
    foreach ($questions_json["questionDefinition"]["questions"] as $q) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM UserQuestions WHERE user_id=? AND question_id=? AND category=?");
        $stmt->execute([$user_id, $q['id'], $category]);
        if (!$stmt->fetchColumn()) {
            $q['category'] = $category; // explicitly add category
            $unanswered[] = $q;
        }
        if (count($unanswered) >= $limit) break;
    }
    return $unanswered;
}

function startGame($pdo, $user_id, $group_id = null) {
    // Load JSON question sets
    $general_questions = json_decode(file_get_contents("../data/general_questions.json"), true);
    $team_questions = json_decode(file_get_contents("../data/team_questions.json"), true);
    $department_questions = json_decode(file_get_contents("../data/department_questions.json"), true);

    // Select questions as per your rules
    $selected_general = getUnansweredQuestions($pdo, $user_id, $general_questions, "general", 3);
    $selected_team = getUnansweredQuestions($pdo, $user_id, $team_questions, "team", 1);
    $selected_department = getUnansweredQuestions($pdo, $user_id, $department_questions, "department", 1);

    // Combine all selected questions
    $final_questions = array_merge($selected_general, $selected_team, $selected_department);

    // Store Selected Questions in Database
    foreach ($final_questions as $q) {
        $stmt = $pdo->prepare("INSERT INTO UserQuestions (user_id, question_id, category, answered) VALUES (?, ?, ?, FALSE)");
        $stmt->execute([$user_id, $q['id'], $q['category']]);
    }

    // Prepare final structured JSON
    $formatted_questions = [
        "state" => [
            "step" => "start",
            "session_id" => null,
            "scriptAnswers" => new stdClass(),
            "currentQuestion" => 0
        ],
        "questionDefinition" => [
            "surveyTitle" => "שאלות טריוויה ומשחקי חברה",
            "questions" => $final_questions
        ],
        "metadata" => [
            "timestamp" => date("Y-m-d H:i:s"),
            "user_id" => $user_id,
            "group_id" => $group_id
        ]
    ];

    // Save session into AllSessions table
    $stmt = $pdo->prepare("INSERT INTO AllSessions (action_script_id, chat_id, data_json, status) VALUES (?, ?, ?, 'active')");
    $stmt->execute([
        1,
        "chat_".$user_id,
        json_encode($formatted_questions, JSON_UNESCAPED_UNICODE)
    ]);

    // Get the session ID of the inserted session
    $session_id = $pdo->lastInsertId();

    // Update session_id in the JSON data
    $formatted_questions["state"]["session_id"] = $session_id;

    // Update session data_json with actual session_id
    $stmt = $pdo->prepare("UPDATE AllSessions SET data_json=? WHERE session_id=?");
    $stmt->execute([
        json_encode($formatted_questions, JSON_UNESCAPED_UNICODE),
        $session_id
    ]);

    // Output the final structured questions JSON
    echo json_encode(["status" => "success", "data" => $formatted_questions], JSON_UNESCAPED_UNICODE);
}
?>
