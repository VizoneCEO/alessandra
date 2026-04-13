<?php
// Script to generate database backup and rotate old files
// Can be run via CLI or requested via browser (protected)

if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 1) {
        die("Acceso Denegado");
    }
}

require 'db_connect.php';

// Configuration
$backupDir = __DIR__ . '/../backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$dbHost = $db_host ?? 'localhost';
$dbUser = $db_user ?? 'root';
$dbPass = $db_pass ?? '';
$dbName = $db_name ?? 'alessandra';

$filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
$filePath = $backupDir . $filename;

// Mysqldump command
// Note: Ensure mysqldump is in PATH or provide full path (e.g., /Applications/XAMPP/xamppfiles/bin/mysqldump)
// On XAMPP Mac it's often: /Applications/XAMPP/xamppfiles/bin/mysqldump
$mysqldump = '/Applications/XAMPP/xamppfiles/bin/mysqldump';
if (!file_exists($mysqldump)) {
    $mysqldump = 'mysqldump'; // Try global path
}

$command = "$mysqldump --user=$dbUser --password=$dbPass --host=$dbHost $dbName > $filePath";

exec($command, $output, $returnVar);

if ($returnVar === 0) {
    if (php_sapi_name() !== 'cli') {
        echo "Respaldo creado exitosamente: $filename<br>";
    } else {
        echo "Backup created: $filename\n";
    }
} else {
    if (php_sapi_name() !== 'cli') {
        echo "Error al crear respaldo. Código: $returnVar<br>";
    } else {
        echo "Error creating backup. Code: $returnVar\n";
    }
}

// --- ROTATION (Delete files older than 15 days) ---
$days = 15;
$files = glob($backupDir . '*.sql');
$now = time();

foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
            unlink($file);
            if (php_sapi_name() !== 'cli') {
                echo "Archivo eliminado por antigüedad: " . basename($file) . "<br>";
            } else {
                echo "Deleted old backup: " . basename($file) . "\n";
            }
        }
    }
}
?>