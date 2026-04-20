
<?php
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("<div class='alert alert-danger'>Error de conexión</div>");
}

/* BUSCADOR */

$buscar = "";

if(isset($_GET['buscar'])){
    $buscar = $_GET['buscar'];
}

/* CONSULTA */

$query = "SELECT 
            pe.ID_PEDIDO,
            pe.FECHA,
            pe.TOTAL,
            pe.ESTADO,
            c.NOMBRE AS CLIENTE,
            pr.NOMBRE AS PRODUCTO,
            dp.CANTIDAD
          FROM MUEBLERIA.PEDIDO pe
          JOIN MUEBLERIA.CLIENTE c 
            ON pe.ID_CLIENTE = c.ID_CLIENTE
          JOIN MUEBLERIA.DETALLE_PEDIDO dp 
            ON pe.ID_PEDIDO = dp.ID_PEDIDO
          JOIN MUEBLERIA.PRODUCTO pr 
            ON dp.ID_PRODUCTO = pr.ID_PRODUCTO
          WHERE UPPER(c.NOMBRE) LIKE UPPER(:buscar)
             OR TO_CHAR(pe.ID_PEDIDO) LIKE :buscar
          ORDER BY pe.ID_PEDIDO DESC";

$stmt = oci_parse($conn, $query);

$buscarParam = "%".$buscar."%";

oci_bind_by_name($stmt, ":buscar", $buscarParam);

oci_execute($stmt);

?>

<h1>Pedidos</h1>

<a href="nuevo.php" class="btn btn-primary mb-3">
Nuevo Pedido
</a>

<!-- BUSCADOR -->

<form method="GET" class="mb-3">

<div class="input-group">

<input type="text"
name="buscar"
class="form-control"
placeholder="Buscar por cliente o ID de pedido"
value="<?php echo htmlspecialchars($buscar); ?>">

<button class="btn btn-dark">
Buscar
</button>

</div>

</form>

<div class="table-responsive">

<table class="table table-striped table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>
<th>Fecha</th>
<th>Cliente</th>
<th>Producto</th>
<th>Cantidad</th>
<th>Total</th>
<th>Estado</th>
<th>Acciones</th>

</tr>

</thead>

<tbody>

<?php while ($row = oci_fetch_assoc($stmt)): ?>

<tr>

<td><?php echo $row['ID_PEDIDO']; ?></td>

<td><?php echo $row['FECHA']; ?></td>

<td><?php echo htmlspecialchars($row['CLIENTE']); ?></td>

<td><?php echo htmlspecialchars($row['PRODUCTO']); ?></td>

<td><?php echo $row['CANTIDAD']; ?></td>

<td>
₡<?php echo number_format($row['TOTAL'],2); ?>
</td>


<td>

<?php

switch($row['ESTADO']){

case "PENDIENTE":
echo "<span class='badge bg-warning text-dark'>PENDIENTE</span>";
break;

case "ENVIADO":
echo "<span class='badge bg-primary'>ENVIADO</span>";
break;

case "ENTREGADO":
echo "<span class='badge bg-success'>ENTREGADO</span>";
break;

case "CANCELADO":
echo "<span class='badge bg-danger'>CANCELADO</span>";
break;

}

?>

</td>



<td>

<a href="editar.php?id=<?php echo $row['ID_PEDIDO']; ?>" 
class="btn btn-warning btn-sm" title="Editar">

<i class="fas fa-edit"></i>

</a>

<a href="javascript:void(0);" 
onclick="confirmarEliminacion(<?php echo $row['ID_PEDIDO']; ?>)" 
class="btn btn-danger btn-sm" title="Eliminar">

<i class="fas fa-trash"></i>

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<script>

function confirmarEliminacion(id){

Swal.fire({
title:'¿Eliminar pedido?',
text:'Esta acción no se puede deshacer',
icon:'warning',
showCancelButton:true,
confirmButtonColor:'#e74c3c',
cancelButtonColor:'#2c3e50',
confirmButtonText:'Sí eliminar',
cancelButtonText:'Cancelar'

}).then((result)=>{

if(result.isConfirmed){
window.location.href=`eliminar.php?id=${id}&confirm=1`;
}

});

}

</script>

<?php
oci_free_statement($stmt);
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>

