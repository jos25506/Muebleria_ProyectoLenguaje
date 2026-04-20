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

// Obtener lista de productos para el select
$sqlProductos = "SELECT ID_PRODUCTO, NOMBRE FROM MUEBLERIA.PRODUCTO WHERE ESTADO = 'ACTIVO' ORDER BY NOMBRE";
$stmtProductos = oci_parse($conn, $sqlProductos);
oci_execute($stmtProductos);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $producto = $_POST['producto'];
    $stock_actual = $_POST['stock_actual'];
    $stock_minimo = $_POST['stock_minimo'];
    
    // Validaciones
    $errores = [];
    
    if (empty($producto)) $errores[] = "Debe seleccionar un producto";
    if (empty($stock_actual)) $errores[] = "El stock actual es requerido";
    if (empty($stock_minimo)) $errores[] = "El stock mínimo es requerido";
    
    if (!is_numeric($stock_actual) || $stock_actual < 0) {
        $errores[] = "El stock actual debe ser un número mayor o igual a 0";
    }
    
    if (!is_numeric($stock_minimo) || $stock_minimo < 0) {
        $errores[] = "El stock mínimo debe ser un número mayor o igual a 0";
    }
    
    if ($stock_minimo > $stock_actual) {
        $errores[] = "El stock mínimo no puede ser mayor que el stock actual";
    }
    
    if (empty($errores)) {
        // Verificar si el producto ya tiene inventario
        $sqlCheck = "SELECT COUNT(*) as total FROM MUEBLERIA.INVENTARIO WHERE ID_PRODUCTO = :producto";
        $stmtCheck = oci_parse($conn, $sqlCheck);
        oci_bind_by_name($stmtCheck, ":producto", $producto);
        oci_execute($stmtCheck);
        $rowCheck = oci_fetch_assoc($stmtCheck);
        
        if ($rowCheck['TOTAL'] > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Inventario ya existe',
                    text: 'Este producto ya tiene un registro de inventario. Puede editarlo desde la lista.',
                    confirmButtonColor: '#2c3e50'
                }).then(() => window.location.href = 'inventario.php');
            </script>";
            exit;
        }
        
        // Obtener el siguiente ID (usando NVL en lugar de SEQ)
        $sqlNextId = "SELECT NVL(MAX(ID_INVENTARIO), 0) + 1 as next_id FROM MUEBLERIA.INVENTARIO";
        $stmtNextId = oci_parse($conn, $sqlNextId);
        oci_execute($stmtNextId);
        $rowNextId = oci_fetch_assoc($stmtNextId);
        $nuevo_id = $rowNextId['NEXT_ID'];
        
        $sql = "INSERT INTO MUEBLERIA.INVENTARIO (ID_INVENTARIO, ID_PRODUCTO, STOCK_ACTUAL, STOCK_MINIMO)
                VALUES (:id, :producto, :actual, :minimo)";
        
        $stmt = oci_parse($conn, $sql);
        
        oci_bind_by_name($stmt, ":id", $nuevo_id);
        oci_bind_by_name($stmt, ":producto", $producto);
        oci_bind_by_name($stmt, ":actual", $stock_actual);
        oci_bind_by_name($stmt, ":minimo", $stock_minimo);
        
        if (oci_execute($stmt)) {
            oci_commit($conn);
            
            // Obtener nombre del producto para el mensaje
            $sqlNombre = "SELECT NOMBRE FROM MUEBLERIA.PRODUCTO WHERE ID_PRODUCTO = :producto";
            $stmtNombre = oci_parse($conn, $sqlNombre);
            oci_bind_by_name($stmtNombre, ":producto", $producto);
            oci_execute($stmtNombre);
            $rowNombre = oci_fetch_assoc($stmtNombre);
            $nombreProducto = $rowNombre['NOMBRE'];
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '¡Inventario registrado!',
                    text: 'Inventario para \"$nombreProducto\" creado exitosamente',
                    confirmButtonColor: '#2c3e50',
                    confirmButtonText: 'Ver inventario'
                }).then(() => window.location.href = 'inventario.php');
            </script>";
        } else {
            $error = oci_error($stmt);
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al guardar: " . addslashes($error['message']) . "',
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
/* Estilos para mensajes de error */
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

/* Estilo para el resumen */
.resumen-card {
    background-color: #f8f9fa;
    border-left: 4px solid #2c3e50;
    padding: 15px;
    margin-top: 20px;
    border-radius: 5px;
    display: none;
}

.resumen-card.show {
    display: block;
}
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-boxes"></i> Nuevo Registro de Inventario
    </div>
    <div class="card-body">
        <form method="POST" onsubmit="return validarFormulario(event)">
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
                        echo "<option value='{$row['ID_PRODUCTO']}'>{$row['NOMBRE']}</option>";
                    }
                    ?>
                </select>
                <div id="error-producto" class="error-message">
                    <i class="fas fa-times-circle"></i> Debe seleccionar un producto
                </div>
            </div>

            <!-- Stock Actual -->
            <div class="mb-3">
                <label for="stock_actual" class="form-label">
                    <i class="fas fa-chart-line"></i> Stock Actual *
                   
                </label>
                <input type="text" name="stock_actual" id="stock_actual" class="form-control" 
                       placeholder="Ej: 10, 25, 100"
                       onkeyup="validarStockActual()"
                       onblur="validarStockActual()"
                       required>
                <div id="error-stock-actual" class="error-message">
                    <i class="fas fa-times-circle"></i> El stock actual debe ser un número entero mayor o igual a 0
                </div>
            </div>

            <!-- Stock Mínimo -->
            <div class="mb-3">
                <label for="stock_minimo" class="form-label">
                    <i class="fas fa-exclamation-triangle"></i> Stock Mínimo *
                    
                </label>
                <input type="text" name="stock_minimo" id="stock_minimo" class="form-control" 
                       placeholder="Ej: 5, 10, 20"
                       onkeyup="validarStockMinimo()"
                       onblur="validarStockMinimo()"
                       required>
                <div id="error-stock-minimo" class="error-message">
                    <i class="fas fa-times-circle"></i> El stock mínimo debe ser un número entero mayor o igual a 0
                </div>
            </div>

            <!-- Resumen y alerta de stock bajo -->
            <div id="resumen-inventario" class="resumen-card">
                <strong><i class="fas fa-chart-simple"></i> Resumen:</strong><br>
                <span id="resumen-producto-nombre"></span><br>
                <span id="resumen-stock-actual"></span><br>
                <span id="resumen-stock-minimo"></span><br>
                <span id="alerta-stock" class="text-danger"></span>
            </div>

            <hr>

            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Registrar Inventario
                </button>
                <a href="inventario.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// VALIDACIONES  PARA INVENTARIO
