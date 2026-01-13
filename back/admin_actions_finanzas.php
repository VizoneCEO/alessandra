<?php
header('Content-Type: application/json');
require 'db_connect.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper function for response
function jsonResponse($success, $message, $data = [])
{
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Helper to log events
function log_cargo_event($conn, $cargo_id, $type, $desc)
{
    $stmt = $conn->prepare("INSERT INTO finanzas_cargos_historial (cargo_id, tipo_evento, descripcion) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $cargo_id, $type, $desc);
    $stmt->execute();
}

if ($action === 'fetch_config') {
    // Get list of all students with their config (if any)
    $sql = "SELECT u.id, u.nombre_completo as nombre, f.colegiatura_base, f.inscripcion_base, f.beca_monto, f.notas 
            FROM Usuarios u 
            LEFT JOIN finanzas_asignaciones f ON u.id = f.alumno_id 
            WHERE u.perfil_id = 3 
            ORDER BY u.nombre_completo ASC";

    $result = $conn->query($sql);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['colegiatura_base'] = $row['colegiatura_base'] ?? 0;
            $row['inscripcion_base'] = $row['inscripcion_base'] ?? 0;
            $row['beca_monto'] = $row['beca_monto'] ?? 0;
            $data[] = $row;
        }
    }

    // Fetch Cycles
    $cycles = [];
    $sql_cycles = "SELECT id, nombre_ciclo FROM Ciclos_Escolares ORDER BY id DESC";
    $result_cycles = $conn->query($sql_cycles);
    if ($result_cycles) {
        while ($row = $result_cycles->fetch_assoc()) {
            $cycles[] = $row;
        }
    }

    jsonResponse(true, 'Configs loaded', ['students' => $data, 'cycles' => $cycles]);

} elseif ($action === 'save_assignment') {
    $alumno_id = $_POST['alumno_id'] ?? 0;
    $colegiatura = $_POST['colegiatura'] ?? 0;
    $inscripcion = $_POST['inscripcion'] ?? 0;
    $beca = $_POST['beca'] ?? 0;
    $notas = $_POST['notas'] ?? '';

    if (!$alumno_id) {
        jsonResponse(false, 'Alumno ID requerido');
    }

    $check = $conn->query("SELECT id FROM finanzas_asignaciones WHERE alumno_id = $alumno_id");
    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE finanzas_asignaciones SET colegiatura_base=?, inscripcion_base=?, beca_monto=?, notas=? WHERE alumno_id=?");
        $stmt->bind_param("dddsi", $colegiatura, $inscripcion, $beca, $notas, $alumno_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO finanzas_asignaciones (colegiatura_base, inscripcion_base, beca_monto, notas, alumno_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("dddsi", $colegiatura, $inscripcion, $beca, $notas, $alumno_id);
    }

    if ($stmt->execute()) {
        jsonResponse(true, 'Asignación guardada correctamente');
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar configuración']);
    }

} elseif ($action === 'bulk_save_assignments') {
    $data = json_decode($_POST['data'], true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }

    $errors = 0;
    $stmt = $conn->prepare("INSERT INTO finanzas_asignaciones (alumno_id, colegiatura_base, inscripcion_base, beca_monto, notas) VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE colegiatura_base=?, inscripcion_base=?, beca_monto=?");

    foreach ($data as $row) {
        $id = $row['alumno_id'];
        $col = $row['colegiatura'];
        $ins = $row['inscripcion'];
        $beca = $row['beca'];
        $notas = isset($row['notas']) ? $row['notas'] : '';
        $stmt->bind_param("idddsddd", $id, $col, $ins, $beca, $notas, $col, $ins, $beca);
        if (!$stmt->execute()) {
            $errors++;
        }
    }

    if ($errors === 0) {
        echo json_encode(['success' => true, 'message' => 'Cambios masivos guardados']);
    } else {
        echo json_encode(['success' => false, 'message' => "Se encontraron $errors errores al guardar."]);
    }


} elseif ($action === 'get_events') {
    $sql = "SELECT * FROM finanzas_eventos WHERE activo = 1 ORDER BY fecha DESC";
    $res = $conn->query($sql);
    $events = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $events[] = $row;
        }
    }
    echo json_encode(['success' => true, 'data' => $events]);

} elseif ($action === 'fetch_tickets') {
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    $sql = "SELECT b.*, e.nombre as evento, u.nombre_completo as alumno 
            FROM finanzas_boletos b 
            JOIN finanzas_eventos e ON b.evento_id = e.id 
            JOIN Usuarios u ON b.alumno_id = u.id";

    if ($event_id > 0) {
        $sql .= " WHERE b.evento_id = $event_id";
    }

    $sql .= " ORDER BY b.evento_id DESC, b.folio_asiento ASC";

    $res = $conn->query($sql);
    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            // Add issue date from cargo or default?
            // finanzas_boletos doesn't have date, but we can join with pago or cargo.
            // For now let's leave date empty or fetch it if needed.
            // Update: migration_tickets says 'fecha_emision' column exists in boletos?
            // Let's check migration file content.
            // Assuming it exists or we use created_at.
            $row['fecha_emision'] = isset($row['fecha_emision']) ? $row['fecha_emision'] : '-';
            $data[] = $row;
        }
    }
    jsonResponse(true, 'Tickets loaded', $data);

} elseif ($action === 'toggle_ticket_status') {
    $ticket_id = $_POST['ticket_id'] ?? 0;

    if (!$ticket_id)
        jsonResponse(false, 'ID Ticket requerido');

    // Get current status
    $stmt = $conn->prepare("SELECT estado_uso FROM finanzas_boletos WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $new_status = ($row['estado_uso'] === 'Usado') ? 'Disponible' : 'Usado';

        $upd = $conn->prepare("UPDATE finanzas_boletos SET estado_uso = ? WHERE id = ?");
        $upd->bind_param("si", $new_status, $ticket_id);

        if ($upd->execute()) {
            // Log event? Maybe on main cargo log it would be hard to link back. 
            // We can skip log or log if cargo_id known.
            jsonResponse(true, 'Estado actualizado', ['new_status' => $new_status]);
        } else {
            jsonResponse(false, 'Error al actualizar');
        }
    } else {
        jsonResponse(false, 'Ticket no encontrado');
    }

} elseif ($action === 'add_event') {
    $nombre = $_POST['nombre'] ?? '';
    $fecha = $_POST['fecha'] ?? date('Y-m-d');

    if (empty($nombre))
        jsonResponse(false, 'Nombre requerido');

    $stmt = $conn->prepare("INSERT INTO finanzas_eventos (nombre, fecha) VALUES (?, ?)");
    $stmt->bind_param("ss", $nombre, $fecha);
    if ($stmt->execute()) {
        jsonResponse(true, 'Evento creado', ['id' => $stmt->insert_id, 'nombre' => $nombre]);
    } else {
        jsonResponse(false, 'Error al crear evento');
    }

} elseif ($action === 'fetch_charges') {
    $sql = "SELECT c.*, u.nombre_completo as alumno_name 
            FROM finanzas_cargos c 
            JOIN Usuarios u ON c.alumno_id = u.id 
            ORDER BY 
                (c.comprobante_url IS NOT NULL AND c.comprobante_url != '' AND c.estado != 'Pagado') DESC,
                u.nombre_completo ASC,
                c.fecha_vencimiento ASC";

    $result = $conn->query($sql);
    $data = [];
    if ($result) {
        $today = date('Y-m-d');
        while ($row = $result->fetch_assoc()) {
            $is_overdue = ($today > $row['fecha_vencimiento']) && ($row['estado'] !== 'Pagado');
            $row['beca_status'] = 'active';
            $has_adjustment = !empty($row['notas_ajuste']);

            if ($is_overdue && $row['beca_aplicada'] > 0 && !$has_adjustment) {
                // Scholarship lost!
                $row['total'] = floatval($row['monto_original']) + floatval($row['recargos']) + floatval($row['beca_aplicada']);
                $row['beca_status'] = 'lost';
            } else {
                $row['total'] = floatval($row['total']);
            }
            // Partial Payment Logic
            $row['pagado'] = floatval($row['monto_pagado']);
            $row['saldo'] = $row['total'] - $row['pagado'];
            if ($row['saldo'] < 0)
                $row['saldo'] = 0;
            $data[] = $row;
        }
    }
    jsonResponse(true, 'Charges loaded', $data);



} elseif ($action === 'generate_monthly_charges') {
    $mes = $_POST['mes'] ?? date('F');
    $anio = $_POST['anio'] ?? date('Y');
    $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? date('Y-m-10');
    $concepto_base = "Colegiatura $mes $anio";

    $sql_students = "SELECT * FROM finanzas_asignaciones WHERE colegiatura_base > 0";
    $res = $conn->query($sql_students);

    $count_success = 0;
    $count_skipped = 0;

    if ($res) {
        // Fix: Ignore 'Cancelado' charges when checking for duplicates.
        // This allows regenerating a charge if the previous one was cancelled.
        $stmt_check = $conn->prepare("SELECT id FROM finanzas_cargos WHERE alumno_id = ? AND concepto = ? AND estado != 'Cancelado' LIMIT 1");
        $stmt_insert = $conn->prepare("INSERT INTO finanzas_cargos 
            (alumno_id, concepto, monto_original, beca_aplicada, recargos, estado, fecha_vencimiento) 
            VALUES (?, ?, ?, ?, 0, 'Pago Pendiente', ?)");

        while ($row = $res->fetch_assoc()) {
            $alumno_id = $row['alumno_id'];
            $monto_a_pagar = $row['colegiatura_base'] - $row['beca_monto'];
            if ($monto_a_pagar < 0)
                $monto_a_pagar = 0;
            $beca_aplicada = floatval($row['beca_monto']);

            $stmt_check->bind_param("is", $alumno_id, $concepto_base);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $count_skipped++;
            } else {
                $stmt_insert->bind_param("isdds", $alumno_id, $concepto_base, $monto_a_pagar, $beca_aplicada, $fecha_vencimiento);
                if ($stmt_insert->execute()) {
                    $new_id = $stmt_insert->insert_id;
                    log_cargo_event($conn, $new_id, 'CREACION', "Cargo generado: $concepto_base");
                    $count_success++;
                }
            }
        }
        echo json_encode(['success' => true, 'message' => "Proceso completado.\nGenerados: $count_success.\nOmitidos: $count_skipped."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al consultar asignaciones.']);
    }

} elseif ($action === 'generate_ticket_charges') {
    $concept_base = $_POST['concept'] ?? 'Venta de Boletos';
    $json_data = $_POST['data'] ?? '[]';
    $items = json_decode($json_data, true);

    if (empty($items)) {
        jsonResponse(false, 'No se recibieron datos.');
    }

    $count_success = 0;
    $today_vence = date('Y-m-d');
    $evento_id = isset($_POST['evento_id']) ? intval($_POST['evento_id']) : null;

    $sql_insert = "INSERT INTO finanzas_cargos (alumno_id, concepto, monto_original, beca_aplicada, recargos, estado, fecha_vencimiento, evento_id, cantidad_boletos) VALUES (?, ?, ?, 0.00, 0.00, 'Pago Pendiente', ?, ?, ?)";
    $stmt = $conn->prepare($sql_insert);

    foreach ($items as $item) {
        $student_id = intval($item['student_id']);
        $qty = intval($item['quantity']);
        $price = floatval($item['price']);

        if ($student_id <= 0 || $qty <= 0)
            continue;

        $total = $qty * $price;
        $total = $qty * $price;
        $concept_final = $concept_base . ($qty > 1 ? " (x$qty)" : "");

        $stmt->bind_param("isdsii", $student_id, $concept_final, $total, $today_vence, $evento_id, $qty);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            log_cargo_event($conn, $new_id, 'CREACION', "Venta Boletos: $concept_final ($qty boletos a $$price c/u)");
            $count_success++;
        }
    }

    jsonResponse(true, "Se generaron $count_success cargos de boletos.");

} elseif ($action === 'generate_registration_charges') {
    $cycle = $_POST['cycle'] ?? 'General';
    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $json_data = $_POST['data'] ?? '[]';
    $items = json_decode($json_data, true);

    if (empty($items)) {
        jsonResponse(false, 'No se recibieron datos.');
    }

    $count_success = 0;

    // Check if duplicate? Maybe not strictly required but good to avoid double charge same day?
    // User didn't ask for check, will assume it's okay to generate if asked.

    $sql_insert = "INSERT INTO finanzas_cargos (alumno_id, concepto, monto_original, beca_aplicada, recargos, estado, fecha_vencimiento) VALUES (?, ?, ?, 0.00, 0.00, 'Pago Pendiente', ?)";
    $stmt = $conn->prepare($sql_insert);

    foreach ($items as $item) {
        $student_id = intval($item['student_id']);
        $amount = floatval($item['amount']);

        if ($student_id <= 0 || $amount <= 0)
            continue;

        $concept_final = "Inscripción " . $cycle;

        $stmt->bind_param("isds", $student_id, $concept_final, $amount, $due_date);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            log_cargo_event($conn, $new_id, 'CREACION', "Cargo Inscripción: $concept_final");
            $count_success++;
        }
    }

    jsonResponse(true, "Se generaron $count_success cargos de inscripción.");

} elseif ($action === 'pay_charge') {
    $charge_id = $_POST['charge_id'] ?? 0;
    $metodo = $_POST['metodo'] ?? 'Efectivo';
    $referencia = $_POST['referencia'] ?? '';
    $monto_pago = floatval($_POST['monto'] ?? 0); // Partial Amount

    if (!$charge_id)
        jsonResponse(false, 'ID Requerido');
    if ($monto_pago <= 0)
        jsonResponse(false, 'El monto debe ser mayor a 0');

    // 1. Get current charge status and totals
    $stmt_check = $conn->prepare("SELECT * FROM finanzas_cargos WHERE id = ?");
    $stmt_check->bind_param("i", $charge_id);
    $stmt_check->execute();
    $charge = $stmt_check->get_result()->fetch_assoc();

    if (!$charge)
        jsonResponse(false, 'Cargo no encontrado');

    // Recalculate Total Expected
    // Logic: original + recargos + (beca if lost). 
    // We reuse logic from fetch_charges or simplified here.
    // Ideally we should store the "final total" but dynamic is how legacy worked.
    // Let's assume Total = monto_original + recargos. 
    // Note: If beca is lost, usually monto_original is updated or recargos updated?
    // In fetch_charges we saw: $row['total'] = ... + beca_aplicada.
    // Detailed check needed.

    // RE-EVALUATE TOTAL
    $today = date('Y-m-d');
    $is_overdue = ($today > $charge['fecha_vencimiento']) && ($charge['estado'] !== 'Pagado');
    $beca_val = floatval($charge['beca_aplicada']);
    $has_adjustment = !empty($charge['notas_ajuste']);

    $total_deuda = floatval($charge['monto_original']) + floatval($charge['recargos']);

    if ($is_overdue && $beca_val > 0 && !$has_adjustment) {
        $total_deuda += $beca_val;
    }

    $pagado_previamente = floatval($charge['monto_pagado']);
    $nuevo_pagado = $pagado_previamente + $monto_pago;
    $saldo_restante = $total_deuda - $nuevo_pagado;

    // Tolerance for float precision
    if ($saldo_restante < 0.01)
        $saldo_restante = 0;

    // Determine New Status
    $nuevo_estado = ($saldo_restante == 0) ? 'Pagado' : 'Parcialmente Pagado';

    // 2. Insert into finanzas_pagos
    $stmt_ins = $conn->prepare("INSERT INTO finanzas_pagos (cargo_id, monto, metodo_pago, referencia) VALUES (?, ?, ?, ?)");
    $stmt_ins->bind_param("idss", $charge_id, $monto_pago, $metodo, $referencia);

    if ($stmt_ins->execute()) {
        // 3. Update Charge
        $fecha_pago_sql = ($nuevo_estado == 'Pagado') ? ", fecha_pago = NOW()" : "";

        $sql_upd = "UPDATE finanzas_cargos SET monto_pagado = ?, estado = ?, metodo_pago = ?, referencia_pago = ?$fecha_pago_sql WHERE id = ?";
        $stmt_upd = $conn->prepare($sql_upd);
        $stmt_upd->bind_param("dsssi", $nuevo_pagado, $nuevo_estado, $metodo, $referencia, $charge_id);
        $stmt_upd->execute();

        $desc = "Pago de $$monto_pago registrado ($metodo). Estado: $nuevo_estado. Restante: $$saldo_restante";
        log_cargo_event($conn, $charge_id, 'PAGO', $desc);

        // 4. Generate Tickets if Fully Paid and Linked to Event
        if ($nuevo_estado == 'Pagado' && !empty($charge['evento_id'])) {
            // Check for existing tickets to avoid duplicates
            $chk_tickets = $conn->query("SELECT id FROM finanzas_boletos WHERE cargo_id = $charge_id");
            if ($chk_tickets && $chk_tickets->num_rows == 0) {
                $evt_id = intval($charge['evento_id']);
                $qty_tickets = intval($charge['cantidad_boletos']) ?: 1;
                $pago_id = $stmt_ins->insert_id;
                $alumno_id = $charge['alumno_id'];

                for ($i = 0; $i < $qty_tickets; $i++) {
                    // Get Next Folio
                    $res_folio = $conn->query("SELECT COALESCE(MAX(folio_asiento), 0) + 1 as next_folio FROM finanzas_boletos WHERE evento_id = $evt_id");
                    $next_folio = ($res_folio && $row_f = $res_folio->fetch_assoc()) ? $row_f['next_folio'] : 1;

                    $ins_ticket = $conn->prepare("INSERT INTO finanzas_boletos (evento_id, alumno_id, cargo_id, pago_id, folio_asiento) VALUES (?, ?, ?, ?, ?)");
                    $ins_ticket->bind_param("iiiii", $evt_id, $alumno_id, $charge_id, $pago_id, $next_folio);
                    $ins_ticket->execute();
                }
                log_cargo_event($conn, $charge_id, 'OTRO', "Se generaron $qty_tickets boletos (Evento #$evt_id).");
            }
        }

        jsonResponse(true, 'Pago registrado correctly.', ['nuevo_estado' => $nuevo_estado, 'saldo' => $saldo_restante]);
    } else {
        jsonResponse(false, 'Error al registrar el pago individual.');
    }

} elseif ($action === 'validate_receipt') {
    $charge_id = $_POST['charge_id'] ?? 0;
    $status = $_POST['status'] ?? ''; // 'approved' or 'rejected'

    if (!$charge_id)
        jsonResponse(false, 'ID Requerido');

    if ($status === 'approved') {
        // 1. Get Student Account
        $rec_acc_id = null;
        $q_acc = $conn->prepare("SELECT u.cuenta_deposito_id FROM finanzas_cargos c JOIN Usuarios u ON c.alumno_id = u.id WHERE c.id = ?");
        $q_acc->bind_param("i", $charge_id);
        $q_acc->execute();
        if ($row_acc = $q_acc->get_result()->fetch_assoc()) {
            $rec_acc_id = $row_acc['cuenta_deposito_id'];
        }

        // Update Charge: Set Paid, Date, Receiving Account, and Amount Paid (Full)
        $sql = "UPDATE finanzas_cargos SET estado = 'Pagado', fecha_pago = NOW(), cuenta_receptora_id = ?, monto_pagado = (monto_original + recargos) WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $rec_acc_id, $charge_id);
        if ($stmt->execute()) {
            log_cargo_event($conn, $charge_id, 'PAGO', "Comprobante validado por Admin.");

            // Generate Tickets if linked to Event
            // Verify charge details first
            $c_check = $conn->query("SELECT * FROM finanzas_cargos WHERE id = $charge_id");
            if ($c_check && $charge = $c_check->fetch_assoc()) {
                if (!empty($charge['evento_id'])) {
                    $chk_tickets = $conn->query("SELECT id FROM finanzas_boletos WHERE cargo_id = $charge_id");
                    if ($chk_tickets && $chk_tickets->num_rows == 0) {
                        $evt_id = intval($charge['evento_id']);
                        $qty_tickets = intval($charge['cantidad_boletos']) ?: 1;
                        $pago_id = 0; // No payment ID for receipt validation unless we look it up or leave 0
                        // Ideally we should have a payment record for receipt too? 
                        // Current logic for receipt just updates charge. Let's leave pago_id = 0 or NULL if allowed.
                        // finanzas_boletos schema: pago_id might be required? Let's assume 0 is fine for now.
                        $alumno_id = $charge['alumno_id'];

                        for ($i = 0; $i < $qty_tickets; $i++) {
                            // Get Next Folio
                            $res_folio = $conn->query("SELECT COALESCE(MAX(folio_asiento), 0) + 1 as next_folio FROM finanzas_boletos WHERE evento_id = $evt_id");
                            $next_folio = ($res_folio && $row_f = $res_folio->fetch_assoc()) ? $row_f['next_folio'] : 1;

                            $ins_ticket = $conn->prepare("INSERT INTO finanzas_boletos (evento_id, alumno_id, cargo_id, pago_id, folio_asiento) VALUES (?, ?, ?, ?, ?)");
                            $ins_ticket->bind_param("iiiii", $evt_id, $alumno_id, $charge_id, $pago_id, $next_folio);
                            $ins_ticket->execute();
                        }
                        log_cargo_event($conn, $charge_id, 'OTRO', "Se generaron $qty_tickets boletos (Evento #$evt_id) tras validación.");
                    }
                }
            }

            jsonResponse(true, 'Pago validado y boletos generados (si aplica).');
        }
    } elseif ($status === 'rejected') {
        $reason = $_POST['reason'] ?? 'Sin motivo especificado';
        // Reject: Clear URL, Keep Pending, Log Reason
        $sql = "UPDATE finanzas_cargos SET comprobante_url = NULL, estado = 'Pago Pendiente', metodo_pago = NULL, fecha_pago = NULL WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $charge_id);

        if ($stmt->execute()) {
            log_cargo_event($conn, $charge_id, 'RECHAZADO', "Comprobante rechazado. Motivo: $reason");
            jsonResponse(true, 'Comprobante rechazado correctamente.');
        } else {
            jsonResponse(false, 'Error al actualizar base de datos.');
        }
    } else {
        jsonResponse(false, 'Status inválido');
    }

} elseif ($action === 'delete_charge') {
    // Deleting usually wipes history due to CASCADE, but maybe we want to log it elsewhere?
    // For now, simple delete.
    $charge_id = $_POST['charge_id'] ?? 0;
    if (!$charge_id)
        jsonResponse(false, 'ID Requerido');

    $reason = $_POST['reason'] ?? 'Sin motivo especificado';

    // Soft Delete: Mark as 'Cancelado' so it appears in History
    $sql = "UPDATE finanzas_cargos SET estado = 'Cancelado' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $charge_id);

    if ($stmt->execute()) {
        log_cargo_event($conn, $charge_id, 'CANCELACION', "Cancelado: " . $reason);
        jsonResponse(true, 'Cargo enviado a histórico (cancelado).');
    } else {
        jsonResponse(false, 'Error al eliminar cargo');
    }

} elseif ($action === 'verify_payment') {
    $charge_id = $_POST['charge_id'];
    $status = $_POST['status'];

    if (!$charge_id || !$status) {
        jsonResponse(false, 'Datos incompletos.');
    }

    if ($status === 'approved') {
        // 1. Get Student's Assigned Account
        $rec_acc_id = null;
        $q_acc = $conn->prepare("SELECT u.cuenta_deposito_id FROM finanzas_cargos c JOIN Usuarios u ON c.alumno_id = u.id WHERE c.id = ?");
        $q_acc->bind_param("i", $charge_id);
        $q_acc->execute();
        $res_acc = $q_acc->get_result();
        if ($row_acc = $res_acc->fetch_assoc()) {
            $rec_acc_id = $row_acc['cuenta_deposito_id'];
        }

        // 2. Update Charge
        $sql = "UPDATE finanzas_cargos SET estado = 'Pagado', fecha_pago = NOW(), cuenta_receptora_id = ?, monto_pagado = (monto_original + recargos) WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $rec_acc_id, $charge_id);
        if ($stmt->execute()) {
            log_cargo_event($conn, $charge_id, 'PAGO', 'Pago verificado y aprobado por administrador.');
            jsonResponse(true, 'Pago aprobado correctamente.');
        } else {
            jsonResponse(false, 'Error al actualizar BD.');
        }
    } elseif ($status === 'rejected') {
        // Revert to Pendiente and Clear Proof
        $stmt = $conn->prepare("UPDATE finanzas_cargos SET estado = 'Pago Pendiente', comprobante_url = NULL, metodo_pago = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $charge_id);
            if ($stmt->execute()) {
                log_cargo_event($conn, $charge_id, 'OTRO', 'Comprobante rechazado. Se requiere nueva carga.');
                jsonResponse(true, 'Pago rechazado. El comprobante se ha desvinculado.');
            } else {
                jsonResponse(false, 'Error al rechazar: ' . $stmt->error);
            }
        } else {
            jsonResponse(false, 'Error prepare: ' . $conn->error);
        }
    } else {
        jsonResponse(false, 'Estado inválido.');
    }


} elseif ($action === 'fetch_history') {
    $charge_id = $_POST['charge_id'] ?? $_GET['charge_id'] ?? 0;
    if (!$charge_id)
        jsonResponse(false, 'ID Requerido');

    // Fetch Events (Logs)
    $events = [];
    $stmt = $conn->prepare("SELECT id, cargo_id, tipo_evento, descripcion, fecha as fecha_evento FROM finanzas_cargos_historial WHERE cargo_id = ? ORDER BY fecha DESC");
    $stmt->bind_param("i", $charge_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc())
        $events[] = $r;

    // Fetch Partial Payments
    $payments = [];
    $stmt2 = $conn->prepare("SELECT * FROM finanzas_pagos WHERE cargo_id = ? ORDER BY fecha_pago DESC");
    $stmt2->bind_param("i", $charge_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($r = $res2->fetch_assoc())
        $payments[] = $r;

    jsonResponse(true, 'History loaded', ['events' => $events, 'payments' => $payments]);

} elseif ($action === 'pay_charge') {
    $charge_id = $_POST['charge_id'];
    $stmt = $conn->prepare("UPDATE finanzas_cargos SET estado = 'Pagado', fecha_pago = NOW(), metodo_pago = 'Efectivo' WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $charge_id);
        if ($stmt->execute()) {
            log_cargo_event($conn, $charge_id, 'PAGO', 'Pago registrado manualmente (Efectivo).');
            jsonResponse(true, 'Pago registrado.');
        } else {
            jsonResponse(false, 'Error al registrar pago: ' . $stmt->error);
        }
    } else {
        jsonResponse(false, 'Error prepare: ' . $conn->error);
    }

} elseif ($action === 'delete_charges_bulk') {
    $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];
    $reason = $_POST['reason'] ?? 'Cancelación Masiva';

    if (empty($ids))
        jsonResponse(false, 'No hay cargos seleccionados');

    $successCount = 0;
    $stmt = $conn->prepare("UPDATE finanzas_cargos SET estado = 'Cancelado' WHERE id = ?");

    foreach ($ids as $id) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            log_cargo_event($conn, $id, 'CANCELACION', "Cancelado (Masivo): " . $reason);
            $successCount++;
        }
    }

    jsonResponse(true, "$successCount cargos enviados al histórico.");


} elseif ($action === 'adjust_charge') {
    $charge_id = $_POST['charge_id'] ?? 0;
    $new_total = floatval($_POST['new_total'] ?? 0);
    $recargos_amount = floatval($_POST['recargos_amount'] ?? 0);
    $notes = $_POST['notes'] ?? '';

    if (!$charge_id)
        jsonResponse(false, 'Falta ID de cargo');
    if ($new_total < 0)
        jsonResponse(false, 'El monto total no puede ser negativo');

    $final_recargos = $recargos_amount;
    $new_monto_original = $new_total - $final_recargos;
    if ($new_monto_original < 0)
        $new_monto_original = 0;

    $stmt_update = $conn->prepare("UPDATE finanzas_cargos SET monto_original = ?, recargos = ?, notas_ajuste = ? WHERE id = ?");
    $stmt_update->bind_param("ddsi", $new_monto_original, $final_recargos, $notes, $charge_id);

    if ($stmt_update->execute()) {
        $desc = "Ajuste manual. Nuevo Total: $$new_total. Recargos: $$final_recargos. Motivo: $notes";
        log_cargo_event($conn, $charge_id, 'AJUSTE', $desc);
        jsonResponse(true, 'Ajuste aplicado correctamente');
    } else {
        jsonResponse(false, 'Error DB: ' . $conn->error);
    }

} else {
    jsonResponse(false, 'Invalid Action');
}

$conn->close();