-- ============================================================
-- VinStudio - Skema Database MySQL / MariaDB (cPanel)
-- Import file ini di phpMyAdmin pada database kamu
-- ============================================================

CREATE TABLE IF NOT EXISTS `profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_name` VARCHAR(150),
    `description` TEXT,
    `teacher_name` VARCHAR(150),
    `school_year` VARCHAR(50),
    `photo` VARCHAR(255),
    `vision` TEXT,
    `mission` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) UNIQUE NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) DEFAULT 'user',
    `is_banned` TINYINT DEFAULT 0,
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `albums` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `photo` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `target_type` VARCHAR(20) NOT NULL,
    `target_id` INT NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `target_type` VARCHAR(20) NOT NULL,
    `target_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_target` (`user_id`, `target_type`, `target_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `login_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `email` VARCHAR(191),
    `display_name` VARCHAR(100),
    `action` VARCHAR(50),
    `detail` TEXT,
    `ip` VARCHAR(45),
    `user_agent` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data Profil Default
INSERT INTO `profiles` (`id`, `class_name`, `description`, `teacher_name`, `school_year`, `photo`, `vision`, `mission`)
VALUES (1, 'Kelas XI TKJ 1', 'Kelas Teknik Komputer dan Jaringan', 'Bu Herlina, S.Pd.', '2026/2027', 'tkj-logo.png', 'Menjadi kelas unggulan dalam bidang TI dan jaringan', 'Menyelenggara pembelajaran inovatif dan praktis')
ON DUPLICATE KEY UPDATE `class_name` = VALUES(`class_name`);

-- Akun Administrator Default (Password: pontianak1)
INSERT INTO `users` (`id`, `email`, `display_name`, `password`, `role`)
VALUES (1, 'kiritohatsuno@gmail.com', 'Kelvin', '$2y$10$ZkYllFyeo6Q2rI11wK3Vke8oVb21tXWz0i3f7YvB0mR6k9T.N1JqG', 'admin')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);
