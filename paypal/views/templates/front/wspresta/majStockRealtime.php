<?php
	require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/config.inc.php');

	ini_set('display_errors', 1);
	error_reporting(E_ALL);

	//boucle sur toutes les produits
	$sql = "
		SELECT s.stock_id, s.stock_qte, s.erp_declinaison, p.id_product_erp 
		FROM stock s 
		INNER JOIN produits p ON s.stock_prod_id = p.prod_id 
		WHERE s.erp_declinaison > 0 
		AND p.marketplace_erp = 1
		AND p.id_product_erp > 0
		AND s.stock_qte != s.lastSyncEbay
	";
	$stmt = $db->query($sql);
	$stmt->execute();
	
	$cpt=0;
	$done=array();
	while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
		// Reset du stock pour le produit
		if (!isset($done[$row->id_product_erp])) {
			$erp->query('UPDATE ps_stock_available SET quantity = 0 WHERE id_product = '.(int)$row->id_product_erp.' AND id_product_attribute = 0 LIMIT 1');
			$erp->query('UPDATE ps_product SET date_upd = NOW() WHERE id_product = '.(int)$row->id_product_erp.' LIMIT 1');
			$done[$row->id_product_erp] = 1;
		}
	
		$stmt2 = $erp->prepare('UPDATE ps_stock_available SET quantity = ? WHERE id_product = ? AND id_product_attribute = ? LIMIT 1');
		
		$stmt2->bindParam(1, $row->stock_qte, PDO::PARAM_INT);
		$stmt2->bindParam(2, $row->id_product_erp, PDO::PARAM_INT);
		$stmt2->bindParam(3, $row->erp_declinaison, PDO::PARAM_INT);
		
		if ($stmt2->execute()) {
			// MAJ du total quantite pour produit
			$erp->query('UPDATE ps_stock_available SET quantity = (quantity+'.(int)$row->stock_qte.') WHERE id_product = '.(int)$row->id_product_erp.' AND id_product_attribute = 0 LIMIT 1');
			$db->query('UPDATE stock SET lastSyncEbay = '.(int)$row->stock_qte.' WHERE stock_id = '.(int)$row->stock_id.' LIMIT 1');
			echo 'UPDATE stock SET lastSyncEbay = '.(int)$row->stock_qte.' WHERE stock_id = '.(int)$row->stock_id.' LIMIT 1' . "\n";
			$cpt++;
		}
	}

	echo "MAJ de $cpt stock termine";