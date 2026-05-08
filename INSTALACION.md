# 🚀 GUÍA DE INSTALACIÓN - Diario Los Ríos

## Instalación en XAMPP (Local)

### Paso 1: Verificar Requisitos

✅ XAMPP instalado con:
- Apache 2.4+
- MySQL 5.7+
- PHP 7.4+

### Paso 2: Copiar Archivos

1. Copia la carpeta `losrios` a `c:\xampp\htdocs\`
2. La ruta final debe ser: `c:\xampp\htdocs\losrios\`

### Paso 3: Iniciar Servicios

1. Abre el Panel de Control de XAMPP
2. Inicia **Apache**
3. Inicia **MySQL**

### Paso 4: Crear Base de Datos

#### Opción A: Usando phpMyAdmin (Recomendado)

1. Abre tu navegador y ve a: `http://localhost/phpmyadmin`
2. Haz clic en "Nuevo" en el panel izquierdo
3. Nombre de la base de datos: `losrios`
4. Cotejamiento: `utf8mb4_unicode_ci`
5. Haz clic en "Crear"
6. Selecciona la base de datos creada
7. Ve a la pestaña "Importar"
8. Haz clic en "Seleccionar archivo"
9. Busca y selecciona: `c:\xampp\htdocs\losrios\database.sql`
10. Haz clic en "Continuar"
11. Espera a que termine la importación

#### Opción B: Usando MySQL Command Line

```bash
mysql -u root -p
CREATE DATABASE losrios CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE losrios;
SOURCE c:/xampp/htdocs/losrios/database.sql;
EXIT;
```

### Paso 5: Configurar Conexión

1. Abre el archivo: `includes/config.php`
2. Verifica que los datos sean correctos:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'losrios');
define('DB_USER', 'root');
define('DB_PASS', '');  // Dejar vacío si no tienes contraseña
```

3. Guarda el archivo

### Paso 6: Probar el Sitio

1. Abre tu navegador
2. Ve a: `http://localhost/losrios`
3. ¡Deberías ver el sitio funcionando!

---

## Instalación en GoDaddy (Hosting)

### Paso 1: Preparar Archivos

1. Comprime todos los archivos del proyecto en un ZIP
2. Excluye la carpeta `.git` si existe

### Paso 2: Subir Archivos via FTP

1. Descarga FileZilla o usa el administrador de archivos de GoDaddy
2. Conéctate a tu servidor FTP:
   - Host: Tu dominio o IP del servidor
   - Usuario: Tu usuario de cPanel
   - Contraseña: Tu contraseña de cPanel
3. Sube todos los archivos a la carpeta `public_html`
4. Estructura final:
   ```
   public_html/
   ├── assets/
   ├── includes/
   ├── ajax/
   ├── index.php
   └── ...
   ```

### Paso 3: Crear Base de Datos en GoDaddy

1. Inicia sesión en tu cPanel de GoDaddy
2. Busca "Bases de datos MySQL"
3. Crea una nueva base de datos:
   - Nombre: `losrios_db` (o el que prefieras)
4. Crea un usuario:
   - Usuario: `losrios_user`
   - Contraseña: (genera una segura)
5. Asigna el usuario a la base de datos con "TODOS LOS PRIVILEGIOS"
6. Anota:
   - Nombre de BD
   - Usuario
   - Contraseña
   - Host (generalmente: localhost)

### Paso 4: Importar Base de Datos

1. En cPanel, busca "phpMyAdmin"
2. Selecciona tu base de datos
3. Ve a "Importar"
4. Selecciona el archivo `database.sql` de tu computadora
5. Haz clic en "Continuar"

### Paso 5: Configurar en Producción

1. Edita el archivo `includes/config.php` via FTP o File Manager
2. Actualiza con tus datos:

```php
define('DB_HOST', 'localhost');  // O el host que te dio GoDaddy
define('DB_NAME', 'losrios_db');
define('DB_USER', 'losrios_user');
define('DB_PASS', 'tu_contraseña_segura');

// Cambia la URL del sitio
define('SITE_URL', 'https://tudominio.com');
```

### Paso 6: Configurar .htaccess

1. Edita el archivo `.htaccess`
2. Cambia la línea:
   ```apache
   RewriteBase /losrios/
   ```
   Por:
   ```apache
   RewriteBase /
   ```

3. Descomenta las líneas de HTTPS:
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

### Paso 7: Verificar Permisos

Asegúrate de que estas carpetas tengan permisos 755:
- `uploads/`
- `cache/`

```bash
chmod 755 uploads
chmod 755 cache
```

### Paso 8: Probar el Sitio

1. Visita tu dominio: `https://tudominio.com`
2. Verifica que todo funcione correctamente

---

## ⚠️ Solución de Problemas Comunes

### Error: "Could not connect to database"

**Solución:**
- Verifica que MySQL esté corriendo
- Revisa los datos en `includes/config.php`
- Verifica que la base de datos exista

### Error: "Table doesn't exist"

**Solución:**
- Importa nuevamente el archivo `database.sql`
- Verifica que todas las tablas se hayan creado

### Error 500 - Internal Server Error

**Solución:**
1. Verifica permisos de archivos (755 para carpetas, 644 para archivos)
2. Revisa el archivo `.htaccess`
3. Verifica los logs de error de Apache

### Las imágenes no se muestran

**Solución:**
- Las imágenes de ejemplo usan Unsplash
- Verifica tu conexión a internet
- En producción, sube tus propias imágenes a la carpeta `uploads/`

### Error: "Headers already sent"

**Solución:**
- Asegúrate de no tener espacios antes de `<?php` en los archivos PHP
- Guarda los archivos con codificación UTF-8 sin BOM

---

## 🎯 Próximos Pasos

Después de la instalación:

1. **Cambiar contraseñas**: Actualiza las contraseñas en la tabla `usuarios`
2. **Personalizar contenido**: Edita las noticias de ejemplo
3. **Agregar tus imágenes**: Sube imágenes reales a `uploads/`
4. **Configurar email**: Para el newsletter y notificaciones
5. **Optimizar SEO**: Agrega meta tags personalizados
6. **Google Analytics**: Agrega tu código de seguimiento

---

## 📧 Soporte

Si tienes problemas con la instalación:
- Revisa el archivo `README.md` para más información
- Verifica los logs de error de Apache y PHP
- Contacta a: contacto@diariolosrios.cl

---

**¡Listo! Tu sitio de noticias está funcionando** 🎉
