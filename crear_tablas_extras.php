<?php
require_once 'includes/config.php';
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS reacciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  noticia_id INT NOT NULL,
  tipo ENUM('me_gusta','me_encanta','sorpresa') NOT NULL,
  ip_hash VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_reaction (noticia_id, ip_hash),
  INDEX idx_noticia (noticia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS vistas_diarias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  noticia_id INT NOT NULL,
  fecha DATE NOT NULL,
  vistas INT DEFAULT 1,
  UNIQUE KEY uniq_vista (noticia_id, fecha),
  INDEX idx_noticia (noticia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Tablas creadas OK\n";
