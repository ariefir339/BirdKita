-- birdkita.sql - buat database dan table users
CREATE DATABASE IF NOT EXISTS birdkita CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE birdkita;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- contoh user (password: secret123)
INSERT INTO users (username, password_hash, created_at) VALUES
('demo', '$2y$10$y5rP2z3s0rK6YxvGz0uW7u8d/3QWQy2yx8XNdqzBz4d6a8aGQ3z5.', NOW());
-- NOTE: hash di atas adalah contoh dan mungkin tidak valid; buat password baru lewat register.php
birdkitausersusersusersusers