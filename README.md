# Web Framework - API RESTful & CMS Builder

Framework completo de desarrollo web que proporciona una **API RESTful dinámica** y un **CMS Builder** modular como base para construir aplicaciones web personalizadas de forma rápida y eficiente.

## 📋 Descripción

Este framework de desarrollo web está diseñado para ser la **base de futuros proyectos**. Combina una **API REST** robusta y flexible con un **Sistema de Gestión de Contenido (CMS Builder)** que permite crear y gestionar aplicaciones web sin necesidad de escribir código repetitivo.

### Filosofía del Proyecto

Este framework está pensado para:
- 🚀 **Acelerar el desarrollo**: Proporciona una base sólida y probada
- 🔧 **Ser extensible**: Fácil de personalizar y adaptar a diferentes necesidades
- 📦 **Reutilizar código**: Componentes modulares que se pueden integrar en múltiples proyectos
- 🎯 **Ser genérico**: No está atado a un dominio específico, puede usarse para cualquier tipo de aplicación

### Componentes Principales

- **API RESTful** (`/api/`): Backend en PHP que proporciona operaciones CRUD dinámicas sobre cualquier tabla de base de datos
- **CMS Builder** (`/cms/`): Sistema de gestión de contenido con interfaz administrativa para crear y gestionar tablas, formularios y páginas dinámicamente
- **Ejemplos de Integración**: Incluye ejemplos de integración con servicios externos (OpenAI, WhatsApp) que demuestran cómo extender el framework

## ✨ Características Principales

### API RESTful Dinámica
- ✅ Operaciones CRUD dinámicas sobre cualquier tabla de base de datos
- ✅ No requiere definir modelos específicos - funciona con cualquier estructura
- ✅ Autenticación mediante API Key y tokens JWT
- ✅ Sistema de acceso público configurable por tabla
- ✅ Validación automática de tablas y columnas
- ✅ Soporte para relaciones entre tablas (JOIN)
- ✅ Filtros, búsquedas y rangos de datos avanzados
- ✅ CORS configurado para peticiones cross-origin
- ✅ Respuestas en formato JSON estandarizado

### CMS Builder
- ✅ **Gestión dinámica de tablas**: Crea y gestiona tablas desde la interfaz
- ✅ **Formularios dinámicos**: Genera formularios automáticamente basados en la estructura de tablas
- ✅ **Sistema modular**: Arquitectura extensible con módulos reutilizables
- ✅ **Gestión de páginas personalizadas**: Crea páginas y rutas sin escribir código
- ✅ **Sistema de archivos multimedia**: Gestión completa de archivos, imágenes y videos
- ✅ **Dashboard configurable**: Gráficos y métricas personalizables
- ✅ **Editor de código integrado**: CodeMirror para editar código personalizado
- ✅ **Editor WYSIWYG**: Summernote para contenido rico
- ✅ **Gestión de usuarios**: Sistema de administradores con roles y permisos
- ✅ **Instalador automático**: Configuración inicial del sistema desde la interfaz

### Ejemplos de Integración (Extras)

El framework incluye ejemplos de cómo integrar servicios externos:

- 🔌 **Integración con OpenAI/ChatGPT**: Ejemplo de cómo integrar IA para generación de contenido
- 📱 **Webhooks de WhatsApp/Meta**: Ejemplo de integración con servicios de mensajería
- 📧 **Sistema de correos**: PHPMailer configurado y listo para usar

> **💡 Nota**: Estas integraciones son **ejemplos** que demuestran cómo extender el framework. Puedes eliminarlas o reemplazarlas según las necesidades de tu proyecto.

## 🛠️ Tecnologías

### Backend
- **PHP** (nativo, arquitectura MVC)
- **MySQL** (base de datos)
- **Composer** (gestión de dependencias)
- **Firebase JWT** (autenticación con tokens)
- **PHPMailer** (envío de correos - ejemplo)

### Frontend (CMS Builder)
- **HTML5, CSS3, JavaScript**
- **Bootstrap 5** (framework CSS)
- **jQuery** (manipulación DOM)
- **Chart.js** (gráficos)
- **Summernote** (editor WYSIWYG)
- **CodeMirror** (editor de código)
- **Select2, DateRangePicker** (componentes UI)

## 📦 Requisitos

- **Servidor Web**: Apache (recomendado) o Nginx
- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior / MariaDB 10.2 o superior
- **Composer**: Para gestionar dependencias PHP
- **Extensiones PHP**:
  - PDO
  - PDO_MySQL
  - JSON
  - cURL
  - OpenSSL (para JWT)

