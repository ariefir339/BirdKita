-- birdkita_fixed.sql - minimal SQL for BirdKita demo
-- This file creates only the database and `users` table used by the demo.
-- Use this file for easy import on Laragon or with the provided import.php script.

DROP DATABASE IF EXISTS `birdkita`;
CREATE DATABASE `birdkita` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `birdkita`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: insert a demo user using the registration form in the app.
-- You can import this file via phpMyAdmin or open http://localhost/birdkita/import.php and run the import.
