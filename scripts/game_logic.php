<?php
require '../config/db_connection.php';

function startGame($pdo, $user_id, $group_id = null) {
    // Fetch unanswered questions
    $stmt = $pdo->prepare("CALL GetUnansweredQuestions(?, ?)");
    $stmt->execute([$user_id, $group_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format questions in the required JSON structure
    $formatted_questions = [
        "questionDefinition" => [
            "surveyTitle" => "שאלות טריוויה ומשחקי חברה",
            "questions" => []
        ]
    ];

    foreach ($questions as $q) {
        $options = json_decode($q['options'], true); // Assuming options are stored as JSON
        $formatted_question = [
            "id" => $q['question_id'],
            "type" => "SINGLE_CHOICE",
            "question" => $q['question_text'],
            "question_theme" => $q['category'],
            "options" => $options,
            "BestAnswer" => $q['best_answer']
        ];
        
        $formatted_questions["questionDefinition"]["questions"][] = $formatted_question;
    }

    echo json_encode(["status" => "success", "data" => $formatted_questions], JSON_UNESCAPED_UNICODE);
}
?>
