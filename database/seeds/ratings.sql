
UNLOCK TABLES;
TRUNCATE TABLE `ratings`;
LOCK TABLES `ratings` WRITE;
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (0,'UNK','Unknown');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (1,'OBS','Observer');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (2,'S1','Student 1');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (3,'S2','Student 2');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (4,'S3','Senior Student');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (5,'C1','Controller');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (6,'C2','Controller 2');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (7,'C3','Senior Controller');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (8,'I1','Instructor');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (9,'I2','Instructor 2');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (10,'I3','Senior Instructor');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (11,'SUP','Supervisor');
INSERT INTO `ratings` (`id`,`short`,`long`) VALUES (12,'ADM','Administrator');
UNLOCK TABLES;