## 🚀 Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/tu-usuario/web-framework.git
cd web-framework
```

### 2. Instalar dependencias

```bash
# Instalar dependencias de la API
cd api
composer install

# Instalar dependencias del CMS (si aplica)
cd ../cms/extensions
composer install
```

### 3. Configurar la base de datos

1. Crea una base de datos MySQL (puedes usar cualquier nombre)
2. El CMS Builder incluye un instalador que creará las tablas necesarias automáticamente

### 4. Configurar archivos de configuración

#### API Configuration

```bash
cp api/config.example.php api/config.php
```

Edita `api/config.php` y configura:
- Credenciales de base de datos
- API Key
- JWT Secret
- Password Salt

#### CMS Configuration

```bash
cp cms/config.example.php cms/config.php
```

Edita `cms/config.php` y configura:
- URL base de la API y API Key
- Timezone
- Password Salt
- **(Opcional)** Configuración de servicios externos si vas a usar los ejemplos:
  - Token de webhook (Meta/WhatsApp)
  - Configuración de email
  - Configuración de OpenAI

> **⚠️ Importante**: Los archivos `config.php` están en `.gitignore` y no deben versionarse. Usa siempre `config.example.php` como plantilla.

### 5. Configurar el servidor web

#### Apache (XAMPP/WAMP)

Asegúrate de que el módulo `mod_rewrite` esté habilitado. Los archivos `.htaccess` ya están configurados.

#### Nginx

Configura las reglas de reescritura apropiadas para las rutas de la API.

### 6. Instalación inicial

1. Accede al CMS en tu navegador: `http://localhost/tu-proyecto/cms/`
2. Si es la primera vez, el sistema te mostrará el instalador
3. Completa el formulario de instalación para crear el administrador inicial y configurar las tablas base

### 7. Permisos de archivos

```bash
# Asegúrate de que el servidor web tenga permisos de escritura en:
chmod -R 755 cms/views/assets/files/
chmod -R 755 api/
chmod -R 755 cms/
```

## ⚙️ Configuración

### Variables de Entorno (Alternativa)

En lugar de usar archivos `config.php`, puedes usar variables de entorno:

#### API
- `DB_HOST` - Host de la base de datos
- `DB_NAME` - Nombre de la base de datos
- `DB_USER` - Usuario de la base de datos
- `DB_PASS` - Contraseña de la base de datos
- `API_KEY` - Clave de API
- `JWT_SECRET` - Secreto para JWT
- `PASSWORD_SALT` - Salt para encriptación de contraseñas

#### CMS
- `API_BASE_URL` - URL base para peticiones internas a la API
- `API_KEY` - Clave de API para peticiones internas
- `PASSWORD_SALT` - Salt para encriptación de contraseñas
- **(Opcional)** Variables para ejemplos de integración:
  - `WEBHOOK_TOKEN` - Token de verificación de webhook
  - `OPENAI_API_URL`, `OPENAI_MODEL`, `OPENAI_TOKEN`, `OPENAI_ORG` - Configuración de OpenAI

Para más detalles sobre la configuración, consulta [`.cursor/README_CONFIG.md`](.cursor/README_CONFIG.md).

## 📁 Estructura del Proyecto

```
proyecto/
├── api/                    # API RESTful
│   ├── config.example.php  # Plantilla de configuración
│   ├── config.php          # Configuración real (no versionado)
│   ├── controllers/        # Controladores
│   ├── models/            # Modelos de datos
│   ├── routes/            # Sistema de enrutamiento
│   └── vendor/            # Dependencias Composer
│
├── cms/                    # CMS Builder
│   ├── config.example.php  # Plantilla de configuración
│   ├── config.php         # Configuración real (no versionado)
│   ├── controllers/       # Controladores
│   ├── views/             # Vistas y templates
│   ├── ajax/              # Endpoints AJAX
│   ├── webhook/           # Ejemplo: Webhooks (WhatsApp/Meta)
│   └── extensions/        # Extensiones y dependencias
│
├── .cursor/               # Documentación para Cursor AI
├── .gitignore             # Archivos ignorados por Git
└── README.md              # Este archivo
```

## 🔌 Uso de la API

### Autenticación

Todas las peticiones (excepto tablas de acceso público) requieren un header de autorización:

```http
Authorization: tu-api-key-aqui
```

### Endpoints

La API funciona de forma dinámica con cualquier tabla. No necesitas definir rutas específicas.

