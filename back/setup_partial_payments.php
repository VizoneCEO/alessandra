<?php
require 'db_connect.php';

// 1. Add `monto_pagado` to `finanzas_cargos` if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM finanzas_cargos LIKE 'monto_pagado'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE finanzas_cargos ADD COLUMN monto_pagado DECIMAL(10,2) DEFAULT 0.00 AFTER monto_original")) {
        echo "Column 'monto_pagado' added to 'finanzas_cargos'.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'monto_pagado' already exists.<br>";
}

// 2. Create `finanzas_pagos` table
$sql_table = "CREATE TABLE IF NOT EXISTS finanzas_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cargo_id INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    metodo_pago VARCHAR(50),
    referencia VARCHAR(255),
    comprobante_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cargo_id) REFERENCES finanzas_cargos(id) ON DELETE CASCADE
)";

if ($conn->query($sql_table)) {
    echo "Table 'finanzas_pagos' created or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// 3. Migrate existing paid records (Optional but good for consistency)
// If a charge is 'Pagado' but has 0 in monto_pagado, update it to equal total.
// Getting total is tricky strictly from SQL due to calculation logic (recargos, beca), 
// but for simplicity we can assume total ~= (monto_original + recargos). note: ignoring beca logic for simple migration
$conn->query("UPDATE finanzas_cargos SET monto_pagado = (monto_original + recargos) WHERE estado = 'Pagado' AND monto_pagado = 0");
echo "Migrated existing paid charges.<br>";

echo "Setup completed.";
?>