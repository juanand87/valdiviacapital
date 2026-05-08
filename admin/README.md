# Panel de Administración - Los Ríos

Sistema completo de administración para el sitio web Los Ríos.

## 🚀 Acceso

**URL:** `http://localhost/losrios/admin/`

**Credenciales de prueba:**
- Email: `editor@losrios.cl`
- Password: `password123`

(Los usuarios se crean en la tabla `usuarios` de la base de datos con contraseñas hasheadas con `password_hash()`)

## 📋 Funcionalidades

### Dashboard Principal
- Estadísticas generales (noticias, categorías, comentarios, newsletter)
- Noticias recientes
- Comentarios pendientes de aprobación

### Gestión de Noticias
- Lista completa con filtros (categoría, autor, búsqueda)
- Ver, editar y eliminar noticias
- Contador de vistas por noticia
- Indicador de noticias destacadas
- Estado: Publicado / Borrador

### Gestión de Categorías
- Crear y editar categorías
- Personalizar: nombre, slug, color, ícono
- Ordenamiento manual
- Vista previa de colores

### Gestión de Comentarios
- Moderar comentarios (aprobar/rechazar)
- Filtros: pendientes, aprobados, todos
- Vista de noticia asociada

### Gestión de Usuarios
- Lista de periodistas y editores
- Roles: Admin, Editor, Periodista
- Contador de noticias por autor
- Último login
- (Solo accesible por Administradores)

### Newsletter
- Lista de suscriptores
- Estadísticas (total, activos, inactivos)
- Exportar a CSV

## 🔐 Seguridad

- Autenticación con sesiones PHP
- Contraseñas hasheadas con `password_hash()`
- Verificación de permisos por rol
- Protección contra SQL injection (PDO prepared statements)
- Protección XSS con `htmlspecialchars()`

## 🎨 Diseño

- Interfaz moderna con sidebar fijo
- Diseño responsive
- Gradientes y colores vibrantes
- Iconos Font Awesome
- Tipografía Inter

## 📁 Estructura de Archivos

```
admin/
├── index.php              # Dashboard principal
├── login.php              # Página de login
├── logout.php             # Cerrar sesión
├── noticias.php           # Gestión de noticias
├── categorias.php         # Gestión de categorías
├── comentarios.php        # Moderación de comentarios
├── usuarios.php           # Gestión de usuarios
├── newsletter.php         # Lista de suscriptores
├── includes/
│   ├── auth.php          # Sistema de autenticación
│   ├── header.php        # Header del admin
│   ├── sidebar.php       # Menú lateral
│   └── footer.php        # Footer del admin
├── ajax/
│   ├── eliminar-noticia.php
│   └── eliminar-categoria.php
└── assets/
    └── css/
        └── admin.css     # Estilos del panel
```

## 🔄 Próximas Mejoras

- Página de crear/editar noticia con editor WYSIWYG
- Subida de imágenes con preview
- Configuración del sitio (título, logo, redes sociales)
- Estadísticas avanzadas con gráficos
- Envío de newsletter masivo
- Logs de actividad

## 🐛 Notas

- Para crear la primera cuenta de administrador, inserta un usuario directamente en la base de datos:
  ```sql
  INSERT INTO usuarios (nombre, email, password, rol, activo)
  VALUES ('Admin', 'admin@losrios.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);
  -- Password: password
  ```
