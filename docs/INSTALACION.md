# Instalación y configuración

## Requisitos

- **Servidor web**: Apache (recomendado, con `mod_rewrite`) o Nginx
- **PHP**: 7.4 o superior — extensiones: PDO, PDO_MySQL, JSON, cURL, OpenSSL
- **MySQL** 5.7+ / MariaDB 10.2+
- **Composer**

## 1. Clonar e instalar dependencias

```bash
git clone https://github.com/tu-usuario/web-framework.git
cd web-framework

# Dependencias de la API
(cd api && composer install)

# Dependencias del CMS (si aplica)
(cd cms/extensions && composer install)
```

## 2. Base de datos

1. Crea una base de datos MySQL (cualquier nombre).
2. El CMS incluye un instalador que crea las tablas necesarias automáticamente
   (paso 5).

## 3. Archivos de configuración

Copia las plantillas y completa tus valores. Los `config.php` están en
`.gitignore` y **no deben versionarse** — usa siempre `config.example.php`.

```bash
cp api/config.example.php api/config.php
cp cms/config.example.php cms/config.php
```

- **`api/config.php`**: credenciales de BD, API Key, JWT Secret, Password Salt.
- **`cms/config.php`**: URL base de la API + API Key, timezone, Password Salt, y
  (opcional) servicios externos (webhook, email, OpenAI) y formato regional
  (`localization`, ver abajo).
- **`web/config.php`**: lo genera `setup.sh` automáticamente desde `cms/config.php`
  (ver paso 6). Es necesario para que las páginas públicas carguen datos.

### Variables de entorno (alternativa)

En vez de `config.php` puedes usar variables de entorno:

- **API**: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `API_KEY`, `JWT_SECRET`,
  `PASSWORD_SALT`.
- **CMS**: `API_BASE_URL`, `API_KEY`, `PASSWORD_SALT`, y opcionales
  (`WEBHOOK_TOKEN`, `OPENAI_*`).

### Formato regional (`localization`, opcional)

En `cms/config.php` puedes añadir un bloque **opcional** `localization` que define
cómo se formatean los listados: moneda de las columnas `money` y formato de las
columnas `date` / `datetime` / `time`. Si lo omites se conserva el comportamiento
por defecto (`$` con 2 decimales; fechas en formato legible):

```php
'localization' => [
    'currency' => [
        'symbol'        => '$',
        'decimals'      => 2,   // CLP: 0
        'thousands_sep' => ',', // CLP: '.'
        'decimal_sep'   => '.', // CLP: ','
    ],
    'date_format'     => 'd-m-Y',
    'datetime_format' => 'd-m-Y H:i',
    'time_format'     => 'H:i',
],
```

## 4. Servidor web

- **Apache (XAMPP/WAMP)**: habilita `mod_rewrite`. Los `.htaccess` ya están
  configurados.
- **Nginx**: configura las reglas de reescritura para las rutas de la API.

## 5. Instalación inicial

1. Abre el CMS: `http://localhost/tu-proyecto/cms/`
2. La primera vez verás el instalador.
3. Completa el formulario para crear el administrador inicial y las tablas base.

## 6. Setup automático

Tras instalar **o restaurar un respaldo**, ejecuta el setup una vez. Crea los
`config.php` faltantes (incluido un `web/config.php` funcional derivado de
`cms/config.php`), crea los directorios escribibles y ajusta dueño y permisos:

```bash
sudo ./setup.sh
# En Linux indica el usuario del servidor si no es www-data:
# sudo ./setup.sh apache
```

Es **idempotente**: nunca sobrescribe un `config.php` existente. Esto evita el
problema de "la página pública no carga datos" (cuando falta `web/config.php`)
y los problemas de permisos.

## 7. Permisos (manual)

Si prefieres no usar `setup.sh`, el servidor web necesita escritura en:

```bash
chmod -R 775 cms/views/assets/files/   # subidas
chmod -R 775 web/pages/                # páginas generadas
chmod -R 775 logs/ api/tmp/ packages/  # logs, temporales, paquetes
```

En XAMPP/macOS, Apache corre como `daemon`; para que escriba directo:

```bash
sudo chown -R daemon:staff web/pages
```

Si `web/pages` no es escribible, el generador igual funciona y ofrece **descargar**
los archivos para colocarlos manualmente.

## Solución de problemas

### Las actualizaciones fallan por permisos

El sistema necesita escritura para respaldos (`backups/`), descarga/extracción de
actualizaciones y migraciones. Solución (ajusta la ruta a tu instalación):

```bash
# XAMPP / macOS
sudo chown -R daemon:admin /ruta/al/proyecto
sudo find /ruta/al/proyecto -type d -exec chmod 775 {} +
sudo find /ruta/al/proyecto -type f -exec chmod 664 {} +
```

- **Linux/Apache**: usa el usuario del servidor (`www-data` o `apache`).
- **Nginx**: verifica permisos del usuario `www-data` / `nginx`.
