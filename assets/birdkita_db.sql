-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for birdkita_db
CREATE DATABASE IF NOT EXISTS `birdkita_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `birdkita_db`;

-- Dumping structure for table birdkita_db.messages
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `conversation_id` varchar(100) DEFAULT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `conversation_id` (`conversation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table birdkita_db.messages: ~6 rows (approximately)
INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `conversation_id`, `message_text`, `is_read`, `created_at`) VALUES
	(1, 2, 2, '2_2', 'sdlkajfe', 1, '2026-02-19 11:28:29'),
	(2, 2, 2, '2_2', 'sdlkajfe', 1, '2026-02-19 11:28:32'),
	(3, 2, 2, '2_2', 'sdlkajfe', 1, '2026-02-19 11:28:34'),
	(4, 2, 2, '2_2', 'sdlkajfe', 1, '2026-02-19 11:28:37'),
	(5, 2, 2, '2_2', 'sdlkajfe', 1, '2026-02-19 11:28:40'),
	(6, 2, 2, '2_2', 'sdlkajfe', 1, '2026-02-19 11:28:43');

-- Dumping structure for table birdkita_db.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bird_id` int NOT NULL,
  `bird_name` varchar(255) DEFAULT NULL,
  `bird_type` varchar(255) DEFAULT NULL,
  `bird_price` varchar(100) DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `total_price` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `bird_id` (`bird_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`bird_id`) REFERENCES `winners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table birdkita_db.orders: ~2 rows (approximately)
INSERT INTO `orders` (`id`, `user_id`, `bird_id`, `bird_name`, `bird_type`, `bird_price`, `quantity`, `total_price`, `status`, `created_at`) VALUES
	(1, 4, 1, 'burung puyuh', 'Cucak Hijau', '200000', 1, '200000', 'Rejected', '2026-02-18 11:08:39'),
	(2, 4, 1, 'burung puyuh', 'Cucak Hijau', '200000', 2, '400000', 'Confirmed', '2026-02-18 11:45:04');

-- Dumping structure for table birdkita_db.seller_listings
CREATE TABLE IF NOT EXISTS `seller_listings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bird_name` varchar(255) NOT NULL,
  `bird_type` varchar(255) DEFAULT NULL,
  `bird_price` varchar(100) DEFAULT NULL,
  `bird_rank` varchar(100) DEFAULT NULL,
  `description` text,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table birdkita_db.seller_listings: ~1 rows (approximately)
INSERT INTO `seller_listings` (`id`, `user_id`, `bird_name`, `bird_type`, `bird_price`, `bird_rank`, `description`, `image_path`, `status`, `rejection_reason`, `created_at`, `updated_at`) VALUES
	(1, 4, 'burung ababil', 'Lainnya', '40000000', 'bisa lempar batu', 'dari neraka batunya', 'uploads/seller_6996932f7681b.jpg', 'approved', NULL, '2026-02-19 11:35:59', '2026-02-19 11:36:55');

-- Dumping structure for table birdkita_db.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table birdkita_db.users: ~3 rows (approximately)
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `created_at`) VALUES
	(2, 'admin', '$2y$10$qcbdaF93vR5z8GJJG9bBx.J9cjvCsNPfj9eCWdiH11UFeEdQgzwBO', 'admin', '2026-02-18 10:47:26'),
	(3, 'admin2', '$2y$10$VtEs5oC0qMlj7PxGJr315ecAh8jprn1rnFc54TNe8eKrBoxRVLj8u', 'admin', '2026-02-18 10:48:52'),
	(4, 'user1', '$2y$10$TYnH/2hwmsQ2aSHPTo8kV.GRJB6xwtZNIlDgQhselTiQzSFYu.wyC', 'user', '2026-02-18 11:00:17');

-- Dumping structure for table birdkita_db.winners
CREATE TABLE IF NOT EXISTS `winners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bird_name` varchar(255) NOT NULL,
  `bird_type` varchar(255) NOT NULL,
  `bird_price` varchar(100) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `bird_rank` varchar(50) DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `winners_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table birdkita_db.winners: ~7 rows (approximately)
INSERT INTO `winners` (`id`, `bird_name`, `bird_type`, `bird_price`, `image_path`, `bird_rank`, `uploaded_by`, `created_at`) VALUES
	(1, 'burung puyuh', 'Cucak Hijau', '200000', 'uploads/1771387174_a27bc9012b61.jpg', 'juara lomba renang', 2, '2026-02-18 10:59:34'),
	(2, 'burung purba', 'Burung Hantu', '1000000000', 'uploads/1771387429_8bff0031547c.jpg', 'bisa makan apa aja', 2, '2026-02-18 11:03:50'),
	(3, 'burung hantu', 'Lainnya', '5000000000', 'uploads/1771475418_b3f7c0fdbf45.jpg', 'juara satu makan tikus', 2, '2026-02-19 11:30:18'),
	(4, 'test', 'Lainnya', '9999999999', 'uploads/1771475455_0e69d27cd868.jpeg', 'test', 2, '2026-02-19 11:30:55'),
	(5, 'test', 'Lainnya', '999999', 'uploads/1771475513_1b60c02795f4.webp', 'test', 2, '2026-02-19 11:31:53'),
	(6, 'test', 'Lainnya', '999999', 'uploads/1771475555_a506ae9718ca.jpg', 'test', 2, '2026-02-19 11:32:35'),
	(7, 'test0', 'Lainnya', '99999', 'uploads/1771475593_9a65a43e92f5.jpg', 'test', 2, '2026-02-19 11:33:13');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
