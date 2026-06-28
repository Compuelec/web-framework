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

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` (`id_page`, `title_page`, `url_page`, `icon_page`, `type_page`, `parent_page`, `order_page`, `date_created_page`, `date_updated_page`) VALUES (10,'Plan de Cuentas','plan_cuentas','bi bi-list-columns-reverse','modules',0,101,'2026-06-28','2026-06-28 01:46:25'),
(11,'Categorías de gasto','categorias_gasto','bi bi-tag','modules',0,102,'2026-06-28','2026-06-28 01:48:21'),
(12,'Clientes','clientes','bi bi-person-vcard','modules',0,103,'2026-06-28','2026-06-28 01:48:21'),
(13,'Proveedores','proveedores','bi bi-truck','modules',0,104,'2026-06-28','2026-06-28 01:48:21'),
(14,'Líneas de asiento','asiento_lineas','bi bi-list-task','modules',0,105,'2026-06-28','2026-06-28 01:49:48'),
(15,'Asientos contables','asientos','bi bi-journal-bookmark','modules',0,106,'2026-06-28','2026-06-28 01:49:48'),
(16,'Comprobantes de venta','comprobantes_venta','bi bi-receipt','modules',0,107,'2026-06-28','2026-06-28 01:49:48'),
(17,'Comprobantes de compra','comprobantes_compra','bi bi-receipt-cutoff','modules',0,108,'2026-06-28','2026-06-28 01:49:48');
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

