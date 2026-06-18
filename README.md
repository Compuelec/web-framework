# Web Framework — API RESTful & CMS Builder

Framework de desarrollo web que combina una **API RESTful dinámica** y un **CMS
Builder** modular como base para construir aplicaciones web personalizadas de
forma rápida — sin escribir código repetitivo.

Está pensado para ser **genérico, extensible y reutilizable**: no está atado a un
dominio, sirve para e-commerce, CRMs, dashboards, blogs, portales internos, etc.

---

## ✨ Qué incluye

- **API RESTful dinámica** — CRUD sobre cualquier tabla sin definir modelos ni
  rutas. Auth por API Key + JWT, relaciones, filtros y búsqueda.
- **CMS Builder** — crea tablas y campos desde la interfaz, gestiona datos,
  archivos y usuarios, todo con formularios y listados automáticos.
- **Generador de Páginas Web** — crea páginas públicas escribiendo tu HTML e
  insertando datos de tus tablas, con **vista previa en vivo**, **galerías de
  imágenes**, **formularios** (crear/editar + subir archivos) y **login con
  control de acceso por rol/usuario**.
- **Campos de imagen modernos** — imagen única y **multi-imagen** (con subida
  directa y miniaturas).
- **Editores de código** (CodeMirror) con autocompletado, **diagnóstico de
  permisos** y **setup automático** para instalar/restaurar.

## 🚀 Inicio rápido

```bash
git clone https://github.com/tu-usuario/web-framework.git
cd web-framework

# Dependencias
(cd api && composer install)
(cd cms/extensions && composer install)

# Configuración
cp api/config.example.php api/config.php   # edita credenciales BD, API Key, JWT
cp cms/config.example.php cms/config.php    # edita API base_url + key, timezone

# Instala desde el navegador
#   http://localhost/tu-proyecto/cms/   (instalador inicial)

# Crea configs faltantes + directorios + permisos (también al restaurar respaldos)
sudo ./setup.sh
```

Guía completa en **[docs/INSTALACION.md](docs/INSTALACION.md)**.

## 📚 Documentación

| Tema | Documento |
| --- | --- |
| Instalación, configuración y permisos | [docs/INSTALACION.md](docs/INSTALACION.md) |
| Arquitectura y estructura del proyecto | [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) |
| Uso de la API REST | [docs/API.md](docs/API.md) |
| CMS Builder, tablas y tipos de campo | [docs/CMS-Y-TABLAS.md](docs/CMS-Y-TABLAS.md) |
| **Generador de Páginas Web** (plantillas, formularios, login) | [docs/GENERADOR-PAGINAS.md](docs/GENERADOR-PAGINAS.md) |
| Crear páginas desde la CLI/API (guía para agentes de IA) | [docs/AGENTE-CREAR-PAGINAS.md](docs/AGENTE-CREAR-PAGINAS.md) |
| Seguridad | [docs/SEGURIDAD.md](docs/SEGURIDAD.md) |
| Desarrollo (tests, generadores, convenciones) | [docs/DESARROLLO.md](docs/DESARROLLO.md) |
| Manual de usuario | [docs/MANUAL-USUARIO.md](docs/MANUAL-USUARIO.md) |
| Empaquetado / distribución | [PACKAGING.md](PACKAGING.md) |

## 🛠️ Stack

**Backend**: PHP nativo (MVC), MySQL, Composer, Firebase JWT.
**Frontend (CMS)**: Bootstrap 5, jQuery, Chart.js, Summernote, CodeMirror, Select2.

Requisitos: PHP 7.4+, MySQL 5.7+/MariaDB 10.2+, Apache/Nginx, Composer. Ver
[Instalación](docs/INSTALACION.md#requisitos).

## 🔐 Seguridad

API Keys + JWT con expiración, contraseñas con bcrypt, CSRF en el CMS, validación
de tablas/columnas, configuración fuera de git y permisos por rol. Detalles en
[docs/SEGURIDAD.md](docs/SEGURIDAD.md).

## 🤝 Contribución

Crea una rama, asegúrate de que `php tests/run.php` pase y abre un Pull Request a
`development`. Ver [docs/DESARROLLO.md](docs/DESARROLLO.md).

## 📄 Licencia

Licencia propietaria. Todos los derechos reservados.

---

**Desarrollado como base reutilizable para acelerar el desarrollo de aplicaciones web.**
