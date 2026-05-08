<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Base de Datos - Diario Los Ríos</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f3f4f6;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2563eb;
            margin-bottom: 10px;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        .warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        .info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #2563eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 5px;
            font-weight: 600;
        }
        .btn:hover {
            background: #1e40af;
        }
        .btn-secondary {
            background: #6b7280;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #dc2626;
        }
        pre {
            background: #1f2937;
            color: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
        }
        .step {
            background: #f9fafb;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
        }
        .step h3 {
            margin-top: 0;
            color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificación de Base de Datos</h1>
        <p>Diagnóstico de la estructura de la base de datos</p>

        <?php
        require_once 'includes/config.php';

        try {
            $db = getDB();
            echo '<div class="status success">✅ Conexión exitosa a la base de datos: <strong>' . DB_NAME . '</strong></div>';

            // Verificar tabla categorias
            echo '<h2>📋 Tabla: categorias</h2>';
            $stmt = $db->query("DESCRIBE categorias");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<table>';
            echo '<tr><th>Columna</th><th>Tipo</th><th>Null</th><th>Default</th></tr>';
            foreach ($columns as $col) {
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($col['Field']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            // Verificar si existen las columnas necesarias
            $column_names = array_column($columns, 'Field');
            $required_columns = ['id', 'nombre', 'slug', 'descripcion', 'color', 'icono', 'orden', 'activo'];
            $missing_columns = array_diff($required_columns, $column_names);

            if (empty($missing_columns)) {
                echo '<div class="status success">✅ Todas las columnas necesarias están presentes</div>';
                echo '<div class="info"><strong>¡Todo está bien!</strong> Puedes usar el sitio normalmente.</div>';
                echo '<a href="index.php" class="btn">Ir al Sitio</a>';
            } else {
                echo '<div class="status error">❌ Faltan las siguientes columnas: <strong>' . implode(', ', $missing_columns) . '</strong></div>';
                
                echo '<div class="step">';
                echo '<h3>🔧 Solución: Actualizar la Base de Datos</h3>';
                echo '<p>Sigue estos pasos para agregar las columnas faltantes:</p>';
                echo '<ol>';
                echo '<li>Abre <strong>phpMyAdmin</strong>: <a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a></li>';
                echo '<li>Selecciona la base de datos <code>losrios</code></li>';
                echo '<li>Ve a la pestaña <strong>"SQL"</strong></li>';
                echo '<li>Copia y pega el siguiente código:</li>';
                echo '</ol>';
                echo '<pre>';
                echo 'ALTER TABLE categorias ADD COLUMN color VARCHAR(7) DEFAULT \'#2563eb\' AFTER descripcion;
ALTER TABLE categorias ADD COLUMN icono VARCHAR(50) AFTER color;
ALTER TABLE categorias ADD COLUMN orden INT DEFAULT 0 AFTER icono;';
                echo '</pre>';
                echo '<p>O importa el archivo <code>actualizar_db.sql</code></p>';
                echo '</div>';
            }

            // Verificar tabla noticias
            echo '<h2>📋 Tabla: noticias</h2>';
            $stmt = $db->query("SELECT COUNT(*) as total FROM noticias");
            $result = $stmt->fetch();
            echo '<div class="info">Total de noticias: <strong>' . $result['total'] . '</strong></div>';

            // Verificar categorías
            echo '<h2>📋 Categorías Existentes</h2>';
            $stmt = $db->query("SELECT * FROM categorias ORDER BY orden, nombre");
            $categorias = $stmt->fetchAll();
            
            if (!empty($categorias)) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Nombre</th><th>Slug</th>';
                if (in_array('color', $column_names)) {
                    echo '<th>Color</th>';
                }
                if (in_array('icono', $column_names)) {
                    echo '<th>Icono</th>';
                }
                echo '</tr>';
                
                foreach ($categorias as $cat) {
                    echo '<tr>';
                    echo '<td>' . $cat['id'] . '</td>';
                    echo '<td><strong>' . htmlspecialchars($cat['nombre']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($cat['slug']) . '</td>';
                    if (in_array('color', $column_names)) {
                        echo '<td><span style="display:inline-block;width:20px;height:20px;background:' . htmlspecialchars($cat['color'] ?? '#ccc') . ';border-radius:4px;"></span> ' . htmlspecialchars($cat['color'] ?? 'N/A') . '</td>';
                    }
                    if (in_array('icono', $column_names)) {
                        echo '<td>' . htmlspecialchars($cat['icono'] ?? 'N/A') . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="warning">⚠️ No hay categorías en la base de datos</div>';
            }

        } catch (PDOException $e) {
            echo '<div class="status error">❌ Error de conexión: ' . htmlspecialchars($e->getMessage()) . '</div>';
            
            echo '<div class="step">';
            echo '<h3>🔧 Soluciones posibles:</h3>';
            echo '<ul>';
            echo '<li><strong>La base de datos no existe:</strong> Crea la base de datos <code>losrios</code> en phpMyAdmin</li>';
            echo '<li><strong>Credenciales incorrectas:</strong> Verifica <code>includes/config.php</code></li>';
            echo '<li><strong>MySQL no está iniciado:</strong> Inicia MySQL desde el panel de XAMPP</li>';
            echo '</ul>';
            echo '</div>';
        }
        ?>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;">
        
        <div class="step">
            <h3>📚 Opciones de Configuración</h3>
            <p><strong>Opción 1: Actualizar estructura (mantiene datos)</strong></p>
            <p>Importa <code>actualizar_db.sql</code> en phpMyAdmin para agregar columnas faltantes</p>
            
            <p><strong>Opción 2: Instalación limpia (borra datos)</strong></p>
            <p>Importa <code>database.sql</code> para crear todo desde cero con datos de ejemplo</p>
        </div>

        <div style="margin-top: 30px;">
            <a href="index.php" class="btn">← Volver al Sitio</a>
            <a href="http://localhost/phpmyadmin" target="_blank" class="btn btn-secondary">Abrir phpMyAdmin</a>
        </div>
    </div>
</body>
</html>
