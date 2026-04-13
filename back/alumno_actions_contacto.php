<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. Verificar sesión
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 3) {
    echo json_encode(["success" => false, "message" => "Acceso no autorizado."]);
    exit();
}

require_once 'db_connect.php';

$usuario_id = $_SESSION['user_id'];
$tab = isset($_POST['tab']) ? $_POST['tab'] : '';

if (empty($tab)) {
    echo json_encode(["success" => false, "message" => "Ocurrió un error: No se especificó la sección."]);
    exit();
}

// Función auxiliar para obtener y limpiar datos por POST
function get_post($key, $default = null) {
    global $conn;
    if (isset($_POST[$key])) {
        return $conn->real_escape_string(trim($_POST[$key]));
    }
    return $default;
}

try {
    switch ($tab) {
        case 'tab1':
            // Traer el curp real de usuarios
            $sql_curp = "SELECT CURP FROM Usuarios WHERE id = ?";
            $st_curp = $conn->prepare($sql_curp);
            $st_curp->bind_param("i", $usuario_id);
            $st_curp->execute();
            $st_curp->bind_result($curp_db);
            $curp = $st_curp->fetch() ? $curp_db : '';
            $st_curp->close();

            $fecha_nacimiento = get_post('fecha_nacimiento') ? : null; // Para manejo de fechas vacías
            $genero = get_post('genero');
            $escolaridad = get_post('escolaridad');
            $ocupacion = get_post('ocupacion');

            $sql = "INSERT INTO Contacto_Alumnos (usuario_id, curp, fecha_nacimiento, genero, escolaridad, ocupacion) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    curp=VALUES(curp), fecha_nacimiento=VALUES(fecha_nacimiento), genero=VALUES(genero), escolaridad=VALUES(escolaridad), ocupacion=VALUES(ocupacion)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssss", $usuario_id, $curp, $fecha_nacimiento, $genero, $escolaridad, $ocupacion);
            break;

        case 'tab2':
            $calle_numero = get_post('calle_numero');
            $colonia = get_post('colonia');
            $codigo_postal = get_post('codigo_postal');
            $municipio_alcaldia = get_post('municipio_alcaldia');
            $estado_republica = get_post('estado_republica');

            $sql = "INSERT INTO Contacto_Alumnos (usuario_id, calle_numero, colonia, codigo_postal, municipio_alcaldia, estado_republica) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    calle_numero=VALUES(calle_numero), colonia=VALUES(colonia), codigo_postal=VALUES(codigo_postal), municipio_alcaldia=VALUES(municipio_alcaldia), estado_republica=VALUES(estado_republica)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssss", $usuario_id, $calle_numero, $colonia, $codigo_postal, $municipio_alcaldia, $estado_republica);
            break;

        case 'tab3':
            $telefono_celular = get_post('telefono_celular');
            $telefono_fijo = get_post('telefono_fijo');
            $facebook = get_post('facebook');
            $instagram = get_post('instagram');
            $tiktok = get_post('tiktok');

            $sql = "INSERT INTO Contacto_Alumnos (usuario_id, telefono_celular, telefono_fijo, facebook, instagram, tiktok) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    telefono_celular=VALUES(telefono_celular), telefono_fijo=VALUES(telefono_fijo), facebook=VALUES(facebook), instagram=VALUES(instagram), tiktok=VALUES(tiktok)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssss", $usuario_id, $telefono_celular, $telefono_fijo, $facebook, $instagram, $tiktok);
            break;

        case 'tab4':
            $tipo_sangre = get_post('tipo_sangre');
            $padece_enfermedad = isset($_POST['padece_enfermedad']) && $_POST['padece_enfermedad'] == '1' ? 1 : 0;
            $enfermedad_descripcion = $padece_enfermedad ? get_post('enfermedad_descripcion') : null;
            $enfermedad_sintomas = $padece_enfermedad ? get_post('enfermedad_sintomas') : null;
            
            $requiere_medicamentos = isset($_POST['requiere_medicamentos']) && $_POST['requiere_medicamentos'] == '1' ? 1 : 0;
            $medicamentos_descripcion = $requiere_medicamentos ? get_post('medicamentos_descripcion') : null;
            
            // Tratamos alergias con un toggle también (si envian toggle 1, se guarda descripcion, si no nulo)
            $tiene_alergias = isset($_POST['tiene_alergias']) && $_POST['tiene_alergias'] == '1' ? 1 : 0;
            $alergias_medicamentos = $tiene_alergias ? get_post('alergias_medicamentos') : null;
            
            $situacion_personal_docente = get_post('situacion_personal_docente');
            $protocolos_emergencia = get_post('protocolos_emergencia');

            $sql = "INSERT INTO Contacto_Alumnos (usuario_id, tipo_sangre, padece_enfermedad, enfermedad_descripcion, enfermedad_sintomas, requiere_medicamentos, medicamentos_descripcion, alergias_medicamentos, situacion_personal_docente, protocolos_emergencia) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    tipo_sangre=VALUES(tipo_sangre), padece_enfermedad=VALUES(padece_enfermedad), enfermedad_descripcion=VALUES(enfermedad_descripcion), enfermedad_sintomas=VALUES(enfermedad_sintomas), requiere_medicamentos=VALUES(requiere_medicamentos), medicamentos_descripcion=VALUES(medicamentos_descripcion), alergias_medicamentos=VALUES(alergias_medicamentos), situacion_personal_docente=VALUES(situacion_personal_docente), protocolos_emergencia=VALUES(protocolos_emergencia)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isisisssss", $usuario_id, $tipo_sangre, $padece_enfermedad, $enfermedad_descripcion, $enfermedad_sintomas, $requiere_medicamentos, $medicamentos_descripcion, $alergias_medicamentos, $situacion_personal_docente, $protocolos_emergencia);
            break;

        case 'tab5':
            $tutor_nombre = get_post('tutor_nombre');
            $tutor_telefono = get_post('tutor_telefono');
            $tutor_ocupacion = get_post('tutor_ocupacion');

            $sql = "INSERT INTO Contacto_Alumnos (usuario_id, tutor_nombre, tutor_telefono, tutor_ocupacion) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    tutor_nombre=VALUES(tutor_nombre), tutor_telefono=VALUES(tutor_telefono), tutor_ocupacion=VALUES(tutor_ocupacion)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $usuario_id, $tutor_nombre, $tutor_telefono, $tutor_ocupacion);
            break;

        default:
            echo json_encode(["success" => false, "message" => "Sección no válida."]);
            exit();
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Sección guardada correctamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al guardar en la base de datos: " . $stmt->error]);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error inesperado: " . $e->getMessage()]);
}

$conn->close();
?>
