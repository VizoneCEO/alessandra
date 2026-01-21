-- Insertar Perfil Seguridad
INSERT IGNORE INTO Perfiles (id, nombre_perfil) VALUES (4, 'seguridad');

-- Insertar Usuario Seguridad (Password: 123456)
INSERT INTO Usuarios (curp, password_hash, perfil_id, nombre_completo, estado, forma) 
VALUES ('SEGURIDAD01', '$2y$10$HI5zZ0WBK0SeQMldop3f9u7YoloiT6uLfgHXP1Vo/9uwWSPlZ1fYK', 4, 'Guardia Seguridad', 'activo', 'no aplica');
