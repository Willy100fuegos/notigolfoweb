# NotiGolfo Web — CMS PHP Lite

CMS híbrido ultraligero y de alto rendimiento para portales de noticias digitales, diseñado con una arquitectura **Flat-File Markdown** para el consumo público y un panel de administración respaldado por **MySQL/PDO** con soporte analítico opcional en **Redis**.

---

## 🏛 Arquitectura y Funcionamiento

El sistema combina la velocidad de los archivos planos con la flexibilidad de una base de datos relacional:

1. **Lectura Pública (Ultra Rápida & Flat-File):**
   - Las noticias se almacenan como archivos `.md` con metadatos Frontmatter (título, autor, fecha, categoría, imagen destacada).
   - Un índice maestro `content/posts_index.json` consolida los metadatos para permitir consultas instantáneas, paginación y categorización sin sobrecargar bases de datos.
   - Analítica de visitas integrada en `article.php` mediante **Redis** (métricas de visitas globales, anuales y mensuales en memoria con tolerancia a fallos).
2. **Panel de Gestión Editorial (`admin/`):**
   - Editor visual y Markdown con soporte para subida y optimización automática de imágenes (formato WebP/JPEG).
   - Autenticación con sesiones PHP seguras, hashes `password_hash()` y protección contra ataques CSRF.
   - Base de datos **MySQL/MariaDB** dedicada exclusivamente al almacenamiento y control de usuarios y roles (`admin`, `editor`).
3. **SEO & Sindicación Automatizada:**
   - Generación automática de `sitemap.xml` y `feed.rss` (RSS 2.0) al publicar o mediante `fix_seo.php`.

---

## 📋 Requisitos del Entorno

* **Servidor Web:** Apache con módulo `mod_rewrite` habilitado, LiteSpeed o Nginx.
* **PHP:** Versión **8.0** o superior.
* **Extensiones PHP Requeridas:**
  * `pdo_mysql` (gestión de usuarios y panel administrativo).
  * `gd` o `imagick` (procesamiento y optimización de imágenes en el editor).
  * `mbstring` (manipulación segura de cadenas multilingües y slugs).
  * `json` (lectura y persistencia de índices y configuración).
  * `session` (control de acceso administrativo).
* **Extensiones / Servicios Opcionales:**
  * `redis` (extensión PECL phpredis) y servidor Redis en `127.0.0.1:6379`. Si Redis no está disponible, el sistema cuenta con fallback silencioso sin interrumpir la visualización.
* **Base de Datos:** MySQL 5.7+ o MariaDB 10.3+.

---

## 🚀 Guía de Instalación y Puesta en Marcha

### 1. Clonar el Repositorio
```bash
git clone https://github.com/Willy100fuegos/notigolfoweb.git
cd notigolfoweb
```

### 2. Configurar la Base de Datos
Crea una base de datos en tu servidor MySQL/MariaDB y ejecuta el siguiente esquema para la tabla de usuarios:

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(255) DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor') DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Crea un usuario administrador inicial (genera el hash con `password_hash('tu_password', PASSWORD_DEFAULT)`):
```sql
INSERT INTO users (username, display_name, password_hash, role) 
VALUES ('admin', 'Administrador', '$2y$10$EjemploDeHashSeguroGeneradoPorPHP...', 'admin');
```

### 3. Configurar Parámetros de Conexión
Puedes configurar las credenciales mediante cualquiera de los siguientes dos métodos:

* **Opción A (Archivo local no rastreado - Recomendado):**
  Copia el archivo de ejemplo y edita tus credenciales:
  ```bash
  cp includes/db_connect.example.php includes/db_config.local.php
  ```
  Ajusta los valores en `includes/db_config.local.php`:
  ```php
  return [
      'host'     => 'localhost',
      'dbname'   => 'tu_base_de_datos',
      'username' => 'tu_usuario',
      'password' => 'tu_password_seguro',
  ];
  ```

* **Opción B (Variables de Entorno):**
  Define las variables en tu servidor o entorno:
  ```env
  DB_HOST=localhost
  DB_NAME=tu_base_de_datos
  DB_USER=tu_usuario
  DB_PASS=tu_password_seguro
  ```

### 4. Estructura de Directorios y Permisos
Crea los directorios de contenido si aún no existen y asigna permisos de escritura al usuario del servidor web (`www-data`, `nobody` o el usuario cPanel correspondiente):

```bash
mkdir -p content/posts content/images logs
chmod -R 775 content logs
```

### 5. Configuración del Servidor Web
Asegúrate de que el documento raíz (`DocumentRoot`) apunte a la carpeta del proyecto y que la directiva `AllowOverride All` esté activa en Apache para interpretar el archivo `.htaccess`.

---

## 📁 Estructura del Proyecto

```text
├── admin/                     # Panel administrativo y de redacción
│   ├── editor.php             # Editor visual y redacción de artículos
│   ├── login.php              # Login seguro con CSRF y password_verify
│   ├── process_post.php       # Procesamiento y guardado de posts
│   └── users.php              # Gestión de usuarios y credenciales
├── content/                   # Datos dinámicos (ignorado en Git por seguridad)
│   ├── config.json            # Configuración de banners y enlaces sociales
│   ├── images/                # Imágenes de portadas y artículos
│   ├── posts/                 # Artículos en formato Markdown (.md)
│   └── posts_index.json       # Índice consolidado en formato JSON
├── includes/                  # Módulos centrales y utilidades PHP
│   ├── auth.php               # Guardias de sesión y tokens CSRF
│   ├── db_connect.php         # Conexión PDO con soporte para config local y env vars
│   ├── db_connect.example.php # Plantilla de configuración de base de datos
│   ├── image_optimizer.php    # Optimización de imágenes
│   ├── indexer.php            # Generador del índice posts_index.json
│   ├── markdown_parser.php    # Parser de Markdown y extractor de frontmatter
│   └── seo_generator.php      # Generador de sitemap.xml y feed.rss
├── .env.example               # Plantilla de variables de entorno
├── .gitignore                 # Exclusiones de seguridad y archivos locales
├── article.php                # Visualizador individual de noticias
├── category.php               # Filtrado por categorías y paginación
├── feed.rss                   # Sindicación RSS 2.0
├── fix_seo.php                # Regenerador manual de SEO/RSS
├── index.php                  # Portada principal del sitio
└── README.md                  # Documentación oficial del proyecto
```

---

## 🔒 Buenas Prácticas de Seguridad

* **Secretos y Credenciales:** Nunca incluyas credenciales reales en el control de versiones. Usa siempre `includes/db_config.local.php` o variables de entorno.
* **Protección de Directorios:** Los directorios `includes/` y `.vscode/` cuentan con reglas `.htaccess` que bloquean el acceso directo HTTP (`Deny from all`).
* **CSRF & Sesiones:** Todos los formularios administrativos validan tokens anti-CSRF aleatorios y regeneran el identificador de sesión al autenticar.

---

## 📄 Licencia
Este proyecto es software privado/editorial. Todos los derechos reservados.
