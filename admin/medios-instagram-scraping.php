<?php
$page_title = 'Scraping Instagram';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Crear tablas si no existen
$db->exec("CREATE TABLE IF NOT EXISTS ig_scraping_perfiles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(60) NOT NULL,
    nombre          VARCHAR(140) NULL,
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    ultima_revision DATETIME NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ig_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS ig_scraping_posts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    perfil_id   INT UNSIGNED NOT NULL,
    shortcode   VARCHAR(30) NOT NULL,
    tipo        ENUM('image','video','carousel') NOT NULL DEFAULT 'image',
    url_post    VARCHAR(500) NOT NULL,
    imagen_url  TEXT NULL,
    caption     TEXT NULL,
    likes       INT UNSIGNED NOT NULL DEFAULT 0,
    comentarios INT UNSIGNED NOT NULL DEFAULT 0,
    fecha_post  DATETIME NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ig_shortcode (shortcode),
    INDEX idx_ig_perfil_fecha (perfil_id, fecha_post DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mensaje = '';
$error   = '';

// ── POST: agregar perfil ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar') {
    $username = trim(ltrim($_POST['username'] ?? '', '@'));
    $nombre   = trim($_POST['nombre'] ?? '');
    $username = preg_replace('/[^a-zA-Z0-9._]/', '', $username);

    if ($username === '') {
        $error = 'El nombre de usuario de Instagram es obligatorio.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO ig_scraping_perfiles (username, nombre) VALUES (?, ?)");
            $stmt->execute([$username, $nombre ?: null]);
            $mensaje = "Perfil @{$username} agregado correctamente.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "El perfil @{$username} ya está registrado.";
            } else {
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

// ── POST: eliminar perfil ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $db->prepare("DELETE FROM ig_scraping_posts WHERE perfil_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM ig_scraping_perfiles WHERE id = ?")->execute([$id]);
        $mensaje = 'Perfil eliminado.';
    }
}

// ── POST: toggle activo ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $db->prepare("UPDATE ig_scraping_perfiles SET activo = 1 - activo WHERE id = ?")->execute([$id]);
    }
}

