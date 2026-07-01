/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `plan_cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_cuentas` (
  `id_cuenta` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_cuenta` text DEFAULT NULL,
  `nombre_cuenta` text DEFAULT NULL,
  `tipo_cuenta` text DEFAULT NULL,
  `naturaleza_cuenta` text DEFAULT NULL,
  `nivel_cuenta` int(11) DEFAULT 0,
  `activa_cuenta` int(11) DEFAULT 1,
  `date_created_cuenta` date DEFAULT NULL,
  `date_updated_cuenta` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cuenta`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `razon_social_cliente` text DEFAULT NULL,
  `rut_cliente` text DEFAULT NULL,
  `giro_cliente` text DEFAULT NULL,
  `direccion_cliente` text DEFAULT NULL,
  `comuna_cliente` text DEFAULT NULL,
  `email_cliente` text DEFAULT NULL,
  `telefono_cliente` text DEFAULT NULL,
  `date_created_cliente` date DEFAULT NULL,
  `date_updated_cliente` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cliente`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL AUTO_INCREMENT,
  `razon_social_proveedor` text DEFAULT NULL,
  `rut_proveedor` text DEFAULT NULL,
  `giro_proveedor` text DEFAULT NULL,
  `direccion_proveedor` text DEFAULT NULL,
  `comuna_proveedor` text DEFAULT NULL,
  `email_proveedor` text DEFAULT NULL,
  `telefono_proveedor` text DEFAULT NULL,
  `date_created_proveedor` date DEFAULT NULL,
  `date_updated_proveedor` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_proveedor`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categorias_gasto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_gasto` (
  `id_categoria` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_categoria` text DEFAULT NULL,
  `cuenta_categoria` int(11) DEFAULT 0,
  `date_created_categoria` date DEFAULT NULL,
  `date_updated_categoria` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comprobantes_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comprobantes_venta` (
  `id_venta` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_documento_venta` text DEFAULT NULL,
  `folio_venta` int(11) DEFAULT 0,
  `fecha_venta` date DEFAULT NULL,
  `cliente_venta` int(11) DEFAULT 0,
  `glosa_venta` text DEFAULT NULL,
  `archivo_venta` text DEFAULT NULL,
  `link_pago_venta` text DEFAULT NULL,
  `neto_venta` double DEFAULT 0,
  `iva_venta` double DEFAULT 0,
  `exento_venta` double DEFAULT 0,
  `total_venta` double DEFAULT 0,
  `estado_venta` text DEFAULT NULL,
  `date_created_venta` date DEFAULT NULL,
  `date_updated_venta` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_venta`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comprobantes_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comprobantes_compra` (
  `id_compra` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_documento_compra` text DEFAULT NULL,
  `folio_compra` int(11) DEFAULT 0,
  `fecha_compra` date DEFAULT NULL,
  `proveedor_compra` int(11) DEFAULT 0,
  `categoria_compra` int(11) DEFAULT 0,
  `glosa_compra` text DEFAULT NULL,
  `archivo_compra` text DEFAULT NULL,
  `neto_compra` double DEFAULT 0,
  `iva_compra` double DEFAULT 0,
  `retencion_compra` double DEFAULT 0,
  `exento_compra` double DEFAULT 0,
  `total_compra` double DEFAULT 0,
  `estado_compra` text DEFAULT NULL,
  `date_created_compra` date DEFAULT NULL,
  `date_updated_compra` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_compra`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asientos` (
  `id_asiento` int(11) NOT NULL AUTO_INCREMENT,
  `numero_asiento` int(11) DEFAULT 0,
  `fecha_asiento` date DEFAULT NULL,
  `glosa_asiento` text DEFAULT NULL,
  `origen_asiento` text DEFAULT NULL,
  `origen_id_asiento` int(11) DEFAULT 0,
  `total_debe_asiento` double DEFAULT 0,
  `total_haber_asiento` double DEFAULT 0,
  `estado_asiento` text DEFAULT NULL,
  `date_created_asiento` date DEFAULT NULL,
  `date_updated_asiento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_asiento`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asiento_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asiento_lineas` (
  `id_linea` int(11) NOT NULL AUTO_INCREMENT,
  `asiento_linea` int(11) DEFAULT 0,
  `cuenta_linea` int(11) DEFAULT 0,
  `glosa_linea` text DEFAULT NULL,
  `debe_linea` double DEFAULT 0,
  `haber_linea` double DEFAULT 0,
  `orden_linea` int(11) DEFAULT 0,
  `date_created_linea` date DEFAULT NULL,
  `date_updated_linea` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_linea`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_pago` date DEFAULT NULL,
  `compra_pago` int(11) DEFAULT 0,
  `proveedor_pago` int(11) DEFAULT 0,
  `medio_pago` text DEFAULT NULL,
  `monto_pago` double DEFAULT 0,
  `glosa_pago` text DEFAULT NULL,
  `estado_pago` text DEFAULT NULL,
  `date_created_pago` date DEFAULT NULL,
  `date_updated_pago` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pago`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cierres_mes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cierres_mes` (
  `id_cierre` int(11) NOT NULL AUTO_INCREMENT,
  `mes_cierre` int(11) DEFAULT NULL,
  `anio_cierre` int(11) DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `usuario_cierre` text DEFAULT NULL,
  `notas_cierre` text DEFAULT NULL,
  `date_created_cierre` date DEFAULT NULL,
  `date_updated_cierre` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cierre`),
  UNIQUE KEY `uniq_mes_anio` (`mes_cierre`,`anio_cierre`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
-- ----------------------------------------------------------------------
-- Cobros recibidos de clientes (espejo de `pagos`). Agregado en commit 6
-- del plan SII: cierra el ciclo de cobros + integración Payku opcional.
-- ----------------------------------------------------------------------
DROP TABLE IF EXISTS `cobros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobros` (
  `id_cobro` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_cobro` date DEFAULT NULL,
  `venta_cobro` int(11) DEFAULT 0,
  `cliente_cobro` int(11) DEFAULT 0,
  `medio_cobro` text DEFAULT NULL,
  `monto_cobro` double DEFAULT 0,
  `glosa_cobro` text DEFAULT NULL,
  `estado_cobro` text DEFAULT NULL,
  `payku_order_cobro` text DEFAULT NULL,
  `date_created_cobro` date DEFAULT NULL,
  `date_updated_cobro` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cobro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

