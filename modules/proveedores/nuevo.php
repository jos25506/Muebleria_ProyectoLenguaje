<?php
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

if($_SERVER["REQUEST_METHOD"]=="POST"){

$nombre = $_POST['nombre'];
$telefono = $_POST['telefono'];
$correo = $_POST['correo'];
$direccion = $_POST['direccion'];

$sql = "INSERT INTO MUEBLERIA.PROVEEDOR
(NOMBRE,TELEFONO,CORREO,DIRECCION)
VALUES(:nombre,:telefono,:correo,:direccion)";

$stmt = oci_parse($conn,$sql);

oci_bind_by_name($stmt,":nombre",$nombre);
oci_bind_by_name($stmt,":telefono",$telefono);
oci_bind_by_name($stmt,":correo",$correo);
oci_bind_by_name($stmt,":direccion",$direccion);

oci_execute($stmt);

oci_commit($conn);

echo "<script>
Swal.fire({
icon:'success',
title:'Proveedor creado'
}).then(()=>window.location='proveedores.php');
</script>";

}
?>

<h1>Nuevo Proveedor</h1>

<form method="POST">

<div class="mb-3">
<label>Nombre</label>
<input type="text" name="nombre" class="form-control" required>
</div>

<div class="mb-3">
<label>Teléfono</label>
<input type="text" name="telefono" class="form-control">
</div>

<div class="mb-3">
<label>Correo</label>
<input type="email" name="correo" class="form-control">
</div>

<div class="mb-3">
<label>Dirección</label>
<input type="text" name="direccion" class="form-control">
</div>

<button class="btn btn-success">Guardar</button>

<a href="proveedores.php" class="btn btn-secondary">
Volver
</a>

</form>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>