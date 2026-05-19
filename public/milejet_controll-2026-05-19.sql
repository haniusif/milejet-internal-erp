-- MySQL dump 10.13  Distrib 9.6.0, for macos15.7 (arm64)
--
-- Host: localhost    Database: milejet_controll
-- ------------------------------------------------------
-- Server version	9.6.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'abf703e6-3bad-11f1-80dd-d54e083c15b6:1-15419';

--
-- Table structure for table `attendances`
--

DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odoo_id` int NOT NULL,
  `odoo_employee_id` int NOT NULL,
  `employee_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `check_in` datetime NOT NULL,
  `check_out` datetime DEFAULT NULL,
  `worked_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendances_odoo_id_unique` (`odoo_id`),
  KEY `attendances_odoo_employee_id_index` (`odoo_employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
INSERT INTO `attendances` VALUES (2,2,1,'Administrator','2026-05-17 13:46:38','2026-05-17 13:47:11',0.01,'2026-05-18 08:15:18','2026-05-17 10:46:40','2026-05-18 08:15:18'),(4,3,1,'Administrator','2026-05-17 16:48:21','2026-05-17 16:48:23',0.00,'2026-05-18 08:15:18','2026-05-18 08:15:18','2026-05-18 08:15:18');
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odoo_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `odoo_employee_id` int NOT NULL,
  `employee_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wage` decimal(12,2) NOT NULL DEFAULT '0.00',
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `state` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `odoo_struct_id` int DEFAULT NULL,
  `struct_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contracts_odoo_id_unique` (`odoo_id`),
  KEY `contracts_odoo_employee_id_index` (`odoo_employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contracts`
--

LOCK TABLES `contracts` WRITE;
/*!40000 ALTER TABLE `contracts` DISABLE KEYS */;
INSERT INTO `contracts` VALUES (35,35,'Contract - SABRI OMER',38,'SABRI OMER',14000.00,'2024-11-01','2025-11-01','close',1,'Saudi Monthly Salary','2026-05-18 18:06:03','2026-05-18 18:06:03','2026-05-18 18:06:03'),(36,36,'Contract - HASSAN SALAH MOHAMMED',39,'HASSAN SALAH MOHAMMED',3000.00,'2024-11-01','2025-11-01','close',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(37,37,'Contract - KAMRUL MD JOYDAL',40,'KAMRUL MD JOYDAL',2500.00,'2025-05-01','2026-05-01','close',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(38,38,'Contract - AHMED ALSULTAN',41,'AHMED ALSULTAN',14000.00,'2025-07-01','2026-07-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(39,39,'Contract - ALAAULDEEN  Nabil ARABI',42,'ALAAULDEEN  Nabil ARABI',4500.00,'2025-07-10','2026-07-10','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(40,40,'Contract - MUHAMMAD SHAHBAZ HUSSAIN',43,'MUHAMMAD SHAHBAZ HUSSAIN',3000.00,'2025-07-21','2026-07-21','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(41,41,'Contract - SHAHZAIB ALI SHABBIR AHMED',44,'SHAHZAIB ALI SHABBIR AHMED',3000.00,'2025-07-21','2026-07-21','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(42,42,'Contract - SAQLAIN ABBAS LIAQAT HUSSAIN',45,'SAQLAIN ABBAS LIAQAT HUSSAIN',3000.00,'2025-07-21','2026-07-21','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(43,43,'Contract - MUZAMMAL MAQSOOD',46,'MUZAMMAL MAQSOOD',3500.00,'2025-08-02','2026-08-02','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(44,44,'Contract - OMER AWADALGED ALZAN',47,'OMER AWADALGED ALZAN',3500.00,'2025-09-01','2026-09-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(45,45,'Contract - AMEEN ABDO MOHAMMED',48,'AMEEN ABDO MOHAMMED',3000.00,'2025-09-02','2026-09-02','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(46,46,'Contract - NUMAN SHUBAYR KALAMIAH',49,'NUMAN SHUBAYR KALAMIAH',3000.00,'2025-09-03','2026-09-03','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(47,47,'Contract - MOHAMMAD SAIED',50,'MOHAMMAD SAIED',3500.00,'2026-02-01','2027-02-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(48,48,'Contract - HANI MOHAMMED',51,'HANI MOHAMMED',4000.00,'2026-02-01','2027-02-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(49,49,'Contract - MUHAND MOHAMMED',52,'MUHAND MOHAMMED',2500.00,'2026-02-01','2027-02-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(50,50,'Contract - MAZIN OSMAN MOHAMED',53,'MAZIN OSMAN MOHAMED',6000.00,'2026-02-09','2027-02-09','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(51,51,'Contract - KHALID ABDU QAYID GHALIB',54,'KHALID ABDU QAYID GHALIB',5000.00,'2026-02-09','2027-02-09','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(52,52,'Contract - REFAT MOHAMED AHMED',55,'REFAT MOHAMED AHMED',3500.00,'2026-02-11','2027-02-11','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(53,53,'Contract - BADR AHMED AHMED ALISHARAF',56,'BADR AHMED AHMED ALISHARAF',3500.00,'2026-02-12','2027-02-12','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(54,54,'Contract - NASSAR THABIT ALIAL',57,'NASSAR THABIT ALIAL',3500.00,'2026-02-25','2027-02-25','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(55,55,'Contract - RAFAT MOHAMED AHMEDADAM',58,'RAFAT MOHAMED AHMEDADAM',3500.00,'2026-02-25','2027-02-25','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(56,56,'Contract - ISMAIL MOHAMED IBRAHIM',59,'ISMAIL MOHAMED IBRAHIM',3500.00,'2026-03-03','2027-03-03','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(57,57,'Contract - MOHAMMED YAHYA THAPT',60,'MOHAMMED YAHYA THAPT',3500.00,'2026-03-11','2027-03-11','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(58,58,'Contract - LOAY MAHJUB ELSHAIKH',61,'LOAY MAHJUB ELSHAIKH',8500.00,'2026-03-17','2027-03-17','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(59,59,'Contract - ARIF SARKER',62,'ARIF SARKER',2000.00,'2026-03-24','2027-03-24','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(60,60,'Contract - Mohammed abdalla hamad',63,'Mohammed abdalla hamad',2500.00,'2026-03-29','2027-03-29','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(61,61,'Contract - KHALID Mohammed FOUAD',64,'KHALID Mohammed FOUAD',16000.00,'2026-04-01','2027-04-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(62,62,'Contract - Mohamad Yousef',65,'Mohamad Yousef',25000.00,'2026-04-01','2027-04-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(63,63,'Contract - AKTER MATUBBER',66,'AKTER MATUBBER',2500.00,'2026-04-01','2027-04-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(64,64,'Contract - MOHAMMED NOOR ALAM',67,'MOHAMMED NOOR ALAM',2500.00,'2026-04-01','2027-04-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(65,65,'Contract - MOHAMMED ALI ABAKAR',68,'MOHAMMED ALI ABAKAR',2500.00,'2026-04-01','2027-04-01','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(66,66,'Contract - RAIHADAHAMMAD',69,'RAIHADAHAMMAD',2000.00,'2026-04-02','2027-04-02','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(67,67,'Contract - ALHADI MOHAMMED MUSTAFA',70,'ALHADI MOHAMMED MUSTAFA',2500.00,'2026-04-19','2027-04-19','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(68,68,'Contract - Lutf Yahya Thabit Alammari',71,'Lutf Yahya Thabit Alammari',3000.00,'2026-04-22','2027-04-22','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(69,69,'Contract - Abdalghafar Abdalla Rahmatalla',72,'Abdalghafar Abdalla Rahmatalla',3500.00,'2026-04-24','2027-04-24','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(70,70,'Contract - Deema Abdulaziz',76,'Deema Abdulaziz',7000.00,'2026-04-26','2027-04-26','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(71,71,'Contract - Moaid Khogali Elhag',77,'Moaid Khogali Elhag',3000.00,'2026-04-27','2027-04-27','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(72,72,'Contract - mohammad sulaiman lal',78,'mohammad sulaiman lal',2500.00,'2026-04-28','2027-04-28','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(73,73,'Contract - Yasmeen Saeed hassanElsayed',82,'Yasmeen Saeed hassanElsayed',7000.00,'2026-05-09','2027-05-09','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(74,74,'Contract - ABDELAZIZ OMER ABDELAZIZ',80,'ABDELAZIZ OMER ABDELAZIZ',2500.00,'2026-05-04','2027-05-04','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(75,75,'Contract - ALDHU ABDULRAHIM ABDULIAH',81,'ALDHU ABDULRAHIM ABDULIAH',2500.00,'2026-05-06','2027-05-06','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(76,76,'Contract - KHURSHID BAHADUR KHAN',73,'KHURSHID BAHADUR KHAN',3500.00,'2026-04-24','2027-04-24','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(77,77,'Contract - IHTISHAM KHAN MUHAMMAD MUNIR',74,'IHTISHAM KHAN MUHAMMAD MUNIR',3500.00,'2026-04-24','2027-04-24','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(78,78,'Contract - ABDALGHAFAR ABDALLA RAHMA TALLA',75,'ABDALGHAFAR ABDALLA RAHMA TALLA',3500.00,'2026-04-24','2027-04-24','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(79,79,'Contract - RIAZ KHAN GULBAR SHAH',79,'RIAZ KHAN GULBAR SHAH',3500.00,'2026-05-02','2027-05-02','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04'),(80,80,'Contract - HUSSIEN ABDELRAHM HAMDOON',83,'HUSSIEN ABDELRAHM HAMDOON',3500.00,'2026-05-12','2027-05-12','open',1,'Saudi Monthly Salary','2026-05-18 18:06:04','2026-05-18 18:06:04','2026-05-18 18:06:04');
/*!40000 ALTER TABLE `contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odoo_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `odoo_parent_id` int DEFAULT NULL,
  `parent_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `odoo_manager_id` int DEFAULT NULL,
  `manager_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_employee` int NOT NULL DEFAULT '0',
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_odoo_id_unique` (`odoo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,1,'تقنية المعلومات',21,'Managment',NULL,NULL,1,'2026-05-18 17:46:34','2026-05-15 08:06:30','2026-05-18 17:46:34'),(2,2,'الموارد البشرية',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:34','2026-05-15 08:06:30','2026-05-18 17:46:34'),(3,3,'المبيعات',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:34','2026-05-15 08:06:30','2026-05-18 17:46:34'),(4,4,'التشغيل',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:34','2026-05-15 08:06:30','2026-05-18 17:46:34'),(5,5,'الاسطول والنقل',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(6,6,'المشتريات',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(7,7,'الشؤون الادارية',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(8,8,'تطوير الاعمال',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(9,9,'خدمة العملاء',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(10,10,'المالية',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(11,11,'العلاقات العامة - حكومية',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(12,12,'ادارة المستودع',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(13,13,'Quality Management -ادارة الجودة',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(14,14,'السلامة والأمن',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(15,15,'التحصيل',10,'المالية',NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(16,16,'قسم المخزون وإدارة المخازن',12,'ادارة المستودع',NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(17,17,'ادارة الشحن الدولي والتخليص الجمركي',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(18,18,'قسم الصيانة',5,'الاسطول والنقل',NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(19,19,'قسم مراقبة الأداء والتقارير',8,'تطوير الاعمال',NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(20,20,'التسويق',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35'),(21,21,'Managment',NULL,NULL,NULL,NULL,0,'2026-05-18 17:46:35','2026-05-15 08:06:30','2026-05-18 17:46:35');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odoo_id` int NOT NULL,
  `emp_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `odoo_department_id` int DEFAULT NULL,
  `department_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `odoo_parent_id` int DEFAULT NULL,
  `parent_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_joining` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `contract_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `family_status` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cchi_card_type` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality_code` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passport_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iqama_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_label` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_salary` decimal(12,2) DEFAULT NULL,
  `basic_salary` decimal(12,2) DEFAULT NULL,
  `allowance_house` decimal(12,2) DEFAULT NULL,
  `allowance_rent` decimal(12,2) DEFAULT NULL,
  `allowance_transport` decimal(12,2) DEFAULT NULL,
  `allowance_car` decimal(12,2) DEFAULT NULL,
  `allowance_special` decimal(12,2) DEFAULT NULL,
  `allowance_project` decimal(12,2) DEFAULT NULL,
  `allowance_food` decimal(12,2) DEFAULT NULL,
  `allowance_other` decimal(12,2) DEFAULT NULL,
  `ot_allowance` decimal(12,2) DEFAULT NULL,
  `loan_balance` decimal(12,2) DEFAULT NULL,
  `alt_ticket` decimal(12,2) DEFAULT NULL,
  `bonus_eligibility_months` decimal(8,2) DEFAULT NULL,
  `bonus_pm` decimal(12,2) DEFAULT NULL,
  `gosi_pm` decimal(12,2) DEFAULT NULL,
  `indemnity_pm` decimal(12,2) DEFAULT NULL,
  `leave_accrual_pm` decimal(12,2) DEFAULT NULL,
  `med_insurance_pm` decimal(12,2) DEFAULT NULL,
  `pa_insurance_pm` decimal(12,2) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `image_small` mediumtext COLLATE utf8mb4_unicode_ci,
  `synced_at` timestamp NULL DEFAULT NULL,
  `master_imported_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_odoo_id_unique` (`odoo_id`),
  KEY `employees_odoo_department_id_index` (`odoo_department_id`),
  KEY `employees_emp_code_index` (`emp_code`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,1,NULL,'Administrator',NULL,'haniusif@gmail.com','966535097129','0',1,'Managment / تقنية المعلومات',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDMwMiwgNDglLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5BPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34',NULL,'2026-05-15 08:06:31','2026-05-18 17:49:34'),(37,38,'ID-001','SABRI OMER','Director Mangar',NULL,'966535097129','0',NULL,NULL,64,'KHALID Mohammed FOUAD','2024-11-01','2025-11-01','Expired','1992-01-01','F','B','SD','Sudanes','Riyadh',NULL,'2499342026','Active','full time',14000.00,10370.37,2592.59,0.00,1037.04,0.00,0.00,0.00,0.00,0.00,5.40,0.00,0.00,0.00,0.00,259.26,345.68,864.20,0.00,53.00,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDI1NywgNjclLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5TPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(38,39,'ID-003','HASSAN SALAH MOHAMMED','HEAD OF.HR',NULL,'966535097129','0',NULL,NULL,38,'SABRI OMER','2024-11-01','2025-11-01','Expired','1995-01-01','F','B','SD','Sudanes','Riyadh',NULL,NULL,'Active','full time',3000.00,2222.22,555.56,0.00,222.22,0.00,0.00,0.00,0.00,0.00,1.16,0.00,0.00,0.00,0.00,55.56,74.07,185.19,50.00,3.00,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDM0NiwgNDIlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5IPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(39,40,'ID-004','KAMRUL MD JOYDAL','LAYPPER',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2025-05-01','2026-05-01','Expired','1992-10-01','F','B','BD','Bangldeshi','Riyadh',NULL,'2505872222','Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,0.00,0.00,0.00,0.00,46.30,61.73,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDEzNywgNTAlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5LPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(40,41,'ID-005','AHMED ALSULTAN','Clinet Support',NULL,'966535097129','0',NULL,NULL,65,'Mohamad Yousef','2025-07-01','2026-07-01','Expiring Soon','1990-02-24','F','B','jor','Jordan','Riyadh',NULL,'2459354664','Active','full time',14000.00,10370.37,2592.59,NULL,1037.04,0.00,0.00,0.00,0.00,0.00,5.40,0.00,0.00,0.00,0.00,259.26,0.00,864.20,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDM1MywgNTQlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5BPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(41,42,'ID-006','ALAAULDEEN  Nabil ARABI','FLEET SUPERVISOR',NULL,'966535097129','0',NULL,NULL,53,'MAZIN OSMAN MOHAMED','2025-07-10','2026-07-10','Expiring Soon','2003-01-12','B','B','SD','Sudanes','jeddah',NULL,'2536850510','Active','full time',4500.00,3333.33,833.33,NULL,333.33,0.00,0.00,0.00,0.00,0.00,1.74,0.00,0.00,0.00,0.00,83.33,0.00,277.78,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDM1NCwgNTQlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5BPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(42,43,'ID-007','MUHAMMAD SHAHBAZ HUSSAIN','courier',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2025-07-21','2026-07-21','Active','1982-04-25','F','B','PK','Pakistani','Riyadh',NULL,'2564444343','Active','full time',3000.00,2222.22,555.56,NULL,222.22,0.00,0.00,0.00,0.00,0.00,1.16,0.00,0.00,0.00,0.00,55.56,0.00,185.19,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDIzNiwgNjQlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5NPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(43,44,'ID-008','SHAHZAIB ALI SHABBIR AHMED','courier',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2025-07-21','2026-07-21','Active','2001-02-25','B','B','PK','Pakistani','Riyadh',NULL,'2527077107','Active','full time',3000.00,2222.22,555.56,NULL,222.22,0.00,0.00,0.00,0.00,0.00,1.16,0.00,0.00,0.00,0.00,55.56,0.00,185.19,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDMzNSwgNTIlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5TPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(44,45,'ID-009','SAQLAIN ABBAS LIAQAT HUSSAIN','courier',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2025-07-21','2026-07-21','Active','2000-06-21','B','B','PK','Pakistani','Riyadh',NULL,'2499893903','Active','full time',3000.00,2222.22,555.56,NULL,222.22,0.00,0.00,0.00,0.00,0.00,1.16,0.00,0.00,0.00,0.00,55.56,0.00,185.19,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDE2LCA1NSUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPlM8L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(45,46,'ID-010','MUZAMMAL MAQSOOD','Taem Lader',NULL,'966535097129','0',NULL,NULL,53,'MAZIN OSMAN MOHAMED','2025-08-02','2026-08-02','Active','1994-05-27','f','B','PK','Pakistani','Riyadh',NULL,'2458701873','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,1.00,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDI3NywgNDYlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5NPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(46,47,'ID-011','OMER AWADALGED ALZAN','HR ASSISTANT',NULL,'966535097129','0',NULL,NULL,39,'HASSAN SALAH MOHAMMED','2025-09-01','2026-09-01','Active','1996-10-30','B','B','SD','Sudanes','Riyadh',NULL,'2311419119','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,0.00,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDAsIDQ3JSwgNDUlKScgaGVpZ2h0PScxODAnIHdpZHRoPScxODAnLz48dGV4dCBmaWxsPScjZmZmZmZmJyBmb250LXNpemU9Jzk2JyB0ZXh0LWFuY2hvcj0nbWlkZGxlJyB4PSc5MCcgeT0nMTI1JyBmb250LWZhbWlseT0nc2Fucy1zZXJpZic+TzwvdGV4dD48L3N2Zz4=','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(47,48,'ID-012','AMEEN ABDO MOHAMMED','courier',NULL,'966535097129','0',NULL,NULL,59,'ISMAIL MOHAMED IBRAHIM','2025-09-02','2026-09-02','Active','1982-01-01','F','B','YE','Yemeni','jeddah',NULL,'2196460311','Active','full time',3000.00,2222.22,555.56,NULL,222.22,0.00,0.00,0.00,0.00,0.00,1.16,0.00,0.00,0.00,0.00,55.56,0.00,185.19,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDM4LCA1MSUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPkE8L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(48,49,'ID-013','NUMAN SHUBAYR KALAMIAH','courier',NULL,'966535097129','0',NULL,NULL,59,'ISMAIL MOHAMED IBRAHIM','2025-09-03','2026-09-03','Active','1999-04-17','B','B','MMR','Mynamar','jeddah',NULL,'2482682800','Active','full time',3000.00,2222.22,555.56,NULL,222.22,0.00,0.00,0.00,0.00,0.00,1.16,0.00,0.00,0.00,0.00,55.56,0.00,185.19,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDg1LCA1OSUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPk48L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(49,50,'ID-014','MOHAMMAD SAIED','ACCOUNTANT',NULL,'966535097129','0',NULL,NULL,38,'SABRI OMER','2026-02-01','2027-02-01','Active','2000-07-01','F','B','EG','Eygept','Riyadh',NULL,'2578130706','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,0.00,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDM1NiwgNDQlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5NPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(50,51,'ID-015','HANI MOHAMMED','IT LADER',NULL,'966535097129','0',NULL,NULL,38,'SABRI OMER','2026-02-01','2027-02-01','Active','1990-06-19','F','B','SD','Sudanes','Riyadh',NULL,'2525273385','Active','full time',4000.00,2962.96,740.74,NULL,296.30,0.00,0.00,0.00,0.00,0.00,1.54,0.00,0.00,0.00,0.00,74.07,0.00,246.91,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDIyNywgNTIlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5IPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(51,52,'ID-016','MUHAND MOHAMMED','FLEET SUPERVISOR',NULL,'966535097129','0',NULL,NULL,42,'ALAAULDEEN  Nabil ARABI','2026-02-01','2027-02-01','Active','1991-10-20','f','B','SD','Sudanes','Riyadh',NULL,'20580559268','Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,0.00,0.00,0.00,0.00,46.30,0.00,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDk3LCA0NCUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPk08L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(52,53,'ID-017','MAZIN OSMAN MOHAMED','HUBS SUPERVISOR',NULL,'966535097129','0',NULL,NULL,38,'SABRI OMER','2026-02-09','2027-02-09','Active','1983-09-12','F','B','SD','Sudanes','Riyadh',NULL,'2570618336','Active','full time',6000.00,4444.44,1111.11,NULL,444.44,NULL,NULL,NULL,NULL,NULL,2.31,NULL,0.00,0.00,0.00,111.11,0.00,370.37,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDkzLCA0NiUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPk08L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(53,54,'ID-018','KHALID ABDU QAYID GHALIB','HUB LADER',NULL,'966535097129','0',NULL,NULL,53,'MAZIN OSMAN MOHAMED','2026-02-09','2027-02-09','Active','1986-03-28','F','B','YE','Yemeni','jeddah',NULL,'2466410095','Active','full time',5000.00,3703.70,925.93,NULL,370.37,0.00,0.00,0.00,0.00,0.00,1.93,0.00,0.00,0.00,0.00,92.59,0.00,308.64,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDE5OSwgNDQlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5LPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(54,55,'ID-019','REFAT MOHAMED AHMED','HUB LADER',NULL,'966535097129','0',NULL,NULL,53,'MAZIN OSMAN MOHAMED','2026-02-11','2027-02-11','Active','1998-07-19','F','B','SD','Sudanes','Dammam',NULL,'2505872222','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,0.00,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDI2NSwgNTglLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5SPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(55,56,'ID-020','BADR AHMED AHMED ALISHARAF','Casher',NULL,'966535097129','0',NULL,NULL,50,'MOHAMMAD SAIED','2026-02-12','2027-02-12','Active','1994-08-07','B','B','YE','Yemeni','jeddah',NULL,'2620512919','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,0.00,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDQ1LCA0NyUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPkI8L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(56,57,'ID-021','NASSAR THABIT ALIAL','courier',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-02-25','2027-02-25','Active','1999-03-25','B','B','YE','Pakistani','Qasim',NULL,'2444225607','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,0.00,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDExOSwgNDQlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5OPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(57,58,'ID-022','RAFAT MOHAMED AHMEDADAM','courier',NULL,'966535097129','0',NULL,NULL,55,'REFAT MOHAMED AHMED','2026-02-25','2027-02-25','Active','1997-01-16','B','B','SD','Sudanes','Dammam',NULL,'2572409825','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,0.00,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDgwLCA2MSUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPlI8L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(58,59,'ID-023','ISMAIL MOHAMED IBRAHIM','HUB LADER',NULL,'966535097129','0',NULL,NULL,53,'MAZIN OSMAN MOHAMED','2026-03-03','2027-03-03','Active','1996-01-17','B','B','SD','Sudanes','Madinah',NULL,'2603959731','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,0.00,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDMyNSwgNjQlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5JPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(59,60,'ID-024','MOHAMMED YAHYA THAPT','courier- line hull',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-03-11','2027-03-11','Active','1988-08-08','F','B','YE','Yemeni','Riyadh',NULL,'2527077107','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,0.00,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDEzMywgNjMlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5NPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(60,61,'ID-025','LOAY MAHJUB ELSHAIKH','SAELES',NULL,'966535097129','0',NULL,NULL,65,'Mohamad Yousef','2026-03-17','2027-03-17','Active','1989-09-02','F','B','SD','Sudanes','Riyadh',NULL,'2566762213','Active','full time',8500.00,6296.30,1574.07,NULL,629.63,0.00,0.00,0.00,0.00,0.00,3.28,0.00,0.00,0.00,0.00,157.41,0.00,524.69,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDIxOSwgNDIlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5MPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(61,62,'ID-026','ARIF SARKER','OFFICEBOY',NULL,'966535097129','0',NULL,NULL,47,'OMER AWADALGED ALZAN','2026-03-24','2027-03-24','Active','1989-12-31','F','B','BD','Bangldeshi','Riyadh',NULL,'2508287634','Active','full time',2000.00,1481.48,370.37,NULL,148.15,0.00,0.00,0.00,0.00,0.00,0.77,NULL,0.00,0.00,0.00,37.04,0.00,123.46,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDMyNSwgNTQlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5BPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(62,63,'ID-027','Mohammed abdalla hamad','courier',NULL,'966535097129','0',NULL,NULL,55,'REFAT MOHAMED AHMED','2026-03-29','2027-03-29','Active','1997-06-10','B','B','SD','Sudanes','Dammam',NULL,'2501909184','Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,0.00,0.00,0.00,0.00,46.30,0.00,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDU0LCA1MyUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPk08L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(63,64,'ID-028','KHALID Mohammed FOUAD','CEO',NULL,'966535097129','0',NULL,NULL,NULL,NULL,'2026-04-01','2027-04-01','Active','1981-08-11','F','B','SD','Sudanes','Riyadh',NULL,'2503552719','Active','full time',16000.00,11851.85,2962.96,NULL,1185.19,0.00,0.00,0.00,0.00,0.00,6.17,0.00,0.00,0.00,0.00,296.30,0.00,987.65,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDE0MSwgNTclLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5LPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(64,65,'ID-029','Mohamad Yousef','SAELES Manger',NULL,'966535097129','0',NULL,NULL,64,'KHALID Mohammed FOUAD','2026-04-01','2027-04-01','Active','1983-12-05','F','A+','jor','Jordan','Riyadh',NULL,'2213094820','Active','full time',25000.00,18518.52,4629.63,NULL,1851.85,0.00,0.00,0.00,0.00,0.00,9.65,0.00,0.00,0.00,0.00,462.96,0.00,1543.21,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDE2NCwgNjglLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5NPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(65,66,'ID-030','AKTER MATUBBER','courier- line hull',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-04-01','2027-04-01','Active','1987-07-19','f','B','BD','Bangldeshi','Riyadh',NULL,'2252283344','Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,0.00,0.00,0.00,0.00,46.30,0.00,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDQwLCA2OSUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPkE8L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(66,67,'ID-031','MOHAMMED NOOR ALAM','courier',NULL,'966535097129','0',NULL,NULL,59,'ISMAIL MOHAMED IBRAHIM','2026-04-01','2027-04-01','Active','2004-09-15','B','B','MMR','Mynamar','jeddah',NULL,'2400449498','Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,0.00,0.00,0.00,0.00,46.30,0.00,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDEwLCA0NSUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPk08L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(67,68,'ID-032','MOHAMMED ALI ABAKAR','courier',NULL,'966535097129','0',NULL,NULL,59,'ISMAIL MOHAMED IBRAHIM','2026-04-01','2027-04-01','Active','1996-06-04','B','B','CH','CHAD','jeddah',NULL,'2169463185','Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,0.00,0.00,0.00,0.00,46.30,0.00,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDc5LCA1NyUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPk08L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(68,69,'ID-033','RAIHADAHAMMAD','LAYPPER',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-04-02','2027-04-02','Active','2003-05-21','B','B','BD','Bangldeshi','Riyadh',NULL,'2544955715','Active','full time',2000.00,1481.48,370.37,NULL,148.15,0.00,0.00,0.00,0.00,0.00,0.77,NULL,0.00,0.00,0.00,37.04,0.00,123.46,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDExNiwgNDElLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5SPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(69,70,'ID-034','ALHADI MOHAMMED MUSTAFA','Courier',NULL,'966535097129','0',NULL,NULL,59,'ISMAIL MOHAMED IBRAHIM','2026-04-19','2027-04-19','Active','2001-01-01','B','B','SD','Sudanes','Madinah',NULL,'2563788898','Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,NULL,0.00,0.00,0.00,46.30,0.00,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDM1NywgNjIlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5BPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(70,72,'ID-036','Abdalghafar Abdalla Rahmatalla','courier- line hull',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-04-24','2027-04-24','Active','1994-01-01','B','B','SD','Sudanes','Riyadh',NULL,'2511990430','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,NULL,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDM2MCwgNjclLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5BPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(71,73,'ID-043','KHURSHID BAHADUR KHAN','courier- line hull',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-04-24','2027-04-24','Active',NULL,'B','B','PK','Pakistani','Riyadh',NULL,'2551606342','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,NULL,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDk5LCA1MCUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPks8L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(72,74,'ID-044','IHTISHAM KHAN MUHAMMAD MUNIR','courier- line hull',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-04-24','2027-04-24','Active',NULL,'B','B','PK','Pakistani','Riyadh',NULL,'2331769766','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,NULL,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDEzNywgNjclLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5JPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(73,75,'ID-045','ABDALGHAFAR ABDALLA RAHMA TALLA','courier- line hull',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-04-24','2027-04-24','Active',NULL,'B','B','SD','Sudanes','Riyadh',NULL,'2527745257','Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,NULL,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDI4MSwgNTglLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5BPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(74,76,'ID-037','Deema Abdulaziz','BD manager',NULL,'966535097129','0',NULL,NULL,65,'Mohamad Yousef','2026-04-26','2027-04-26','Active','1998-11-28','B','B','SA','SAUDI','Riyadh',NULL,'2612931762','Active','full time',7000.00,5185.19,1296.30,NULL,518.52,0.00,0.00,0.00,0.00,0.00,2.70,NULL,0.00,0.00,0.00,129.63,0.00,432.10,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDE3MSwgNTglLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5EPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(75,77,'ID-038','Moaid Khogali Elhag','logestic officer',NULL,'966535097129','0',NULL,NULL,55,'REFAT MOHAMED AHMED','2026-04-27','2027-04-27','Active','1998-03-15','B','B','SD','Sudanes','Dammam',NULL,NULL,'Active','full time',3000.00,2222.22,555.56,NULL,222.22,0.00,0.00,0.00,0.00,0.00,1.16,NULL,0.00,0.00,0.00,55.56,0.00,185.19,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDEyNiwgNjclLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5NPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(76,78,'ID-039','mohammad sulaiman lal','courier- line hull',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-04-28','2027-04-28','Active','1996-02-18','B','B','pk','Pakistani','Riyadh',NULL,NULL,'Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,NULL,0.00,0.00,0.00,46.30,0.00,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDI1OCwgNTMlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5NPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(77,79,'ID-046','RIAZ KHAN GULBAR SHAH','courier- line hull',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-05-02','2027-05-02','Active',NULL,'B','B','PK','Pakistani','Riyadh',NULL,NULL,'Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,NULL,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDQ5LCA2NSUsIDQ1JSknIGhlaWdodD0nMTgwJyB3aWR0aD0nMTgwJy8+PHRleHQgZmlsbD0nI2ZmZmZmZicgZm9udC1zaXplPSc5NicgdGV4dC1hbmNob3I9J21pZGRsZScgeD0nOTAnIHk9JzEyNScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnPlI8L3RleHQ+PC9zdmc+','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(78,80,'ID-041','ABDELAZIZ OMER ABDELAZIZ','COURIER',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-05-04','2027-05-04','Active',NULL,'B','B','SD','Sudanes','Riyadh',NULL,'2611600335','Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,NULL,0.00,0.00,0.00,46.30,0.00,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDE1MCwgNjclLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5BPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(79,81,'ID-042','ALDHU ABDULRAHIM ABDULIAH','COURIER',NULL,'966535097129','0',NULL,NULL,46,'MUZAMMAL MAQSOOD','2026-05-06','2027-05-06','Active',NULL,'B','B','SD','Sudanes','Riyadh',NULL,NULL,'Active','full time',2500.00,1851.85,462.96,NULL,185.19,0.00,0.00,0.00,0.00,0.00,0.96,NULL,0.00,0.00,0.00,46.30,0.00,154.32,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDMyMiwgNDclLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5BPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(80,82,'ID-040','Yasmeen Saeed hassanElsayed','key account manager',NULL,'966535097129','0',NULL,NULL,64,'KHALID Mohammed FOUAD','2026-05-09','2027-05-09','Active',NULL,'F','B','EG','Eygept','Riyadh',NULL,NULL,'Active','full time',7000.00,5185.19,1296.30,NULL,518.52,0.00,0.00,0.00,0.00,0.00,2.70,NULL,0.00,0.00,0.00,129.63,0.00,432.10,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDIyMCwgNjElLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5ZPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(81,83,'ID-047','HUSSIEN ABDELRAHM HAMDOON','courier- line hull',NULL,'966535097129','0',NULL,NULL,59,'ISMAIL MOHAMED IBRAHIM','2026-05-12','2027-05-12','Active',NULL,'B','B','SD','Sudanes','Madinah',NULL,NULL,'Active','full time',3500.00,2592.59,648.15,NULL,259.26,0.00,0.00,0.00,0.00,0.00,1.35,NULL,0.00,0.00,0.00,64.81,0.00,216.05,NULL,NULL,1,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDE2OSwgNjUlLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5IPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:46:35','2026-05-18 17:49:39'),(82,71,'ID-035','Lutf Yahya Thabit Alammari','courier- line hull',NULL,'966535097129','0',NULL,NULL,NULL,NULL,'2026-04-22','2027-04-22','Active','1995-01-23','B','B','YE','Yemeni','Madinah',NULL,'1104882996','Terminated','full time',3000.00,2222.22,555.56,NULL,222.22,0.00,0.00,0.00,0.00,0.00,1.16,NULL,0.00,0.00,0.00,55.56,0.00,185.19,NULL,NULL,0,'PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnID8+PHN2ZyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcgeG1sbnM9J2h0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnJyB4bWxuczp4bGluaz0naHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayc+PHJlY3QgZmlsbD0naHNsKDE1MiwgNjklLCA0NSUpJyBoZWlnaHQ9JzE4MCcgd2lkdGg9JzE4MCcvPjx0ZXh0IGZpbGw9JyNmZmZmZmYnIGZvbnQtc2l6ZT0nOTYnIHRleHQtYW5jaG9yPSdtaWRkbGUnIHg9JzkwJyB5PScxMjUnIGZvbnQtZmFtaWx5PSdzYW5zLXNlcmlmJz5MPC90ZXh0Pjwvc3ZnPg==','2026-05-18 17:49:34','2026-05-18 17:49:39','2026-05-18 17:49:34','2026-05-18 17:49:39');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odoo_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_types_odoo_id_unique` (`odoo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_types`
--

LOCK TABLES `leave_types` WRITE;
/*!40000 ALTER TABLE `leave_types` DISABLE KEYS */;
INSERT INTO `leave_types` VALUES (1,1,'Paid Time Off','2026-05-18 08:15:17','2026-05-15 08:06:32','2026-05-18 08:15:17'),(2,2,'Sick Time Off','2026-05-18 08:15:17','2026-05-15 08:06:32','2026-05-18 08:15:17'),(3,3,'Compensatory Days','2026-05-18 08:15:17','2026-05-15 08:06:32','2026-05-18 08:15:17'),(4,4,'Unpaid','2026-05-18 08:15:17','2026-05-15 08:06:32','2026-05-18 08:15:17');
/*!40000 ALTER TABLE `leave_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leaves`
--

DROP TABLE IF EXISTS `leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leaves` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odoo_id` int NOT NULL,
  `odoo_employee_id` int NOT NULL,
  `employee_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `odoo_leave_type_id` int DEFAULT NULL,
  `leave_type_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_from` datetime NOT NULL,
  `date_to` datetime NOT NULL,
  `number_of_days` decimal(5,2) NOT NULL DEFAULT '0.00',
  `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `description` text COLLATE utf8mb4_unicode_ci,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leaves_odoo_id_unique` (`odoo_id`),
  KEY `leaves_odoo_employee_id_index` (`odoo_employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leaves`
--

LOCK TABLES `leaves` WRITE;
/*!40000 ALTER TABLE `leaves` DISABLE KEYS */;
INSERT INTO `leaves` VALUES (1,6,1,'Administrator',2,'Sick Time Off','2026-05-17 05:00:00','2026-05-17 14:00:00',0.00,'confirm','test','2026-05-18 08:15:18','2026-05-17 10:56:40','2026-05-18 08:15:18');
/*!40000 ALTER TABLE `leaves` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2024_01_01_000001_create_hr_tables',1),(5,'2026_05_14_224457_create_payroll_tables',1),(6,'2026_05_15_073911_add_roles_to_users',1),(7,'2026_05_15_081430_add_image_small_to_employees',1),(8,'2026_05_15_113000_widen_odoo_api_key_on_users',2),(9,'2026_05_17_085545_create_personal_access_tokens_table',3),(10,'2026_05_17_085550_add_odoo_employee_id_to_users',4),(12,'2026_05_18_203348_extend_employees_with_master_sheet_fields',5);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payslip_lines`
--

DROP TABLE IF EXISTS `payslip_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payslip_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odoo_id` int NOT NULL,
  `odoo_payslip_id` int NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `sequence` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payslip_lines_odoo_id_unique` (`odoo_id`),
  KEY `payslip_lines_odoo_payslip_id_index` (`odoo_payslip_id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payslip_lines`
--

LOCK TABLES `payslip_lines` WRITE;
/*!40000 ALTER TABLE `payslip_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `payslip_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payslips`
--

DROP TABLE IF EXISTS `payslips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payslips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odoo_id` int NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `odoo_employee_id` int NOT NULL,
  `employee_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `odoo_contract_id` int DEFAULT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `state` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `basic_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `allowance_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `gross_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `deduction_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `net_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payslips_odoo_id_unique` (`odoo_id`),
  KEY `payslips_odoo_employee_id_index` (`odoo_employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payslips`
--

LOCK TABLES `payslips` WRITE;
/*!40000 ALTER TABLE `payslips` DISABLE KEYS */;
/*!40000 ALTER TABLE `payslips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (1,'App\\Models\\User',2,'mobile','627280d7edcb15fd80c752e6020b05e699ea3b5868dc477e01c80ec9836550de','[\"*\"]','2026-05-17 13:15:06','2026-06-16 07:23:11','2026-05-17 07:23:11','2026-05-17 13:15:06'),(2,'App\\Models\\User',2,'test-session','a349a5dd13aa9923009f2a730d9cd5adaa76f78890fc3833a335081366e83042','[\"*\"]','2026-05-17 10:47:11','2026-05-17 11:45:29','2026-05-17 10:45:29','2026-05-17 10:47:11');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('6lwnSl9xzeVtxVL5gWSb2uCs0zXDPj6SKNe1UMJK',NULL,'127.0.0.1','curl/8.7.1','eyJfdG9rZW4iOiJHaTE3c3JTRWt5OE9VQ1pGeWZiUmdCV0lEdDhsQTZTNjhzVUVsYmtkIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDcifSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDA3Iiwicm91dGUiOiJkYXNoYm9hcmQifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1779139352),('7NGVF4WKqesWIcplWkLNdT2Cx3a0kBX1WUCt7oMB',NULL,'127.0.0.1','curl/8.7.1','eyJfdG9rZW4iOiI3MlNSeWNEbk4xNWFnWk94NlQ4RE5qTmZ3aFNUbXpXeWFvUWdWREp4IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2NhbGUiOiJlbiIsInRoZW1lIjoiZGFyayJ9',1778843969),('A5eZeCSYqkjDhFVPYcpSdXRqxsmgb6wUZzUrUCOp',2,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:150.0) Gecko/20100101 Firefox/150.0','eyJfdG9rZW4iOiJSUkZ3QVBhRmFtSWRZVEFLYXJTQ3BnRVNIT29JSUxnb21VSEZxdE50IiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjIsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC8xMjcuMC4wLjE6ODAwN1wvcGF5c2xpcHNcL2NyZWF0ZSIsInJvdXRlIjoicGF5c2xpcHMuY3JlYXRlIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1779140932),('ekCdNkV5PN78zdPGj9WYbkMeqyCDOHfXJZ6RKzZG',2,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:150.0) Gecko/20100101 Firefox/150.0','eyJfdG9rZW4iOiIzWmVRelk0ekltcjJpS25JOWpIcmhjOUluUGJBWmRCcHFjRFcyM2k3IiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjIsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC8xMjcuMC4wLjE6ODAwMSIsInJvdXRlIjoiZGFzaGJvYXJkIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1779025818),('h89xzSNgU25c8WxVRB2wOTNbpDY9HWpu85JZY46u',2,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:150.0) Gecko/20100101 Firefox/150.0','eyJfdG9rZW4iOiI1dms4NGt3UlU5MWFIQXRuWU9QNTZROWxMSTNYeDRFQkJMSElSRU9DIiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjIsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC8xMjcuMC4wLjE6ODAwMSIsInJvdXRlIjoiZGFzaGJvYXJkIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1779008470),('nghQb7DbA9Z5nEAL4ugamTPyFJGvO0ZOxLtAShkO',NULL,'127.0.0.1','curl/8.7.1','eyJfdG9rZW4iOiJ3REc0am1veFVjME5QUWtINVM4MGVvUjhZeVpyRHpndWVzTkFwZVFaIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDAifSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJkYXNoYm9hcmQifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1778833612),('NZI0J3iM7wzxvnWpCV5LwurFGkm42MSXwPcry1Rj',2,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:150.0) Gecko/20100101 Firefox/150.0','eyJfdG9rZW4iOiJVVUF3QXpHZlRmNVdoUE9VUUZReGE1NnluMEhwSjNVRHVtZEZVQTA1IiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjIsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC8xMjcuMC4wLjE6ODAwMVwvcGF5c2xpcHMiLCJyb3V0ZSI6InBheXNsaXBzLmluZGV4In0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1779102940),('sUZje3AfeKLL5ETYfAKkOcuq5hBBttNalvwjhJ2v',2,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:150.0) Gecko/20100101 Firefox/150.0','eyJfdG9rZW4iOiJRdXFtZ3djaUtid2xhcFJscXI2Rk13cnp1Z3FRWTZ0bDNYaTV3OWN4IiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjIsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC8xMjcuMC4wLjE6ODAwMVwvbGVhdmVzIiwicm91dGUiOiJsZWF2ZXMuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2NhbGUiOiJhciJ9',1779034947),('vTvvVaY4AudWHnLCzRQNp38cOaXcen2YZZo8ziMF',NULL,'127.0.0.1','curl/8.7.1','eyJfdG9rZW4iOiJMM1B2SnRHZkJlYVZ6Z0VDSTF0eXBtU0VmcDRXNHhTNnoxVEhSUnpkIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1778843944),('YESwCnUdEC5sjqRlBT8mLrvwzTCU3YakaqFqga0T',2,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:150.0) Gecko/20100101 Firefox/150.0','eyJfdG9rZW4iOiJWQll1ZzJZdkh4OXNSWkRVQ2dxTmFZa0tpR2tYa1A3NlBoVVk2TG80IiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9lbXBsb3llZXNcLzEwIiwicm91dGUiOiJlbXBsb3llZXMuc2hvdyJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoyfQ==',1778843208),('zS8xzKpeoShyFHg9o8QKaJIL0ETwS8hG6vN3CS8w',NULL,'127.0.0.1','curl/8.7.1','eyJfdG9rZW4iOiJoNXdURGpWcEo4TzhxYnlJZ2tFQmlJSHE4Q1FyZmhibXE4Z0lDNVpKIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1778843944);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sync_logs`
--

DROP TABLE IF EXISTS `sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `records_synced` int NOT NULL DEFAULT '0',
  `records_failed` int NOT NULL DEFAULT '0',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sync_logs`
--

LOCK TABLES `sync_logs` WRITE;
/*!40000 ALTER TABLE `sync_logs` DISABLE KEYS */;
INSERT INTO `sync_logs` VALUES (1,'hr.department',21,0,NULL,'success','2026-05-15 08:06:29','2026-05-15 08:06:30','2026-05-15 08:06:29','2026-05-15 08:06:30'),(2,'hr.employee',36,0,NULL,'success','2026-05-15 08:06:30','2026-05-15 08:06:31','2026-05-15 08:06:30','2026-05-15 08:06:31'),(3,'hr.leave.type',4,0,NULL,'success','2026-05-15 08:06:31','2026-05-15 08:06:32','2026-05-15 08:06:31','2026-05-15 08:06:32'),(4,'hr.leave',0,0,NULL,'success','2026-05-15 08:06:32','2026-05-15 08:06:32','2026-05-15 08:06:32','2026-05-15 08:06:32'),(5,'hr.attendance',1,0,NULL,'success','2026-05-15 08:06:32','2026-05-15 08:06:33','2026-05-15 08:06:32','2026-05-15 08:06:33'),(6,'hr.contract',34,0,NULL,'success','2026-05-15 08:06:33','2026-05-15 08:06:34','2026-05-15 08:06:33','2026-05-15 08:06:34'),(7,'hr.payslip',2,0,NULL,'success','2026-05-15 08:06:34','2026-05-15 08:06:35','2026-05-15 08:06:34','2026-05-15 08:06:35'),(8,'hr.department',21,0,NULL,'success','2026-05-17 13:12:15','2026-05-17 13:12:16','2026-05-17 13:12:15','2026-05-17 13:12:16'),(9,'hr.employee',35,0,NULL,'success','2026-05-17 13:12:16','2026-05-17 13:12:17','2026-05-17 13:12:16','2026-05-17 13:12:17'),(10,'hr.leave.type',4,0,NULL,'success','2026-05-17 13:12:17','2026-05-17 13:12:18','2026-05-17 13:12:17','2026-05-17 13:12:18'),(11,'hr.leave',1,0,NULL,'success','2026-05-17 13:12:18','2026-05-17 13:12:18','2026-05-17 13:12:18','2026-05-17 13:12:18'),(12,'hr.attendance',2,0,NULL,'success','2026-05-17 13:12:18','2026-05-17 13:12:19','2026-05-17 13:12:18','2026-05-17 13:12:19'),(13,'hr.contract',34,0,NULL,'success','2026-05-17 13:12:19','2026-05-17 13:12:20','2026-05-17 13:12:19','2026-05-17 13:12:20'),(14,'hr.payslip',3,0,NULL,'success','2026-05-17 13:12:20','2026-05-17 13:12:22','2026-05-17 13:12:20','2026-05-17 13:12:22'),(15,'hr.department',21,0,NULL,'success','2026-05-18 08:15:14','2026-05-18 08:15:15','2026-05-18 08:15:14','2026-05-18 08:15:15'),(16,'hr.employee',35,0,NULL,'success','2026-05-18 08:15:15','2026-05-18 08:15:16','2026-05-18 08:15:15','2026-05-18 08:15:16'),(17,'hr.leave.type',4,0,NULL,'success','2026-05-18 08:15:16','2026-05-18 08:15:17','2026-05-18 08:15:16','2026-05-18 08:15:17'),(18,'hr.leave',1,0,NULL,'success','2026-05-18 08:15:17','2026-05-18 08:15:18','2026-05-18 08:15:17','2026-05-18 08:15:18'),(19,'hr.attendance',3,0,NULL,'success','2026-05-18 08:15:18','2026-05-18 08:15:18','2026-05-18 08:15:18','2026-05-18 08:15:18'),(20,'hr.contract',34,0,NULL,'success','2026-05-18 08:15:18','2026-05-18 08:15:19','2026-05-18 08:15:18','2026-05-18 08:15:19'),(21,'hr.payslip',3,0,NULL,'success','2026-05-18 08:15:19','2026-05-18 08:15:21','2026-05-18 08:15:19','2026-05-18 08:15:21'),(22,'hr.department',21,0,NULL,'success','2026-05-18 17:46:34','2026-05-18 17:46:35','2026-05-18 17:46:34','2026-05-18 17:46:35'),(23,'hr.employee',46,0,NULL,'success','2026-05-18 17:46:35','2026-05-18 17:46:35','2026-05-18 17:46:35','2026-05-18 17:46:35'),(24,'hr.contract',0,0,NULL,'success','2026-05-18 17:46:35','2026-05-18 17:46:36','2026-05-18 17:46:35','2026-05-18 17:46:36'),(25,'hr.contract',46,0,NULL,'success','2026-05-18 18:06:03','2026-05-18 18:06:04','2026-05-18 18:06:03','2026-05-18 18:06:04');
/*!40000 ALTER TABLE `sync_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odoo_uid` int DEFAULT NULL,
  `odoo_employee_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `odoo_api_key` text COLLATE utf8mb4_unicode_ci,
  `odoo_group_ids` json DEFAULT NULL,
  `roles` json DEFAULT NULL,
  `roles_synced_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_odoo_uid_unique` (`odoo_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,NULL,'Test User','test@example.com',NULL,NULL,NULL,NULL,'2026-05-15 05:24:35','$2y$12$2qFkU3020GXHHZU8CigWUOPzM/d9IxvzHaQlVrkhxGc9XOnU7vGge','d5jjZ5PtrI','2026-05-15 05:24:36','2026-05-15 05:24:36'),(2,2,1,'Administrator','haniusif@gmail.com','eyJpdiI6IndHMWlGSHE4RG1sQ05wTzRHTDIxQVE9PSIsInZhbHVlIjoiWU5laWdRajArb1NIcUpKSHlSb25JZz09IiwibWFjIjoiYjliNGE4MjJmMGUxOGQxMzRkMTZlZGQyYzE1MTI2NGY1OTJkMGZlZjM1YzgwNmVmY2Q2MmUzMjRmYTliZjJkNCIsInRhZyI6IiJ9','[2, 8, 40, 71, 16, 20, 93, 22, 84, 48, 82, 64, 79, 66, 69, 63, 32, 34, 3, 9, 94, 75, 21, 1, 91, 12, 96, 6, 95, 92, 19, 81, 65, 68, 74, 73, 4, 62, 7, 80, 47, 78, 83, 70, 15, 14, 67, 39]','[\"admin\", \"hr_manager\", \"hr_officer\", \"payroll_manager\", \"payroll_officer\", \"leave_manager\", \"employee\"]','2026-05-17 07:23:11',NULL,NULL,'1QS1lk0MYM9f9BLfUeiZy1pggSnXMPJC6bZLTCRKDnTxA6PHVLHEBVFpxe2N','2026-05-15 05:29:40','2026-05-17 07:23:11');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'milejet_controll'
--

--
-- Dumping routines for database 'milejet_controll'
--
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-19  9:55:49