// ── Cargar perfiles ───────────────────────────────────────────────────────────
$perfiles = $db->query("SELECT p.*,
    (SELECT COUNT(*) FROM ig_scraping_posts WHERE perfil_id = p.id) AS total_posts
    FROM ig_scraping_perfiles p
    ORDER BY p.created_at DESC")->fetchAll();
?>

<div class="page-header">
    <h1><i class="fab fa-instagram" style="color:#e4405f;"></i> Scraping Instagram</h1>
    <p>Lee las últimas 2 publicaciones de perfiles públicos de Instagram (sin suscripción requerida).</p>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="content-grid">

    <!-- ── Agregar perfil ──────────────────────────────────────────── -->
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-plus"></i> Agregar perfil</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="form-group">
                        <label>Usuario de Instagram</label>
                        <input type="text" name="username" placeholder="@ejemplo" class="form-control" required>
                        <small>Solo perfiles públicos. Puedes incluir o no el @.</small>
                    </div>
                    <div class="form-group">
                        <label>Nombre o descripción <em>(opcional)</em></label>
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
                    El sistema accede a la página pública del perfil de Instagram y extrae
                    las <strong>últimas 2 publicaciones</strong> (imagen, descripción y fecha).
                    Solo funciona con perfiles <strong>públicos</strong> que no requieren
                    iniciar sesión para ver su contenido.
                </p>
                <p style="font-size:13px;color:#888;margin-top:10px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Instagram puede limitar accesos frecuentes. Se recomienda
                    no analizar más de 1 vez cada 30 minutos por perfil.
                </p>
            </div>
        </div>
    </div>

    <!-- ── Listado de perfiles ─────────────────────────────────────── -->
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h2>Perfiles configurados</h2>
                <span><?php echo count($perfiles); ?> perfil(es)</span>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!$perfiles): ?>
                    <p style="padding:20px;color:#888;">No hay perfiles configurados aún.</p>
                <?php else: ?>
                    <table class="data-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Perfil</th>
                                <th>Última revisión</th>
                                <th>Posts</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($perfiles as $p): ?>
                            <tr id="row-<?php echo $p['id']; ?>">
                                <td>
                                    <strong>@<?php echo htmlspecialchars($p['username']); ?></strong>
                                    <?php if ($p['nombre']): ?>
                                        <div style="font-size:12px;color:#888;"><?php echo htmlspecialchars($p['nombre']); ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="https://www.instagram.com/<?php echo urlencode($p['username']); ?>/" target="_blank" style="font-size:11px;color:#e4405f;">
                                            <i class="fab fa-instagram"></i> Ver perfil
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <?php echo $p['ultima_revision']
                                        ? date('d-m-Y H:i', strtotime($p['ultima_revision']))
                                        : '<em style="color:#aaa;">Nunca</em>'; ?>
                                </td>
                                <td><?php echo (int)$p['total_posts']; ?></td>
                                <td>
                                    <span class="badge <?php echo $p['activo'] ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo $p['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <button class="btn btn-sm btn-primary btn-analizar"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($p['username']); ?>">
                                            <i class="fas fa-sync-alt"></i> Analizar
                                        </button>
                                        <button class="btn btn-sm btn-secondary btn-ver-posts"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($p['username']); ?>">
                                            <i class="fas fa-images"></i> Ver posts
                                        </button>
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('¿Eliminar este perfil y sus posts guardados?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <!-- Posts embebidos -->
                            <tr id="posts-row-<?php echo $p['id']; ?>" style="display:none;">
                                <td colspan="5">
                                    <div id="posts-container-<?php echo $p['id']; ?>" style="padding:10px 20px 20px;">
                                        <em class="cargando-posts">Cargando...</em>
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

<!-- ── Modal de resultado ─────────────────────────────────────────────────── -->
<div id="modal-resultado" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;padding:30px;max-width:700px;width:95%;max-height:85vh;overflow-y:auto;position:relative;">
        <button onclick="document.getElementById('modal-resultado').style.display='none';"
            style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>
        <h3 id="modal-titulo" style="margin-bottom:20px;"></h3>
        <div id="modal-contenido"></div>
    </div>
</div>

<style>
.btn-block { width: 100%; }
.badge { padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-secondary { background: #e2e3e5; color: #383d41; }
.ig-post-card {
    display: flex;
    gap: 16px;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    background: #fafafa;
}
.ig-post-card img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 6px;
    flex-shrink: 0;
}
.ig-post-card .ig-post-info { flex: 1; min-width: 0; }
.ig-post-card .ig-post-caption {
    font-size: 13px;
    color: #333;
    line-height: 1.5;
    max-height: 80px;
    overflow: hidden;
    margin-bottom: 8px;
}
.ig-post-meta { font-size: 12px; color: #888; }
.ig-post-meta span { margin-right: 12px; }
.spinner { display:inline-block;width:16px;height:16px;border:2px solid #ccc;border-top-color:#e4405f;border-radius:50%;animation:spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
$(function () {

    // ── Analizar ──────────────────────────────────────────────────────────────
    $('.btn-analizar').on('click', function () {
        var $btn = $(this);
        var id   = $btn.data('id');
        var user = $btn.data('username');
        var orig = $btn.html();

        $btn.html('<span class="spinner"></span> Analizando...').prop('disabled', true);

        $.post('ajax/scraping-ig-analizar.php', { perfil_id: id }, function (res) {
            $btn.html(orig).prop('disabled', false);

            var html = '';
            if (res.error) {
                html = '<div class="alert alert-error">' + res.error + '</div>';
            } else if (!res.posts || res.posts.length === 0) {
                html = '<div class="alert alert-warning">No se encontraron publicaciones.</div>';
            } else {
                if (res.fuente) {
                    html += '<p style="font-size:12px;color:#888;margin-bottom:12px;"><i class="fas fa-info-circle"></i> Datos obtenidos vía <strong>' + res.fuente + '</strong></p>';
                }
                res.posts.forEach(function (p) {
                    var img = p.imagen_url
                        ? '<img src="' + p.imagen_url + '" alt="post" onerror="this.style.display=\'none\'">'
                        : '<div style="width:120px;height:120px;background:#eee;border-radius:6px;display:flex;align-items:center;justify-content:center;"><i class=\'fab fa-instagram\' style=\'font-size:40px;color:#e4405f;\'></i></div>';
                    var caption = p.caption ? p.caption.substring(0, 200) + (p.caption.length > 200 ? '…' : '') : '<em>Sin descripción</em>';
                    html += '<div class="ig-post-card">'
                        + img
                        + '<div class="ig-post-info">'
                        + '<div class="ig-post-caption">' + escHtml(caption) + '</div>'
                        + '<div class="ig-post-meta">'
                        + (p.fecha_post ? '<span><i class="fas fa-calendar"></i> ' + p.fecha_post + '</span>' : '')
                        + (p.likes > 0 ? '<span><i class="fas fa-heart"></i> ' + p.likes + '</span>' : '')
                        + '</div>'
                        + '<a href="' + p.url_post + '" target="_blank" style="font-size:12px;color:#e4405f;"><i class="fab fa-instagram"></i> Ver en Instagram</a>'
                        + '</div>'
                        + '</div>';
                });
            }

            // Actualizar "Última revisión" en la fila
            $('#row-' + id + ' td:nth-child(2)').html(res.fecha_revision || '');

            // Mostrar modal
            $('#modal-titulo').text('Últimas publicaciones de @' + user);
            $('#modal-contenido').html(html);
            $('#modal-resultado').css('display', 'flex');

        }, 'json').fail(function () {
            $btn.html(orig).prop('disabled', false);
            alert('Error de comunicación con el servidor.');
        });
    });

    // ── Ver posts guardados ───────────────────────────────────────────────────
    $('.btn-ver-posts').on('click', function () {
        var id   = $(this).data('id');
        var user = $(this).data('username');
        var $row = $('#posts-row-' + id);

        if ($row.is(':visible')) {
            $row.hide();
            return;
        }

        var $cont = $('#posts-container-' + id);
        $cont.html('<em class="cargando-posts">Cargando...</em>');
        $row.show();

        $.get('ajax/scraping-ig-posts.php', { perfil_id: id }, function (res) {
            if (!res.posts || res.posts.length === 0) {
                $cont.html('<p style="color:#888;"><em>Sin posts guardados. Usa "Analizar" primero.</em></p>');
                return;
            }
            var html = '';
            res.posts.forEach(function (p) {
                var img = p.imagen_url
                    ? '<img src="' + p.imagen_url + '" alt="post" onerror="this.style.display=\'none\'">'
                    : '<div style="width:120px;height:120px;background:#eee;border-radius:6px;display:flex;align-items:center;justify-content:center;"><i class=\'fab fa-instagram\' style=\'font-size:40px;color:#e4405f;\'></i></div>';
                var caption = p.caption ? p.caption.substring(0, 200) + (p.caption.length > 200 ? '…' : '') : '<em>Sin descripción</em>';
                html += '<div class="ig-post-card">'
                    + img
                    + '<div class="ig-post-info">'
                    + '<div class="ig-post-caption">' + escHtml(caption) + '</div>'
                    + '<div class="ig-post-meta">'
                    + (p.fecha_post ? '<span><i class="fas fa-calendar"></i> ' + p.fecha_post + '</span>' : '')
                    + (p.likes > 0 ? '<span><i class="fas fa-heart"></i> ' + p.likes + '</span>' : '')
                    + '</div>'
                    + '<a href="' + p.url_post + '" target="_blank" style="font-size:12px;color:#e4405f;"><i class="fab fa-instagram"></i> Ver en Instagram</a>'
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
