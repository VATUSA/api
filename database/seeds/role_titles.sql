-- MySQL dump 10.16  Distrib 10.1.25-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: vatusa
-- ------------------------------------------------------
-- Server version	10.1.25-MariaDB-1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `role_titles`
--

LOCK TABLES `role_titles` WRITE;
/*!40000 ALTER TABLE `role_titles` DISABLE KEYS */;
INSERT INTO `role_titles` VALUES ('ATM','Air Traffic Manager'),('DATM','Deputy Air Traffic Manager'),('EC','Events Coordinator'),('FE','Facility Engineer'),('TA','Training Administrator'),('US1','Division Director'),('US11','Division Communications Manager'),('US12','Senior Web Developer'),('US2','Deputy Division Director'),('US3','Training Director'),('US4','Division Conflict Resolution Manager'),('US5','Events Manager/VA Liaison'),('US6','Data Services Manager'),('US7','Western Regional Air Traffic Director'),('US8','Southern Regional Air Traffic Director'),('US9','Northeastern Regional Air Traffic Director'),('WM','Webmaster');
/*!40000 ALTER TABLE `role_titles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2017-12-30 13:44:32
