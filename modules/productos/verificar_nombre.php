<?php
session_start();
require_once __DIR__ . '/../../Conexion/conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['existe' => false]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$nombre = $_GET['nombre'] ?? '';
$id_actual = $_GET['id'] ?? 0;

if (empty($nombre)) {
    echo json_encode(['existe' => false]);
    exit;
}

// Buscar si el nombre ya existe (excluyendo el producto actual si es edición)
$query = "SELECT COUNT(*) as total FROM MUEBLERIA.PRODUCTO WHERE NOMBRE = :nombre";
if ($id_actual > 0) {
    $query .= " AND ID_PRODUCTO != :id";
}

$stmt = oci_parse($conn, $query);
oci_bind_by_name($stmt, ':nombre', $nombre);
if ($id_actual > 0) {
    oci_bind_by_name($stmt, ':id', $id_actual);
}
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);

$existe = ($row['TOTAL'] > 0);

echo json_encode(['existe' => $existe]);

$db->close();
?>