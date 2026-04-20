<?php
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$query = "SELECT 
            i.ID_INVENTARIO,
            p.NOMBRE PRODUCTO,
            i.STOCK_ACTUAL,
            i.STOCK_MINIMO
          FROM MUEBLERIA.INVENTARIO i
          LEFT JOIN MUEBLERIA.PRODUCTO p
          ON i.ID_PRODUCTO = p.ID_PRODUCTO
          ORDER BY i.ID_INVENTARIO";

$stmt = oci_parse($conn,$query);
oci_execute($stmt);
?>

<h1>Inventario</h1>

<a href="nuevo.php" class="btn btn-primary mb-3">
Nuevo Registro
</a>

<table class="table table-striped">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Producto</th>
<th>Stock Actual</th>
<th>Stock Mínimo</th>
<th>Acciones</th>
</tr>
</thead>

<tbody>

<?php while($row = oci_fetch_assoc($stmt)): ?>

<tr>

<td><?php echo $row['ID_INVENTARIO']; ?></td>

<td><?php echo htmlspecialchars($row['PRODUCTO']); ?></td>

<td><?php echo $row['STOCK_ACTUAL']; ?></td>

<td><?php echo $row['STOCK_MINIMO']; ?></td>

<td>

<a href="editar.php?id=<?php echo $row['ID_INVENTARIO']; ?>"
class="btn btn-warning btn-sm">
Editar
</a>

<a href="javascript:void(0);" 
onclick="confirmarEliminacion(<?php echo $row['ID_INVENTARIO']; ?>)" 
class="btn btn-danger btn-sm">
<i class="fas fa-trash"></i>
</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>
</table>

<script>

function confirmarEliminacion(id){

Swal.fire({
title:'¿Eliminar inventario?',
text:'Esta acción no se puede deshacer',
icon:'warning',
showCancelButton:true,
confirmButtonColor:'#e74c3c',
cancelButtonColor:'#2c3e50',
confirmButtonText:'Sí eliminar',
cancelButtonText:'Cancelar'
}).then((result)=>{

if(result.isConfirmed){
window.location.href=`eliminar.php?id=${id}`;
}

});

}

</script>

<?php
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>