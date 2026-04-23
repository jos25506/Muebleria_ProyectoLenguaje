<?php
session_start();
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /Muebleria_Proyecto/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Obtener pedidos pendientes de pago
$query_pedidos = "SELECT p.ID_PEDIDO, p.TOTAL, c.NOMBRE as CLIENTE
                  FROM MUEBLERIA.PEDIDO p
                  JOIN MUEBLERIA.CLIENTE c ON p.ID_CLIENTE = c.ID_CLIENTE
                  WHERE p.ID_PEDIDO NOT IN (SELECT ID_PEDIDO FROM MUEBLERIA.PAGO)
                  ORDER BY p.ID_PEDIDO";
$stmt_pedidos = oci_parse($conn, $query_pedidos);
oci_execute($stmt_pedidos);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_pedido = $_POST['id_pedido'];
    $metodo = $_POST['metodo'];
    $monto = floatval(str_replace(',', '.', $_POST['monto']));
    $referencia = $_POST['referencia'];
    
    // Validaciones
    $errores = [];
    
    if (empty($id_pedido)) $errores[] = "Debe seleccionar un pedido";
    if (empty($metodo)) $errores[] = "Debe seleccionar un método de pago";
    if (empty($monto) || $monto <= 0) $errores[] = "El monto debe ser mayor a 0";
    
    $maximo_permitido = 99999999.99;
    if ($monto > $maximo_permitido) {
        $errores[] = "El monto (₡" . number_format($monto, 2) . ") excede el límite permitido de ₡" . number_format($maximo_permitido, 2);
    }
    
    // Validar monto contra total del pedido
    if (!empty($id_pedido)) {
        $query_total = "SELECT TOTAL FROM MUEBLERIA.PEDIDO WHERE ID_PEDIDO = :id_pedido";
        $stmt_total = oci_parse($conn, $query_total);
        oci_bind_by_name($stmt_total, ':id_pedido', $id_pedido);
        oci_execute($stmt_total);
        $row_total = oci_fetch_assoc($stmt_total);
        $total_pedido = floatval($row_total['TOTAL']);
        
        if ($monto > $total_pedido) {
            $errores[] = "El monto (₡" . number_format($monto, 2) . ") no puede superar el total del pedido (₡" . number_format($total_pedido, 2) . ")";
        }
    }
    
    if (empty($errores)) {
        // Obtener siguiente ID
        $query_id = "SELECT NVL(MAX(ID_PAGO), 0) + 1 as next_id FROM MUEBLERIA.PAGO";
        $stmt_id = oci_parse($conn, $query_id);
        oci_execute($stmt_id);
        $row_id = oci_fetch_assoc($stmt_id);
        $nuevo_id = $row_id['NEXT_ID'];
        
        // ============================================
        // CORRECCIÓN DE LA FECHA: Usar TO_DATE con SYSDATE
        // ============================================
        $query = "INSERT INTO MUEBLERIA.PAGO (ID_PAGO, METODO, MONTO, FECHA, REFERENCIA, ID_PEDIDO) 
                  VALUES (:id, :metodo, :monto, TO_DATE(TO_CHAR(SYSDATE, 'YYYY-MM-DD'), 'YYYY-MM-DD'), :referencia, :id_pedido)";
        
        $stmt = oci_parse($conn, $query);
        oci_bind_by_name($stmt, ':id', $nuevo_id);
        oci_bind_by_name($stmt, ':metodo', $metodo);
        
        // Convertir el monto a formato Oracle (coma decimal)
        $monto_oracle = str_replace('.', ',', $monto);
        oci_bind_by_name($stmt, ':monto', $monto_oracle);
        
        oci_bind_by_name($stmt, ':referencia', $referencia);
        oci_bind_by_name($stmt, ':id_pedido', $id_pedido);
        
        try {
            if (!@oci_execute($stmt)) {
                $e = oci_error($stmt);
                throw new Exception($e['message']);
            }
            
            oci_commit($conn);
            
            // Obtener la fecha insertada para mostrar
            $query_fecha = "SELECT TO_CHAR(FECHA, 'DD/MM/YYYY') as fecha FROM MUEBLERIA.PAGO WHERE ID_PAGO = :id";
            $stmt_fecha = oci_parse($conn, $query_fecha);
            oci_bind_by_name($stmt_fecha, ':id', $nuevo_id);
            oci_execute($stmt_fecha);
            $row_fecha = oci_fetch_assoc($stmt_fecha);
            $fecha_mostrar = $row_fecha['FECHA'] ?? date('d/m/Y');
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '¡Pago registrado!',
                    text: 'El pago de ₡" . number_format($monto, 2) . " ha sido registrado exitosamente con fecha " . $fecha_mostrar . "',
                    confirmButtonColor: '#2c3e50'
                }).then(() => {
                    window.location.href = 'pagos.php';
                });
            </script>";
            
        } catch (Exception $e) {
            oci_rollback($conn);
            $error = $e->getMessage();
            
            if (strpos($error, 'ORA-20401') !== false) {
                $msg = "El monto debe ser mayor a 0";
            } elseif (strpos($error, 'ORA-20402') !== false) {
                $msg = "El monto excede el total del pedido";
            } else {
                $msg = "Error al registrar: " . $error;
            }
            
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '$msg',
                    confirmButtonColor: '#2c3e50'
                });
            </script>";
        }
    } else {
        $mensaje_error = implode("\\n", $errores);
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Errores de validación',
                text: '$mensaje_error',
                confirmButtonColor: '#2c3e50'
            });
        </script>";
    }
}
?>

