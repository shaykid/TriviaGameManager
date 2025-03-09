DELIMITER //
CREATE PROCEDURE GetUnansweredQuestions(IN p_user_id INT, IN p_group_id INT)
BEGIN
    SELECT q.*
    FROM Questions q
    LEFT JOIN UserQuestions uq ON q.question_id = uq.question_id AND uq.user_id = p_user_id
    WHERE uq.question_id IS NULL
    ORDER BY q.difficulty ASC
    LIMIT 5;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE UpdateUserScore(IN p_user_id INT, IN p_correct_answers INT)
BEGIN
    DECLARE base_score FLOAT;
    SET base_score = IF(p_correct_answers >= 3, 1, p_correct_answers * 0.333);
    
    INSERT INTO UserScores (user_id, total_score) 
    VALUES (p_user_id, base_score) 
    ON DUPLICATE KEY UPDATE total_score = total_score + base_score;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE LogChatMessage(IN p_chat_id INT, IN p_user_id INT, IN p_session_id INT, IN p_message_text TEXT)
BEGIN
    INSERT INTO ChatLogs (chat_id, user_id, session_id, message_text) VALUES (p_chat_id, p_user_id, p_session_id, p_message_text);
END //
DELIMITER ;
