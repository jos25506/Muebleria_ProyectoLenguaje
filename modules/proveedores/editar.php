<?php
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$id = $_GET['id'];

if($_SERVER["REQUEST_METHOD"]=="POST"){

$nombre = $_POST['nombre'];
$telefono = $_POST['telefono'];
$correo = $_POST['correo'];
$direccion = $_POST['direccion'];

$sql = "UPDATE MUEBLERIA.PROVEEDOR
SET NOMBRE=:nombre,
TELEFONO=:telefono,
CORREO=:correo,
DIRECCION=:direccion
WHERE ID_PROVEEDOR=:id";

$stmt = oci_parse($conn,$sql);

oci_bind_by_name($stmt,":nombre",$nombre);
oci_bind_by_name($stmt,":telefono",$telefono);
oci_bind_by_name($stmt,":correo",$correo);
oci_bind_by_name($stmt,":direccion",$direccion);
oci_bind_by_name($stmt,":id",$id);

oci_execute($stmt);

oci_commit($conn);

echo "<script>
Swal.fire({
icon:'success',
title:'Proveedor actualizado'
}).then(()=>window.location='proveedores.php');
</script>";

}

$sql = "SELECT * FROM MUEBLERIA.PROVEEDOR
WHERE ID_PROVEEDOR=:id";

$stmt = oci_parse($conn,$sql);
oci_bind_by_name($stmt,":id",$id);
oci_execute($stmt);

$row = oci_fetch_assoc($stmt);
?>

<h1>Editar Proveedor</h1>

<form method="POST">

<div class="mb-3">
<label>Nombre</label>
<input type="text" name="nombre"
class="form-control"
value="<?php echo $row['NOMBRE']; ?>">
</div>

<div class="mb-3">
<label>Teléfono</label>
<input type="text" name="telefono"
class="form-control"
value="<?php echo $row['TELEFONO']; ?>">
</div>

<div class="mb-3">
<label>Correo</label>
<input type="email" name="correo"
class="form-control"
value="<?php echo $row['CORREO']; ?>">
</div>

<div class="mb-3">
<label>Dirección</label>
<input type="text" name="direccion"
class="form-control"
value="<?php echo $row['DIRECCION']; ?>">
</div>

<button class="btn btn-warning">
Actualizar
</button>

<a href="proveedores.php" class="btn btn-secondary">
Volver
</a>

</form>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>