
<?php
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("<div class='alert alert-danger'>Error de conexión</div>");
}

if(!isset($_GET['id'])){
    die("ID no especificado");
}

$id = $_GET['id'];

/* ACTUALIZAR PEDIDO */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

$cliente = $_POST['cliente'];
$producto = $_POST['producto'];
$cantidad = $_POST['cantidad'];
$estado = $_POST['estado'];

/* obtener precio del producto */

$sqlPrecio = "SELECT PRECIO 
              FROM MUEBLERIA.PRODUCTO 
              WHERE ID_PRODUCTO = :producto";

$stmtPrecio = oci_parse($conn,$sqlPrecio);

oci_bind_by_name($stmtPrecio,":producto",$producto);

oci_execute($stmtPrecio);

$rowPrecio = oci_fetch_assoc($stmtPrecio);

$precio = $rowPrecio['PRECIO'];

$subtotal = $precio * $cantidad;

/* actualizar pedido */

$sqlPedido = "UPDATE MUEBLERIA.PEDIDO
SET ID_CLIENTE = :cliente,
    TOTAL = :total,
    ESTADO = :estado
WHERE ID_PEDIDO = :id";

$stmtPedido = oci_parse($conn,$sqlPedido);

oci_bind_by_name($stmtPedido,":cliente",$cliente);
oci_bind_by_name($stmtPedido,":total",$subtotal);
oci_bind_by_name($stmtPedido,":estado",$estado);
oci_bind_by_name($stmtPedido,":id",$id);

oci_execute($stmtPedido);

/* actualizar detalle */

$sqlDetalle = "UPDATE MUEBLERIA.DETALLE_PEDIDO
SET ID_PRODUCTO = :producto,
    CANTIDAD = :cantidad,
    PRECIO_UNITARIO = :precio,
    SUB_TOTAL = :subtotal
WHERE ID_PEDIDO = :id";

$stmtDetalle = oci_parse($conn,$sqlDetalle);

oci_bind_by_name($stmtDetalle,":producto",$producto);
oci_bind_by_name($stmtDetalle,":cantidad",$cantidad);
oci_bind_by_name($stmtDetalle,":precio",$precio);
oci_bind_by_name($stmtDetalle,":subtotal",$subtotal);
oci_bind_by_name($stmtDetalle,":id",$id);

oci_execute($stmtDetalle);

echo "<script>
Swal.fire({
icon:'success',
title:'Pedido actualizado'
}).then(()=>window.location='pedidos.php');
</script>";

}

/* OBTENER DATOS DEL PEDIDO */

$query = "SELECT 
            pe.ID_PEDIDO,
            pe.ID_CLIENTE,
            pe.ESTADO,
            dp.ID_PRODUCTO,
            dp.CANTIDAD
          FROM MUEBLERIA.PEDIDO pe
          JOIN MUEBLERIA.DETALLE_PEDIDO dp
          ON pe.ID_PEDIDO = dp.ID_PEDIDO
          WHERE pe.ID_PEDIDO = :id";

$stmt = oci_parse($conn,$query);

oci_bind_by_name($stmt,":id",$id);

oci_execute($stmt);

$row = oci_fetch_assoc($stmt);

if(!$row){
echo "<div class='alert alert-danger'>Pedido no encontrado</div>";
exit;
}

?>

<h1>Editar Pedido</h1>

<form method="POST">

<div class="mb-3">

<label>Cliente</label>

<select name="cliente" class="form-control">

<?php

$sql="SELECT ID_CLIENTE,NOMBRE FROM MUEBLERIA.CLIENTE";

$s=oci_parse($conn,$sql);

oci_execute($s);

while($c=oci_fetch_assoc($s)){

$selected = ($c['ID_CLIENTE'] == $row['ID_CLIENTE']) ? "selected" : "";

echo "<option value='{$c['ID_CLIENTE']}' $selected>{$c['NOMBRE']}</option>";

}

?>

</select>

</div>

<div class="mb-3">

<label>Producto</label>

<select name="producto" class="form-control">

<?php

$sql="SELECT ID_PRODUCTO,NOMBRE FROM MUEBLERIA.PRODUCTO";

$s=oci_parse($conn,$sql);

oci_execute($s);

while($p=oci_fetch_assoc($s)){

$selected = ($p['ID_PRODUCTO'] == $row['ID_PRODUCTO']) ? "selected" : "";

echo "<option value='{$p['ID_PRODUCTO']}' $selected>{$p['NOMBRE']}</option>";

}

?>

</select>

</div>

<div class="mb-3">

<label>Cantidad</label>

<input type="number"
name="cantidad"
class="form-control"
value="<?php echo $row['CANTIDAD']; ?>"
required>


<div class="mb-3">

<label>Estado</label>

<select name="estado" class="form-control">

<option value="PENDIENTE"
<?php if($row['ESTADO']=="PENDIENTE") echo "selected"; ?>>
PENDIENTE
</option>

<option value="ENVIADO"
<?php if($row['ESTADO']=="ENVIADO") echo "selected"; ?>>
ENVIADO
</option>

<option value="ENTREGADO"
<?php if($row['ESTADO']=="ENTREGADO") echo "selected"; ?>>
ENTREGADO
</option>

<option value="CANCELADO"
<?php if($row['ESTADO']=="CANCELADO") echo "selected"; ?>>
CANCELADO
</option>

</select>

</div>



<button class="btn btn-warning">
Actualizar
</button>

<a href="pedidos.php" class="btn btn-secondary">
Volver
</a>

</form>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>

