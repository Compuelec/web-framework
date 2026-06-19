# Seguridad

## Mecanismos del framework

- ✅ **API Keys** para autenticar peticiones a la API.
- ✅ **Tokens JWT** con expiración (1 día por defecto) y validación en BD.
- ✅ **Contraseñas** encriptadas (bcrypt/Blowfish).
- ✅ **CSRF**: el CMS envía `X-CSRF-Token` en cada petición AJAX; el servidor lo
  valida (exime GET).
- ✅ **Configuración sensible** fuera del control de versiones (`config.php` en
  `.gitignore`) + soporte de variables de entorno.
- ✅ **CORS** configurado.
- ✅ **Validación de tablas y columnas** antes de operar (identificadores
  saneados, prepared statements).
- ✅ **Sistema de permisos** por rol en el CMS.

## Páginas generadas (Generador de Páginas)

- Los **datos de registros se escapan** (`htmlspecialchars`) al renderizar; el
  HTML/CSS/JS del autor se emite tal cual (es su propio sitio).
- Las **páginas públicas solo crean** registros desde formularios; **no** pueden
  editar registros existentes vía `?id` (evita modificación no autorizada / IDOR).
- Las **páginas privadas** validan login contra los `admins` (`password_verify`)
  y **regeneran el id de sesión** al iniciar (anti session-fixation).
- El acceso se restringe por **rol** y/o **usuario**; los no autorizados no ven
  el contenido.
- Subidas desde formularios: extensiones permitidas en lista blanca, nombres
  aleatorios, guardadas en `web/uploads/`.

## Buenas prácticas al desplegar

- Ejecuta [`sudo ./setup.sh`](INSTALACION.md#setup-automático) para crear los
  config y fijar permisos correctos.
- Nunca subas `config.php` con credenciales reales.
- No elimines los `.htaccess` que protegen directorios sensibles.