#### GET - Obtener datos
```http
GET /api/{tabla}                          # Obtener todos los registros
GET /api/{tabla}?linkTo=columna&equalTo=valor  # Filtrar por columna
GET /api/{tabla}?rel=tabla1,tabla2        # Incluir relaciones
GET /api/{tabla}?search=columna:valor     # Buscar
GET /api/{tabla}?orderBy=columna&orderMode=ASC  # Ordenar
```

#### POST - Crear registro
```http
POST /api/{tabla}
Content-Type: application/x-www-form-urlencoded

campo1=valor1&campo2=valor2
```

#### PUT - Actualizar registro
```http
PUT /api/{tabla}?id=123&nameId=id_tabla

campo1=nuevo_valor1
```

#### DELETE - Eliminar registro
```http
DELETE /api/{tabla}?id=123&nameId=id_tabla
```

### Ejemplo de uso

```javascript
// Obtener todos los productos
fetch('http://localhost/tu-proyecto/api/products', {
  headers: {
    'Authorization': 'tu-api-key'
  }
})
.then(response => response.json())
.then(data => console.log(data));

// Crear un nuevo producto
fetch('http://localhost/tu-proyecto/api/products', {
  method: 'POST',
  headers: {
    'Authorization': 'tu-api-key',
    'Content-Type': 'application/x-www-form-urlencoded'
  },
  body: 'name_product=Producto 1&price_product=99.99&stock_product=100'
})
.then(response => response.json())
.then(data => console.log(data));
```

## 🎨 Uso del CMS Builder

El CMS Builder te permite:

1. **Crear tablas dinámicamente**: Define la estructura de tus tablas desde la interfaz
2. **Gestionar datos**: CRUD completo desde el panel administrativo
3. **Crear páginas personalizadas**: Construye páginas sin escribir código
4. **Gestionar archivos**: Sube y organiza archivos multimedia
5. **Configurar permisos**: Controla el acceso con roles y permisos

### Flujo de trabajo típico

1. Accede al CMS: `http://localhost/tu-proyecto/cms/`
2. Crea una nueva página desde el menú
3. Agrega módulos a la página (tablas, formularios, etc.)
4. Define las columnas de tus tablas
5. ¡Listo! Ya puedes gestionar datos desde el CMS y consumirlos desde la API

## 🔐 Seguridad

- ✅ API Keys para autenticación de peticiones
- ✅ Tokens JWT con expiración (1 día por defecto)
- ✅ Validación de tokens en base de datos
- ✅ Encriptación de contraseñas con Blowfish
- ✅ Configuración sensible en archivos no versionados
- ✅ Soporte para variables de entorno
- ✅ CORS configurado
- ✅ Validación de tablas y columnas antes de operaciones
- ✅ Sistema de permisos granular en el CMS

## 📝 Convenciones de Código

- **Comentarios en inglés**: Todos los comentarios del código deben estar en inglés
- **Arquitectura MVC**: Separación clara de responsabilidades
- **Validación**: Validación de tablas y columnas antes de operaciones
- **Configuración centralizada**: Uso de archivos `config.php` para información sensible
- **Código genérico**: El código está diseñado para ser reutilizable y no atado a un dominio específico

## 🚀 Casos de Uso

Este framework es ideal para:

- 🛒 **E-commerce**: Gestión de productos, pedidos, clientes
- 📝 **Blogs y CMS**: Gestión de contenido, artículos, categorías
- 👥 **Aplicaciones de gestión**: CRM, ERP, sistemas administrativos
- 📊 **Dashboards**: Aplicaciones con visualización de datos
- 💬 **Aplicaciones de chat**: Sistemas de mensajería y comunicación
- 🎓 **Plataformas educativas**: Gestión de cursos, estudiantes, contenido
- Y cualquier aplicación que requiera CRUD dinámico y gestión de contenido

## 🔧 Personalización

El framework está diseñado para ser fácilmente personalizable:

- **Temas**: Personaliza la interfaz del CMS modificando las vistas
- **Módulos**: Crea tus propios módulos siguiendo la estructura existente
- **Endpoints personalizados**: Extiende la API agregando nuevos controladores
- **Integraciones**: Agrega tus propias integraciones siguiendo los ejemplos incluidos

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está bajo una licencia propietaria. Todos los derechos reservados.

## 📞 Soporte

Para soporte, por favor abre un issue en el repositorio de GitHub.

## 🙏 Agradecimientos

- Firebase JWT para autenticación
- PHPMailer para envío de correos
- Bootstrap, jQuery y todas las librerías de código abierto utilizadas

---

**Desarrollado como base reutilizable para acelerar el desarrollo de aplicaciones web**
