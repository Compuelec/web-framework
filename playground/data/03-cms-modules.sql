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

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
INSERT INTO `modules` (`id_module`, `id_page_module`, `type_module`, `title_module`, `suffix_module`, `content_module`, `width_module`, `editable_module`, `date_created_module`, `date_updated_module`) VALUES (4,10,'tables','plan_cuentas','cuenta',NULL,100,0,'2026-06-28','2026-06-28 01:46:25'),
(6,11,'tables','categorias_gasto','categoria',NULL,100,0,'2026-06-28','2026-06-28 01:48:21'),
(7,12,'breadcrumbs','Clientes',NULL,NULL,100,1,'2026-06-28','2026-06-28 01:48:21'),
(8,12,'tables','clientes','cliente',NULL,100,0,'2026-06-28','2026-06-28 01:48:21'),
(9,13,'breadcrumbs','Proveedores',NULL,NULL,100,1,'2026-06-28','2026-06-28 01:48:21'),
(10,13,'tables','proveedores','proveedor',NULL,100,0,'2026-06-28','2026-06-28 01:48:21'),
(12,14,'tables','asiento_lineas','linea',NULL,100,0,'2026-06-28','2026-06-28 01:49:48'),
(14,15,'tables','asientos','asiento',NULL,100,0,'2026-06-28','2026-06-28 01:49:48'),
(16,16,'tables','comprobantes_venta','venta',NULL,100,0,'2026-06-28','2026-06-28 01:49:48'),
(18,17,'tables','comprobantes_compra','compra',NULL,100,0,'2026-06-28','2026-06-28 01:49:48'),
(19,18,'breadcrumbs','Pagos a proveedores',NULL,NULL,100,1,'2026-06-29','2026-06-29 21:22:33'),
(20,18,'tables','pagos','pago',NULL,100,0,'2026-06-29','2026-06-29 21:22:33'),
(21,0,'tables','cierres_mes','cierre',NULL,100,1,'2026-06-30','2026-06-30 12:54:44');
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

