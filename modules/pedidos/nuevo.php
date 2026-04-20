<?php
session_start();
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /Muebleria_Proyecto/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("<div class='alert alert-danger'>Error de conexión</div>");
}

// Obtener lista de clientes para el select
$sqlClientes = "SELECT ID_CLIENTE, NOMBRE FROM MUEBLERIA.CLIENTE ORDER BY NOMBRE";
$stmtClientes = oci_parse($conn, $sqlClientes);
oci_execute($stmtClientes);

// Obtener lista de productos para el select
$sqlProductos = "SELECT ID_PRODUCTO, NOMBRE, PRECIO FROM MUEBLERIA.PRODUCTO WHERE ESTADO = 'ACTIVO' ORDER BY NOMBRE";
$stmtProductos = oci_parse($conn, $sqlProductos);
oci_execute($stmtProductos);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cliente = $_POST['cliente'];
    $producto = $_POST['producto'];
    $cantidad = $_POST['cantidad'];
    $estado = $_POST['estado'];
    
    // Validaciones adicionales
    $errores = [];
    
    if (empty($cliente)) $errores[] = "Debe seleccionar un cliente";
    if (empty($producto)) $errores[] = "Debe seleccionar un producto";
    if (empty($cantidad) || $cantidad <= 0) $errores[] = "La cantidad debe ser mayor a 0";
    if (!is_numeric($cantidad)) $errores[] = "La cantidad debe ser un número";
    
    if (empty($errores)) {
        // Obtener precio del producto
        $sqlPrecio = "SELECT PRECIO FROM MUEBLERIA.PRODUCTO WHERE ID_PRODUCTO = :producto";
        $stmtPrecio = oci_parse($conn, $sqlPrecio);
        oci_bind_by_name($stmtPrecio, ":producto", $producto);
        oci_execute($stmtPrecio);
        $rowPrecio = oci_fetch_assoc($stmtPrecio);
        $precio = $rowPrecio['PRECIO'];
        $subtotal = $precio * $cantidad;
        
        // Obtener el siguiente ID para el pedido (usando NVL)
        $sqlNextId = "SELECT NVL(MAX(ID_PEDIDO), 0) + 1 as next_id FROM MUEBLERIA.PEDIDO";
        $stmtNextId = oci_parse($conn, $sqlNextId);
        oci_execute($stmtNextId);
        $rowNextId = oci_fetch_assoc($stmtNextId);
        $nuevoId = $rowNextId['NEXT_ID'];
        
        // Insertar pedido
        $sqlPedido = "INSERT INTO MUEBLERIA.PEDIDO (ID_PEDIDO, FECHA, ESTADO, TOTAL, ID_CLIENTE, ID_USUARIO) 
                      VALUES (:id, SYSDATE, :estado, :total, :cliente, :usuario)";
        
        $stmtPedido = oci_parse($conn, $sqlPedido);
        oci_bind_by_name($stmtPedido, ":id", $nuevoId);
        oci_bind_by_name($stmtPedido, ":estado", $estado);
        oci_bind_by_name($stmtPedido, ":total", $subtotal);
        oci_bind_by_name($stmtPedido, ":cliente", $cliente);
        oci_bind_by_name($stmtPedido, ":usuario", $_SESSION['usuario_id']);
        oci_execute($stmtPedido);
        
        // Obtener el siguiente ID para el detalle
        $sqlNextIdDet = "SELECT NVL(MAX(ID_DETALLE), 0) + 1 as next_id FROM MUEBLERIA.DETALLE_PEDIDO";
        $stmtNextIdDet = oci_parse($conn, $sqlNextIdDet);
        oci_execute($stmtNextIdDet);
        $rowNextIdDet = oci_fetch_assoc($stmtNextIdDet);
        $nuevoIdDet = $rowNextIdDet['NEXT_ID'];
        
        // Insertar detalle del pedido
        $sqlDetalle = "INSERT INTO MUEBLERIA.DETALLE_PEDIDO (ID_DETALLE, CANTIDAD, PRECIO_UNITARIO, SUB_TOTAL, ID_PEDIDO, ID_PRODUCTO) 
                       VALUES (:id, :cantidad, :precio, :subtotal, :pedido, :producto)";
        
        $stmtDetalle = oci_parse($conn, $sqlDetalle);
        oci_bind_by_name($stmtDetalle, ":id", $nuevoIdDet);
        oci_bind_by_name($stmtDetalle, ":cantidad", $cantidad);
        oci_bind_by_name($stmtDetalle, ":precio", $precio);
        oci_bind_by_name($stmtDetalle, ":subtotal", $subtotal);
        oci_bind_by_name($stmtDetalle, ":pedido", $nuevoId);
        oci_bind_by_name($stmtDetalle, ":producto", $producto);
        oci_execute($stmtDetalle);
        
        oci_commit($conn);
        
        // Obtener nombre del producto y cliente para el mensaje
        $sqlNombreProd = "SELECT NOMBRE FROM MUEBLERIA.PRODUCTO WHERE ID_PRODUCTO = :producto";
        $stmtNombreProd = oci_parse($conn, $sqlNombreProd);
        oci_bind_by_name($stmtNombreProd, ":producto", $producto);
        oci_execute($stmtNombreProd);
        $rowNombreProd = oci_fetch_assoc($stmtNombreProd);
        $nombreProducto = $rowNombreProd['NOMBRE'];
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: '¡Pedido creado!',
                text: 'Se ha creado el pedido #$nuevoId para el producto \"$nombreProducto\"',
                confirmButtonColor: '#2c3e50'
            }).then(() => window.location.href = 'pedidos.php');
        </script>";
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
/* Estilos para mensajes de error en tiempo real */
.error-message {
    color: #e74c3c;
    font-size: 12px;
    margin-top: 5px;
    display: none;
}

