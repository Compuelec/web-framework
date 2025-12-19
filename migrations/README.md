# Sistema de Migraciones

Este directorio contiene los archivos de migración SQL que se ejecutan automáticamente durante las actualizaciones del framework.

## Formato de Nombres

Los archivos de migración deben seguir este formato:

```
{version_desde}_to_{version_hasta}.sql
```

Ejemplo:
- `1.0.0_to_1.0.1.sql` - Migración de la versión 1.0.0 a 1.0.1
- `1.0.1_to_1.1.0.sql` - Migración de la versión 1.0.1 a 1.1.0
- `1.1.0_to_2.0.0.sql` - Migración de la versión 1.1.0 a 2.0.0

## Estructura de un Archivo de Migración

Cada archivo SQL puede contener múltiples sentencias SQL separadas por punto y coma:

```sql
-- Comentarios son permitidos
-- Esta migración agrega una nueva tabla

CREATE TABLE IF NOT EXISTS nueva_tabla (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agregar una columna a una tabla existente
ALTER TABLE tabla_existente 
ADD COLUMN nueva_columna VARCHAR(100) AFTER columna_existente;

-- Actualizar datos existentes
UPDATE tabla_existente 
SET nueva_columna = 'valor_por_defecto' 
WHERE nueva_columna IS NULL;
```

## Buenas Prácticas

1. **Siempre usa IF NOT EXISTS / IF EXISTS**: Esto previene errores si la migración se ejecuta múltiples veces
2. **Usa transacciones cuando sea posible**: El sistema ejecuta cada migración en una transacción
3. **Documenta tus cambios**: Usa comentarios para explicar qué hace cada migración
4. **Prueba las migraciones**: Asegúrate de probar las migraciones en un entorno de desarrollo antes de publicarlas
5. **No elimines datos sin backup**: Las migraciones destructivas deben ser claramente documentadas

## Ejecución Automática

Las migraciones se ejecutan automáticamente cuando:
- Se instala una actualización del framework
- El sistema detecta que hay migraciones pendientes para la versión objetivo

Las migraciones se ejecutan en orden según las versiones, y solo se ejecutan una vez (se registran en la tabla `framework_migrations`).
