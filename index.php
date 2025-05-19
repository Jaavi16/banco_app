<?php

// Proteccion de datos (reemplazar)
$db_host = 'TU_DIRECCION_IP_RDS';
$db_name = 'nombre_de_la_base_de_datos';
$db_user = 'nombre_de_usuario_bd';
$db_pass = 'contraseña_bd';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error al conectar a la base de datos: " . $e->getMessage());
}

// Funciones básicas del banco 

function obtenerSaldo($pdo, $cuenta_id) {
    $stmt = $pdo->prepare("SELECT saldo FROM cuentas WHERE id = :id");
    $stmt->bindParam(':id', $cuenta_id);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ? $resultado['saldo'] : false;
}

function realizarTransferencia($pdo, $cuenta_origen_id, $cuenta_destino_id, $cantidad) {
    $pdo->beginTransaction();
    try {
        // Verificar si las cuentas existen
        $stmt_existe_origen = $pdo->prepare("SELECT id FROM cuentas WHERE id = :id");
        $stmt_existe_origen->bindParam(':id', $cuenta_origen_id);
        $stmt_existe_origen->execute();
        if (!$stmt_existe_origen->fetch()) return "Cuenta de origen inválida.";

        $stmt_existe_destino = $pdo->prepare("SELECT id FROM cuentas WHERE id = :id");
        $stmt_existe_destino->bindParam(':id', $cuenta_destino_id);
        $stmt_existe_destino->execute();
        if (!$stmt_existe_destino->fetch()) return "Cuenta de destino inválida.";

        // Verificar saldo suficiente
        $saldo_origen = obtenerSaldo($pdo, $cuenta_origen_id);
        if ($saldo_origen === false || $saldo_origen < $cantidad) return "Saldo insuficiente.";

        // Debitar de la cuenta de origen
        $stmt_origen = $pdo->prepare("UPDATE cuentas SET saldo = saldo - :cantidad WHERE id = :id");
        $stmt_origen->bindParam(':cantidad', $cantidad);
        $stmt_origen->bindParam(':id', $cuenta_origen_id);
        $stmt_origen->execute();

        // Acreditar en la cuenta de destino
        $stmt_destino = $pdo->prepare("UPDATE cuentas SET saldo = saldo + :cantidad WHERE id = :id");
        $stmt_destino->bindParam(':cantidad', $cantidad);
        $stmt_destino->bindParam(':id', $cuenta_destino_id);
        $stmt_destino->execute();

        // Registrar la transacción
        $stmt_transaccion = $pdo->prepare("INSERT INTO transacciones (cuenta_origen_id, cuenta_destino_id, cantidad, fecha) VALUES (:origen, :destino, :cantidad, NOW())");
        $stmt_transaccion->bindParam(':origen', $cuenta_origen_id);
        $stmt_transaccion->bindParam(':destino', $cuenta_destino_id);
        $stmt_transaccion->bindParam(':cantidad', $cantidad);
        $stmt_transaccion->execute();

        $pdo->commit();
        return "Transferencia realizada con éxito.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error en la transferencia: " . $e->getMessage());
        return "Error al realizar la transferencia.";
    }
}

// --- Rutas básicas para la aplicación (ejemplos) ---

// Página principal
if ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/index.php') {
    echo "<h1>Bienvenido al Banco Simple</h1>";
    echo "<p>Esta es una simulación de una aplicación bancaria sencilla.</p>";
    echo "<p><a href='/saldo?cuenta=1'>Ver Saldo de la Cuenta 1</a></p>";
    echo "<p><a href='/transferir'>Realizar Transferencia</a></p>";
    exit();
}

// Ver saldo
if (strpos($_SERVER['REQUEST_URI'], '/saldo') === 0) {
    $cuenta_id = isset($_GET['cuenta']) ? $_GET['cuenta'] : null;
    echo "<h2>Ver Saldo</h2>";
    if ($cuenta_id) {
        $saldo = obtenerSaldo($pdo, $cuenta_id);
        if ($saldo !== false) {
            echo "<p>Saldo de la Cuenta " . htmlspecialchars($cuenta_id) . ": $" . htmlspecialchars($saldo) . "</p>";
        } else {
            echo "<p>Cuenta " . htmlspecialchars($cuenta_id) . " no encontrada.</p>";
        }
    } else {
        echo "<p>Por favor, especifique un número de cuenta en la URL (ej: /saldo?cuenta=1).</p>";
    }
    echo "<p><a href='/'>Volver al inicio</a></p>";
    exit();
}

// Formulario para realizar transferencia
if ($_SERVER['REQUEST_URI'] === '/transferir') {
    echo "<h2>Realizar Transferencia</h2>";
    echo "<form method='POST' action='/procesar_transferencia'>";
    echo "Cuenta de origen: <input type='text' name='origen'><br>";
    echo "Cuenta de destino: <input type='text' name='destino'><br>";
    echo "Cantidad: <input type='text' name='cantidad'><br>";
    echo "<input type='submit' value='Transferir'>";
    echo "</form>";
    echo "<p><a href='/'>Volver al inicio</a></p>";
    exit();
}

// Procesar la transferencia
if ($_SERVER['REQUEST_URI'] === '/procesar_transferencia' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Procesando Transferencia</h2>";
    $origen = isset($_POST['origen']) ? $_POST['origen'] : null;
    $destino = isset($_POST['destino']) ? $_POST['destino'] : null;
    $cantidad = isset($_POST['cantidad']) ? floatval($_POST['cantidad']) : 0;

    if ($origen && $destino && $cantidad > 0) {
        $resultado = realizarTransferencia($pdo, $origen, $destino, $cantidad);
        echo "<p>" . htmlspecialchars($resultado) . "</p>";
    } else {
        echo "<p>Por favor, complete todos los campos correctamente.</p>";
    }
    echo "<p><a href='/'>Volver al inicio</a></p>";
    exit();
}

// Si no coincide ninguna ruta
http_response_code(404);
echo "Página no encontrada.";

?>
