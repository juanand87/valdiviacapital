# Medios Conectados

Sistema de sincronización de contenido desde diferentes medios digitales para el portal Los Ríos.

## Descripción

El módulo de **Medios Conectados** permite configurar y gestionar la sincronización automática de contenido desde diferentes fuentes:

- **Diarios Online**: Extracción de noticias mediante scrapping web o APIs
- **Medios de Facebook**: Sincronización de publicaciones desde páginas de Facebook
- **Medios de Instagram**: Obtención de contenido desde perfiles de Instagram Business/Creator

## Estructura de Base de Datos

### Tablas creadas:

1. **medios_conectados**: Almacena la información básica de cada medio
2. **medios_diarios_config**: Configuración específica para scrapping de diarios
3. **medios_facebook_config**: Configuración de API de Facebook
4. **medios_instagram_config**: Configuración de API de Instagram
5. **medios_contenido_sincronizado**: Registro de todo el contenido sincronizado

## Instalación

1. Ejecutar el archivo SQL para crear las tablas:
```sql
mysql -u usuario -p nombre_db < medios_conectados.sql
```

O importar manualmente desde phpMyAdmin.

## Archivos del Módulo

### Administración:
- `admin/medios-conectados.php` - Dashboard principal
- `admin/medios-diarios.php` - Gestión de diarios online
- `admin/medios-facebook.php` - Gestión de medios de Facebook
- `admin/medios-instagram.php` - Gestión de medios de Instagram

## Configuración

### Diarios Online

Para configurar un diario online necesitas:

1. **Scrapping Web**:
   - URL del sitio
   - Selectores CSS para: título, contenido, imagen, fecha, autor, categoría
   - Frecuencia de sincronización

2. **Vía API**:
   - URL del endpoint API
   - API Key (si es requerida)
   - Frecuencia de sincronización

### Facebook

1. Crear una aplicación en [Facebook Developers](https://developers.facebook.com/)
2. Obtener:
   - App ID
   - App Secret
   - Page ID de la página a sincronizar
   - Access Token de larga duración

3. Configurar permisos:
   - `pages_read_engagement`
   - `pages_show_list`

### Instagram

1. Crear una aplicación en [Facebook Developers](https://developers.facebook.com/)
2. Configurar Instagram Basic Display o Instagram Graph API
3. Conectar cuenta de Instagram Business o Creator a una página de Facebook
4. Obtener:
   - Username
   - User ID
   - Access Token de larga duración

**Nota**: Solo funciona con cuentas de Instagram Business o Creator.

## Características

### Diarios Online
- ✅ Configuración de selectores CSS personalizados
- ✅ Soporte para APIs REST
- ✅ Frecuencia de sincronización configurable
- ⏳ Sistema de scrapping automático (pendiente)
- ⏳ Detección de nuevas noticias (pendiente)

### Facebook
- ✅ Configuración de credenciales de API
- ✅ Opciones de sincronización (posts/comentarios)
- ✅ Frecuencia configurable
- ⏳ Sincronización automática (pendiente)
- ⏳ Importación de imágenes (pendiente)

### Instagram
- ✅ Configuración de credenciales de API
- ✅ Sincronización de posts
- ✅ Opción para sincronizar stories
- ✅ Frecuencia configurable
- ⏳ Sincronización automática (pendiente)
- ⏳ Descarga de imágenes/videos (pendiente)

## Próximos Pasos

1. **Implementar sistema de scrapping**
   - Librería para extracción de contenido web
   - Parser de HTML con selectores CSS
   - Manejo de errores y timeouts

2. **Implementar sincronización con Facebook**
   - Integración con Graph API
   - Manejo de tokens y renovación
   - Importación de imágenes y metadatos

3. **Implementar sincronización con Instagram**
   - Integración con Instagram Graph API
   - Descarga de medios
   - Procesamiento de stories

4. **Sistema de procesamiento automático**
   - Cron jobs para sincronización periódica
   - Cola de procesamiento
   - Conversión automática a noticias

5. **Panel de monitoreo**
   - Estado de sincronizaciones
   - Logs de errores
   - Estadísticas de contenido

6. **Gestión de contenido sincronizado**
   - Revisar contenido pendiente
   - Aprobar/rechazar publicaciones
   - Editar antes de publicar

## Uso

### Acceder al módulo:

1. Ingresar al panel de administración
2. Hacer clic en "Medios Conectados" en el menú lateral
3. Seleccionar el tipo de medio a configurar
4. Completar el formulario con los datos requeridos
5. Guardar la configuración

### Gestionar medios:

- **Agregar**: Completar el formulario en la columna derecha
- **Editar**: Hacer clic en el botón de edición (lápiz)
- **Eliminar**: Hacer clic en el botón de eliminación (papelera)
- **Activar/Desactivar**: Editar el medio y marcar/desmarcar la casilla "Activo"

## Seguridad

⚠️ **Importante**:
- Los Access Tokens son sensibles, mantenlos seguros
- No compartir credenciales de API
- Renovar tokens periódicamente
- Configurar permisos mínimos necesarios

## Soporte

Para más información o reportar problemas, contactar al equipo de desarrollo.

---

**Versión**: 1.0.0  
**Fecha**: Febrero 2026  
**Estado**: En desarrollo - Interfaz completada, sincronización pendiente