<style>
.error-message {
    color: #e74c3c;
    font-size: 12px;
    margin-top: 5px;
    display: none;
}
.error-message.show { display: block; }
.input-error { border-color: #e74c3c !important; }
.input-success { border-color: #27ae60 !important; }
.resumen-pago {
    background-color: #d1ecf1;
    border-left: 4px solid #17a2b8;
    padding: 15px;
    margin-top: 20px;
    border-radius: 5px;
    display: none;
}
.resumen-pago.show { display: block; }
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-credit-card"></i> Nuevo Pago
        <small class="text-muted float-end">Máximo permitido: ₡99,999,999.99</small>
    </div>
    <div class="card-body">
        <form method="POST" onsubmit="return validarFormulario(event)" id="formPago">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_pedido" class="form-label">
                        <i class="fas fa-shopping-cart"></i> Pedido *
                    </label>
                    <select class="form-control" id="id_pedido" name="id_pedido" required 
                            onchange="cargarMonto(this.value); validarPedido()">
                        <option value="">Seleccione un pedido...</option>
                        <?php while ($pedido = oci_fetch_assoc($stmt_pedidos)): ?>
                        <option value="<?php echo $pedido['ID_PEDIDO']; ?>" 
                                data-monto="<?php echo $pedido['TOTAL']; ?>"
                                data-cliente="<?php echo htmlspecialchars($pedido['CLIENTE']); ?>">
                            Pedido #<?php echo $pedido['ID_PEDIDO']; ?> - 
                            <?php echo htmlspecialchars($pedido['CLIENTE']); ?> - 
                            ₡<?php echo number_format($pedido['TOTAL'], 0, ',', '.'); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="error-pedido" class="error-message">
                        <i class="fas fa-times-circle"></i> Debe seleccionar un pedido
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="monto" class="form-label">
                        <i class="fas fa-dollar-sign"></i> Monto a pagar *
                    </label>
                    <input type="text" class="form-control" id="monto" name="monto" 
                           placeholder="0.00"
                           onkeyup="validarMonto()"
                           onblur="validarMonto()"
                           required>
                    <div id="error-monto" class="error-message">
                        <i class="fas fa-times-circle"></i> El monto debe ser un número positivo
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="metodo" class="form-label">
                        <i class="fas fa-credit-card"></i> Método de pago *
                    </label>
                    <select class="form-control" id="metodo" name="metodo" required 
                            onchange="validarMetodo()">
                        <option value="">Seleccione...</option>
                        <option value="EFECTIVO">💵 Efectivo</option>
                        <option value="TARJETA">💳 Tarjeta de crédito/débito</option>
                        <option value="TRANSFERENCIA">🏦 Transferencia bancaria</option>
                    </select>
                    <div id="error-metodo" class="error-message">
                        <i class="fas fa-times-circle"></i> Debe seleccionar un método de pago
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="referencia" class="form-label">
                        <i class="fas fa-hashtag"></i> Referencia
                        <small class="text-muted">(opcional)</small>
                    </label>
                    <input type="text" class="form-control" id="referencia" name="referencia" 
                           placeholder="Número de referencia o transacción"
                           onkeyup="validarReferencia()"
                           onblur="validarReferencia()">
                    <div id="error-referencia" class="error-message">
                        <i class="fas fa-times-circle"></i> La referencia solo puede contener letras, números y guiones
                    </div>
                </div>
            </div>
            
            <div id="resumen-pago" class="resumen-pago">
                <strong><i class="fas fa-chart-line"></i> Resumen del pago:</strong><br>
                <span id="resumen-pedido"></span><br>
                <span id="resumen-total-pedido"></span><br>
                <span id="resumen-monto-pagar"></span><br>
                <span id="resumen-restante" class="text-success"></span>
            </div>
            
            <hr>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Registrar Pago
                </button>
                <a href="pagos.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
var MAXIMO_PERMITIDO = 99999999.99;
var totalPedido = 0;

function validarPedido() {
    var input = document.getElementById('id_pedido');
    var errorDiv = document.getElementById('error-pedido');
    var valor = input.value;
    
    if (valor === '') {
        errorDiv.classList.add('show');
        input.classList.add('input-error');
        input.classList.remove('input-success');
        return false;
    } else {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error');
        input.classList.add('input-success');
        
        var option = input.options[input.selectedIndex];
        totalPedido = parseFloat(option.getAttribute('data-monto'));
        
        actualizarResumen();
        validarMonto();
        return true;
    }
}

function validarMonto() {
    var input = document.getElementById('monto');
    var errorDiv = document.getElementById('error-monto');
    var valor = input.value.trim();
    var regex = /^[0-9]+(\.[0-9]{1,2})?$/;
    
    if (valor === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        actualizarResumen();
        return true;
    }
    
    if (!regex.test(valor) || parseFloat(valor) <= 0) {
        errorDiv.classList.add('show');
        input.classList.add('input-error');
        input.classList.remove('input-success');
        actualizarResumen();
        return false;
    }
    
    if (parseFloat(valor) > MAXIMO_PERMITIDO) {
        errorDiv.classList.add('show');
        input.classList.add('input-error');
        input.classList.remove('input-success');
        actualizarResumen();
        return false;
    }
    
    if (parseFloat(valor) > totalPedido && totalPedido > 0) {
        errorDiv.classList.add('show');
        input.classList.add('input-error');
        input.classList.remove('input-success');
        actualizarResumen();
        return false;
    }
    
    errorDiv.classList.remove('show');
    input.classList.remove('input-error');
    input.classList.add('input-success');
    actualizarResumen();
    return true;
}

function validarMetodo() {
    var input = document.getElementById('metodo');
    var errorDiv = document.getElementById('error-metodo');
    var valor = input.value;
    
    if (valor === '') {
        errorDiv.classList.add('show');
        input.classList.add('input-error');
        input.classList.remove('input-success');
        return false;
    } else {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error');
        input.classList.add('input-success');
        return true;
    }
}

function validarReferencia() {
    var input = document.getElementById('referencia');
    var errorDiv = document.getElementById('error-referencia');
    var valor = input.value.trim();
    var regex = /^[a-zA-Z0-9\-]*$/;
    
    if (valor === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        return true;
    }
    
    if (!regex.test(valor)) {
        errorDiv.classList.add('show');
        input.classList.add('input-error');
        input.classList.remove('input-success');
        return false;
    } else {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error');
        input.classList.add('input-success');
        return true;
    }
}

function actualizarResumen() {
    var pedidoSelect = document.getElementById('id_pedido');
    var resumenDiv = document.getElementById('resumen-pago');
    var monto = parseFloat(document.getElementById('monto').value) || 0;
    
    if (pedidoSelect.value !== '') {
        var option = pedidoSelect.options[pedidoSelect.selectedIndex];
        var cliente = option.getAttribute('data-cliente');
        var total = parseFloat(option.getAttribute('data-monto'));
        var restante = total - monto;
        
        document.getElementById('resumen-pedido').innerHTML = '<i class="fas fa-receipt"></i> <strong>Pedido:</strong> #' + pedidoSelect.value + ' - ' + cliente;
        document.getElementById('resumen-total-pedido').innerHTML = '<i class="fas fa-dollar-sign"></i> <strong>Total:</strong> ₡' + total.toLocaleString('es-CR', {minimumFractionDigits: 2});
        
        if (monto > 0) {
            document.getElementById('resumen-monto-pagar').innerHTML = '<i class="fas fa-credit-card"></i> <strong>Monto:</strong> ₡' + monto.toLocaleString('es-CR', {minimumFractionDigits: 2});
            document.getElementById('resumen-restante').innerHTML = '<i class="fas fa-coins"></i> <strong>Restante:</strong> ₡' + restante.toLocaleString('es-CR', {minimumFractionDigits: 2});
            if (monto > total) {
                document.getElementById('resumen-restante').innerHTML = '<span class="text-danger">⚠️ El monto excede el total</span>';
            }
        }
        resumenDiv.classList.add('show');
    } else {
        resumenDiv.classList.remove('show');
    }
}

function cargarMonto(pedidoId) {
    var select = document.getElementById('id_pedido');
    var option = select.options[select.selectedIndex];
    var monto = option.getAttribute('data-monto');
    if (monto) {
        document.getElementById('monto').value = monto;
        validarMonto();
    }
}

function validarFormulario(event) {
    event.preventDefault();
    
    var id_pedido = document.getElementById('id_pedido').value;
    var monto = document.getElementById('monto').value;
    var metodo = document.getElementById('metodo').value;
    
    if (id_pedido === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Seleccione un pedido', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (monto === '' || parseFloat(monto) <= 0) {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Ingrese un monto válido', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (metodo === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Seleccione un método de pago', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    var select = document.getElementById('id_pedido');
    var option = select.options[select.selectedIndex];
    var totalPedidoVal = parseFloat(option.getAttribute('data-monto'));
    
    if (parseFloat(monto) > totalPedidoVal) {
        Swal.fire({ icon: 'warning', title: 'Monto excede el total', text: 'El monto no puede superar el total del pedido', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    event.target.submit();
    return true;
}

document.getElementById('id_pedido').addEventListener('change', actualizarResumen);
document.getElementById('monto').addEventListener('keyup', actualizarResumen);
</script>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>