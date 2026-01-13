-- Create Events Table
CREATE TABLE IF NOT EXISTS finanzas_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    fecha DATE,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Tickets Table
CREATE TABLE IF NOT EXISTS finanzas_boletos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    alumno_id INT NOT NULL,
    cargo_id INT NOT NULL,
    pago_id INT DEFAULT NULL,
    folio_asiento INT NOT NULL,
    fecha_emision DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES finanzas_eventos(id),
    FOREIGN KEY (alumno_id) REFERENCES Usuarios(id),
    FOREIGN KEY (cargo_id) REFERENCES finanzas_cargos(id)
    -- We can add FOREIGN KEY for pago_id if finanzas_pagos(id) matches
);

-- Add Reference Columns to Charges
-- Using a safe procedure to check if column exists before adding (or just try/catch in PHP, but SQL script is cleaner to just run)
-- Since MySQL syntax for IF NOT EXISTS on ADD COLUMN depends on version, we'll just run it. If it fails it might be because column exists.
-- But given user environment, simpler is often better. Let's assume they don't exist yet.

ALTER TABLE finanzas_cargos ADD COLUMN IF NOT EXISTS evento_id INT DEFAULT NULL;
ALTER TABLE finanzas_cargos ADD COLUMN IF NOT EXISTS cantidad_boletos INT DEFAULT 1;
