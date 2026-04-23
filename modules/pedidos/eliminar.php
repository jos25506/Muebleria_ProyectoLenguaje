<?php
require_once __DIR__ . '/../../Conexion/conexion.php';

$db = new Database();
$conn = $db->getConnection();

if(!$conn){
    die("Error de conexión");
}

/* verificar id */

if(!isset($_GET['id'])){
    die("ID no especificado");
}

$id = $_GET['id'];

/* eliminar detalle del pedido */

$sql = "DELETE FROM MUEBLERIA.DETALLE_PEDIDO
        WHERE ID_PEDIDO = :id";

$stmt = oci_parse($conn,$sql);

oci_bind_by_name($stmt,":id",$id);

oci_execute($stmt);

/* eliminar pedido */

$sql = "DELETE FROM MUEBLERIA.PEDIDO
        WHERE ID_PEDIDO = :id";

$stmt = oci_parse($conn,$sql);

oci_bind_by_name($stmt,":id",$id);

oci_execute($stmt);

/* redireccionar */

header("Location: pedidos.php");
exit;

$db->close();
?>