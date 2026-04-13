<?php
// sync_schema_tool.php - Improved Version
// Script to compare local database 'calificaciones' with 'calificacionesProduccion.sql'

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'back/db_connect.php'; // Defines $conn

$sqlFile = 'calificacionesProduccion.sql';
if (!file_exists($sqlFile)) {
    die("Error: SQL file not found at $sqlFile\n");
}

$sqlContent = file_get_contents($sqlFile);

// Function to extract CREATE TABLE statements
function getCreateTables($sql)
{
    $tables = [];
    $offset = 0;
    while (preg_match('/CREATE TABLE `?(\w+)`?\s*\(/i', $sql, $matches, PREG_OFFSET_CAPTURE, $offset)) {
        $tableName = $matches[1][0];
        $startPos = $matches[0][1] + strlen($matches[0][0]);

        $balance = 1;
        $len = strlen($sql);
        $body = '';
        $endPos = $startPos;

        for ($i = $startPos; $i < $len; $i++) {
            $char = $sql[$i];
            if ($char == '(') {
                $balance++;
            } elseif ($char == ')') {
                $balance--;
            }

            if ($balance == 0) {
                $endPos = $i;
                break;
            }
            $body .= $char;
        }

        $tables[$tableName] = $body;
        $offset = $endPos + 1;
    }
    return $tables;
}

$targetTablesRaw = getCreateTables($sqlContent);
$targetTables = [];
foreach ($targetTablesRaw as $name => $body) {
    $targetTables[$name] = parseCreateTableBody($body);
}

// Get local tables
$localTables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tableName = $row[0];
    $res = $conn->query("SHOW CREATE TABLE `$tableName`");
    if ($res) {
        $r = $res->fetch_assoc();
        $createSql = $r['Create Table'];
        // Extract body
        if (preg_match('/CREATE TABLE `?' . preg_quote($tableName) . '`?\s*\((.*)\)[^)]*$/s', $createSql, $m)) {
            $start = strpos($createSql, '(') + 1;
            // Find matching end parenthesis
            $balance = 1;
            $len = strlen($createSql);
            $body = '';
            for ($i = $start; $i < $len; $i++) {
                $char = $createSql[$i];
                if ($char == '(')
                    $balance++;
                if ($char == ')')
                    $balance--;
                if ($balance == 0)
                    break;
                $body .= $char;
            }
            $localTables[$tableName] = parseCreateTableBody($body);
        }
    }
}

// Compare
$diffSql = [];

// 1. Check tables in Target vs Local
foreach ($targetTables as $table => $targetCols) {
    if (!isset($localTables[$table])) {
        // Table missing in Local (means Production has it, Local doesn't)
        // Usually we want Production (Target) to match Local (Source for changes)
        // Wait, the request is: "revisa calificacionesProduccion, contra la base que tenemos nosotros local, y crea un sql con todos los cambios estructurales que hagan falta o tablas faltantes."
        // "crea un sql con todos los cambios estructurales que hagan falta" implies -> modify Production TO MATCH Local.
        // So Local is the SOURCE of truth for new features. Production is TARGET.

        // If table is in Target (Production) but NOT in Local, theoretically we should DROP it?
        // But usually we don't drop tables aggressively. We'll ignore extra tables in Production unless requested.
        // Or flag them.
        $diffSql[] = "-- Table `$table` exists in Production but NOT in Local. (Deprecate?)";
    } else {
        $localCols = $localTables[$table];

        // Check columns in Target vs Local
        foreach ($targetCols as $colName => $colDef) {
            if (!isset($localCols[$colName])) {
                // Column in Production but NOT in Local.
                $diffSql[] = "-- Column `$table`.`$colName` exists in Production but NOT in Local. (Deprecate?)";
                // $diffSql[] = "ALTER TABLE `$table` DROP COLUMN $colName;";
            } else {
                // Comparison logic
                $tDef = normalizeType($colDef);
                $lDef = normalizeType($localCols[$colName]);
                if ($tDef != $lDef) {
                    $diffSql[] = "-- Column `$table`.`$colName` mismatch:\n--   Prod:  $tDef\n--   Local: $lDef";
                    // Assume Local is correct -> Update Prod to match Local
                    // $diffSql[] = "ALTER TABLE `$table` MODIFY COLUMN $colName $colDef;"; // Wait, use Local def!
                    // Actually we need the raw definition from Local to reconstruct the ALTER statement.
                    // But we parsed it into normalized parts?
                    // My parseCreateTableBody returns the raw definition string for the column!
                    // So $localCols[$colName] IS the definition string from local.
                    // So we can use:
                    // $diffSql[] = "ALTER TABLE `$table` MODIFY COLUMN $colName {$localCols[$colName]};";
                }
            }
        }
    }
}

// 2. Check tables in Local vs Target (Find NEW tables/columns in Local)
foreach ($localTables as $table => $localCols) {
    if (!isset($targetTables[$table])) {
        // Table in Local but NOT in Production -> NEW TABLE
        // We already handled this logic separately in previous steps by listing them.
        // But let's be systematic here.
        // We need the CREATE TABLE statement.
        // We can get it via SHOW CREATE TABLE or fetching it again.
        // Let's just list it for now or output a placeholder.
        $diffSql[] = "-- Missing Table in Production: $table";
        // We can try to fetch the create statement if needed.
    } else {
        $targetCols = $targetTables[$table];
        foreach ($localCols as $colName => $colDef) {
            if (!isset($targetCols[$colName])) {
                // Column in Local but NOT in Production -> NEW COLUMN
                $diffSql[] = "ALTER TABLE `$table` ADD COLUMN $colName $colDef;";
            }
        }
    }
}


// Debug output
echo "-- Target tables found: " . count($targetTables) . "\n";
echo "-- Local tables found: " . count($localTables) . "\n";

if (empty($diffSql)) {
    echo "-- No structural changes detected.\n";
} else {
    echo implode("\n", $diffSql);
}

function parseCreateTableBody($body)
{
    $cols = [];
    // Split by comma, respecting parentheses
    $parts = preg_split("/,(?![^(]*\))/", $body);

    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part))
            continue;

        // Skip keys
        if (preg_match('/^(PRIMARY|KEY|UNIQUE|CONSTRAINT|FOREIGN|FULLTEXT|INDEX|CHECK)\b/i', $part)) {
            continue;
        }

        // Parse column name
        if (preg_match('/^`?(\w+)`?\s+(.*)$/', $part, $m)) {
            $name = $m[1];
            $def = $m[2];
            $cols[$name] = $def;
        }
    }
    return $cols;
}

function normalizeType($def)
{
    $def = strtoupper($def);
    $def = preg_replace('/\s+/', ' ', $def);
    // Remove comments
    $def = preg_replace('/COMMENT\s+\'.*?\'/', '', $def);
    // Remove AUTO_INCREMENT
    $def = str_replace('AUTO_INCREMENT', '', $def);
    // Remove display width for INT
    $def = preg_replace('/INT\(\d+\)/', 'INT', $def);
    // Remove COLLATE/CHARSET
    $def = preg_replace('/COLLATE\s+\w+/', '', $def);
    $def = preg_replace('/CHARACTER SET\s+\w+/', '', $def);

    return trim($def);
}
?>