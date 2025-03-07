<?php
function updateUserScore($pdo, $user_id, $correct_answers) {
    $base_score = ($correct_answers / 5 >= 0.6) ? 1 : ($correct_answers * 0.333);
    $stmt = $pdo->prepare("INSERT INTO UserScores (user_id, total_score) 
                           VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE total_score = total_score + ?");
    $stmt->execute([$user_id, $base_score, $base_score]);
}
?>
