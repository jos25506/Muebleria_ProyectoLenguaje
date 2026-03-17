<?php

require_once __DIR__ . '/../../Conexion/conexion.php';

$db = new Database();
$conn = $db->getConnection();

$id = $_GET['id'];

$sql="DELETE FROM MUEBLERIA.INVENTARIO
WHERE ID_INVENTARIO=:id";

$stmt=oci_parse($conn,$sql);

oci_bind_by_name($stmt,":id",$id);

oci_execute($stmt);
oci_commit($conn);

header("Location: inventario.php");

$db->close();
?>