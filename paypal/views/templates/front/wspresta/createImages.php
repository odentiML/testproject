<?php
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/config.inc.php');
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/PSWebServiceLibrary.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

//boucle sur toutes les produits
$sql = "
	SELECT p.* 
	FROM produits p 
	WHERE p.marketplace_erp = 1 
	AND p.id_product_erp > 0 
	AND p.images_import_erp = 0 
	ORDER BY p.prod_id DESC 
	LIMIT 500
";
$stmt = $db->query($sql);
$stmt->execute();

$cpt=0;
while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	$relaDir = "/var/www/vhosts/allezdiscount.com/httpdocs".recup_folder_erp($row->prod_id);			
	
	$images = array();
	$images[] = $relaDir.$row->prod_id.'-'.$row->prod_imgFirst.'-2.jpg';
	$images[] = $relaDir.$row->prod_id.'-1-2.jpg';
	$images[] = $relaDir.$row->prod_id.'-2-2.jpg';
	$images[] = $relaDir.$row->prod_id.'-3-2.jpg';
	$images[] = $relaDir.$row->prod_id.'-4-2.jpg';
	$images[] = $relaDir.$row->prod_id.'-5-2.jpg';
	
	$id_default_image = null;
	$ids_images = array();
	foreach ($images as $key => $image) {
		if (file_exists($image)) { 			
			try {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, PS_SHOP_PATH.'/api/images/products/'.$row->id_product_erp);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_USERPWD, PS_WS_AUTH_KEY.':');
				curl_setopt($ch, CURLOPT_POSTFIELDS, array('image' => '@'.$image));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$xmlResponse = curl_exec($ch);
				curl_close($ch);
			
				$objResponse = new SimpleXMLElement($xmlResponse);
				$id_image    = $objResponse->image->id;
				if ($id_image) {
					$cpt++;
					$ids_images[] = $id_image;
				}
			}
			catch (Exception $e) {
				echo $error = date('Y-m-d H:i:s') . ' - Error creating images '.$row->prod_id.' : ' . $e->getMessage() . "\n";
				file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
				continue;
			}
		}
	}
	
	if (count($ids_images)) {
		$stmt2 = $db->prepare('UPDATE produits SET images_import_erp = 1 WHERE prod_id = ?');
		$stmt2->bindParam(1, $row->prod_id, PDO::PARAM_INT);
		$stmt2->execute();
	}
}

echo "$cpt images crees";