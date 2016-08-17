<?php
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/config.inc.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

//boucle sur toutes les produits
$sql = "
	SELECT prixmoment, id_product_erp 
	FROM produits 
	WHERE marketplace_erp = 1 
	AND id_product_erp > 0
";
$stmt = $db->query($sql);
$stmt->execute();

$cpt=0;
while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	$stmt2 = $erp->prepare('UPDATE ps_product SET price = ? WHERE id_product = ? LIMIT 1');
	$stmt2->bindParam(1, $row->prixmoment, PDO::PARAM_INT);
	$stmt2->bindParam(2, $row->id_product_erp, PDO::PARAM_INT);
	if ($stmt2->execute()) {
		$cpt++;
	}
}

echo "MAJ de $cpt prix termine";