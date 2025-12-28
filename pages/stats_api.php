<?php
include __DIR__ . "/db.php";
header("Content-Type: application/json");

$data = [];
$res = $conn->query("SELECT DATE_FORMAT(created_at, '%b') as month, COUNT(*) as total FROM citizens GROUP BY month ORDER BY created_at ASC LIMIT 6");
while ($r = $res->fetch_assoc()) $data[] = ["month"=>$r['month'], "value"=>$r['total']];
echo json_encode(["monthly"=>$data]);
?>
