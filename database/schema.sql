CREATE TABLE UserQuestions (
    user_id INT,
    question_id VARCHAR(255),
    category VARCHAR(50),
    answered BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (user_id, question_id)
);

CREATE TABLE UserScores (
    user_id INT PRIMARY KEY,
    total_score FLOAT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
