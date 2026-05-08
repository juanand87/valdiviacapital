-- Actualizar usuario admin con contraseña: admin123
UPDATE usuarios 
SET password = '$2y$10$/zgod0oiGd4C86Hc.Lry7eXqRGm/HqKTd1gLFqCQfYKhm6ShVArKe'
WHERE email = 'admin@losrios.cl';

-- Si no existe, crearlo
INSERT INTO usuarios (nombre, email, password, rol, activo, created_at)
SELECT 'Admin Los Ríos', 'admin@losrios.cl', '$2y$10$/zgod0oiGd4C86Hc.Lry7eXqRGm/HqKTd1gLFqCQfYKhm6ShVArKe', 'admin', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'admin@losrios.cl');
