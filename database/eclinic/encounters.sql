/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `encounters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `enccode` varchar(50) NOT NULL,
  `duration_of_problems` varchar(255) DEFAULT NULL,
  `previous_treatment` varchar(255) DEFAULT NULL,
  `present_treatment_plan` varchar(255) DEFAULT NULL,
  `reason_for_referral` varchar(255) DEFAULT NULL,
  `primary_care_physician` varchar(255) DEFAULT NULL,
  `last_visit` date DEFAULT NULL,
  `prognosis` varchar(255) DEFAULT NULL,
  `medications` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `healthcare_barriers` varchar(255) DEFAULT NULL,
  `encounter_type` enum('ADM','OPD','ERADM','ER','WALKN','OPDAD') NOT NULL,
  `ward_code` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67512 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
