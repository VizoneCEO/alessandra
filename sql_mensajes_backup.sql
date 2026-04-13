-- Tabla Mensajes
CREATE TABLE IF NOT EXISTS `Mensajes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `remitente_id` INT NOT NULL,
    `destinatario_id` INT NULL COMMENT 'NULL for global messages if needed, currently intended for specific users',
    `asunto` VARCHAR(255) NOT NULL,
    `cuerpo` TEXT NOT NULL,
    `leido` TINYINT(1) DEFAULT 0,
    `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    FOREIGN KEY (`remitente_id`) REFERENCES `Usuarios`(`id`),
    FOREIGN KEY (`destinatario_id`) REFERENCES `Usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nota: Esta tabla fue utilizada por el módulo de mensajería eliminado.
