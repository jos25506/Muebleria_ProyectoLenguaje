<?php
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$id = $_GET['id'];

if($_SERVER["REQUEST_METHOD"]=="POST"){

$stock_actual = $_POST['stock_actual'];
$stock_minimo = $_POST['stock_minimo'];

$sql="UPDATE MUEBLERIA.INVENTARIO
SET STOCK_ACTUAL=:actual,
STOCK_MINIMO=:minimo
WHERE ID_INVENTARIO=:id";

$stmt=oci_parse($conn,$sql);

oci_bind_by_name($stmt,":actual",$stock_actual);
oci_bind_by_name($stmt,":minimo",$stock_minimo);
oci_bind_by_name($stmt,":id",$id);

oci_execute($stmt);
oci_commit($conn);

echo "<script>
Swal.fire({
icon:'success',
title:'Inventario actualizado'
}).then(()=>window.location='inventario.php');
</script>";

}

/* obtener datos */

$sql="SELECT i.*,p.NOMBRE PRODUCTO
FROM MUEBLERIA.INVENTARIO i
LEFT JOIN MUEBLERIA.PRODUCTO p
ON i.ID_PRODUCTO=p.ID_PRODUCTO
WHERE ID_INVENTARIO=:id";

$stmt=oci_parse($conn,$sql);
oci_bind_by_name($stmt,":id",$id);
oci_execute($stmt);

$row=oci_fetch_assoc($stmt);
?>

<h1>Editar Inventario</h1>

<form method="POST">

<div class="mb-3">

<label>Producto</label>

<input type="text"
class="form-control"
value="<?php echo $row['PRODUCTO']; ?>"
readonly>

</div>

<div class="mb-3">

<label>Stock Actual</label>

<input type="number"
name="stock_actual"
class="form-control"
value="<?php echo $row['STOCK_ACTUAL']; ?>">

</div>

<div class="mb-3">

<label>Stock Mínimo</label>

<input type="number"
name="stock_minimo"
class="form-control"
value="<?php echo $row['STOCK_MINIMO']; ?>">

</div>

<button class="btn btn-warning">Actualizar</button>

<a href="inventario.php" class="btn btn-secondary">Volver</a>

</form>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>