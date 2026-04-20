<?php
session_start();
require_once __DIR__ . '/../../Conexion/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /Muebleria_Proyecto/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$id = $_GET['id'] ?? 0;
$confirm = $_GET['confirm'] ?? 0;

// Verificar si la compra existe
$query_check = "SELECT COUNT(*) as existe FROM MUEBLERIA.COMPRA WHERE ID_COMPRA = :id";
$stmt_check = oci_parse($conn, $query_check);
oci_bind_by_name($stmt_check, ':id', $id);
oci_execute($stmt_check);
$row_check = oci_fetch_assoc($stmt_check);

if ($row_check['EXISTE'] == 0) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Compra no encontrada',
            text: 'La compra que intenta eliminar no existe',
            confirmButtonColor: '#2c3e50'
        }).then(() => {
            window.location.href = 'compras.php';
        });
    </script>
    </body>
    </html>";
    exit;
}

if ($confirm != 1) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
    <script>
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¿Desea eliminar esta compra? También se eliminarán todos sus detalles. Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#2c3e50',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'eliminar.php?id=$id&confirm=1';
            } else {
                window.location.href = 'compras.php';
            }
        });
    </script>
    </body>
    </html>";
    exit;
}

// Primero eliminar los detalles de la compra
$query_det = "DELETE FROM MUEBLERIA.DETALLE_COMPRA WHERE ID_COMPRA = :id";
$stmt_det = oci_parse($conn, $query_det);
oci_bind_by_name($stmt_det, ':id', $id);
oci_execute($stmt_det);

// Luego eliminar la compra
$query_delete = "DELETE FROM MUEBLERIA.COMPRA WHERE ID_COMPRA = :id";
$stmt_delete = oci_parse($conn, $query_delete);
oci_bind_by_name($stmt_delete, ':id', $id);

if (oci_execute($stmt_delete)) {
    oci_commit($conn);
    echo "<!DOCTYPE html>
    <html>
    <head>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
    <script>
        Swal.fire({
            icon: 'success',
            title: '¡Eliminado!',
            text: 'Compra eliminada exitosamente',
            confirmButtonColor: '#2c3e50'
        }).then(() => {
            window.location.href = 'compras.php';
        });
    </script>
    </body>
    </html>";
} else {
    $error = oci_error($stmt_delete);
    echo "<!DOCTYPE html>
    <html>
    <head>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al eliminar: " . addslashes($error['message']) . "',
            confirmButtonColor: '#2c3e50'
        }).then(() => {
            window.location.href = 'compras.php';
        });
    </script>
    </body>
    </html>";
}

$db->close();
?>