// ============================================

// 1. Validar selección de producto
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

//  Validar STOCK ACTUAL (solo números enteros)
function validarStockActual() {
    var input = document.getElementById('stock_actual');
    var errorDiv = document.getElementById('error-stock-actual');
    var valor = input.value.trim();
    var regex = /^[0-9]+$/;
    
    if (valor === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        actualizarResumen();
        return true;
    }
    
    if (!regex.test(valor) || parseInt(valor) < 0) {
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

//  Validar STOCK MÍNIMO (solo números enteros)
function validarStockMinimo() {
    var input = document.getElementById('stock_minimo');
    var errorDiv = document.getElementById('error-stock-minimo');
    var valor = input.value.trim();
    var regex = /^[0-9]+$/;
    
    if (valor === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        actualizarResumen();
        return true;
    }
    
    if (!regex.test(valor) || parseInt(valor) < 0) {
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

//  Actualizar resumen 
function actualizarResumen() {
    var productoSelect = document.getElementById('producto');
    var stockActualInput = document.getElementById('stock_actual');
    var stockMinimoInput = document.getElementById('stock_minimo');
    var resumenDiv = document.getElementById('resumen-inventario');
    
    var productoId = productoSelect.value;
    var stockActual = stockActualInput.value.trim();
    var stockMinimo = stockMinimoInput.value.trim();
    
    if (productoId !== '') {
        // Obtener nombre del producto
        var selectedOption = productoSelect.options[productoSelect.selectedIndex];
        var nombreProducto = selectedOption.text;
        
        document.getElementById('resumen-producto-nombre').innerHTML = '<i class="fas fa-couch"></i> <strong>Producto:</strong> ' + nombreProducto;
        
        if (stockActual !== '' && /^[0-9]+$/.test(stockActual)) {
            document.getElementById('resumen-stock-actual').innerHTML = '<i class="fas fa-chart-line"></i> <strong>Stock Actual:</strong> ' + stockActual;
        } else {
            document.getElementById('resumen-stock-actual').innerHTML = '<i class="fas fa-chart-line"></i> <strong>Stock Actual:</strong> (por definir)';
        }
        
        if (stockMinimo !== '' && /^[0-9]+$/.test(stockMinimo)) {
            document.getElementById('resumen-stock-minimo').innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Stock Mínimo:</strong> ' + stockMinimo;
            
            // Verificar alerta de stock bajo
            if (stockActual !== '' && /^[0-9]+$/.test(stockActual) && parseInt(stockActual) <= parseInt(stockMinimo)) {
                document.getElementById('alerta-stock').innerHTML = '<i class="fas fa-bell"></i> ⚠️ ¡Alerta! El stock actual está en o por debajo del mínimo.';
            } else {
                document.getElementById('alerta-stock').innerHTML = '';
            }
        } else {
            document.getElementById('resumen-stock-minimo').innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Stock Mínimo:</strong> (por definir)';
        }
        
        resumenDiv.classList.add('show');
    } else {
        resumenDiv.classList.remove('show');
    }
}

// Validar TODO el formulario antes de enviar
function validarFormulario(event) {
    event.preventDefault();
    
    var productoValido = validarProducto();
    var stockActualValido = validarStockActual();
    var stockMinimoValido = validarStockMinimo();
    
    var producto = document.getElementById('producto').value;
    var stockActual = document.getElementById('stock_actual').value.trim();
    var stockMinimo = document.getElementById('stock_minimo').value.trim();
    
    if (producto === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor seleccione un producto', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (stockActual === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese el stock actual', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (!/^[0-9]+$/.test(stockActual) || parseInt(stockActual) < 0) {
        Swal.fire({ icon: 'warning', title: 'Stock actual inválido', text: 'El stock actual debe ser un número entero mayor o igual a 0', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (stockMinimo === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese el stock mínimo', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (!/^[0-9]+$/.test(stockMinimo) || parseInt(stockMinimo) < 0) {
        Swal.fire({ icon: 'warning', title: 'Stock mínimo inválido', text: 'El stock mínimo debe ser un número entero mayor o igual a 0', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (parseInt(stockMinimo) > parseInt(stockActual)) {
        Swal.fire({ icon: 'warning', title: 'Stock inválido', text: 'El stock mínimo no puede ser mayor que el stock actual', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    event.target.submit();
    return true;
}

// Agregar event listeners
document.getElementById('producto').addEventListener('change', actualizarResumen);
document.getElementById('stock_actual').addEventListener('keyup', actualizarResumen);
document.getElementById('stock_minimo').addEventListener('keyup', actualizarResumen);
document.getElementById('producto').addEventListener('change', validarProducto);
</script>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>