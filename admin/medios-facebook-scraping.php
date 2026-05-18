<?php
$page_title = 'Scraping Facebook';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Crear tablas si no existen
$db->exec("CREATE TABLE IF NOT EXISTS fb_scraping_paginas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_url        VARCHAR(300) NOT NULL,
    nombre          VARCHAR(140) NULL,
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    ultima_revision DATETIME NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fb_page_url (page_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS fb_scraping_posts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pagina_id   INT UNSIGNED NOT NULL,
    post_id     VARCHAR(60) NOT NULL,
    url_post    VARCHAR(600) NOT NULL,
    imagen_url  TEXT NULL,
    texto       TEXT NULL,
    fecha_post  DATETIME NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fb_post_id (post_id),
    INDEX idx_fb_pagina_fecha (pagina_id, fecha_post DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mensaje = '';
$error   = '';

// ── POST: agregar página ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar') {
    $pageUrl = trim($_POST['page_url'] ?? '');
    $nombre  = trim($_POST['nombre'] ?? '');

    // Normalizar URL: aceptar sólo la parte de la página
    if ($pageUrl !== '') {
        // Si pegan URL completa, extraer sólo el path de la página
        $pageUrl = rtrim($pageUrl, '/');
        if (preg_match('#facebook\.com/([^/?]+)#i', $pageUrl, $m)) {
            $pageUrl = 'https://www.facebook.com/' . $m[1];
        } elseif (!preg_match('#^https?://#i', $pageUrl)) {
            $pageUrl = 'https://www.facebook.com/' . ltrim($pageUrl, '/');
        }
    }

    if ($pageUrl === '') {
        $error = 'La URL de la página de Facebook es obligatoria.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO fb_scraping_paginas (page_url, nombre) VALUES (?, ?)");
            $stmt->execute([$pageUrl, $nombre ?: null]);
            $mensaje = "Página agregada correctamente.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Esa URL de página ya está registrada.';
            } else {
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

// ── POST: eliminar ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $db->prepare("DELETE FROM fb_scraping_posts WHERE pagina_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM fb_scraping_paginas WHERE id = ?")->execute([$id]);
        $mensaje = 'Página eliminada.';
    }
}

// ── Cargar páginas ────────────────────────────────────────────────────────────
$paginas = $db->query("SELECT p.*,
    (SELECT COUNT(*) FROM fb_scraping_posts WHERE pagina_id = p.id) AS total_posts
    FROM fb_scraping_paginas p
    ORDER BY p.created_at DESC")->fetchAll();
?>

<div class="page-header">
    <h1><i class="fab fa-facebook" style="color:#1877f2;"></i> Scraping Facebook</h1>
    <p>Lee las últimas publicaciones de páginas públicas de Facebook, sin necesidad de API ni token.</p>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="content-grid">

    <!-- ── Agregar página ──────────────────────────────────────────── -->
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-plus"></i> Agregar página</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="form-group">
                        <label>URL o nombre de la página</label>
                        <input type="text" name="page_url" placeholder="https://www.facebook.com/nombrePagina" class="form-control" required>
                        <small>Pega la URL completa o sólo el nombre de usuario de la página.</small>
                    </div>
                    <div class="form-group">
                        <label>Descripción <em>(opcional)</em></label>
                        <input type="text" name="nombre" placeholder="Ej: Municipalidad de Valdivia" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-plus"></i> Agregar
                    </button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Cómo funciona</h2>
            </div>
            <div class="card-body">
                <p style="font-size:13px;color:#555;line-height:1.6;">
                    El sistema accede a la versión <strong>móvil pública</strong> de Facebook
                    (<code>m.facebook.com</code>) donde el contenido de páginas públicas
                    es accesible sin iniciar sesión, y extrae las
                    <strong>últimas 2 publicaciones</strong> (texto e imagen si tiene).
                </p>
                <p style="font-size:13px;color:#888;margin-top:10px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Solo funciona con páginas <strong>públicas</strong>. 
                    Páginas con restricción de edad o privadas no serán accesibles.
                </p>
            </div>
        </div>
    </div>

    <!-- ── Listado ─────────────────────────────────────────────────── -->
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h2>Páginas configuradas</h2>
                <span><?php echo count($paginas); ?> página(s)</span>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!$paginas): ?>
                    <p style="padding:20px;color:#888;">No hay páginas configuradas aún.</p>
                <?php else: ?>
                    <table class="data-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Página</th>
                                <th>Última revisión</th>
                                <th>Posts</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paginas as $p): ?>
                            <tr id="row-<?php echo $p['id']; ?>">
                                <td>
                                    <?php if ($p['nombre']): ?>
                                        <strong><?php echo htmlspecialchars($p['nombre']); ?></strong><br>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars($p['page_url']); ?>" target="_blank" style="font-size:12px;color:#1877f2;">
                                        <i class="fab fa-facebook"></i> <?php echo htmlspecialchars($p['page_url']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo $p['ultima_revision']
                                        ? date('d-m-Y H:i', strtotime($p['ultima_revision']))
                                        : '<em style="color:#aaa;">Nunca</em>'; ?>
                                </td>
                                <td><?php echo (int)$p['total_posts']; ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <button class="btn btn-sm btn-primary btn-analizar"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($p['nombre'] ?: $p['page_url']); ?>">
                                            <i class="fas fa-sync-alt"></i> Analizar
                                        </button>
                                        <button class="btn btn-sm btn-secondary btn-ver-posts"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($p['nombre'] ?: $p['page_url']); ?>">
                                            <i class="fas fa-list"></i> Ver posts
                                        </button>
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('¿Eliminar esta página y sus posts guardados?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr id="posts-row-<?php echo $p['id']; ?>" style="display:none;">
                                <td colspan="4">
                                    <div id="posts-container-<?php echo $p['id']; ?>" style="padding:10px 20px 20px;">
                                        <em>Cargando...</em>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal resultado ────────────────────────────────────────────────────── -->
<div id="modal-resultado" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;padding:30px;max-width:700px;width:95%;max-height:85vh;overflow-y:auto;position:relative;">
        <button onclick="document.getElementById('modal-resultado').style.display='none';"
            style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>
        <h3 id="modal-titulo" style="margin-bottom:20px;"></h3>
        <div id="modal-contenido"></div>
    </div>
</div>

<style>
.btn-block { width:100%; }
.fb-post-card {
    display: flex;
    gap: 16px;
    border: 1px solid #e4e6eb;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    background: #f8f9fa;
}
.fb-post-card img {
    width: 130px;
    height: 100px;
    object-fit: cover;
    border-radius: 6px;
    flex-shrink: 0;
}
.fb-post-card .fb-no-img {
    width: 130px;
    height: 100px;
    background: #e4e6eb;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #1877f2;
    font-size: 36px;
}
.fb-post-info { flex:1; min-width:0; }
.fb-post-texto {
    font-size: 13px;
    color: #333;
    line-height: 1.5;
    max-height: 80px;
    overflow: hidden;
    margin-bottom: 8px;
}
.fb-post-meta { font-size:12px; color:#888; }
.spinner { display:inline-block;width:16px;height:16px;border:2px solid #ccc;border-top-color:#1877f2;border-radius:50%;animation:spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
$(function () {

    // ── Analizar ──────────────────────────────────────────────────────────────
    $('.btn-analizar').on('click', function () {
        var $btn   = $(this);
        var id     = $btn.data('id');
        var nombre = $btn.data('nombre');
        var orig   = $btn.html();

        $btn.html('<span class="spinner"></span> Analizando...').prop('disabled', true);

        $.post('ajax/scraping-fb-analizar.php', { pagina_id: id }, function (res) {
            $btn.html(orig).prop('disabled', false);

            if (res.error) {
                $('#modal-titulo').text('Error');
                $('#modal-contenido').html('<div class="alert alert-error">' + escHtml(res.error) + '</div>');
                $('#modal-resultado').css('display', 'flex');
                return;
            }

            var html = '';
            if (!res.posts || res.posts.length === 0) {
                html = '<div class="alert alert-warning">No se encontraron publicaciones.</div>';
            } else {
                res.posts.forEach(function (p) {
                    var imgHtml = p.imagen_url
                        ? '<img src="' + p.imagen_url + '" alt="post" onerror="this.parentNode.innerHTML=\'<div class=fb-no-img><i class=fab fa-facebook></i></div>\'">'
                        : '<div class="fb-no-img"><i class="fab fa-facebook"></i></div>';
                    var texto = p.texto ? p.texto.substring(0, 280) + (p.texto.length > 280 ? '…' : '') : '<em>Sin texto</em>';
                    html += '<div class="fb-post-card">'
                        + imgHtml
                        + '<div class="fb-post-info">'
                        + '<div class="fb-post-texto">' + escHtml(texto) + '</div>'
                        + '<div class="fb-post-meta">'
                        + (p.fecha_post ? '<span><i class="fas fa-calendar"></i> ' + p.fecha_post + '</span> ' : '')
                        + '</div>'
                        + '<a href="' + p.url_post + '" target="_blank" style="font-size:12px;color:#1877f2;"><i class="fab fa-facebook"></i> Ver publicación</a>'
                        + '</div>'
                        + '</div>';
                });
            }

            $('#row-' + id + ' td:nth-child(2)').html(res.fecha_revision || '');
            $('#modal-titulo').text('Últimas publicaciones: ' + nombre);
            $('#modal-contenido').html(html);
            $('#modal-resultado').css('display', 'flex');

        }, 'json').fail(function (xhr) {
            $btn.html(orig).prop('disabled', false);
            var msg = 'Error de comunicación con el servidor.';
            try { var r = JSON.parse(xhr.responseText); if (r.error) msg = r.error; } catch(e) {}
            alert(msg);
        });
    });

    // ── Ver posts guardados ───────────────────────────────────────────────────
    $('.btn-ver-posts').on('click', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        var $row   = $('#posts-row-' + id);

        if ($row.is(':visible')) { $row.hide(); return; }

        var $cont = $('#posts-container-' + id);
        $cont.html('<em>Cargando...</em>');
        $row.show();

        $.get('ajax/scraping-fb-posts.php', { pagina_id: id }, function (res) {
            if (!res.posts || res.posts.length === 0) {
                $cont.html('<p style="color:#888;"><em>Sin posts guardados. Usa "Analizar" primero.</em></p>');
                return;
            }
            var html = '';
            res.posts.forEach(function (p) {
                var imgHtml = p.imagen_url
                    ? '<img src="' + p.imagen_url + '" alt="post" onerror="this.parentNode.innerHTML=\'<div class=fb-no-img><i class=fab fa-facebook></i></div>\'">'
                    : '<div class="fb-no-img"><i class="fab fa-facebook"></i></div>';
                var texto = p.texto ? p.texto.substring(0, 280) + (p.texto.length > 280 ? '…' : '') : '<em>Sin texto</em>';
                html += '<div class="fb-post-card">'
                    + imgHtml
                    + '<div class="fb-post-info">'
                    + '<div class="fb-post-texto">' + escHtml(texto) + '</div>'
                    + '<div class="fb-post-meta">'
                    + (p.fecha_post ? '<span><i class="fas fa-calendar"></i> ' + p.fecha_post + '</span>' : '')
                    + '</div>'
                    + '<a href="' + p.url_post + '" target="_blank" style="font-size:12px;color:#1877f2;"><i class="fab fa-facebook"></i> Ver publicación</a>'
                    + '</div>'
                    + '</div>';
            });
            $cont.html(html);
        }, 'json').fail(function () {
            $cont.html('<p style="color:red;">Error al cargar posts.</p>');
        });
    });

    function escHtml(str) {
        return $('<div>').text(str).html();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
