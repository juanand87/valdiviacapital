-- Usuario de prueba para el panel de administración
-- Email: admin@losrios.cl
-- Password: admin123

INSERT INTO usuarios (nombre, email, password, rol, biografia, activo, created_at)
VALUES (
    'Administrador',
    'admin@losrios.cl',
    '$2y$10$zX5EqYHN7vqE5rKZ.MhJPuLrqHQYzWJGH5VYQr9zXqN9c6PqH/jXS',
    'admin',
    'Administrador principal del sitio Los Ríos',
    1,
    NOW()
);

-- Usuario Editor de prueba
-- Email: editor@losrios.cl  
-- Password: editor123

INSERT INTO usuarios (nombre, email, password, rol, biografia, activo, created_at)
VALUES (
    'Editor Principal',
    'editor@losrios.cl',
    '$2y$10$E8.kQ3PqY8N/WL4j2Q5HFu8vZ9TyL6R4nQ2J8M.3K5pX9mN7wL2Eq',
    'editor',
    'Editor de contenidos del diario Los Ríos',
    1,
    NOW()
);

-- Usuario Periodista de prueba  
-- Email: periodista@losrios.cl
-- Password: periodista123

INSERT INTO usuarios (nombre, email, password, rol, biografia, activo, created_at)
VALUES (
    'María González',
    'periodista@losrios.cl',
    '$2y$10$vT9wK5P2X7L.mQ8R3N6HYu4pZ8TyL6R4nQ2J8M.3K5pX9mN7wL2Eq',
    'periodista',
    'Periodista especializada en noticias regionales',
    1,
    NOW()
);