.error-message.show {
    display: block;
}

.input-error {
    border-color: #e74c3c !important;
}

.input-success {
    border-color: #27ae60 !important;
}
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-cart-plus"></i> Nuevo Pedido
    </div>
    <div class="card-body">
        <form method="POST" onsubmit="return validarFormulario(event)">
            <!-- Cliente -->
            <div class="mb-3">
                <label for="cliente" class="form-label">
                    <i class="fas fa-user"></i> Cliente *
                </label>
                <select name="cliente" id="cliente" class="form-control" required>
                    <option value="">Seleccione un cliente...</option>
                    <?php
                    oci_execute($stmtClientes);
                    while ($row = oci_fetch_assoc($stmtClientes)) {
                        echo "<option value='{$row['ID_CLIENTE']}'>{$row['NOMBRE']}</option>";
                    }
                    ?>
                </select>
                <div id="error-cliente" class="error-message">
                    <i class="fas fa-times-circle"></i> Debe seleccionar un cliente
                </div>
            </div>

            <!-- Producto -->
            <div class="mb-3">
                <label for="producto" class="form-label">
                    <i class="fas fa-couch"></i> Producto *
                </label>
                <select name="producto" id="producto" class="form-control" required>
                    <option value="">Seleccione un producto...</option>
                    <?php
                    oci_execute($stmtProductos);
                    while ($row = oci_fetch_assoc($stmtProductos)) {
                        echo "<option value='{$row['ID_PRODUCTO']}' data-precio='{$row['PRECIO']}'>{$row['NOMBRE']} - ₡" . number_format($row['PRECIO'], 0, ',', '.') . "</option>";
                    }
                    ?>
                </select>
                <div id="error-producto" class="error-message">
                    <i class="fas fa-times-circle"></i> Debe seleccionar un producto
                </div>
            </div>

            <!-- Cantidad -->
            <div class="mb-3">
                <label for="cantidad" class="form-label">
                    <i class="fas fa-hashtag"></i> Cantidad *
                    <small class="text-muted">(solo números enteros positivos)</small>
                </label>
                <input type="text" name="cantidad" id="cantidad" class="form-control" 
                       placeholder="Ej: 1, 2, 3..."
                       onkeyup="validarCantidad()"
                       onblur="validarCantidad()"
                       required>
                <div id="error-cantidad" class="error-message">
                    <i class="fas fa-times-circle"></i> La cantidad debe ser un número entero positivo
                </div>
            </div>

            <!-- Estado -->
            <div class="mb-3">
                <label for="estado" class="form-label">
                    <i class="fas fa-circle"></i> Estado *
                </label>
                <select name="estado" id="estado" class="form-control" required>
                    <option value="PENDIENTE">PENDIENTE</option>
                    <option value="ENVIADO">ENVIADO</option>
                    <option value="ENTREGADO">ENTREGADO</option>
                    <option value="CANCELADO">CANCELADO</option>
                </select>
            </div>

            <!-- Resumen del pedido -->
            <div class="alert alert-info mt-3" id="resumen-pedido" style="display: none;">
                <strong><i class="fas fa-receipt"></i> Resumen del pedido:</strong><br>
                <span id="resumen-producto"></span><br>
                <span id="resumen-cantidad"></span><br>
                <span id="resumen-precio"></span><br>
                <strong><span id="resumen-total"></span></strong>
            </div>

            <hr>

            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Guardar Pedido
            </button>
            <a href="pedidos.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </form>
    </div>
