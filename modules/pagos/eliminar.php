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

// Verificar si el pago existe
$query_check = "SELECT COUNT(*) as existe FROM MUEBLERIA.PAGO WHERE ID_PAGO = :id";
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
            title: 'Pago no encontrado',
            text: 'El pago que intenta eliminar no existe',
            confirmButtonColor: '#2c3e50'
        }).then(() => {
            window.location.href = 'pagos.php';
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
            text: '¿Desea eliminar este pago? Esta acción no se puede deshacer.',
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
                window.location.href = 'pagos.php';
            }
        });
    </script>
    </body>
    </html>";
    exit;
}

// Eliminar pago
$query_delete = "DELETE FROM MUEBLERIA.PAGO WHERE ID_PAGO = :id";
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
            text: 'Pago eliminado exitosamente',
            confirmButtonColor: '#2c3e50'
        }).then(() => {
            window.location.href = 'pagos.php';
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
            window.location.href = 'pagos.php';
        });
    </script>
    </body>
    </html>";
}

$db->close();
?>