<?php
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

if($_SERVER["REQUEST_METHOD"]=="POST"){

$producto = $_POST['producto'];
$stock_actual = $_POST['stock_actual'];
$stock_minimo = $_POST['stock_minimo'];

$sql="INSERT INTO MUEBLERIA.INVENTARIO
(ID_INVENTARIO,ID_PRODUCTO,STOCK_ACTUAL,STOCK_MINIMO)
VALUES(SEQ_INVENTARIO.NEXTVAL,:producto,:actual,:minimo)";

$stmt=oci_parse($conn,$sql);

oci_bind_by_name($stmt,":producto",$producto);
oci_bind_by_name($stmt,":actual",$stock_actual);
oci_bind_by_name($stmt,":minimo",$stock_minimo);

oci_execute($stmt);
oci_commit($conn);

echo "<script>
Swal.fire({
icon:'success',
title:'Inventario creado'
}).then(()=>window.location='inventario.php');
</script>";

}
?>

<h1>Nuevo Inventario</h1>

<form method="POST">

<div class="mb-3">

<label>Producto</label>

<select name="producto" class="form-control">

<?php

$sql="SELECT ID_PRODUCTO,NOMBRE FROM MUEBLERIA.PRODUCTO";

$s=oci_parse($conn,$sql);
oci_execute($s);

while($row=oci_fetch_assoc($s)){

echo "<option value='{$row['ID_PRODUCTO']}'>{$row['NOMBRE']}</option>";

}

?>

</select>
</div>

<div class="mb-3">
<label>Stock Actual</label>
<input type="number" name="stock_actual" class="form-control" required>
</div>

<div class="mb-3">
<label>Stock Mínimo</label>
<input type="number" name="stock_minimo" class="form-control" required>
</div>

<button class="btn btn-success">Guardar</button>

<a href="inventario.php" class="btn btn-secondary">Volver</a>

</form>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>