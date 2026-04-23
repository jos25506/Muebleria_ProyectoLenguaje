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

$id = $_GET['id'] ?? 0;

// Obtener datos del cliente
$query = "SELECT * FROM MUEBLERIA.CLIENTE WHERE ID_CLIENTE = :id";
$stmt = oci_parse($conn, $query);
oci_bind_by_name($stmt, ':id', $id);
oci_execute($stmt);
$cliente = oci_fetch_assoc($stmt);

if (!$cliente) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Cliente no encontrado',
            text: 'El cliente que intenta editar no existe',
            confirmButtonColor: '#2c3e50'
        }).then((result) => {
            window.location.href = 'clientes.php';
        });
    </script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];
    $direccion = $_POST['direccion'];
    $id = $_POST['id'];
    
    // Validaciones
    $errores = [];
    
    if (empty($nombre)) $errores[] = "El nombre es requerido";
    if (empty($telefono)) $errores[] = "El teléfono es requerido";
    if (empty($correo)) $errores[] = "El correo es requerido";
    if (empty($direccion)) $errores[] = "La dirección es requerida";
    
    // Validación de nombre (solo letras y espacios)
    if (!preg_match("/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]+$/", $nombre)) {
        $errores[] = "El nombre solo debe contener letras y espacios";
    }
    
    // Validación de teléfono (exactamente 8 dígitos)
    if (!preg_match("/^[0-9]{8}$/", $telefono)) {
        $errores[] = "El teléfono debe tener exactamente 8 dígitos numéricos";
    }
    
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido";
    }
    
    // Validación de dirección (letras, números, espacios, #, -, .)
    if (!preg_match("/^[a-zA-Z0-9áéíóúñÁÉÍÓÚÑ\s\#\-\.]+$/", $direccion)) {
        $errores[] = "La dirección contiene caracteres no válidos";
    }
    
    if (empty($errores)) {
        // Actualizar cliente
        $query = "UPDATE MUEBLERIA.CLIENTE 
                  SET NOMBRE = :nombre, TELEFONO = :telefono, 
                      CORREO = :correo, DIRECCION = :direccion
                  WHERE ID_CLIENTE = :id";
        
        $stmt = oci_parse($conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_bind_by_name($stmt, ':nombre', $nombre);
        oci_bind_by_name($stmt, ':telefono', $telefono);
        oci_bind_by_name($stmt, ':correo', $correo);
        oci_bind_by_name($stmt, ':direccion', $direccion);
        
        try {
            if (!@oci_execute($stmt)) {
                $e = oci_error($stmt);
                throw new Exception($e['message']);
            }
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '¡Cliente actualizado!',
                    text: 'El cliente \"$nombre\" ha sido actualizado exitosamente',
                    confirmButtonColor: '#2c3e50'
                }).then(() => {
                    window.location.href = 'clientes.php';
                });
            </script>";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            
            if (strpos($error, 'ORA-20101') !== false) {
                $msg = "Nombre demasiado corto";
            } elseif (strpos($error, 'ORA-20102') !== false) {
                $msg = "Dirección demasiado corta";
            } else {
                $msg = "Error al actualizar: " . $error;
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
        <i class="fas fa-user-edit"></i> Editar Cliente: <?php echo htmlspecialchars($cliente['NOMBRE']); ?>
    </div>
    <div class="card-body">
        <form method="POST" onsubmit="return validarFormulario(event)">
            <input type="hidden" name="id" value="<?php echo $cliente['ID_CLIENTE']; ?>">
            
            <div class="row">
                <!-- Campo NOMBRE - solo letras -->
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">
                        <i class="fas fa-user"></i> Nombre completo *
                    </label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required 
                           placeholder="Ingrese el nombre completo"
                           value="<?php echo htmlspecialchars($cliente['NOMBRE']); ?>"
                           onkeyup="validarNombre()"
                           onblur="validarNombre()">
                    <div id="error-nombre" class="error-message">
                        <i class="fas fa-times-circle"></i> El nombre solo debe contener letras y espacios
                    </div>
                </div>
                
                <!-- Campo TELÉFONO - solo números, 8 dígitos -->
                <div class="col-md-6 mb-3">
                    <label for="telefono" class="form-label">
                        <i class="fas fa-phone"></i> Teléfono *
                    </label>
                    <input type="text" class="form-control" id="telefono" name="telefono" required 
                           placeholder="Ej: 70020001"
                           value="<?php echo htmlspecialchars($cliente['TELEFONO']); ?>"
                           onkeyup="validarTelefono()"
                           onblur="validarTelefono()"
                           maxlength="8">
                    <div id="error-telefono" class="error-message">
                        <i class="fas fa-times-circle"></i> El teléfono debe tener exactamente 8 dígitos numéricos
                    </div>
                </div>
                
                <!-- Campo CORREO - formato email -->
                <div class="col-md-6 mb-3">
                    <label for="correo" class="form-label">
                        <i class="fas fa-envelope"></i> Correo electrónico *
                    </label>
                    <input type="email" class="form-control" id="correo" name="correo" required 
                           placeholder="cliente@email.com"
                           value="<?php echo htmlspecialchars($cliente['CORREO']); ?>"
                           onkeyup="validarCorreo()"
                           onblur="validarCorreo()">
                    <div id="error-correo" class="error-message">
                        <i class="fas fa-times-circle"></i> Ingrese un correo electrónico válido (ejemplo@dominio.com)
                    </div>
                </div>
                
                <!-- Campo DIRECCIÓN - letras, números, espacios, #, - -->
                <div class="col-md-6 mb-3">
                    <label for="direccion" class="form-label">
                        <i class="fas fa-map-marker-alt"></i> Dirección *
                    </label>
                    <input type="text" class="form-control" id="direccion" name="direccion" required 
                           placeholder="Ej: San José, Calle 5, Casa #10"
                           value="<?php echo htmlspecialchars($cliente['DIRECCION']); ?>"
                           onkeyup="validarDireccion()"
                           onblur="validarDireccion()">
                    <div id="error-direccion" class="error-message">
                        <i class="fas fa-times-circle"></i> La dirección contiene caracteres no válidos
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Actualizar Cliente
                </button>
                <a href="clientes.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// VALIDACIONES EN TIEMPO REAL PARA CLIENTES
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
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese el nombre del cliente', confirmButtonColor: '#2c3e50' });
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

// Al cargar la página, validar los campos existentes
document.addEventListener('DOMContentLoaded', function() {
    validarNombre();
    validarTelefono();
    validarCorreo();
    validarDireccion();
});
</script>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>