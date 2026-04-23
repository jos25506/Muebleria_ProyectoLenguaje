<?php
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("<div class='alert alert-danger'>Error de conexión</div>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

$cliente = $_POST['cliente'];
$producto = $_POST['producto'];
$cantidad = $_POST['cantidad'];
$estado = $_POST['estado'];

/* obtener precio */

$sqlPrecio = "SELECT PRECIO 
FROM MUEBLERIA.PRODUCTO 
WHERE ID_PRODUCTO = :producto";

$stmtPrecio = oci_parse($conn,$sqlPrecio);

oci_bind_by_name($stmtPrecio,":producto",$producto);

oci_execute($stmtPrecio);

$rowPrecio = oci_fetch_assoc($stmtPrecio);

$precio = $rowPrecio['PRECIO'];

$subtotal = $precio * $cantidad;

/* crear pedido */

$sqlPedido = "INSERT INTO MUEBLERIA.PEDIDO
(ID_PEDIDO,FECHA,ESTADO,TOTAL,ID_CLIENTE)
VALUES(SEQ_PEDIDO.NEXTVAL,SYSDATE,:estado,:total,:cliente)
RETURNING ID_PEDIDO INTO :id";

$stmtPedido = oci_parse($conn,$sqlPedido);

oci_bind_by_name($stmtPedido,":estado",$estado);
oci_bind_by_name($stmtPedido,":total",$subtotal);
oci_bind_by_name($stmtPedido,":cliente",$cliente);
oci_bind_by_name($stmtPedido,":id",$idPedido,32);

oci_execute($stmtPedido);

/* crear detalle */

$sqlDetalle = "INSERT INTO MUEBLERIA.DETALLE_PEDIDO
(ID_DETALLE,CANTIDAD,PRECIO_UNITARIO,SUB_TOTAL,ID_PEDIDO,ID_PRODUCTO)
VALUES(SEQ_DETALLE_PEDIDO.NEXTVAL,:cantidad,:precio,:subtotal,:pedido,:producto)";

$stmtDetalle = oci_parse($conn,$sqlDetalle);

oci_bind_by_name($stmtDetalle,":cantidad",$cantidad);
oci_bind_by_name($stmtDetalle,":precio",$precio);
oci_bind_by_name($stmtDetalle,":subtotal",$subtotal);
oci_bind_by_name($stmtDetalle,":pedido",$idPedido);
oci_bind_by_name($stmtDetalle,":producto",$producto);

oci_execute($stmtDetalle);

oci_commit($conn);

echo "<script>
Swal.fire({
icon:'success',
title:'Pedido creado correctamente'
}).then(()=>window.location='pedidos.php');
</script>";

}
?>

<h1>Nuevo Pedido</h1>

<form method="POST">

<div class="mb-3">
<label>Cliente</label>

<select name="cliente" class="form-control">

<?php

$sql="SELECT ID_CLIENTE,NOMBRE 
FROM MUEBLERIA.CLIENTE";

$s=oci_parse($conn,$sql);

oci_execute($s);

while($row=oci_fetch_assoc($s)){

echo "<option value='{$row['ID_CLIENTE']}'>{$row['NOMBRE']}</option>";

}

?>

</select>
</div>


<div class="mb-3">
<label>Producto</label>

<select name="producto" class="form-control">

<?php

$sql="SELECT ID_PRODUCTO,NOMBRE 
FROM MUEBLERIA.PRODUCTO";

$s=oci_parse($conn,$sql);

oci_execute($s);

while($row=oci_fetch_assoc($s)){

echo "<option value='{$row['ID_PRODUCTO']}'>{$row['NOMBRE']}</option>";

}

?>

</select>
</div>


<div class="mb-3">
<label>Cantidad</label>
<input type="number" name="cantidad" class="form-control" required>
</div>


<div class="mb-3">
<label>Estado</label>

<select name="estado" class="form-control" required>

<option value="PENDIENTE">PENDIENTE</option>
<option value="ENVIADO">ENVIADO</option>
<option value="ENTREGADO">ENTREGADO</option>
<option value="CANCELADO">CANCELADO</option>

</select>

</div>


<button class="btn btn-success">Guardar</button>

<a href="pedidos.php" class="btn btn-secondary">
Volver
</a>

</form>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>