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

// Obtener categorías
$query_cat = "SELECT * FROM MUEBLERIA.CATEGORIA ORDER BY NOMBRE_CATEGORIA";
$stmt_cat = oci_parse($conn, $query_cat);
oci_execute($stmt_cat);

// Obtener proveedores
$query_prov = "SELECT * FROM MUEBLERIA.PROVEEDOR ORDER BY NOMBRE";
$stmt_prov = oci_parse($conn, $query_prov);
oci_execute($stmt_prov);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = $_POST['descripcion'];
    $precio = floatval($_POST['precio']);
    $madera = $_POST['madera'];
    $medidas = $_POST['medidas'];
    $foto_url = $_POST['foto_url'];
    $id_categoria = $_POST['id_categoria'];
    $id_proveedor = $_POST['id_proveedor'];
    $estado = $_POST['estado'];
    
    // Validaciones
    $errores = [];
    
    if (empty($nombre)) $errores[] = "El nombre es requerido";
    if (empty($precio)) $errores[] = "El precio es requerido";
    if (empty($id_categoria)) $errores[] = "Debe seleccionar una categoría";
    if (empty($id_proveedor)) $errores[] = "Debe seleccionar un proveedor";
    
    // ============================================
    // VALIDACIÓN DE NOMBRE ÚNICO
    // ============================================
    $query_check = "SELECT COUNT(*) as total FROM MUEBLERIA.PRODUCTO WHERE NOMBRE = :nombre";
    $stmt_check = oci_parse($conn, $query_check);
    oci_bind_by_name($stmt_check, ':nombre', $nombre);
    oci_execute($stmt_check);
    $row_check = oci_fetch_assoc($stmt_check);
    
    if ($row_check['TOTAL'] > 0) {
        $errores[] = "Ya existe un producto con el nombre '$nombre'. Por favor use otro nombre.";
    }
    // ============================================
    
    // ============================================
    // VALIDACIÓN DE RANGO PARA NUMBER(10,2)
    // El valor máximo es 99,999,999.99
    // ============================================
    $maximo_permitido = 99999999.99;
    
    if ($precio > $maximo_permitido) {
        $errores[] = "El precio (₡" . number_format($precio, 2) . ") excede el límite permitido de ₡" . number_format($maximo_permitido, 2);
    }
    
    if ($precio <= 0) {
        $errores[] = "El precio debe ser mayor a 0";
    }
    
    // Validar formato de nombre (solo letras)
    if (!empty($nombre) && !preg_match("/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]+$/", $nombre)) {
        $errores[] = "El nombre solo debe contener letras y espacios";
    }
    
    // Validar formato de madera (solo letras)
    if (!empty($madera) && !preg_match("/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]+$/", $madera)) {
        $errores[] = "La madera solo debe contener letras";
    }
    
    // Validar formato de medidas
    if (!empty($medidas) && !preg_match("/^[0-9]+x[0-9]+x[0-9]+$/", $medidas)) {
        $errores[] = "Las medidas deben tener el formato: 45x45x90";
    }
    
    // Validar URL de imagen
    if (!empty($foto_url) && !filter_var($foto_url, FILTER_VALIDATE_URL)) {
        $errores[] = "La URL de la imagen no es válida";
    }
    // ============================================
    
    if (empty($foto_url)) {
        $foto_url = 'https://via.placeholder.com/300x300?text=Sin+Imagen';
    }
    
    if (empty($errores)) {
        $query_id = "SELECT NVL(MAX(ID_PRODUCTO), 0) + 1 as ID FROM MUEBLERIA.PRODUCTO";
        $stmt_id = oci_parse($conn, $query_id);
        oci_execute($stmt_id);
        $row_id = oci_fetch_assoc($stmt_id);
        $nuevo_id = $row_id['ID'];
        
        $query = "INSERT INTO MUEBLERIA.PRODUCTO 
                  (ID_PRODUCTO, NOMBRE, DESCRIPCION, PRECIO, MADERA, MEDIDAS, FOTO_URL, ESTADO, ID_CATEGORIA, ID_PROVEEDOR)
                  VALUES 
                  (:id, :nombre, :descripcion, :precio, :madera, :medidas, :foto_url, :estado, :id_categoria, :id_proveedor)";
        
        $stmt = oci_parse($conn, $query);
        oci_bind_by_name($stmt, ':id', $nuevo_id);
        oci_bind_by_name($stmt, ':nombre', $nombre);
        oci_bind_by_name($stmt, ':descripcion', $descripcion);
        
        // Convertir el precio a formato Oracle (coma decimal)
        $precio_oracle = str_replace('.', ',', $precio);
        oci_bind_by_name($stmt, ':precio', $precio_oracle);
        
        oci_bind_by_name($stmt, ':madera', $madera);
        oci_bind_by_name($stmt, ':medidas', $medidas);
        oci_bind_by_name($stmt, ':foto_url', $foto_url);
        oci_bind_by_name($stmt, ':estado', $estado);
        oci_bind_by_name($stmt, ':id_categoria', $id_categoria);
        oci_bind_by_name($stmt, ':id_proveedor', $id_proveedor);
        
        if (oci_execute($stmt)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '¡Producto guardado!',
                    text: 'El producto \"$nombre\" ha sido creado exitosamente',
                    confirmButtonColor: '#2c3e50',
                    confirmButtonText: 'Ver productos'
                }).then((result) => {
                    window.location.href = 'productos.php';
                });
            </script>";
        } else {
            $error = oci_error($stmt);
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
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
.image-preview {
    width: 200px;
    height: 200px;
    object-fit: cover;
    border-radius: 10px;
    border: 2px dashed #ddd;
    padding: 5px;
    margin-top: 10px;
    display: none;
}

.image-preview.show {
    display: block;
}

.image-preview-container {
    text-align: center;
    margin-bottom: 20px;
}

.help-text {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 5px;
}

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
        <i class="fas fa-plus-circle"></i> Nuevo Producto
        <small class="text-muted float-end">Precio máximo: ₡99,999,999.99</small>
    </div>
    <div class="card-body">
        <form method="POST" onsubmit="return validarFormulario(event)" id="formProducto">
            <div class="row">
                <!-- Columna izquierda - Imagen -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-image"></i> Imagen del Producto
                        </div>
                        <div class="card-body text-center">
                            <div class="image-preview-container">
                                <img id="preview" class="image-preview show" 
                                     src="https://via.placeholder.com/300x300?text=Previsualización" 
                                     alt="Vista previa">
                            </div>
                            <div class="mb-3">
                                <label for="foto_url" class="form-label">URL de la imagen</label>
                                <input type="url" class="form-control" id="foto_url" name="foto_url" 
                                       placeholder="https://ejemplo.com/imagen.jpg"
                                       onchange="actualizarPreview(this.value)">
                                <div class="help-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Puedes usar imágenes de Freepik, Pexels, Unsplash, etc.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Columna derecha - Datos del producto -->
                <div class="col-md-8">
                    <div class="row">
                        <!-- Campo NOMBRE - con validación en tiempo real -->
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">
                                <i class="fas fa-tag"></i> Nombre * 
                               
                            </label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required 
                                   placeholder="Ej: Silla Clásica"
                                   onkeyup="validarNombre(); validarNombreUnico()"
                                   onblur="validarNombre(); validarNombreUnico()">
                            <div id="error-nombre" class="error-message">
                                <i class="fas fa-times-circle"></i> El nombre solo debe contener letras y espacios
                            </div>
                            <div id="error-nombre-unico" class="error-message">
                                <i class="fas fa-times-circle"></i> Ya existe un producto con este nombre
                            </div>
                        </div>
                        
                        <!-- Campo PRECIO - con validación en tiempo real -->
                        <div class="col-md-6 mb-3">
                            <label for="precio" class="form-label">
                                <i class="fas fa-dollar-sign"></i> Precio * 
                                <
                            </label>
                            <input type="text" class="form-control" id="precio" name="precio" 
                                   required placeholder="0.00"
                                   onkeyup="validarPrecio()"
                                   onblur="validarPrecio()">
                            <div id="error-precio" class="error-message">
                                <i class="fas fa-times-circle"></i> El precio debe ser un número válido mayor a 0 (max: ₡99,999,999.99)
                            </div>
                        </div>
                        
                        <!-- Campo DESCRIPCIÓN -->
                        <div class="col-md-12 mb-3">
                            <label for="descripcion" class="form-label">
                                <i class="fas fa-align-left"></i> Descripción
                            </label>
                            <textarea class="form-control" id="descripcion" name="descripcion" 
                                      rows="3" placeholder="Descripción del producto"></textarea>
                        </div>
                        
                        <!-- Campo MADERA - solo letras -->
                        <div class="col-md-6 mb-3">
                            <label for="madera" class="form-label">
                                <i class="fas fa-tree"></i> Madera 
                                
                            </label>
                            <input type="text" class="form-control" id="madera" name="madera" 
                                   placeholder="Ej: Roble, Cedro, Pino..."
                                   onkeyup="validarMadera()"
                                   onblur="validarMadera()">
                            <div id="error-madera" class="error-message">
                                <i class="fas fa-times-circle"></i> La madera solo debe contener letras
                            </div>
                        </div>
                        
                        <!-- Campo MEDIDAS - formato 45x45x90 -->
                        <div class="col-md-6 mb-3">
                            <label for="medidas" class="form-label">
                                <i class="fas fa-ruler"></i> Medidas 
                            
                            </label>
                            <input type="text" class="form-control" id="medidas" name="medidas" 
                                   placeholder="Ej: 45x45x90"
                                   onkeyup="validarMedidas()"
                                   onblur="validarMedidas()">
                            <div id="error-medidas" class="error-message">
                                <i class="fas fa-times-circle"></i> Las medidas deben tener el formato: 45x45x90 (números separados por x)
                            </div>
                        </div>
                        
                        <!-- Campo CATEGORÍA -->
                        <div class="col-md-4 mb-3">
                            <label for="id_categoria" class="form-label">
                                <i class="fas fa-list"></i> Categoría *
                            </label>
                            <select class="form-control" id="id_categoria" name="id_categoria" required>
                                <option value="">Seleccione...</option>
                                <?php 
                                oci_execute($stmt_cat);
                                while ($cat = oci_fetch_assoc($stmt_cat)): 
                                ?>
                                <option value="<?php echo $cat['ID_CATEGORIA']; ?>">
                                    <?php echo $cat['NOMBRE_CATEGORIA']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Campo PROVEEDOR -->
                        <div class="col-md-4 mb-3">
                            <label for="id_proveedor" class="form-label">
                                <i class="fas fa-truck"></i> Proveedor *
                            </label>
                            <select class="form-control" id="id_proveedor" name="id_proveedor" required>
                                <option value="">Seleccione...</option>
                                <?php 
                                oci_execute($stmt_prov);
                                while ($prov = oci_fetch_assoc($stmt_prov)): 
                                ?>
                                <option value="<?php echo $prov['ID_PROVEEDOR']; ?>">
                                    <?php echo $prov['NOMBRE']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Campo ESTADO -->
                        <div class="col-md-4 mb-3">
                            <label for="estado" class="form-label">
                                <i class="fas fa-circle"></i> Estado
                            </label>
                            <select class="form-control" id="estado" name="estado">
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Guardar Producto
                </button>
                <a href="productos.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// VALIDACIONES EN TIEMPO REAL
// ============================================

var MAXIMO_PERMITIDO = 99999999.99;
var timeoutNombre = null;

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

// 2. Validar que el nombre sea único (usando AJAX)
function validarNombreUnico() {
    var nombre = document.getElementById('nombre').value.trim();
    var errorDiv = document.getElementById('error-nombre-unico');
    var input = document.getElementById('nombre');
    
    if (nombre === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        return;
    }
    
    // Limpiar timeout anterior
    if (timeoutNombre) clearTimeout(timeoutNombre);
    
    // Esperar a que el usuario termine de escribir (debounce)
    timeoutNombre = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'verificar_nombre.php?nombre=' + encodeURIComponent(nombre), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var respuesta = JSON.parse(xhr.responseText);
                if (respuesta.existe) {
                    errorDiv.classList.add('show');
                    input.classList.add('input-error');
                    input.classList.remove('input-success');
                } else {
                    errorDiv.classList.remove('show');
                    input.classList.remove('input-error');
                    if (validarNombre()) {
                        input.classList.add('input-success');
                    }
                }
            }
        };
        xhr.send();
    }, 500);
}

