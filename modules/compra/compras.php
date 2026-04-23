<?php
session_start();
require_once __DIR__ . '/../../Conexion/conexion.php';
include __DIR__ . '/../../includes/header.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /Muebleria_Proyecto/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// ============================================
// CONSULTA CORREGIDA: FECHA FORMATEADA DESDE ORACLE
// ============================================
$query = "SELECT c.ID_COMPRA, c.TOTAL, 
                 TO_CHAR(c.FECHA, 'DD/MM/YYYY') as FECHA_FORMATEADA,
                 p.NOMBRE as PROVEEDOR, u.NOMBRE as USUARIO
          FROM MUEBLERIA.COMPRA c
          JOIN MUEBLERIA.PROVEEDOR p ON c.ID_PROVEEDOR = p.ID_PROVEEDOR
          JOIN MUEBLERIA.USUARIO u ON c.ID_USUARIO = u.ID_USUARIO
          ORDER BY c.FECHA DESC";
$stmt = oci_parse($conn, $query);
oci_execute($stmt);
?>

<style>
#tablaCompras tbody td {
    color: #333333 !important;
    background-color: #ffffff !important;
}
#tablaCompras thead th {
    background-color: #2c3e50 !important;
    color: white !important;
}
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-shopping-cart"></i> Lista de Compras
        <a href="nueva.php" class="btn btn-primary btn-sm float-end">
            <i class="fas fa-plus"></i> Nueva Compra
        </a>
    </div>
    <div class="card-body">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" class="form-control" id="buscarCompra" 
                       placeholder="Buscar por proveedor..." 
                       onkeyup="buscarTabla()">
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="tablaCompras">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Total</th>
                        <th>Usuario</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $hay_compras = false;
                    while ($row = oci_fetch_assoc($stmt)): 
                        $hay_compras = true;
                    ?>
                    <tr>
                        <td><?php echo $row['ID_COMPRA']; ?></td>
                        <!-- FECHA YA FORMATEADA POR ORACLE -->
                        <td><?php echo $row['FECHA_FORMATEADA']; ?></td>
                        <td><?php echo htmlspecialchars($row['PROVEEDOR']); ?></td>
                        <td>₡<?php echo number_format($row['TOTAL'], 0, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($row['USUARIO']); ?></td>
                        <td>
                            <a href="detalle.php?id=<?php echo $row['ID_COMPRA']; ?>" 
                               class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                            <a href="javascript:void(0);" 
                               onclick="confirmarEliminacion(<?php echo $row['ID_COMPRA']; ?>)" 
                               class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php 
                    endwhile; 
                    
                    if (!$hay_compras):
                    ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="alert alert-info mb-0">
                                No hay compras registradas. 
                                <a href="nueva.php">Registrar la primera compra</a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function buscarTabla() {
    var input, filter, table, tr, td, i;
    input = document.getElementById("buscarCompra");
    filter = input.value.toUpperCase();
    table = document.getElementById("tablaCompras");
    tr = table.getElementsByTagName("tr");
    
    for (i = 0; i < tr.length; i++) {
        td = tr[i].getElementsByTagName("td")[2];
        if (td) {
            if (td.textContent.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

function confirmarEliminacion(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: '¿Desea eliminar esta compra? También se eliminarán sus detalles.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#2c3e50',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `eliminar.php?id=${id}&confirm=1`;
        }
    });
}
</script>

<?php
oci_free_statement($stmt);
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>