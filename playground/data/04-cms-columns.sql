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

LOCK TABLES `columns` WRITE;
/*!40000 ALTER TABLE `columns` DISABLE KEYS */;
INSERT INTO `columns` (`id_column`, `id_module_column`, `title_column`, `alias_column`, `type_column`, `matrix_column`, `conditions_column`, `visible_column`, `date_created_column`, `date_updated_column`) VALUES (5,4,'codigo_cuenta','Código','text','','',1,'2026-06-28','2026-06-28 01:46:25'),
(6,4,'nombre_cuenta','Nombre','text','','',1,'2026-06-28','2026-06-28 01:46:25'),
(7,4,'tipo_cuenta','Tipo','select','activo,pasivo,patrimonio,ingreso,gasto,costo','',1,'2026-06-28','2026-06-28 02:05:22'),
(8,4,'naturaleza_cuenta','Naturaleza','select','deudora,acreedora','',1,'2026-06-28','2026-06-28 02:05:22'),
(9,4,'nivel_cuenta','Nivel','int','','',1,'2026-06-28','2026-06-28 01:46:25'),
(10,4,'activa_cuenta','Activa','boolean','','',1,'2026-06-28','2026-06-28 01:46:25'),
(11,6,'nombre_categoria','Nombre','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(12,6,'cuenta_categoria','Cuenta contable','int','','',1,'2026-06-28','2026-06-28 01:48:21'),
(13,8,'razon_social_cliente','Razón social','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(14,8,'rut_cliente','RUT','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(15,8,'giro_cliente','Giro','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(16,10,'razon_social_proveedor','Razón social','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(17,8,'direccion_cliente','Dirección','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(18,10,'rut_proveedor','RUT','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(19,8,'comuna_cliente','Comuna','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(20,10,'giro_proveedor','Giro','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(21,8,'email_cliente','Email','email','','',1,'2026-06-28','2026-06-28 01:48:21'),
(22,10,'direccion_proveedor','Dirección','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(23,8,'telefono_cliente','Teléfono','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(24,10,'comuna_proveedor','Comuna','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(25,10,'email_proveedor','Email','email','','',1,'2026-06-28','2026-06-28 01:48:21'),
(26,10,'telefono_proveedor','Teléfono','text','','',1,'2026-06-28','2026-06-28 01:48:21'),
(27,12,'asiento_linea','Asiento','int','','',1,'2026-06-28','2026-06-28 01:49:48'),
(28,12,'cuenta_linea','Cuenta','int','','',1,'2026-06-28','2026-06-28 01:49:48'),
(29,12,'glosa_linea','Glosa','text','','',1,'2026-06-28','2026-06-28 01:49:48'),
(30,12,'debe_linea','Debe','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(31,12,'haber_linea','Haber','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(32,12,'orden_linea','Orden','int','','',1,'2026-06-28','2026-06-28 01:49:48'),
(33,14,'numero_asiento','Número','int','','',1,'2026-06-28','2026-06-28 01:49:48'),
(34,14,'fecha_asiento','Fecha','date','','',1,'2026-06-28','2026-06-28 01:49:48'),
(35,14,'glosa_asiento','Glosa','textarea','','',1,'2026-06-28','2026-06-28 01:49:48'),
(36,14,'origen_asiento','Origen','select','manual,venta,compra,pago,ajuste','',1,'2026-06-28','2026-06-28 02:05:22'),
(37,14,'origen_id_asiento','Origen ID','int','','',1,'2026-06-28','2026-06-28 01:49:48'),
(38,14,'total_debe_asiento','Total Debe','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(39,14,'total_haber_asiento','Total Haber','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(40,14,'estado_asiento','Estado','select','borrador,validado,anulado','',1,'2026-06-28','2026-06-28 02:05:22'),
(41,16,'tipo_documento_venta','Tipo de documento (factura/boleta)','select','factura_afecta,factura_exenta,boleta,nota_credito,nota_debito','',1,'2026-06-28','2026-06-28 21:43:25'),
(42,16,'folio_venta','Folio (N° del documento)','int','','',1,'2026-06-28','2026-06-28 21:43:25'),
(43,16,'fecha_venta','Fecha','date','','',1,'2026-06-28','2026-06-28 01:49:48'),
(44,16,'cliente_venta','Cliente','int','','',1,'2026-06-28','2026-06-28 01:49:48'),
(45,16,'glosa_venta','Glosa','textarea','','',1,'2026-06-28','2026-06-28 01:49:48'),
(46,18,'tipo_documento_compra','Tipo de documento (factura/boleta)','select','factura_afecta,factura_exenta,boleta_honorarios,nota_credito,nota_debito','',1,'2026-06-28','2026-06-28 21:43:25'),
(47,16,'neto_venta','Neto','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(48,18,'folio_compra','Folio (N° del documento)','int','','',1,'2026-06-28','2026-06-28 21:43:25'),
(49,16,'iva_venta','IVA','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(50,18,'fecha_compra','Fecha','date','','',1,'2026-06-28','2026-06-28 01:49:48'),
(51,16,'exento_venta','Exento','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(52,18,'proveedor_compra','Proveedor','int','','',1,'2026-06-28','2026-06-28 01:49:48'),
(53,16,'total_venta','Total','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(54,18,'categoria_compra','Categoría','int','','',1,'2026-06-28','2026-06-28 01:49:48'),
(55,16,'estado_venta','Estado','select','emitido,anulado','',1,'2026-06-28','2026-06-28 02:05:22'),
(56,18,'glosa_compra','Glosa','textarea','','',1,'2026-06-28','2026-06-28 01:49:48'),
(57,18,'neto_compra','Neto','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(58,18,'iva_compra','IVA','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(59,18,'exento_compra','Exento','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(60,18,'total_compra','Total','money','','',1,'2026-06-28','2026-06-28 01:49:48'),
(61,18,'estado_compra','Estado','select','registrado,pagado,anulado','',1,'2026-06-28','2026-06-28 02:05:22'),
(62,16,'archivo_venta','Archivo (PDF/imagen)','file',NULL,NULL,0,NULL,'2026-06-28 21:41:23'),
(63,18,'archivo_compra','Archivo (PDF/imagen)','file',NULL,NULL,0,NULL,'2026-06-28 21:41:23'),
(64,20,'fecha_pago','Fecha del pago','date','','',1,'2026-06-29','2026-06-29 21:22:33'),
(65,20,'compra_pago','Compra (id)','int','','',1,'2026-06-29','2026-06-29 21:22:33'),
(66,20,'proveedor_pago','Proveedor (id)','int','','',1,'2026-06-29','2026-06-29 21:22:33'),
(67,20,'medio_pago','Medio de pago','select','','',1,'2026-06-29','2026-06-29 21:22:33'),
(68,20,'monto_pago','Monto','money','','',1,'2026-06-29','2026-06-29 21:22:33'),
(69,20,'glosa_pago','Glosa / referencia','textarea','','',1,'2026-06-29','2026-06-29 21:22:33'),
(70,20,'estado_pago','Estado','select','','',1,'2026-06-29','2026-06-29 21:22:33'),
(71,18,'retencion_compra','Retención 10% (honorarios)','money',NULL,NULL,1,'2026-06-30','2026-06-30 00:17:22'),
(72,21,'mes_cierre','Mes','int',NULL,NULL,1,'2026-06-30','2026-06-30 13:05:51'),
(73,21,'anio_cierre','Año','int',NULL,NULL,1,'2026-06-30','2026-06-30 13:05:51'),
(74,21,'fecha_cierre','Fecha del cierre','date',NULL,NULL,1,'2026-06-30','2026-06-30 13:05:51'),
(75,21,'usuario_cierre','Usuario que cerró','text',NULL,NULL,1,'2026-06-30','2026-06-30 13:05:51'),
(76,21,'notas_cierre','Notas / observaciones','textarea',NULL,NULL,0,'2026-06-30','2026-06-30 13:05:51');
/*!40000 ALTER TABLE `columns` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

