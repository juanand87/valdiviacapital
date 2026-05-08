# 📰 Diario Los Ríos - Portal de Noticias

Portal de noticias moderno y responsive para la región de Los Ríos, Chile.

## ✨ Características

- **Diseño Moderno**: Interfaz limpia y profesional con CSS moderno
- **Responsive**: Adaptable a todos los dispositivos (móvil, tablet, desktop)
- **Sistema de Categorías**: Organización por temas (Regional, Política, Economía, etc.)
- **Newsletter**: Sistema de suscripción para boletines informativos
- **Búsqueda Avanzada**: Búsqueda en títulos y contenido de noticias
- **Comentarios**: Sistema de comentarios en cada noticia
- **Contador de Vistas**: Estadísticas de visualización
- **Compartir en Redes**: Integración con Facebook, Twitter, WhatsApp
- **SEO Optimizado**: URLs amigables y meta tags

## 🚀 Tecnologías

- **Frontend**: HTML5, CSS3, JavaScript, jQuery
- **Backend**: PHP 7.4+
- **Base de Datos**: MySQL 5.7+
- **Fonts**: Google Fonts (Inter, Poppins)
- **Iconos**: Font Awesome 6.4

## 📋 Requisitos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Apache con mod_rewrite
- Extensiones PHP: PDO, pdo_mysql

## 🔧 Instalación

### 1. Clonar o Descargar el Proyecto

Copia todos los archivos a tu servidor web (ej: `c:\xampp\htdocs\losrios`)

### 2. Crear la Base de Datos

1. Abre phpMyAdmin o tu gestor de MySQL
2. Importa el archivo `database.sql`
3. Esto creará la base de datos `losrios` con tablas y datos de ejemplo

### 3. Configurar la Conexión

Edita el archivo `includes/config.php` y ajusta los parámetros de conexión:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'losrios');
define('DB_USER', 'root');      // Tu usuario MySQL
define('DB_PASS', '');          // Tu contraseña MySQL
```

### 4. Configurar Apache

Asegúrate de que el archivo `.htaccess` esté configurado correctamente y que `mod_rewrite` esté habilitado.

### 5. Permisos de Archivos

Si estás en Linux/Mac, asegúrate de dar permisos de escritura:

```bash
chmod 755 uploads/
chmod 755 cache/
```

### 6. Acceder al Sitio

Abre tu navegador y ve a: `http://localhost/losrios`

## 📁 Estructura del Proyecto

```
losrios/
│
├── assets/
│   ├── css/
│   │   └── style.css         # Estilos principales
│   ├── js/
│   │   └── main.js           # JavaScript principal
│   └── images/               # Imágenes del sitio
│
├── includes/
│   └── config.php            # Configuración y funciones
│
├── ajax/
│   ├── newsletter.php        # Manejo de suscripciones
│   └── incrementar_vista.php # Contador de vistas
│
├── uploads/                  # Imágenes subidas
├── cache/                    # Caché del sistema
│
├── index.php                 # Página principal
├── seccion.php               # Página de categorías
├── noticia.php               # Página individual de noticia
├── busqueda.php              # Página de búsqueda
├── database.sql              # Script de base de datos
└── README.md                 # Este archivo
```

## 🎨 Personalización

### Colores

Los colores principales se definen en `assets/css/style.css`:

```css
:root {
    --color-primary: #2563eb;    /* Azul principal */
    --color-secondary: #1e40af;  /* Azul secundario */
    --color-accent: #dc2626;     /* Rojo para destacados */
    --color-dark: #1f2937;       /* Texto oscuro */
}
```

### Tipografía

Las fuentes se pueden cambiar en las variables CSS:

```css
:root {
    --font-primary: 'Inter', sans-serif;
    --font-heading: 'Poppins', sans-serif;
}
```

## 📊 Base de Datos

### Tablas Principales

- **categorias**: Secciones del periódico
- **usuarios**: Periodistas y editores
- **noticias**: Artículos y noticias
- **comentarios**: Comentarios de lectores
- **newsletter**: Suscriptores al boletín
- **tags**: Etiquetas para categorización

### Datos de Ejemplo

La base de datos incluye:
- 10 categorías predefinidas
- 5 usuarios de ejemplo
- 7 noticias de ejemplo con contenido completo
- Comentarios de ejemplo
- Tags relacionados

## 🔐 Seguridad

- Uso de PDO con prepared statements
- Sanitización de entradas con `htmlspecialchars()`
- Validación de datos en el servidor
- Protección contra SQL injection
- CSRF protection en formularios

## 📱 Responsive Design

El sitio está optimizado para:
- **Móvil**: < 768px
- **Tablet**: 768px - 1024px
- **Desktop**: > 1024px

## 🌐 Hosting en GoDaddy

Para subir a GoDaddy:

1. Comprime todos los archivos
2. Sube via FTP al directorio `public_html`
3. Importa `database.sql` via phpMyAdmin
4. Edita `includes/config.php` con credenciales de producción
5. Verifica permisos de carpetas

## 🛠️ Mantenimiento

### Agregar una Noticia

Inserta en la tabla `noticias`:

```sql
INSERT INTO noticias (titulo, slug, bajada, contenido, categoria_id, autor_id)
VALUES ('Título', 'titulo-slug', 'Bajada', 'Contenido HTML', 1, 2);
```

### Ver Estadísticas

```sql
SELECT titulo, vistas FROM noticias ORDER BY vistas DESC LIMIT 10;
```

## 📈 Próximas Mejoras

- [ ] Panel de administración
- [ ] Sistema de roles y permisos
- [ ] Editor WYSIWYG para noticias
- [ ] Sistema de publicidad
- [ ] Newsletter automático
- [ ] API REST
- [ ] Modo oscuro

## 📞 Soporte

Para preguntas o problemas:
- Email: contacto@diariolosrios.cl

## 📄 Licencia

Este proyecto es de uso libre para fines educativos y comerciales.

---

**Desarrollado con ❤️ para la Región de Los Ríos, Chile**
