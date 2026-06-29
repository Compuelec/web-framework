/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` (`id_admin`, `email_admin`, `password_admin`, `rol_admin`, `permissions_admin`, `token_admin`, `token_exp_admin`, `status_admin`, `title_admin`, `symbol_admin`, `font_admin`, `color_admin`, `back_admin`, `scode_admin`, `chatgpt_admin`, `date_created_admin`, `date_updated_admin`, `id_role_admin`) VALUES (1,'admin@admin.com','$2y$10$Sn0lgWKK/ZBO4y4C7n/xRONv0In.CnSIuRNMiLl/J6wfyAxZxpweO','superadmin','{\"todo\":\"on\"}','eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3ODI2OTg3NzIsImV4cCI6MTc4Mjc4NTE3MiwiZGF0YSI6eyJpZCI6MSwiZW1haWwiOiJhZG1pbkBhZG1pbi5jb20ifX0.EzOS3GFyD3h4e1vh8KWbVbzJ8FynFDwPNooMYVxJTo4','1782785172',1,'Admin Local','A','Inter','#6c5ce7','#ffffff',NULL,NULL,'2026-06-19','2026-06-29 02:06:12',NULL),
(2,'contador@empresa.cl','$2y$10$nO4e.MVWBEwsLHUn1nmhRu6OFq5fh3qAaJ.d1h/ln94I9UNvVpiK6','contador','{\"todo\":\"on\"}',NULL,NULL,1,'Contador',NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-29','2026-06-29 18:02:58',NULL),
(3,'lectura@empresa.cl','$2y$10$D7Xr00zu.0yyAHy.VyMVOOaPx7MtNfnLeqE/Vw18n/yHTDrzTntr6','lectura','{}',NULL,NULL,1,'Lectura',NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-29','2026-06-29 18:02:58',NULL);
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

