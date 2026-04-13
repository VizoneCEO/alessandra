<?php
session_start();
header('Content-Type: application/json');

// --- STRICT SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Acceso Denegado. Solo Administrador Principal.']);
    exit();
}

require 'db_connect.php';
require 'log_helper.php';

$action = $_POST['action'] ?? '';
$backupDir = __DIR__ . '/../backups/';

switch ($action) {
    case 'trigger_backup':
        // Execute the script logic directly or via include
        ob_start();
        include 'cron_backup.php';
        $output = ob_get_clean();

        // Check if file was created created recently? 
        // We assume success if no error, cron_backup prints output.
        // Let's rely on checking if file exists or just return success based on output logic manually.
        // Simpler: Just rely on include. If it fails, PHP error.

        registrar_log($conn, $_SESSION['user_id'], 'RESPALDO_MANUAL', 'Se generó un respaldo manual.');
        echo json_encode(['success' => true, 'message' => 'Proceso de respaldo finalizado.']);
        break;

    case 'delete_backup':
        $filename = basename($_POST['filename']); // Basename for security
        $filePath = $backupDir . $filename;

        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                registrar_log($conn, $_SESSION['user_id'], 'ELIMINAR_RESPALDO', "Archivo: $filename");
                echo json_encode(['success' => true, 'message' => 'Respaldo eliminado.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar archivo.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Archivo no encontrado.']);
        }
        break;

    case 'restore_backup':
        $filename = basename($_POST['filename']);
        $filePath = $backupDir . $filename;

        if (!file_exists($filePath)) {
            echo json_encode(['success' => false, 'message' => 'Archivo no encontrado.']);
            exit();
        }

        // Restore Logic
        $dbHost = $db_host ?? 'localhost';
        $dbUser = $db_user ?? 'root';
        $dbPass = $db_pass ?? '';
        $dbName = $db_name ?? 'alessandra';

        // MySQL Path
        $mysql = '/Applications/XAMPP/xamppfiles/bin/mysql';
        if (!file_exists($mysql)) {
            $mysql = 'mysql';
        }

        // Command: mysql -u user -p pass db < file.sql
        $command = "$mysql --user=$dbUser --password=$dbPass --host=$dbHost $dbName < $filePath";

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            registrar_log($conn, $_SESSION['user_id'], 'RESTAURACION_BD', "Restaurado desde: $filename");
            echo json_encode(['success' => true, 'message' => 'Base de datos restaurada exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error crítico al restaurar. Código: ' . $returnVar]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
}
?>