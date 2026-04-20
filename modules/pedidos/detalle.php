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

// Datos del pedido
$query = "SELECT p.*, c.NOMBRE as CLIENTE, c.TELEFONO, c.CORREO, c.DIRECCION,
                 u.NOMBRE as USUARIO
          FROM MUEBLERIA.PEDIDO p
          JOIN MUEBLERIA.CLIENTE c ON p.ID_CLIENTE = c.ID_CLIENTE
          JOIN MUEBLERIA.USUARIO u ON p.ID_USUARIO = u.ID_USUARIO
          WHERE p.ID_PEDIDO = :id";
$stmt = oci_parse($conn, $query);
oci_bind_by_name($stmt, ':id', $id);
oci_execute($stmt);
$pedido = oci_fetch_assoc($stmt);

if (!$pedido) {
    echo "<script>
        Swal.fire({ icon: 'warning', title: 'Pedido no encontrado', text: 'El pedido que intenta ver no existe', confirmButtonColor: '#2c3e50' })
        .then(() => window.location.href = 'pedidos.php');
    </script>";
    exit;
}

// Detalles del pedido (productos)
$query_det = "SELECT d.*, pr.NOMBRE as PRODUCTO, pr.PRECIO as PRECIO_VENTA
              FROM MUEBLERIA.DETALLE_PEDIDO d
              JOIN MUEBLERIA.PRODUCTO pr ON d.ID_PRODUCTO = pr.ID_PRODUCTO
              WHERE d.ID_PEDIDO = :id
              ORDER BY d.ID_DETALLE";
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
.estado-badge {
    font-size: 14px;
    padding: 8px 15px;
}
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-receipt"></i> Detalle del Pedido #<?php echo $pedido['ID_PEDIDO']; ?>
        <a href="pedidos.php" class="btn btn-secondary btn-sm float-end">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
    <div class="card-body">
        
        <!-- Información del pedido -->
        <div class="row detalle-card">
            <div class="col-md-3">
                <div class="detalle-label">ID Pedido:</div>
                <div class="detalle-valor"><?php echo $pedido['ID_PEDIDO']; ?></div>
            </div>
            <div class="col-md-3">
                <div class="detalle-label">Fecha:</div>
                <div class="detalle-valor"><?php echo date('d/m/Y', strtotime($pedido['FECHA'])); ?></div>
            </div>
            <div class="col-md-3">
                <div class="detalle-label">Estado:</div>
                <div class="detalle-valor">
                    <?php
                    $badge_class = '';
                    if ($pedido['ESTADO'] == 'ENTREGADO') $badge_class = 'bg-success';
                    elseif ($pedido['ESTADO'] == 'PENDIENTE') $badge_class = 'bg-warning';
                    elseif ($pedido['ESTADO'] == 'CANCELADO') $badge_class = 'bg-danger';
                    else $badge_class = 'bg-secondary';
                    ?>
                    <span class="badge estado-badge <?php echo $badge_class; ?>">
                        <?php echo $pedido['ESTADO']; ?>
                    </span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="detalle-label">Total:</div>
                <div class="detalle-valor h4 text-success">₡<?php echo number_format($pedido['TOTAL'], 0, ',', '.'); ?></div>
            </div>
        </div>
        
        <!-- Información del cliente -->
        <h4 class="mt-4 mb-3"><i class="fas fa-user"></i> Información del Cliente</h4>
        <div class="row detalle-card">
            <div class="col-md-4">
                <div class="detalle-label">Nombre:</div>
                <div class="detalle-valor"><?php echo htmlspecialchars($pedido['CLIENTE']); ?></div>
            </div>
            <div class="col-md-4">
                <div class="detalle-label">Teléfono:</div>
                <div class="detalle-valor"><?php echo htmlspecialchars($pedido['TELEFONO']); ?></div>
            </div>
            <div class="col-md-4">
                <div class="detalle-label">Correo:</div>
                <div class="detalle-valor"><?php echo htmlspecialchars($pedido['CORREO']); ?></div>
            </div>
            <div class="col-md-12 mt-2">
                <div class="detalle-label">Dirección:</div>
                <div class="detalle-valor"><?php echo htmlspecialchars($pedido['DIRECCION']); ?></div>
            </div>
        </div>
        
        <!-- Productos del pedido -->
        <h4 class="mt-4 mb-3"><i class="fas fa-box"></i> Productos del Pedido</h4>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subtotal_total = 0;
                    $hay_productos = false;
                    while ($detalle = oci_fetch_assoc($stmt_det)): 
                        $hay_productos = true;
                        $subtotal_total += $detalle['SUB_TOTAL'];
                    ?>
                    <tr>
                        <td><?php echo $detalle['ID_DETALLE']; ?></td>
                        <td><?php echo htmlspecialchars($detalle['PRODUCTO']); ?></td>
                        <td><?php echo $detalle['CANTIDAD']; ?></td>
                        <td>₡<?php echo number_format($detalle['PRECIO_UNITARIO'], 0, ',', '.'); ?></td>
                        <td>₡<?php echo number_format($detalle['SUB_TOTAL'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if (!$hay_productos): ?>
                    <tr>
                        <td colspan="5" class="text-center">
                            <div class="alert alert-warning mb-0">
                                No hay productos registrados en este pedido.
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($hay_productos): ?>
                <tfoot class="table-secondary">
                    <tr>
                        <th colspan="4" class="text-end">Total General:</th>
                        <th>₡<?php echo number_format($subtotal_total, 0, ',', '.'); ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Verificar si el total coincide -->
        <?php if ($hay_productos && $subtotal_total != $pedido['TOTAL']): ?>
        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle"></i> 
            Nota: El total de los detalles (₡<?php echo number_format($subtotal_total, 0, ',', '.'); ?>) 
            no coincide con el total registrado (₡<?php echo number_format($pedido['TOTAL'], 0, ',', '.'); ?>).
        </div>
        <?php endif; ?>
        
        <!-- Información del usuario que registró -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="fas fa-user-check"></i> 
                    <strong>Registrado por:</strong> <?php echo htmlspecialchars($pedido['USUARIO']); ?>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="pedidos.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Pedidos
            </a>
            <a href="javascript:window.print()" class="btn btn-info">
                <i class="fas fa-print"></i> Imprimir
            </a>
            <?php if ($pedido['ESTADO'] == 'PENDIENTE'): ?>
            <a href="editar_estado.php?id=<?php echo $pedido['ID_PEDIDO']; ?>&estado=ENVIADO" 
               class="btn btn-primary"
               onclick="return confirm('¿Marcar este pedido como ENVIADO?')">
                <i class="fas fa-truck"></i> Marcar como Enviado
            </a>
            <a href="editar_estado.php?id=<?php echo $pedido['ID_PEDIDO']; ?>&estado=ENTREGADO" 
               class="btn btn-success"
               onclick="return confirm('¿Marcar este pedido como ENTREGADO?')">
                <i class="fas fa-check-circle"></i> Marcar como Entregado
            </a>
            <?php elseif ($pedido['ESTADO'] == 'ENVIADO'): ?>
            <a href="editar_estado.php?id=<?php echo $pedido['ID_PEDIDO']; ?>&estado=ENTREGADO" 
               class="btn btn-success"
               onclick="return confirm('¿Marcar este pedido como ENTREGADO?')">
                <i class="fas fa-check-circle"></i> Marcar como Entregado
            </a>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<?php
oci_free_statement($stmt);
oci_free_statement($stmt_det);
$db->close();
include __DIR__ . '/../../includes/footer.php';
?>