// 3. Validar PRECIO (solo números, decimales opcionales, con límite)
function validarPrecio() {
    var input = document.getElementById('precio');
    var errorDiv = document.getElementById('error-precio');
    var valor = input.value.trim();
    var regex = /^[0-9]+(\.[0-9]{1,2})?$/;
    
    if (valor === '') {
        errorDiv.classList.remove('show');
        input.classList.remove('input-error', 'input-success');
        return true;
    }
    
    if (!regex.test(valor) || parseFloat(valor) <= 0) {
        errorDiv.classList.add('show');
        errorDiv.innerHTML = '<i class="fas fa-times-circle"></i> El precio debe ser un número positivo (ej: 10000 o 10000.50)';
        input.classList.add('input-error');
        input.classList.remove('input-success');
        return false;
    }
    
    if (parseFloat(valor) > MAXIMO_PERMITIDO) {
        errorDiv.classList.add('show');
        errorDiv.innerHTML = '<i class="fas fa-times-circle"></i> El precio no puede exceder ₡' + MAXIMO_PERMITIDO.toLocaleString('es-CR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        input.classList.add('input-error');
        input.classList.remove('input-success');
        return false;
    }
    
    errorDiv.classList.remove('show');
    input.classList.remove('input-error');
    input.classList.add('input-success');
    return true;
}

// 4. Validar MADERA (solo letras)
function validarMadera() {
    var input = document.getElementById('madera');
    var errorDiv = document.getElementById('error-madera');
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

// 5. Validar MEDIDAS (formato: número x número x número)
function validarMedidas() {
    var input = document.getElementById('medidas');
    var errorDiv = document.getElementById('error-medidas');
    var valor = input.value.trim();
    var regex = /^[0-9]+x[0-9]+x[0-9]+$/;
    
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

// Función para actualizar la vista previa de la imagen
function actualizarPreview(url) {
    var preview = document.getElementById('preview');
    if (url.trim() !== '') {
        preview.src = url;
        preview.classList.add('show');
        preview.onerror = function() {
            this.src = 'https://via.placeholder.com/300x300?text=Error+al+cargar';
        };
    } else {
        preview.src = 'https://via.placeholder.com/300x300?text=Previsualización';
    }
}

// Validar TODO el formulario antes de enviar
function validarFormulario(event) {
    event.preventDefault();
    
    var nombreValido = validarNombre();
    var precioValido = validarPrecio();
    var maderaValido = validarMadera();
    var medidasValido = validarMedidas();
    
    var nombre = document.getElementById('nombre').value.trim();
    var precio = document.getElementById('precio').value.trim();
    var id_categoria = document.getElementById('id_categoria').value;
    var id_proveedor = document.getElementById('id_proveedor').value;
    var foto_url = document.getElementById('foto_url').value;
    
    if (nombre === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese el nombre del producto', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (!nombreValido) {
        Swal.fire({ icon: 'warning', title: 'Error en nombre', text: 'El nombre solo debe contener letras', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (precio === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingrese el precio', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (!precioValido) {
        Swal.fire({ icon: 'warning', title: 'Error en precio', text: 'El precio debe ser un número válido mayor a 0 (max: ₡' + MAXIMO_PERMITIDO.toLocaleString('es-CR', {minimumFractionDigits: 2}) + ')', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (id_categoria === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor seleccione una categoría', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (id_proveedor === '') {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor seleccione un proveedor', confirmButtonColor: '#2c3e50' });
        return false;
    }
    
    if (foto_url.trim() !== '') {
        var urlPattern = /^(http|https):\/\/[^ "]+$/;
        if (!urlPattern.test(foto_url)) {
            Swal.fire({ icon: 'warning', title: 'URL no válida', text: 'Por favor ingrese una URL válida para la imagen', confirmButtonColor: '#2c3e50' });
            return false;
        }
    }
    
    event.target.submit();
    return true;
}

// Inicializar la vista previa
document.addEventListener('DOMContentLoaded', function() {
    var fotoUrl = document.getElementById('foto_url');
    if (fotoUrl.value) {
        actualizarPreview(fotoUrl.value);
    }
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>