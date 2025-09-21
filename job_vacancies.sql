CREATE TABLE IF NOT EXISTS `job_vacancies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `banner_path` VARCHAR(255) NOT NULL,
  `banner_description` TEXT NOT NULL,
  `job_posted_date` DATE NOT NULL,
  `job_title` VARCHAR(255) NOT NULL,
  `job_id` VARCHAR(100) NOT NULL,
  `vacancy` VARCHAR(255) NOT NULL,
  `education` VARCHAR(255) NOT NULL,
  `gender` VARCHAR(20) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `job_description` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
