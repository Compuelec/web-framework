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

LOCK TABLES `plan_cuentas` WRITE;
/*!40000 ALTER TABLE `plan_cuentas` DISABLE KEYS */;
INSERT INTO `plan_cuentas` (`id_cuenta`, `codigo_cuenta`, `nombre_cuenta`, `tipo_cuenta`, `naturaleza_cuenta`, `nivel_cuenta`, `activa_cuenta`, `date_created_cuenta`, `date_updated_cuenta`) VALUES (1,'1.1.04','IVA Crédito Fiscal','activo','deudora',3,1,NULL,'2026-06-28 01:54:20'),
(2,'1.1.03','Clientes','activo','deudora',3,1,NULL,'2026-06-28 01:54:20'),
(3,'1.1.02','Banco','activo','deudora',3,1,NULL,'2026-06-28 01:54:20'),
(4,'1','ACTIVO','activo','deudora',1,1,NULL,'2026-06-28 01:54:20'),
(5,'1.1.01','Caja','activo','deudora',3,1,NULL,'2026-06-28 01:54:20'),
(6,'1.1','Activo Circulante','activo','deudora',2,1,NULL,'2026-06-28 01:54:20'),
(7,'1.2','Activo Fijo','activo','deudora',2,1,NULL,'2026-06-28 01:54:21'),
(8,'1.2.01','Muebles y equipos','activo','deudora',3,1,NULL,'2026-06-28 01:54:22'),
(9,'2','PASIVO','pasivo','acreedora',1,1,NULL,'2026-06-28 01:54:22'),
(10,'2.1.01','Proveedores','pasivo','acreedora',3,1,NULL,'2026-06-28 01:54:23'),
(11,'2.1.03','Sueldos por pagar','pasivo','acreedora',3,1,NULL,'2026-06-28 01:54:23'),
(12,'2.1','Pasivo Circulante','pasivo','acreedora',2,1,NULL,'2026-06-28 01:54:23'),
(13,'2.1.02','IVA Débito Fiscal','pasivo','acreedora',3,1,NULL,'2026-06-28 01:54:23'),
(14,'3.1.02','Resultado del Ejercicio','patrimonio','acreedora',3,1,NULL,'2026-06-28 01:54:23'),
(15,'3','PATRIMONIO','patrimonio','acreedora',1,1,NULL,'2026-06-28 01:54:23'),
(16,'5.1.01','Gastos de administración','gasto','deudora',3,1,NULL,'2026-06-28 01:54:23'),
(17,'4.1.02','Ventas exentas','ingreso','acreedora',3,1,NULL,'2026-06-28 01:54:23'),
(18,'3.1.01','Capital','patrimonio','acreedora',3,1,NULL,'2026-06-28 01:54:23'),
(19,'4.1.01','Ventas afectas','ingreso','acreedora',3,1,NULL,'2026-06-28 01:54:23'),
(20,'5.1.02','Gastos generales','gasto','deudora',3,1,NULL,'2026-06-28 01:54:24'),
(21,'5.1.03','Honorarios','gasto','deudora',3,1,NULL,'2026-06-28 01:54:24'),
(22,'6.1.01','Costo de ventas','costo','deudora',3,1,NULL,'2026-06-28 01:54:24'),
(23,'2.1.04','Retención honorarios por pagar','pasivo','acreedora',3,1,'2026-06-30','2026-06-30 00:17:22');
/*!40000 ALTER TABLE `plan_cuentas` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` (`id_cliente`, `razon_social_cliente`, `rut_cliente`, `giro_cliente`, `direccion_cliente`, `comuna_cliente`, `email_cliente`, `telefono_cliente`, `date_created_cliente`, `date_updated_cliente`) VALUES (1,'Comercial Demo SpA','76.123.456-7','Comercio al por menor',NULL,'Santiago','contacto@demo.cl','+56 9 1234 5678',NULL,'2026-06-28 12:17:27'),
(2,'Constructora Sur EIRL','78.456.789-0','Construcción',NULL,'Concepción','admin@consur.cl','+56 41 234 5678',NULL,'2026-06-28 12:19:23'),
(3,'Diseño Visual Ltda','79.567.890-1','Diseño gráfico',NULL,'Ñuñoa','studio@diseno.cl','+56 2 2333 4444',NULL,'2026-06-28 12:19:23'),
(4,'Imprenta Norte Ltda','76.234.567-8','Imprenta',NULL,'Antofagasta','ventas@norte.cl','+56 55 234 5678',NULL,'2026-06-28 12:19:23'),
(5,'Café del Centro SpA','77.345.678-9','Cafetería',NULL,'Santiago','hola@cafecentro.cl','+56 2 2876 5432',NULL,'2026-06-28 12:19:23'),
(6,'Juan Pérez (boleta)','15.678.901-K','Cliente final',NULL,'Valparaíso','jperez@gmail.com','+56 9 9876 5432',NULL,'2026-06-28 12:19:23');
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` (`id_proveedor`, `razon_social_proveedor`, `rut_proveedor`, `giro_proveedor`, `direccion_proveedor`, `comuna_proveedor`, `email_proveedor`, `telefono_proveedor`, `date_created_proveedor`, `date_updated_proveedor`) VALUES (1,'Servicios TI Ltda','77.987.654-3','Servicios informáticos',NULL,'Providencia','ventas@ti.cl','+56 2 2345 6789',NULL,'2026-06-28 12:17:27'),
(2,'Suministros Oficina SA','80.111.222-3','Materiales de oficina',NULL,'Quilicura','ventas@oficina.cl','+56 2 2555 6666',NULL,'2026-06-28 12:19:23'),
(3,'Internet Pro SpA','82.333.444-5','Telecomunicaciones',NULL,'Las Condes','soporte@netpro.cl','+56 600 999 8888',NULL,'2026-06-28 12:19:23'),
(4,'Contador Externo (bh)','12.345.678-9','Servicios profesionales',NULL,'Providencia','contador@email.cl','+56 9 1111 2222',NULL,'2026-06-28 12:19:23'),
(5,'Electricidad Norte Ltda','81.222.333-4','Suministro eléctrico',NULL,'Santiago','facturacion@elec.cl','+56 600 123 4567',NULL,'2026-06-28 12:19:23'),
(6,'Distribuidora El Sol Ltda','83.444.555-6','Distribución mayorista',NULL,'Quilicura','pedidos@sol.cl','+56 2 2777 8888',NULL,'2026-06-28 12:19:23');
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `categorias_gasto` WRITE;
/*!40000 ALTER TABLE `categorias_gasto` DISABLE KEYS */;
INSERT INTO `categorias_gasto` (`id_categoria`, `nombre_categoria`, `cuenta_categoria`, `date_created_categoria`, `date_updated_categoria`) VALUES (1,'Arriendos',20,NULL,'2026-06-28 01:57:08'),
(2,'Servicios profesionales (boletas)',21,NULL,'2026-06-28 01:57:08'),
(3,'Materiales de oficina',16,NULL,'2026-06-28 01:57:08'),
(4,'Telefonía e internet',20,NULL,'2026-06-28 01:57:08'),
(5,'Mercadería (costo)',22,NULL,'2026-06-28 01:57:08'),
(6,'Servicios básicos (luz, agua, gas)',20,NULL,'2026-06-28 01:57:08');
/*!40000 ALTER TABLE `categorias_gasto` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `comprobantes_venta` WRITE;
/*!40000 ALTER TABLE `comprobantes_venta` DISABLE KEYS */;
INSERT INTO `comprobantes_venta` (`id_venta`, `tipo_documento_venta`, `folio_venta`, `fecha_venta`, `cliente_venta`, `glosa_venta`, `archivo_venta`, `neto_venta`, `iva_venta`, `exento_venta`, `total_venta`, `estado_venta`, `date_created_venta`, `date_updated_venta`) VALUES (1,'factura_afecta',1001,'2026-06-03',2,'Servicios de impresión 1000 trípticos',NULL,850000,161500,0,1011500,'emitido',NULL,'2026-06-28 12:20:15'),
(2,'factura_exenta',1006,'2026-06-22',2,'Servicios educacionales (exento)',NULL,0,0,450000,450000,'emitido',NULL,'2026-06-28 12:20:15'),
(3,'boleta',1004,'2026-06-15',5,'Venta mostrador varios',NULL,65000,12350,0,77350,'emitido',NULL,'2026-06-28 12:20:15'),
(4,'factura_afecta',1005,'2026-06-18',6,'Diseño de catálogo y manual de marca',NULL,1200000,228000,0,1428000,'emitido',NULL,'2026-06-28 12:20:15'),
(5,'factura_afecta',1002,'2026-06-08',3,'Café y catering reunión directorio',NULL,280000,53200,0,333200,'emitido',NULL,'2026-06-28 12:20:15'),
(6,'factura_afecta',991,'2026-05-05',2,'Impresión folletos mayo',NULL,420000,79800,0,499800,'emitido',NULL,'2026-06-28 12:20:15'),
(7,'boleta',993,'2026-05-20',5,'Compra mostrador (mayo)',NULL,42000,7980,0,49980,'emitido',NULL,'2026-06-28 12:20:15'),
(8,'factura_afecta',992,'2026-05-14',3,'Catering evento corporativo',NULL,780000,148200,0,928200,'emitido',NULL,'2026-06-28 12:20:17'),
(9,'factura_afecta',1003,'2026-06-12',4,'Materiales obra civil galpón',NULL,3500000,665000,0,4165000,'emitido',NULL,'2026-06-28 12:20:18');
/*!40000 ALTER TABLE `comprobantes_venta` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `comprobantes_compra` WRITE;
/*!40000 ALTER TABLE `comprobantes_compra` DISABLE KEYS */;
INSERT INTO `comprobantes_compra` (`id_compra`, `tipo_documento_compra`, `folio_compra`, `fecha_compra`, `proveedor_compra`, `categoria_compra`, `glosa_compra`, `archivo_compra`, `neto_compra`, `iva_compra`, `retencion_compra`, `exento_compra`, `total_compra`, `estado_compra`, `date_created_compra`, `date_updated_compra`) VALUES (1,'factura_afecta',5002,'2026-06-05',2,6,'Luz mes de mayo',NULL,142000,26980,0,0,168980,'pagado',NULL,'2026-06-28 12:20:15'),
(2,'factura_afecta',4902,'2026-05-06',2,6,'Luz mes de abril',NULL,128000,24320,0,0,152320,'pagado',NULL,'2026-06-28 12:20:15'),
(3,'factura_afecta',4901,'2026-05-03',1,3,'Materiales de oficina mayo',NULL,95000,18050,0,0,113050,'pagado',NULL,'2026-06-28 12:20:15'),
(4,'boleta_honorarios',87,'2026-05-15',4,2,'Contabilidad mes de mayo',NULL,0,0,0,380000,380000,'pagado',NULL,'2026-06-28 12:20:15'),
(5,'factura_afecta',5001,'2026-06-01',1,3,'Resmas, lápices, post-its',NULL,185000,35150,0,0,220150,'pagado',NULL,'2026-06-28 12:20:16'),
(6,'factura_afecta',5004,'2026-06-20',5,5,'Mercadería para reventa (Q2)',NULL,2400000,456000,0,0,2856000,'registrado',NULL,'2026-06-28 12:20:16'),
(7,'factura_afecta',5003,'2026-06-10',3,4,'Internet + telefonía junio',NULL,89000,16910,0,0,105910,'registrado',NULL,'2026-06-28 12:20:17'),
(8,'boleta_honorarios',88,'2026-06-15',4,2,'Contabilidad mes de junio',NULL,0,0,0,380000,380000,'pagado',NULL,'2026-06-28 12:20:18');
/*!40000 ALTER TABLE `comprobantes_compra` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `asientos` WRITE;
/*!40000 ALTER TABLE `asientos` DISABLE KEYS */;
INSERT INTO `asientos` (`id_asiento`, `numero_asiento`, `fecha_asiento`, `glosa_asiento`, `origen_asiento`, `origen_id_asiento`, `total_debe_asiento`, `total_haber_asiento`, `estado_asiento`, `date_created_asiento`, `date_updated_asiento`) VALUES (1,1,'2026-06-03','Venta — factura_afecta N° 1001','venta',1,1011500,1011500,'validado','2026-06-28','2026-06-28 12:53:44'),
(2,2,'2026-06-22','Venta — factura_exenta N° 1006','venta',2,450000,450000,'validado','2026-06-28','2026-06-28 12:53:44'),
(3,3,'2026-06-15','Venta — boleta N° 1004','venta',3,77350,77350,'validado','2026-06-28','2026-06-28 12:53:44'),
(4,4,'2026-06-18','Venta — factura_afecta N° 1005','venta',4,1428000,1428000,'validado','2026-06-28','2026-06-28 12:53:44'),
(5,5,'2026-06-08','Venta — factura_afecta N° 1002','venta',5,333200,333200,'validado','2026-06-28','2026-06-28 12:53:44'),
(6,6,'2026-05-05','Venta — factura_afecta N° 991','venta',6,499800,499800,'validado','2026-06-28','2026-06-28 12:53:44'),
(7,7,'2026-05-20','Venta — boleta N° 993','venta',7,49980,49980,'validado','2026-06-28','2026-06-28 12:53:44'),
(8,8,'2026-05-14','Venta — factura_afecta N° 992','venta',8,928200,928200,'validado','2026-06-28','2026-06-28 12:53:44'),
(9,9,'2026-06-12','Venta — factura_afecta N° 1003','venta',9,4165000,4165000,'validado','2026-06-28','2026-06-28 12:53:44'),
(10,10,'2026-06-05','Compra — factura_afecta N° 5002','compra',1,168980,168980,'validado','2026-06-28','2026-06-28 12:53:44'),
(11,11,'2026-05-06','Compra — factura_afecta N° 4902','compra',2,152320,152320,'validado','2026-06-28','2026-06-28 12:53:45'),
(12,12,'2026-05-03','Compra — factura_afecta N° 4901','compra',3,113050,113050,'validado','2026-06-28','2026-06-28 12:53:45'),
(13,13,'2026-05-15','Compra — boleta_honorarios N° 87','compra',4,380000,380000,'validado','2026-06-28','2026-06-28 12:53:45'),
(14,14,'2026-06-01','Compra — factura_afecta N° 5001','compra',5,220150,220150,'validado','2026-06-28','2026-06-28 12:53:45'),
(15,15,'2026-06-20','Compra — factura_afecta N° 5004','compra',6,2856000,2856000,'validado','2026-06-28','2026-06-28 12:53:45'),
(16,16,'2026-06-10','Compra — factura_afecta N° 5003','compra',7,105910,105910,'validado','2026-06-28','2026-06-28 12:53:45'),
(17,17,'2026-06-15','Compra — boleta_honorarios N° 88','compra',8,380000,380000,'validado','2026-06-28','2026-06-28 12:53:45');
/*!40000 ALTER TABLE `asientos` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `asiento_lineas` WRITE;
/*!40000 ALTER TABLE `asiento_lineas` DISABLE KEYS */;
INSERT INTO `asiento_lineas` (`id_linea`, `asiento_linea`, `cuenta_linea`, `glosa_linea`, `debe_linea`, `haber_linea`, `orden_linea`, `date_created_linea`, `date_updated_linea`) VALUES (1,1,2,'Factura/boleta N° 1001',1011500,0,1,'2026-06-28','2026-06-28 12:53:44'),
(2,1,19,'Neto afecto',0,850000,2,'2026-06-28','2026-06-28 12:53:44'),
(3,1,13,'IVA 19%',0,161500,3,'2026-06-28','2026-06-28 12:53:44'),
(4,2,2,'Factura/boleta N° 1006',450000,0,1,'2026-06-28','2026-06-28 12:53:44'),
(5,2,17,'Monto exento',0,450000,2,'2026-06-28','2026-06-28 12:53:44'),
(6,3,2,'Factura/boleta N° 1004',77350,0,1,'2026-06-28','2026-06-28 12:53:44'),
(7,3,19,'Neto afecto',0,65000,2,'2026-06-28','2026-06-28 12:53:44'),
(8,3,13,'IVA 19%',0,12350,3,'2026-06-28','2026-06-28 12:53:44'),
(9,4,2,'Factura/boleta N° 1005',1428000,0,1,'2026-06-28','2026-06-28 12:53:44'),
(10,4,19,'Neto afecto',0,1200000,2,'2026-06-28','2026-06-28 12:53:44'),
(11,4,13,'IVA 19%',0,228000,3,'2026-06-28','2026-06-28 12:53:44'),
(12,5,2,'Factura/boleta N° 1002',333200,0,1,'2026-06-28','2026-06-28 12:53:44'),
(13,5,19,'Neto afecto',0,280000,2,'2026-06-28','2026-06-28 12:53:44'),
(14,5,13,'IVA 19%',0,53200,3,'2026-06-28','2026-06-28 12:53:44'),
(15,6,2,'Factura/boleta N° 991',499800,0,1,'2026-06-28','2026-06-28 12:53:44'),
(16,6,19,'Neto afecto',0,420000,2,'2026-06-28','2026-06-28 12:53:44'),
(17,6,13,'IVA 19%',0,79800,3,'2026-06-28','2026-06-28 12:53:44'),
(18,7,2,'Factura/boleta N° 993',49980,0,1,'2026-06-28','2026-06-28 12:53:44'),
(19,7,19,'Neto afecto',0,42000,2,'2026-06-28','2026-06-28 12:53:44'),
(20,7,13,'IVA 19%',0,7980,3,'2026-06-28','2026-06-28 12:53:44'),
(21,8,2,'Factura/boleta N° 992',928200,0,1,'2026-06-28','2026-06-28 12:53:44'),
(22,8,19,'Neto afecto',0,780000,2,'2026-06-28','2026-06-28 12:53:44'),
(23,8,13,'IVA 19%',0,148200,3,'2026-06-28','2026-06-28 12:53:44'),
(24,9,2,'Factura/boleta N° 1003',4165000,0,1,'2026-06-28','2026-06-28 12:53:44'),
(25,9,19,'Neto afecto',0,3500000,2,'2026-06-28','2026-06-28 12:53:44'),
(26,9,13,'IVA 19%',0,665000,3,'2026-06-28','2026-06-28 12:53:44'),
(27,10,20,'Gasto del período',142000,0,1,'2026-06-28','2026-06-28 12:53:44'),
(28,10,1,'IVA crédito fiscal',26980,0,2,'2026-06-28','2026-06-28 12:53:44'),
(29,10,10,'Factura proveedor N° 5002',0,168980,3,'2026-06-28','2026-06-28 12:53:44'),
(30,11,20,'Gasto del período',128000,0,1,'2026-06-28','2026-06-28 12:53:45'),
(31,11,1,'IVA crédito fiscal',24320,0,2,'2026-06-28','2026-06-28 12:53:45'),
(32,11,10,'Factura proveedor N° 4902',0,152320,3,'2026-06-28','2026-06-28 12:53:45'),
(33,12,16,'Gasto del período',95000,0,1,'2026-06-28','2026-06-28 12:53:45'),
(34,12,1,'IVA crédito fiscal',18050,0,2,'2026-06-28','2026-06-28 12:53:45'),
(35,12,10,'Factura proveedor N° 4901',0,113050,3,'2026-06-28','2026-06-28 12:53:45'),
(36,13,21,'Gasto del período',380000,0,1,'2026-06-28','2026-06-28 12:53:45'),
(37,13,10,'Factura proveedor N° 87',0,380000,2,'2026-06-28','2026-06-28 12:53:45'),
(38,14,16,'Gasto del período',185000,0,1,'2026-06-28','2026-06-28 12:53:45'),
(39,14,1,'IVA crédito fiscal',35150,0,2,'2026-06-28','2026-06-28 12:53:45'),
(40,14,10,'Factura proveedor N° 5001',0,220150,3,'2026-06-28','2026-06-28 12:53:45'),
(41,15,22,'Gasto del período',2400000,0,1,'2026-06-28','2026-06-28 12:53:45'),
(42,15,1,'IVA crédito fiscal',456000,0,2,'2026-06-28','2026-06-28 12:53:45'),
(43,15,10,'Factura proveedor N° 5004',0,2856000,3,'2026-06-28','2026-06-28 12:53:45'),
(44,16,20,'Gasto del período',89000,0,1,'2026-06-28','2026-06-28 12:53:45'),
(45,16,1,'IVA crédito fiscal',16910,0,2,'2026-06-28','2026-06-28 12:53:45'),
(46,16,10,'Factura proveedor N° 5003',0,105910,3,'2026-06-28','2026-06-28 12:53:45'),
(47,17,21,'Gasto del período',380000,0,1,'2026-06-28','2026-06-28 12:53:45'),
(48,17,10,'Factura proveedor N° 88',0,380000,2,'2026-06-28','2026-06-28 12:53:45');
/*!40000 ALTER TABLE `asiento_lineas` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

