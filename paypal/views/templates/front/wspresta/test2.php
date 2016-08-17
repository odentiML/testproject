<?php
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/config.inc.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

SELECT * FROM stock s INNER JOIN produits p ON s.stock_prod_id = p.prod_id WHERE p.prod_rang = '' AND p.prod_marque_id = 97

$stmt = $db->query("SELECT * FROM produits p WHERE p.prod_rang = '' AND p.prod_marque_id = 97");
$stmt->execute();

$cpt=0;
while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	//echo "UPDATE stock SET stock_qte = 0 WHERE stock_prod_id = ".$row->prod_id;
	//$db->exec("UPDATE stock SET stock_qte = 0 WHERE stock_prod_id = ".$row->prod_id);
	$cpt++;
}

echo 'done';
echo $cpt;