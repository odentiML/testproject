<?php
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/config.inc.php');
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/PSWebServiceLibrary.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);
/*
//boucle sur toutes les produits
$sql = "SELECT * FROM tempo6";
$stmt = $db->query($sql);
$stmt->execute();

$collec = array();
while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	$stmt2 = $db->query("SELECT prod_id FROM produits WHERE id_product_erp = ".$row->id_prod);
	$stmt2->execute();
	$row2 = $stmt2->fetch(PDO::FETCH_OBJ);
	if (empty($row2->prod_id)) {
		$collec[] = $row->id_prod;
	}
}

var_dump($collec);
*/

/*
SELECT COUNT(reference) AS occurrences, *
FROM ps_product
GROUP BY reference
HAVING occurrences > 1


SELECT t.*
FROM tempo6 t
LEFT JOIN produits p ON p.id_product_erp = t.id_prod
WHERE p.prod_id IS NULL
ORDER BY t.id_prod DESC
*/


$sql = "SELECT * FROM badprod";
$stmt = $erp->query($sql);
$stmt->execute();

$cpt=0;
while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	$erp->exec("DELETE FROM ps_accessory WHERE id_product_1 = ".$row->id_prod);
	$erp->exec("DELETE FROM ps_category_product WHERE id_product = ".$row->id_prod);
	$erp->exec("DELETE FROM ps_feature_product WHERE id_product = ".$row->id_prod);
	$erp->exec("DELETE FROM ps_layered_price_index WHERE id_product = ".$row->id_prod);
	$erp->exec("DELETE FROM ps_product_attribute WHERE id_product = ".$row->id_prod);
	$erp->exec("DELETE FROM ps_product_lang WHERE id_product = ".$row->id_prod);
	$erp->exec("DELETE FROM ps_product_shop WHERE id_product = ".$row->id_prod);
	$erp->exec("DELETE FROM ps_product_tag WHERE id_product = ".$row->id_prod);
	$erp->exec("DELETE FROM ps_product WHERE id_product = ".$row->id_prod);
	$erp->exec("DELETE FROM ps_stock_available WHERE id_product = ".$row->id_prod);
	$cpt++;
}

echo 'done';
echo $cpt;