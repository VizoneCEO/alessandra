
-- Insertar Perfil Finanzas
INSERT IGNORE INTO Perfiles (id, nombre_perfil) VALUES (5, 'Finanzas');

-- Insertar Usuario Finanzas
INSERT INTO Usuarios (curp, password_hash, perfil_id, nombre_completo, estado, forma) 
VALUES ('FINANZAS', '$2y$10$kEXxSU4d4KDUpkbjAZBc0e2DEVEN1.HxLvzCMdcIg5wl/FOKEeTYi', 5, 'Usuario Finanzas', 'activo', 'no aplica');
