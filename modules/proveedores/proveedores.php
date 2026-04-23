<?php
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$query = "SELECT * FROM MUEBLERIA.PROVEEDOR
ORDER BY ID_PROVEEDOR";

$stmt = oci_parse($conn,$query);
oci_execute($stmt);
?>

<h1>Proveedores</h1>

<a href="nuevo.php" class="btn btn-primary mb-3">
Nuevo Proveedor
</a>

<div class="table-responsive">

<table class="table table-striped table-hover">

<thead class="table-dark">

<tr>
<th>ID</th>
<th>Nombre</th>
<th>Teléfono</th>
<th>Correo</th>
<th>Dirección</th>
<th>Acciones</th>
</tr>

</thead>

<tbody>

<?php while($row = oci_fetch_assoc($stmt)): ?>

<tr>

<td><?php echo $row['ID_PROVEEDOR']; ?></td>

<td><?php echo htmlspecialchars($row['NOMBRE']); ?></td>

<td><?php echo htmlspecialchars($row['TELEFONO']); ?></td>

<td><?php echo htmlspecialchars($row['CORREO']); ?></td>

<td><?php echo htmlspecialchars($row['DIRECCION']); ?></td>

<td>

<a href="editar.php?id=<?php echo $row['ID_PROVEEDOR']; ?>"
class="btn btn-warning btn-sm">
<i class="fas fa-edit"></i>
</a>

<a href="javascript:void(0);"
onclick="confirmarEliminacion(<?php echo $row['ID_PROVEEDOR']; ?>)"
class="btn btn-danger btn-sm">
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
title:'¿Eliminar proveedor?',
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
oci_free_statement($stmt);
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>