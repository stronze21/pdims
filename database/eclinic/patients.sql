/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `patients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `hpercode` varchar(20) DEFAULT NULL,
  `patlast` varchar(100) NOT NULL,
  `patfirst` varchar(100) NOT NULL,
  `patmiddle` varchar(100) DEFAULT NULL,
  `patbdate` date DEFAULT NULL,
  `pataddress` varchar(100) DEFAULT '',
  `patcivilstat` varchar(100) DEFAULT NULL,
  `patgender` varchar(10) DEFAULT NULL,
  `patcontactno` varchar(100) DEFAULT NULL,
  `patoccupation` varchar(100) DEFAULT NULL,
  `patemail` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `patimage` varchar(100) DEFAULT NULL,
  `isLGBTQ` tinyint(1) NOT NULL DEFAULT 0,
  `patReligion` varchar(20) DEFAULT NULL,
  `patEducation` varchar(20) DEFAULT NULL,
  `patIncome` decimal(9,2) NOT NULL DEFAULT 0.00,
  `patNationality` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=141589 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
