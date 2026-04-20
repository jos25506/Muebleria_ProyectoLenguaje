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

$id = $_GET['id'] ?? 0;

// Datos de la compra
$query = "SELECT c.*, p.NOMBRE as PROVEEDOR, u.NOMBRE as USUARIO
          FROM MUEBLERIA.COMPRA c
          JOIN MUEBLERIA.PROVEEDOR p ON c.ID_PROVEEDOR = p.ID_PROVEEDOR
          JOIN MUEBLERIA.USUARIO u ON c.ID_USUARIO = u.ID_USUARIO
          WHERE c.ID_COMPRA = :id";
$stmt = oci_parse($conn, $query);
oci_bind_by_name($stmt, ':id', $id);
oci_execute($stmt);
$compra = oci_fetch_assoc($stmt);

if (!$compra) {
    echo "<script>
        Swal.fire({ icon: 'warning', title: 'Compra no encontrada', text: 'La compra que intenta ver no existe', confirmButtonColor: '#2c3e50' })
        .then(() => window.location.href = 'compras.php');
    </script>";
    exit;
}

// Detalles de la compra
$query_det = "SELECT d.*, pr.NOMBRE as PRODUCTO
              FROM MUEBLERIA.DETALLE_COMPRA d
              JOIN MUEBLERIA.PRODUCTO pr ON d.ID_PRODUCTO = pr.ID_PRODUCTO
              WHERE d.ID_COMPRA = :id
              ORDER BY d.ID_DETALLE_COMPRA";
$stmt_det = oci_parse($conn, $query_det);
oci_bind_by_name($stmt_det, ':id', $id);
oci_execute($stmt_det);
?>

<style>
.detalle-card {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
.detalle-label {
    font-weight: bold;
    color: #2c3e50;
}
.detalle-valor {
    color: #555;
}
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-receipt"></i> Detalle de Compra #<?php echo $compra['ID_COMPRA']; ?>
        <a href="compras.php" class="btn btn-secondary btn-sm float-end">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
    <div class="card-body">
        
        <!-- Información de la compra -->
        <div class="row detalle-card">
            <div class="col-md-3">
                <div class="detalle-label">ID Compra:</div>
                <div class="detalle-valor"><?php echo $compra['ID_COMPRA']; ?></div>
            </div>
            <div class="col-md-3">
                <div class="detalle-label">Fecha:</div>
                <div class="detalle-valor"><?php echo date('d/m/Y', strtotime($compra['FECHA'])); ?></div>
            </div>
            <div class="col-md-3">
                <div class="detalle-label">Proveedor:</div>
                <div class="detalle-valor"><?php echo htmlspecialchars($compra['PROVEEDOR']); ?></div>
            </div>
            <div class="col-md-3">
                <div class="detalle-label">Usuario:</div>
                <div class="detalle-valor"><?php echo htmlspecialchars($compra['USUARIO']); ?></div>
            </div>
            <div class="col-md-3 mt-3">
                <div class="detalle-label">Total Compra:</div>
                <div class="detalle-valor h4 text-success">₡<?php echo number_format($compra['TOTAL'], 0, ',', '.'); ?></div>
            </div>
        </div>
        
        <h4 class="mt-4 mb-3">Productos de la compra</h4>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Costo Unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subtotal_total = 0;
                    while ($detalle = oci_fetch_assoc($stmt_det)): 
                        $subtotal_total += $detalle['SUB_TOTAL'];
                    ?>
                    <tr>
                        <td><?php echo $detalle['ID_DETALLE_COMPRA']; ?></td>
                        <td><?php echo htmlspecialchars($detalle['PRODUCTO']); ?></td>
                        <td><?php echo $detalle['CANTIDAD']; ?></td>
                        <td>₡<?php echo number_format($detalle['COSTO_UNITARIO'], 0, ',', '.'); ?></td>
                        <td>₡<?php echo number_format($detalle['SUB_TOTAL'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-secondary">
                    <tr>
                        <th colspan="4" class="text-end">Total General:</th>
                        <th>₡<?php echo number_format($subtotal_total, 0, ',', '.'); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Verificar si el total coincide -->
        <?php if ($subtotal_total != $compra['TOTAL']): ?>
        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle"></i> 
            Nota: El total de los detalles (₡<?php echo number_format($subtotal_total, 0, ',', '.'); ?>) 
            no coincide con el total registrado (₡<?php echo number_format($compra['TOTAL'], 0, ',', '.'); ?>).
        </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <a href="compras.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Compras
            </a>
            <a href="javascript:window.print()" class="btn btn-info">
                <i class="fas fa-print"></i> Imprimir
            </a>
        </div>
        
    </div>
</div>

<?php
oci_free_statement($stmt);
oci_free_statement($stmt_det);
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>