</div>

<script>
// ============================================
// VALIDACIONES EN TIEMPO REAL PARA PEDIDOS
// ============================================

// 1. Validar selección de cliente
function validarCliente() {
    var input = document.getElementById('cliente');
    var errorDiv = document.getElementById('error-cliente');
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

// 2. Validar selección de producto
function validarProducto() {
    var input = document.getElementById('producto');
    var errorDiv = document.getElementById('error-producto');
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
        actualizarResumen();
        return true;
    }
}

// 3. Validar CANTIDAD (solo números enteros positivos)
function validarCantidad() {
    var input = document.getElementById('cantidad');
    var errorDiv = document.getElementById('error-cantidad');
    var valor = input.value.trim();
    var regex = /^[0-9]+$/;
    
    if (valor === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        actualizarResumen();
        return true;
    }
    
    if (!regex.test(valor) || parseInt(valor) <= 0) {
        errorDiv.classList.add('show');
        input.classList.add('input-error');
        input.classList.remove('input-success');
        actualizarResumen();
        return false;
    } else {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error');
        input.classList.add('input-success');
        actualizarResumen();
        return true;
    }
}

// 4. Actualizar resumen del pedido en tiempo real
function actualizarResumen() {
    var productoSelect = document.getElementById('producto');
    var cantidadInput = document.getElementById('cantidad');
    var resumenDiv = document.getElementById('resumen-pedido');
    
    var productoId = productoSelect.value;
    var cantidad = cantidadInput.value.trim();
    
    if (productoId !== '' && cantidad !== '' && /^[0-9]+$/.test(cantidad) && parseInt(cantidad) > 0) {
        // Obtener nombre y precio del producto seleccionado
        var selectedOption = productoSelect.options[productoSelect.selectedIndex];
        var nombreProducto = selectedOption.text.split(' - ')[0];
        var precio = parseFloat(selectedOption.getAttribute('data-precio'));
        var subtotal = precio * parseInt(cantidad);
        
        document.getElementById('resumen-producto').innerHTML = '<i class="fas fa-couch"></i> <strong>Producto:</strong> ' + nombreProducto;
        document.getElementById('resumen-cantidad').innerHTML = '<i class="fas fa-hashtag"></i> <strong>Cantidad:</strong> ' + cantidad;
        document.getElementById('resumen-precio').innerHTML = '<i class="fas fa-dollar-sign"></i> <strong>Precio unitario:</strong> ₡' + precio.toLocaleString('es-CR');
        document.getElementById('resumen-total').innerHTML = '<i class="fas fa-receipt"></i> <strong>Total:</strong> ₡' + subtotal.toLocaleString('es-CR');
        
        resumenDiv.style.display = 'block';
    } else {
        resumenDiv.style.display = 'none';
    }
}

// 5. Validar TODO el formulario antes de enviar
function validarFormulario(event) {
    event.preventDefault();
    
    var clienteValido = validarCliente();
    var productoValido = validarProducto();
    var cantidadValido = validarCantidad();
    
    var cliente = document.getElementById('cliente').value;
    var producto = document.getElementById('producto').value;
    var cantidad = document.getElementById('cantidad').value.trim();
    
    if (cliente === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor seleccione un cliente', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (producto === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor seleccione un producto', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (cantidad === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese la cantidad', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (!/^[0-9]+$/.test(cantidad) || parseInt(cantidad) <= 0) {
        Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'La cantidad debe ser un número entero positivo', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    event.target.submit();
    return true;
}

// Agregar event listeners para actualizar resumen
document.getElementById('producto').addEventListener('change', actualizarResumen);
document.getElementById('cantidad').addEventListener('keyup', actualizarResumen);
document.getElementById('cliente').addEventListener('change', validarCliente);
document.getElementById('producto').addEventListener('change', validarProducto);
</script>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>