<?php
require '../config/db_connection.php';
require '../scripts/score_manager.php';

$user_id = $_GET['user_id'];
$correct_answers = $_GET['correct_answers'];

updateUserScore($pdo, $user_id, $correct_answers);
?>
