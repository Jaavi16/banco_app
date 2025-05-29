<?php

// Proteccion de datos (reemplazar)
$db_host = 'TU_DIRECCION_IP_RDS';
$db_name = 'nombre_de_la_base_de_datos';
$db_user = 'nombre_de_usuario_bd';
$db_pass = 'contraseña_bd';

// Nombre del banco y color corporativo
$bank_name = "Bankinclán";
$corporate_blue = "#0056b3"; // Un tono de azul oscuro para el corporativo

$pdo = null; // Inicializar PDO a null
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $db_error_message = "Error al conectar a la base de datos: " . $e->getMessage();
}

// Funciones básicas del banco
function obtenerSaldo($pdo, $cuenta_id) {
    if (!$pdo) return false;
    $stmt = $pdo->prepare("SELECT saldo FROM cuentas WHERE id = :id");
    $stmt->bindParam(':id', $cuenta_id);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ? $resultado['saldo'] : false;
}

function realizarTransferencia($pdo, $cuenta_origen_id, $cuenta_destino_id, $cantidad) {
    if (!$pdo) return "Error: No hay conexión a la base de datos.";
    $pdo->beginTransaction();
    try {
        $stmt_existe_origen = $pdo->prepare("SELECT id FROM cuentas WHERE id = :id");
        $stmt_existe_origen->bindParam(':id', $cuenta_origen_id);
        $stmt_existe_origen->execute();
        if (!$stmt_existe_origen->fetch()) return "Cuenta de origen inválida.";

        $stmt_existe_destino = $pdo->prepare("SELECT id FROM cuentas WHERE id = :id");
        $stmt_existe_destino->bindParam(':id', $cuenta_destino_id);
        $stmt_existe_destino->execute();
        if (!$stmt_existe_destino->fetch()) return "Cuenta de destino inválida.";

        $saldo_origen = obtenerSaldo($pdo, $cuenta_origen_id);
        if ($saldo_origen === false || $saldo_origen < $cantidad) return "Saldo insuficiente.";

        $stmt_origen = $pdo->prepare("UPDATE cuentas SET saldo = saldo - :cantidad WHERE id = :id");
        $stmt_origen->bindParam(':cantidad', $cantidad);
        $stmt_origen->bindParam(':id', $cuenta_origen_id);
        $stmt_origen->execute();

        $stmt_destino = $pdo->prepare("UPDATE cuentas SET saldo = saldo + :cantidad WHERE id = :id");
        $stmt_destino->bindParam(':cantidad', $cantidad);
        $stmt_destino->bindParam(':id', $cuenta_destino_id);
        $stmt_destino->execute();

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

// --- HTML y CSS Interno ---
echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($bank_name) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            margin-top: 20px;
        }
        /* Estilos para el nombre del banco */
        .main-title {
            color: ' . htmlspecialchars($corporate_blue) . ';
            text-align: center;
            font-size: 3.5em; /* Tamaño grande para la página principal */
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2); /* Sombra para que destaque */
        }
        .subpage-header {
            color: ' . htmlspecialchars($corporate_blue) . ';
            text-align: center;
            font-size: 1.8em; /* Tamaño más pequeño para las subpáginas */
            margin-bottom: 20px;
        }
        /* Estilos generales para otros encabezados */
        h2 {
            color: #333; /* Un gris oscuro para los subtítulos */
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        p {
            margin-bottom: 10px;
        }
        a {
            color: ' . htmlspecialchars($corporate_blue) . '; /* Enlaces en color corporativo */
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        form {
            margin-top: 20px;
            border: 1px solid #eee;
            padding: 20px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        input[type="text"], input[type="submit"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            background-color: #28a745; /* Verde para el botón de acción */
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        input[type="submit"]:hover {
            background-color: #218838;
        }
        .error-message {
            color: #dc3545;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border: 1px solid #dc3545;
            background-color: #f8d7da;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        /* NUEVO: Estilos para el mensaje de éxito */
        .success-message {
            color: #28a745; /* Verde oscuro */
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border: 1px solid #28a745;
            background-color: #d4edda; /* Verde claro */
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
';

// Si hubo un error en la conexión a la base de datos, lo mostramos primero
if (isset($db_error_message)) {
    echo '<div class="error-message">' . htmlspecialchars($db_error_message) . '</div>';
}


// Página principal
if ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/index.php') {
    echo '<h1 class="main-title">' . htmlspecialchars($bank_name) . '</h1>'; // Título grande
    echo "<p>Simplicidad financiera, confianza azul.</p>";
    if ($pdo) {
        echo "<p><a href='/saldo?cuenta=1'>Ver Saldo de la Cuenta 1</a></p>";
        echo "<p><a href='/transferir'>Realizar Transferencia</a></p>";
    } else {
         echo "<p>La funcionalidad de la base de datos está actualmente inactiva.</p>";
    }
}

// Ver saldo
else if (strpos($_SERVER['REQUEST_URI'], '/saldo') === 0) {
    echo '<h1 class="subpage-header">' . htmlspecialchars($bank_name) . '</h1>'; // Título más pequeño
    echo "<h2>Ver Saldo</h2>";
    if ($pdo) {
        $cuenta_id = isset($_GET['cuenta']) ? $_GET['cuenta'] : null;
        if ($cuenta_id) {
            $saldo = obtenerSaldo($pdo, $cuenta_id);
            if ($saldo !== false) {
                echo "<p>Saldo de la Cuenta " . htmlspecialchars($cuenta_id) . ": <strong>$" . htmlspecialchars(number_format($saldo, 2)) . "</strong></p>";
            } else {
                echo "<p>Cuenta " . htmlspecialchars($cuenta_id) . " no encontrada.</p>";
            }
        } else {
            echo "<p>Por favor, especifique un número de cuenta en la URL (ej: /saldo?cuenta=1).</p>";
        }
    } else {
        echo "<p>No se puede consultar el saldo. No hay conexión a la base de datos.</p>";
    }
    echo "<p><a href='/'>Volver al inicio</a></p>";
}

// Formulario para realizar transferencia
else if ($_SERVER['REQUEST_URI'] === '/transferir') {
    echo '<h1 class="subpage-header">' . htmlspecialchars($bank_name) . '</h1>'; // Título más pequeño
    echo "<h2>Realizar Transferencia</h2>";
    if ($pdo) {
        echo "<form method='POST' action='/procesar_transferencia'>";
        echo "Cuenta de origen: <input type='text' name='origen'><br>";
        echo "Cuenta de destino: <input type='text' name='destino'><br>";
        echo "Cantidad: <input type='text' name='cantidad'><br>";
        echo "<input type='submit' value='Transferir'>";
        echo "</form>";
    } else {
        echo "<p>No se pueden realizar transferencias. No hay conexión a la base de datos.</p>";
    }
    echo "<p><a href='/'>Volver al inicio</a></p>";
}

// Procesar la transferencia
else if ($_SERVER['REQUEST_URI'] === '/procesar_transferencia' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<h1 class="subpage-header">' . htmlspecialchars($bank_name) . '</h1>'; // Título más pequeño
    echo "<h2>Procesando Transferencia</h2>";
    if ($pdo) {
        $origen = isset($_POST['origen']) ? $_POST['origen'] : null;
        $destino = isset($_POST['destino']) ? $_POST['destino'] : null;
        $cantidad = isset($_POST['cantidad']) ? floatval($_POST['cantidad']) : 0;

        if ($origen && $destino && $cantidad > 0) {
            $resultado = realizarTransferencia($pdo, $origen, $destino, $cantidad);

            // AQUÍ ESTÁ EL CAMBIO SOLICITADO
            if ($resultado === "Transferencia realizada con éxito.") {
                echo "<div class='success-message'>" . htmlspecialchars($resultado) . "</div>";
            } else {
                echo "<div class='error-message'>" . htmlspecialchars($resultado) . "</div>";
            }
        } else {
            echo "<p>Por favor, complete todos los campos correctamente y asegúrese de que la cantidad sea positiva.</p>";
        }
    } else {
        echo "<p>No se pudo procesar la transferencia. No hay conexión a la base de datos.</p>";
    }
    echo "<p><a href='/'>Volver al inicio</a></p>";
}

// Si no coincide ninguna ruta
else {
    http_response_code(404);
    echo '<h1 class="subpage-header">' . htmlspecialchars($bank_name) . '</h1>'; // Título más pequeño
    echo "<h2>Página no encontrada</h2>";
    echo "<p>La ruta que intentaste acceder no existe. <a href='/'>Volver al inicio</a></p>";
}

echo '
    </div>
</body>
</html>
';

?>
