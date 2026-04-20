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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];
    $direccion = $_POST['direccion'];
    
    // Validaciones
    $errores = [];
    
    if (empty($nombre)) $errores[] = "El nombre es requerido";
    if (empty($telefono)) $errores[] = "El teléfono es requerido";
    if (empty($correo)) $errores[] = "El correo es requerido";
    if (empty($direccion)) $errores[] = "La dirección es requerida";
    
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido";
    }
    
    if (empty($errores)) {
        // Obtener el siguiente ID
        $query_id = "SELECT NVL(MAX(ID_PROVEEDOR), 0) + 1 as next_id FROM MUEBLERIA.PROVEEDOR";
        $stmt_id = oci_parse($conn, $query_id);
        oci_execute($stmt_id);
        $row_id = oci_fetch_assoc($stmt_id);
        $nuevo_id = $row_id['NEXT_ID'];
        
        $sql = "INSERT INTO MUEBLERIA.PROVEEDOR (ID_PROVEEDOR, NOMBRE, TELEFONO, CORREO, DIRECCION)
                VALUES (:id, :nombre, :telefono, :correo, :direccion)";
        
        $stmt = oci_parse($conn, $sql);
        
        oci_bind_by_name($stmt, ":id", $nuevo_id);
        oci_bind_by_name($stmt, ":nombre", $nombre);
        oci_bind_by_name($stmt, ":telefono", $telefono);
        oci_bind_by_name($stmt, ":correo", $correo);
        oci_bind_by_name($stmt, ":direccion", $direccion);
        
        if (oci_execute($stmt)) {
            oci_commit($conn);
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '¡Proveedor guardado!',
                    text: 'El proveedor \"$nombre\" ha sido creado exitosamente',
                    confirmButtonColor: '#2c3e50',
                    confirmButtonText: 'Ver proveedores'
                }).then(() => window.location.href = 'proveedores.php');
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
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-truck"></i> Nuevo Proveedor
    </div>
    <div class="card-body">
        <form method="POST" onsubmit="return validarFormulario(event)">
            <!-- Campo NOMBRE - solo letras -->
            <div class="mb-3">
                <label for="nombre" class="form-label">
                    <i class="fas fa-building"></i> Nombre *
                    
                </label>
                <input type="text" name="nombre" id="nombre" class="form-control" 
                       placeholder="Ej: Maderas del Norte"
                       onkeyup="validarNombre()"
                       onblur="validarNombre()"
                       required>
                <div id="error-nombre" class="error-message">
                    <i class="fas fa-times-circle"></i> El nombre solo debe contener letras y espacios
                </div>
            </div>

            <!-- Campo TELÉFONO - solo números, 8 dígitos -->
            <div class="mb-3">
                <label for="telefono" class="form-label">
                    <i class="fas fa-phone"></i> Teléfono *
                    <small class="text-muted">(8 dígitos, solo números)</small>
                </label>
                <input type="text" name="telefono" id="telefono" class="form-control" 
                       placeholder="Ej: 60010001"
                       onkeyup="validarTelefono()"
                       onblur="validarTelefono()"
                       maxlength="8"
                       required>
                <div id="error-telefono" class="error-message">
                    <i class="fas fa-times-circle"></i> El teléfono debe tener exactamente 8 dígitos numéricos
                </div>
            </div>

            <!-- Campo CORREO - formato email -->
            <div class="mb-3">
                <label for="correo" class="form-label">
                    <i class="fas fa-envelope"></i> Correo electrónico *
                    <small class="text-muted">(ejemplo@dominio.com)</small>
                </label>
                <input type="email" name="correo" id="correo" class="form-control" 
                       placeholder="proveedor@empresa.com"
                       onkeyup="validarCorreo()"
                       onblur="validarCorreo()"
                       required>
                <div id="error-correo" class="error-message">
                    <i class="fas fa-times-circle"></i> Ingrese un correo electrónico válido (ejemplo@dominio.com)
                </div>
            </div>

            <!-- Campo DIRECCIÓN - letras, números, espacios, #, - -->
            <div class="mb-3">
                <label for="direccion" class="form-label">
                    <i class="fas fa-map-marker-alt"></i> Dirección *
                    
                </label>
                <input type="text" name="direccion" id="direccion" class="form-control" 
                       placeholder="Ej: San José, Zona Industrial"
                       onkeyup="validarDireccion()"
                       onblur="validarDireccion()"
                       required>
                <div id="error-direccion" class="error-message">
                    <i class="fas fa-times-circle"></i> La dirección contiene caracteres no válidos
                </div>
            </div>

            <hr>

            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Guardar Proveedor
                </button>
                <a href="proveedores.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// VALIDACIONES EN TIEMPO REAL PARA PROVEEDORES
// ============================================

// 1. Validar NOMBRE (solo letras y espacios)
function validarNombre() {
    var input = document.getElementById('nombre');
    var errorDiv = document.getElementById('error-nombre');
    var valor = input.value.trim();
    var regex = /^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]*$/;
    
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

// 2. Validar TELÉFONO (solo números, exactamente 8 dígitos)
function validarTelefono() {
    var input = document.getElementById('telefono');
    var errorDiv = document.getElementById('error-telefono');
    var valor = input.value.trim();
    var regex = /^[0-9]{8}$/;
    
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

// 3. Validar CORREO (formato email)
function validarCorreo() {
    var input = document.getElementById('correo');
    var errorDiv = document.getElementById('error-correo');
    var valor = input.value.trim();
    var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
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

// 4. Validar DIRECCIÓN (letras, números, espacios, #, -, .)
function validarDireccion() {
    var input = document.getElementById('direccion');
    var errorDiv = document.getElementById('error-direccion');
    var valor = input.value.trim();
    var regex = /^[a-zA-Z0-9áéíóúñÁÉÍÓÚÑ\s\#\-\.]+$/;
    
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

// Validar TODO el formulario antes de enviar
function validarFormulario(event) {
    event.preventDefault();
    
    var nombreValido = validarNombre();
    var telefonoValido = validarTelefono();
    var correoValido = validarCorreo();
    var direccionValido = validarDireccion();
    
    var nombre = document.getElementById('nombre').value.trim();
    var telefono = document.getElementById('telefono').value.trim();
    var correo = document.getElementById('correo').value.trim();
    var direccion = document.getElementById('direccion').value.trim();
    
    if (nombre === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese el nombre del proveedor', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (!nombreValido) {
        Swal.fire({ icon: 'warning', title: 'Error en nombre', text: 'El nombre solo debe contener letras', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (telefono === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese el teléfono', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (!telefonoValido) {
        Swal.fire({ icon: 'warning', title: 'Error en teléfono', text: 'El teléfono debe tener 8 dígitos numéricos', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (correo === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese el correo electrónico', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (!correoValido) {
        Swal.fire({ icon: 'warning', title: 'Correo inválido', text: 'Ingrese un correo electrónico válido', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (direccion === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese la dirección', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (!direccionValido) {
        Swal.fire({ icon: 'warning', title: 'Dirección inválida', text: 'La dirección contiene caracteres no permitidos', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    event.target.submit();
    return true;
}
</script>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>