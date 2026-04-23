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
$query = "SELECT p.ID_PAGO, p.METODO, p.MONTO, p.REFERENCIA, 
                 TO_CHAR(p.FECHA, 'DD/MM/YYYY') as FECHA_FORMATEADA,
                 pe.ID_PEDIDO, pe.TOTAL as TOTAL_PEDIDO, 
                 c.NOMBRE as CLIENTE
          FROM MUEBLERIA.PAGO p
          JOIN MUEBLERIA.PEDIDO pe ON p.ID_PEDIDO = pe.ID_PEDIDO
          JOIN MUEBLERIA.CLIENTE c ON pe.ID_CLIENTE = c.ID_CLIENTE
          ORDER BY p.FECHA DESC";
$stmt = oci_parse($conn, $query);
oci_execute($stmt);
?>

<style>
#tablaPagos tbody td {
    color: #333333 !important;
    background-color: #ffffff !important;
}
#tablaPagos thead th {
    background-color: #2c3e50 !important;
    color: white !important;
}
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-credit-card"></i> Lista de Pagos
        <a href="nuevo.php" class="btn btn-primary btn-sm float-end">
            <i class="fas fa-plus"></i> Nuevo Pago
        </a>
    </div>
    <div class="card-body">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" class="form-control" id="buscarPago" 
                       placeholder="Buscar por cliente o referencia..." 
                       onkeyup="buscarTabla()">
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="tablaPagos">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Monto</th>
                        <th>Método</th>
                        <th>Referencia</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $hay_pagos = false;
                    while ($row = oci_fetch_assoc($stmt)): 
                        $hay_pagos = true;
                    ?>
                    <tr>
                        <td><?php echo $row['ID_PAGO']; ?></td>
                        <td><?php echo $row['ID_PEDIDO']; ?></td>
                        <td><?php echo htmlspecialchars($row['CLIENTE']); ?></td>
                        <td>₡<?php echo number_format($row['MONTO'], 0, ',', '.'); ?></td>
                        <td>
                            <?php 
                            $badge_class = '';
                            if ($row['METODO'] == 'TARJETA') $badge_class = 'bg-info';
                            elseif ($row['METODO'] == 'EFECTIVO') $badge_class = 'bg-success';
                            else $badge_class = 'bg-warning';
                            ?>
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo $row['METODO']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['REFERENCIA']); ?></td>
                         <!-- FECHA YA FORMATEADA POR ORACLE -->
                        <td><?php echo $row['FECHA_FORMATEADA']; ?></td>
                        <td>
                            <a href="javascript:void(0);" 
                               onclick="confirmarEliminacion(<?php echo $row['ID_PAGO']; ?>)" 
                               class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php 
                    endwhile; 
                    
                    if (!$hay_pagos):
                    ?>
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="alert alert-info mb-0">
                                No hay pagos registrados. 
                                <a href="nuevo.php">Registrar el primer pago</a>
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
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("buscarPago");
    filter = input.value.toUpperCase();
    table = document.getElementById("tablaPagos");
    tr = table.getElementsByTagName("tr");
    
    for (i = 0; i < tr.length; i++) {
        td = tr[i].getElementsByTagName("td")[2];
        if (td) {
            txtValue = td.textContent || td.innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
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
        text: '¿Desea eliminar este pago? Esta acción no se puede deshacer.',
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