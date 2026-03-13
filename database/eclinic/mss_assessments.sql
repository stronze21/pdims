/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `mss_assessments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `msscode` varchar(30) NOT NULL,
  `informant_name` varchar(100) DEFAULT NULL,
  `informant_address` varchar(150) DEFAULT NULL,
  `informant_contact` varchar(50) DEFAULT NULL,
  `informant_relation` varchar(20) DEFAULT NULL,
  `health_accessibility_problem` varchar(255) DEFAULT NULL,
  `medical_condition_understanding` varchar(255) DEFAULT NULL,
  `mss_class` varchar(100) DEFAULT NULL,
  `phic_subclass` varchar(2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `encounter_id` bigint(20) unsigned DEFAULT NULL,
  `family_other_income` varchar(150) DEFAULT NULL,
  `assessment_statement` text DEFAULT NULL,
  `recommended_interventions` text DEFAULT NULL,
  `hospital_bill` decimal(11,2) NOT NULL DEFAULT 0.00,
  `mandatory_discount` decimal(11,2) NOT NULL DEFAULT 0.00,
  `hospital_share` decimal(11,2) NOT NULL DEFAULT 0.00,
  `patient_share` decimal(11,2) NOT NULL DEFAULT 0.00,
  `other_expense` decimal(11,2) NOT NULL DEFAULT 0.00,
  `phic_share` decimal(11,2) NOT NULL DEFAULT 0.00,
  `malasakit_share` decimal(11,2) NOT NULL DEFAULT 0.00,
  `user_id` bigint(20) unsigned NOT NULL,
  `conforme_name` varchar(150) DEFAULT NULL,
  `conforme_contact` varchar(100) DEFAULT NULL,
  `conforme_relation` varchar(20) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `other_income_source` varchar(100) DEFAULT NULL,
  `other_income_value` decimal(11,2) DEFAULT 0.00,
  `is_final` tinyint(1) NOT NULL DEFAULT 0,
  `referral_name` varchar(100) DEFAULT NULL,
  `referral_address` varchar(150) DEFAULT NULL,
  `referral_contact` varchar(100) DEFAULT NULL,
  `form_type` varchar(20) NOT NULL DEFAULT 'ADULT',
  PRIMARY KEY (`id`),
  KEY `mss_assessments_encounter_id_foreign` (`encounter_id`),
  KEY `mss_assessments_user_id_foreign` (`user_id`),
  CONSTRAINT `mss_assessments_encounter_id_foreign` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`),
  CONSTRAINT `mss_assessments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67392 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
