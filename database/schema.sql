CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) UNIQUE, -- Added phone number field
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE UserScores (
    user_id INT PRIMARY KEY,
    total_score FLOAT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

CREATE TABLE UserQuestions (
    user_id INT,
    question_id VARCHAR(255),
    category VARCHAR(50),
    answered BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (user_id, question_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES Questions(question_id) ON DELETE CASCADE
);

CREATE TABLE Questions (
    question_id VARCHAR(255) PRIMARY KEY,
    question_text TEXT NOT NULL,
    category VARCHAR(50),
    difficulty INT DEFAULT 1
);

CREATE TABLE ChatLogs (
    chat_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id INT,
    message_text TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

CREATE TABLE QrScans (
    qr_scan_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    qr_id VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);
