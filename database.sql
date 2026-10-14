-- ============================================================
-- TechLearn Hub - Database Schema
-- Import this file in phpMyAdmin or via:
--   mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS techlearn_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE techlearn_hub;

-- ---------------------------------------------------------
-- Users (students) + Admins share one table, differentiated by role
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','admin') NOT NULL DEFAULT 'student',
    status ENUM('active','banned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin login: admin@techlearnhub.com / Admin@123
-- (password below is a bcrypt hash generated with PHP password_hash)
INSERT INTO users (name, email, password, role) VALUES
('Super Admin', 'admin@techlearnhub.com', '$2y$10$92xU1u5tK1f0m8N0m1o0EuY1S1yv2wQjq0aQx8B1cQe0S8m6y2b8O', 'admin');

-- ---------------------------------------------------------
-- Subjects
-- ---------------------------------------------------------
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,   -- SFT, ICT, ET, BST, English
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO subjects (code, name, description) VALUES
('SFT', 'Software Technology', 'Software Technology stream subject'),
('ICT', 'Information & Communication Technology', 'ICT stream subject'),
('ET', 'Engineering Technology', 'Engineering Technology stream subject'),
('BST', 'Bio System Technology', 'Bio System Technology stream subject'),
('English', 'English Language', 'English language subject');

-- ---------------------------------------------------------
-- Resources (PDFs: Notes, Past Papers, Tutorials, Marking Schemes, Unit Papers)
-- ---------------------------------------------------------
CREATE TABLE resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    type ENUM('Notes','Past Papers','Tutorials','Marking Schemes','Unit Papers') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_size INT DEFAULT 0,
    content_text LONGTEXT NULL,   -- extracted PDF text, used as AI chatbot reference material
    uploaded_by INT,
    downloads INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    FULLTEXT KEY ft_content (title, content_text)
);

-- ---------------------------------------------------------
-- Newsletter subscribers (footer subscribe form)
-- ---------------------------------------------------------
CREATE TABLE newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- AI Chatbot conversation logs
-- ---------------------------------------------------------
CREATE TABLE chatbot_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- Quiz Generator
-- ---------------------------------------------------------
CREATE TABLE quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_id INT,
    topic VARCHAR(200) NOT NULL,
    difficulty ENUM('Easy','Medium','Hard') DEFAULT 'Medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
);

CREATE TABLE quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('a','b','c','d') NOT NULL,
    explanation TEXT,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT NOT NULL,
    total INT NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- Mind-map Generator (stores structure as JSON)
-- ---------------------------------------------------------
CREATE TABLE mindmaps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_id INT,
    title VARCHAR(200) NOT NULL,
    content JSON NOT NULL,   -- nested {title, children:[...]} tree
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
);