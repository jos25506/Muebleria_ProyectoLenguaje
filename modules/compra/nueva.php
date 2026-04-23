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

// Obtener proveedores
$query_prov = "SELECT * FROM MUEBLERIA.PROVEEDOR ORDER BY NOMBRE";
$stmt_prov = oci_parse($conn, $query_prov);
oci_execute($stmt_prov);

// Obtener productos activos
$query_prod = "SELECT * FROM MUEBLERIA.PRODUCTO WHERE ESTADO = 'ACTIVO' ORDER BY NOMBRE";
$stmt_prod = oci_parse($conn, $query_prod);
oci_execute($stmt_prod);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_proveedor = $_POST['id_proveedor'];
    $fecha = $_POST['fecha'];
    $total = floatval($_POST['total']);
    
    // Validaciones
    $errores = [];
    
    if (empty($id_proveedor)) $errores[] = "Debe seleccionar un proveedor";
    if (empty($fecha)) $errores[] = "La fecha es requerida";
    
    // ============================================
    // VALIDACIÓN DE RANGO PARA NUMBER(10,2)
    // El valor máximo es 99,999,999.99
    // ============================================
    $maximo_permitido = 99999999.99;
    
    // Validar total de la compra
    if ($total > $maximo_permitido) {
        $errores[] = "El total (₡" . number_format($total, 2) . ") excede el límite permitido de ₡" . number_format($maximo_permitido, 2);
    }
    
    // Validar cada producto (costos y subtotales)
    $productos = $_POST['productos'];
    $cantidades = $_POST['cantidades'];
    $costos = $_POST['costos'];
    
    for ($i = 0; $i < count($productos); $i++) {
        if ($productos[$i] != '') {
            $costo = floatval($costos[$i]);
            $cantidad = intval($cantidades[$i]);
            $subtotal = $cantidad * $costo;
            
            // Validar costo unitario
            if ($costo > $maximo_permitido) {
                $errores[] = "El costo unitario (₡" . number_format($costo, 2) . ") excede el límite de ₡" . number_format($maximo_permitido, 2);
            }
            
            // Validar subtotal
            if ($subtotal > $maximo_permitido) {
                $errores[] = "El subtotal del producto (₡" . number_format($subtotal, 2) . ") excede el límite de ₡" . number_format($maximo_permitido, 2);
            }
        }
    }
    // ============================================
    
    if (empty($errores)) {
        // Obtener siguiente ID
        $query_id = "SELECT NVL(MAX(ID_COMPRA), 0) + 1 as next_id FROM MUEBLERIA.COMPRA";
        $stmt_id = oci_parse($conn, $query_id);
        oci_execute($stmt_id);
        $row_id = oci_fetch_assoc($stmt_id);
        $nuevo_id = $row_id['NEXT_ID'];
        
        // Insertar compra
        $query = "INSERT INTO MUEBLERIA.COMPRA (ID_COMPRA, FECHA, TOTAL, ID_PROVEEDOR, ID_USUARIO) 
                  VALUES (:id, TO_DATE(:fecha, 'YYYY-MM-DD'), :total, :id_proveedor, :usuario)";
        
        $stmt = oci_parse($conn, $query);
        oci_bind_by_name($stmt, ':id', $nuevo_id);
        oci_bind_by_name($stmt, ':fecha', $fecha);
        oci_bind_by_name($stmt, ':total', $total);
        oci_bind_by_name($stmt, ':id_proveedor', $id_proveedor);
        oci_bind_by_name($stmt, ':usuario', $_SESSION['usuario_id']);
        
        try {
            // INSERTAR COMPRA
            if (!@oci_execute($stmt)) {
                $e = oci_error($stmt);
                throw new Exception($e['message']);
            }

            // INSERTAR DETALLES
            $productos = $_POST['productos'];
            $cantidades = $_POST['cantidades'];
            $costos = $_POST['costos'];

            for ($i = 0; $i < count($productos); $i++) {
                if ($productos[$i] != '') {
                    $subtotal = $cantidades[$i] * $costos[$i];

                    $query_det = "INSERT INTO MUEBLERIA.DETALLE_COMPRA 
                        (ID_DETALLE_COMPRA, ID_COMPRA, ID_PRODUCTO, CANTIDAD, COSTO_UNITARIO, SUB_TOTAL) 
                        VALUES 
                        ((SELECT NVL(MAX(ID_DETALLE_COMPRA), 0) + 1 FROM MUEBLERIA.DETALLE_COMPRA), 
                         :id_compra, :id_producto, :cantidad, :costo, :subtotal)";

                    $stmt_det = oci_parse($conn, $query_det);

                    oci_bind_by_name($stmt_det, ':id_compra', $nuevo_id);
                    oci_bind_by_name($stmt_det, ':id_producto', $productos[$i]);
                    oci_bind_by_name($stmt_det, ':cantidad', $cantidades[$i]);
                    oci_bind_by_name($stmt_det, ':costo', $costos[$i]);
                    oci_bind_by_name($stmt_det, ':subtotal', $subtotal);

                    if (!oci_execute($stmt_det)) {
                        $e = oci_error($stmt_det);
                        throw new Exception($e['message']);
                    }
                }
            }
            
            oci_commit($conn);

            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '¡Compra registrada!',
                    text: 'La compra ha sido registrada exitosamente',
                    confirmButtonColor: '#2c3e50'
                }).then(() => window.location.href = 'compras.php');
            </script>";

        } catch (Exception $e) {
            $error = $e->getMessage();

            // TRADUCIR ERRORES DE TRIGGERS
            if (strpos($error, 'ORA-20201') !== false) {
                $msg = "El total debe ser mayor a 0";
            } elseif (strpos($error, 'ORA-20202') !== false) {
                $msg = "La fecha no puede ser futura";
            } elseif (strpos($error, 'ORA-20203') !== false) {
                $msg = "Cantidad inválida en un producto";
            } elseif (strpos($error, 'ORA-20204') !== false) {
                $msg = "Subtotal inválido en un producto";
            } else {
                $msg = "Error al registrar: " . $error;
            }

            // REVERSAR TODO
            oci_rollback($conn);

            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: " . json_encode($msg) . ",
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

.producto-row {
    background-color: #f8f9fa;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.producto-row:hover {
    background-color: #e9ecef;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.resumen-compra {
    background-color: #d1ecf1;
    border-left: 4px solid #17a2b8;
    padding: 15px;
    margin-top: 20px;
    border-radius: 5px;
    display: none;
}

.resumen-compra.show {
    display: block;
}

.text-danger {
    color: #e74c3c;
}
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-cart-plus"></i> Nueva Compra
        <small class="text-muted float-end">Máximo permitido: ₡99,999,999.99</small>
    </div>
    <div class="card-body">
        <form method="POST" onsubmit="return validarFormulario(event)" id="formCompra">
            <div class="row">
                <!-- Proveedor -->
                <div class="col-md-6 mb-3">
                    <label for="id_proveedor" class="form-label">
                        <i class="fas fa-truck"></i> Proveedor *
                    </label>
                    <select class="form-control" id="id_proveedor" name="id_proveedor" 
                            onchange="validarProveedor()" required>
                        <option value="">Seleccione un proveedor...</option>
                        <?php while ($prov = oci_fetch_assoc($stmt_prov)): ?>
                        <option value="<?php echo $prov['ID_PROVEEDOR']; ?>">
                            <?php echo htmlspecialchars($prov['NOMBRE']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="error-proveedor" class="error-message">
                        <i class="fas fa-times-circle"></i> Debe seleccionar un proveedor
                    </div>
                </div>
                
                <!-- Fecha -->
                <div class="col-md-6 mb-3">
                    <label for="fecha" class="form-label">
                        <i class="fas fa-calendar"></i> Fecha *
                    </label>
                    <input type="date" class="form-control" id="fecha" name="fecha" 
                           value="<?php echo date('Y-m-d'); ?>"
                           onchange="validarFecha()" required>
                    <div id="error-fecha" class="error-message">
                        <i class="fas fa-times-circle"></i> La fecha no puede ser futura
                    </div>
                </div>
            </div>
            
            <h4 class="mt-3"><i class="fas fa-box"></i> Productos</h4>
            <div id="productos-container">
                <div class="producto-row row">
                    <div class="col-md-5">
                        <select name="productos[]" class="form-control" required>
                            <option value="">Seleccione un producto...</option>
                            <?php 
                            oci_execute($stmt_prod);
                            while ($prod = oci_fetch_assoc($stmt_prod)): 
                            ?>
                            <option value="<?php echo $prod['ID_PRODUCTO']; ?>">
                                <?php echo htmlspecialchars($prod['NOMBRE']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="cantidades[]" class="form-control" 
                               placeholder="Cantidad" 
                               onkeyup="validarCantidad(this)"
                               onblur="validarCantidad(this)"
                               required>
                        <div class="error-message error-cantidad" style="font-size: 10px;">
                            Solo números enteros > 0
                        </div>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="costos[]" class="form-control" 
                               placeholder="Costo unitario" 
                               onkeyup="validarCosto(this)"
                               onblur="validarCosto(this)"
                               required>
                        <div class="error-message error-costo" style="font-size: 10px;">
                            Solo números positivos (max: 99,999,999.99)
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFila(this)">Eliminar</button>
                    </div>
                </div>
            </div>
            
            <button type="button" class="btn btn-secondary mt-2" onclick="agregarProducto()">
                <i class="fas fa-plus"></i> Agregar producto
            </button>
            
            <hr>
            
            <!-- Resumen de la compra -->
            <div id="resumen-compra" class="resumen-compra">
                <strong><i class="fas fa-chart-line"></i> Resumen de la compra:</strong><br>
                <span id="resumen-proveedor"></span><br>
                <span id="resumen-cantidad-productos"></span><br>
                <span id="resumen-total"></span>
            </div>
            
            <div class="row">
                <div class="col-md-12 text-end">
                    <h3>Total: ₡ <span id="totalSpan" class="text-success">0</span></h3>
                    <input type="hidden" name="total" id="totalInput" value="0">
                    <small class="text-muted">Máximo permitido: ₡99,999,999.99</small>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Registrar Compra
                </button>
                <a href="compras.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// VALIDACIONES EN TIEMPO REAL PARA COMPRAS
// ============================================

var MAXIMO_PERMITIDO = 99999999.99;

// 1. Validar proveedor
function validarProveedor() {
    var input = document.getElementById('id_proveedor');
    var errorDiv = document.getElementById('error-proveedor');
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

// 2. Validar fecha (no futura)
function validarFecha() {
    var input = document.getElementById('fecha');
    var errorDiv = document.getElementById('error-fecha');
    var valor = input.value;
    var hoy = new Date().toISOString().split('T')[0];
    
    if (valor === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        return true;
    }
    
    if (valor > hoy) {
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

// 3. Validar cantidad (solo números enteros positivos)
function validarCantidad(input) {
    var valor = input.value.trim();
    var errorDiv = input.parentElement.querySelector('.error-cantidad');
    var regex = /^[0-9]+$/;
    
    if (valor === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        calcularTotal();
        return true;
    }
    
    if (!regex.test(valor) || parseInt(valor) <= 0) {
        errorDiv.classList.add('show');
        input.classList.add('input-error');
        input.classList.remove('input-success');
        calcularTotal();
        return false;
    } else {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error');
        input.classList.add('input-success');
        calcularTotal();
        return true;
    }
}

// 4. Validar costo (solo números positivos, decimales opcionales, y límite)
function validarCosto(input) {
    var valor = input.value.trim();
    var errorDiv = input.parentElement.querySelector('.error-costo');
    var regex = /^[0-9]+(\.[0-9]{1,2})?$/;
    
    if (valor === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        calcularTotal();
        return true;
    }
    
    if (!regex.test(valor) || parseFloat(valor) <= 0) {
        errorDiv.classList.add('show');
        errorDiv.innerHTML = '<i class="fas fa-times-circle"></i> Solo números positivos (max: 99,999,999.99)';
        input.classList.add('input-error');
        input.classList.remove('input-success');
        calcularTotal();
        return false;
    }
    
    // Validar límite máximo
    if (parseFloat(valor) > MAXIMO_PERMITIDO) {
        errorDiv.classList.add('show');
        errorDiv.innerHTML = '<i class="fas fa-times-circle"></i> El costo no puede exceder ₡' + MAXIMO_PERMITIDO.toLocaleString('es-CR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        input.classList.add('input-error');
        input.classList.remove('input-success');
        calcularTotal();
        return false;
    }
    
    errorDiv.classList.remove('show');
    input.classList.remove('input-error');
    input.classList.add('input-success');
    calcularTotal();
    return true;
}

// 5. Agregar nueva fila de producto
function agregarProducto() {
    var container = document.getElementById('productos-container');
    var template = document.querySelector('.producto-row').cloneNode(true);
    
    // Limpiar valores
    template.querySelectorAll('select, input').forEach(el => el.value = '');
    
    // Limpiar clases de error
    template.querySelectorAll('.error-message').forEach(el => el.classList.remove('show'));
    template.querySelectorAll('select, input').forEach(el => {
        el.classList.remove('input-error', 'input-success');
    });
    
    container.appendChild(template);
    calcularTotal();
}

// 6. Eliminar fila de producto
function eliminarFila(btn) {
    if (document.querySelectorAll('.producto-row').length > 1) {
        btn.closest('.producto-row').remove();
        calcularTotal();
    } else {
        Swal.fire({ icon: 'warning', title: 'Error', text: 'Debe haber al menos un producto', confirmButtonColor: '#2c3e50' });
    }
}

// 7. Calcular total de la compra
function calcularTotal() {
    var total = 0;
    var cantidadProductos = 0;
    
    document.querySelectorAll('.producto-row').forEach(row => {
        var cantidad = row.querySelector('input[name="cantidades[]"]').value || 0;
        var costo = row.querySelector('input[name="costos[]"]').value || 0;
        
        if (cantidad > 0 && costo > 0) {
            total += parseFloat(cantidad) * parseFloat(costo);
            cantidadProductos++;
        }
    });
    
    document.getElementById('totalSpan').innerText = total.toLocaleString('es-CR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('totalInput').value = total.toFixed(2);
    
    actualizarResumen();
    return total;
}

// 8. Actualizar resumen en tiempo real
function actualizarResumen() {
    var proveedorSelect = document.getElementById('id_proveedor');
    var resumenDiv = document.getElementById('resumen-compra');
    var total = parseFloat(document.getElementById('totalInput').value) || 0;
    var cantidadProductos = 0;
    
    document.querySelectorAll('.producto-row').forEach(row => {
        var cantidad = row.querySelector('input[name="cantidades[]"]').value;
        if (cantidad > 0) cantidadProductos++;
    });
    
    if (proveedorSelect.value !== '') {
        var proveedorNombre = proveedorSelect.options[proveedorSelect.selectedIndex].text;
        document.getElementById('resumen-proveedor').innerHTML = '<i class="fas fa-truck"></i> <strong>Proveedor:</strong> ' + proveedorNombre;
        document.getElementById('resumen-cantidad-productos').innerHTML = '<i class="fas fa-box"></i> <strong>Productos:</strong> ' + cantidadProductos;
        document.getElementById('resumen-total').innerHTML = '<i class="fas fa-receipt"></i> <strong>Total a pagar:</strong> ₡' + total.toLocaleString('es-CR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        // Advertencia si el total excede el límite
        if (total > MAXIMO_PERMITIDO) {
            document.getElementById('resumen-total').innerHTML += '<br><span class="text-danger"><i class="fas fa-exclamation-triangle"></i> El total excede el límite de ₡' + MAXIMO_PERMITIDO.toLocaleString('es-CR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>';
        }
        
        resumenDiv.classList.add('show');
    } else {
        resumenDiv.classList.remove('show');
    }
}

// 9. Validar TODO el formulario antes de enviar
function validarFormulario(event) {
    event.preventDefault();
    
    var proveedorValido = validarProveedor();
    var fechaValida = validarFecha();
    
    var proveedor = document.getElementById('id_proveedor').value;
    var productos = document.querySelectorAll('select[name="productos[]"]');
    var total = parseFloat(document.getElementById('totalInput').value) || 0;
    
    if (proveedor === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Seleccione un proveedor', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    for (var i = 0; i < productos.length; i++) {
        if (productos[i].value === '') {
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Seleccione un producto para todas las filas', confirmButtonColor: '#2c3e50' });
            return false;
        }
    }
    
    // Validar cantidades y costos
    var filasValidas = true;
    document.querySelectorAll('.producto-row').forEach(row => {
        var cantidad = row.querySelector('input[name="cantidades[]"]').value;
        var costo = row.querySelector('input[name="costos[]"]').value;
        
        if (cantidad === '' || !/^[0-9]+$/.test(cantidad) || parseInt(cantidad) <= 0) {
            filasValidas = false;
        }
        if (costo === '' || !/^[0-9]+(\.[0-9]{1,2})?$/.test(costo) || parseFloat(costo) <= 0) {
            filasValidas = false;
        }
        if (parseFloat(costo) > MAXIMO_PERMITIDO) {
            filasValidas = false;
        }
    });
    
    if (!filasValidas) {
        Swal.fire({ icon: 'warning', title: 'Datos inválidos', text: 'Verifique que todas las cantidades y costos sean válidos (max: ₡' + MAXIMO_PERMITIDO.toLocaleString('es-CR') + ')', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (total <= 0) {
        Swal.fire({ icon: 'warning', title: 'Error', text: 'El total de la compra debe ser mayor a 0', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (total > MAXIMO_PERMITIDO) {
        Swal.fire({ icon: 'warning', title: 'Límite excedido', text: 'El total (₡' + total.toLocaleString('es-CR', {minimumFractionDigits: 2}) + ') excede el límite permitido de ₡' + MAXIMO_PERMITIDO.toLocaleString('es-CR', {minimumFractionDigits: 2}), confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    event.target.submit();
    return true;
}

// Inicializar eventos
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar validaciones
    validarProveedor();
    validarFecha();
    calcularTotal();
});

document.addEventListener('change', function(e) {
    if (e.target.matches('input[name="cantidades[]"], input[name="costos[]"]')) {
        calcularTotal();
    }
});

document.addEventListener('keyup', function(e) {
    if (e.target.matches('input[name="cantidades[]"], input[name="costos[]"]')) {
        calcularTotal();
    }
});

// Agregar event listener para proveedor
document.getElementById('id_proveedor').addEventListener('change', actualizarResumen);
</script>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>