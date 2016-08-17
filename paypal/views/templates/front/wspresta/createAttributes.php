<?php
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/config.inc.php');
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/PSWebServiceLibrary.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get all declinaisons
$product_option_values = array();
try {
	$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
	$xml = $webService->get(array('resource' => 'product_option_values', 'display' => 'full', 'filter[id_attribute_group]' => '[1]'));
	
	foreach ($xml->product_option_values->product_option_value as $product_option_value) {
		$product_option_values[(int) $product_option_value->id] = (string) utf8_decode($product_option_value->name->language[0]);
	}
}
catch (PrestaShopWebserviceException $e) {
	echo $error = date('Y-m-d H:i:s') . ' - Error getting product_option_value : ' . $e->getMessage() . "\n";
	file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
	exit(0);
}

//boucle sur toutes les produits
$sql = "
	SELECT p.* 
	FROM produits p 
	WHERE p.marketplace_erp = 1 
	AND p.id_product_erp > 0 
	ORDER BY p.prod_id DESC
";
$stmt = $db->query($sql);
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	// Recupère les tailles pour chaque produit et les infos ean, stock...
	$sql = "
		SELECT s.*, e.ean, t.ss_taille_nom AS nom_taille, ss_taille_order AS position, t.ss_taille_id AS id_taille
		FROM stock s
		INNER JOIN ss_taille t ON (t.ss_taille_id = s.stock_ss_taille_id)
		LEFT JOIN ean e ON (e.prod_id = s.stock_prod_id AND e.stock_ss_taille_id = s.stock_ss_taille_id)
		WHERE s.erp_declinaison = 0
		AND s.stock_prod_id = ?
	";
	$stmt2 = $db->prepare($sql);
	$stmt2->bindParam(1, $row->prod_id, PDO::PARAM_INT);
	$stmt2->execute();
	
	$i=0; // Compteur taille
	while ($taille = $stmt2->fetch(PDO::FETCH_OBJ)) {
		// Format values
		$default_on = ($i == 0) ? true: false;
		$i++;
	
		if (!in_array($taille->nom_taille, $product_option_values)) {
			$attributeXML = '<?xml version="1.0" encoding="utf-8"?>
				<prestashop>
					<product_option_value>
						<id></id>
						<id_attribute_group>1</id_attribute_group>
						<position>'.$taille->position.'</position>
						<name><language id="1"><![CDATA['.utf8_encode(str_replace('=', '', $taille->nom_taille)).']]></language></name>
					</product_option_value>
				</prestashop>';
		
			// Add attribute
			try {
				$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
				$xml = new SimpleXMLElement($attributeXML);
				$opt = array( 'resource' => 'product_option_values' );
				$opt['postXml'] = $xml->asXML();
				$response = $webService->add( $opt );
				
				// Get new ID from response
				$id_product_option_value = (int) $response->product_option_value->id;
				$product_option_values[$id_product_option_value] = $taille->nom_taille;

				if ($id_product_option_value) {
					$stmt3 = $db->prepare('UPDATE ss_taille SET ss_taille_id_attribute_erp = ? WHERE ss_taille_id = ?');
					$stmt3->bindParam(1, $id_product_option_value, PDO::PARAM_INT);
					$stmt3->bindParam(2, $taille->id_taille, PDO::PARAM_INT);
					$stmt3->execute();
				}
			}
			catch (PrestaShopWebserviceException $e) {
				echo $error = date('Y-m-d H:i:s') . ' - Error webservice creating product_option_value '.$row->prod_id.' : ' . $e->getMessage() . "\n";
				file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
				continue;
			}
			catch (Exception $e) {
				echo $error = date('Y-m-d H:i:s') . ' - Error creating product_option_value '.$row->prod_id.' : ' . $e->getMessage() . "\n";
				file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
				continue;
			}
		} else {
			$id_product_option_value = array_search($taille->nom_taille, $product_option_values);
		}
		
		// EAN check
		$ean = NULL;
		if (checkEAN13(strip_spaces($taille->ean))) {
			$ean = strip_spaces($taille->ean);
		}
		
		$combinationXML = '<?xml version="1.0" encoding="utf-8"?>
			<prestashop>
				<combination>
					<id></id>
					<id_product>'.$row->id_product_erp.'</id_product>
					<location></location>
					<ean13><![CDATA['.$ean.']]></ean13>
					<quantity></quantity>
					<reference><![CDATA['.$row->id_product_erp.'_'.$id_product_option_value.']]></reference>
					<supplier_reference><![CDATA['.$row->prod_id.'_'.$taille->id_taille.']]></supplier_reference>
					<wholesale_price></wholesale_price>
					<price></price>
					<ecotax></ecotax>
					<weight></weight>
					<unit_price_impact></unit_price_impact>
					<minimal_quantity>1</minimal_quantity>
					<default_on>'.$default_on.'</default_on>
					<available_date></available_date>
					<associations>
						<product_option_values>
							<product_option_value>
								<id>'.$id_product_option_value.'</id>
							</product_option_value>
						</product_option_values>
						<images>
							<image>
								<id></id>
							</image>
						</images>
					</associations>
				</combination>
			</prestashop>';
	
		// Add combination
		try {
			$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
			$xml = new SimpleXMLElement($combinationXML);
			$opt = array( 'resource' => 'combinations' );
			$opt['postXml'] = $xml->asXML();
			$response = $webService->add( $opt );
			
			// Get new ID from response
			$erp_declinaison = (int) $response->combination->id;

			if ($erp_declinaison) {
				$stmt4 = $db->prepare('UPDATE stock SET erp_declinaison = ? WHERE stock_prod_id = ? AND stock_ss_taille_id = ?');
				$stmt4->bindParam(1, $erp_declinaison, PDO::PARAM_INT);
				$stmt4->bindParam(2, $row->prod_id, PDO::PARAM_INT);
				$stmt4->bindParam(3, $taille->id_taille, PDO::PARAM_INT);
				$stmt4->execute();
			}
		}
		catch (PrestaShopWebserviceException $e) {
			echo $error = date('Y-m-d H:i:s') . ' - Error webservice creating combination '.$row->prod_id.'_'.$taille->id_taille.' : ' . $e->getMessage() . "\n";
			var_dump($combinationXML);
			file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
			continue;
		}
		catch (Exception $e) {
			echo $error = date('Y-m-d H:i:s') . ' - Error creating combination '.$row->prod_id.'_'.$taille->id_taille.' : ' . $e->getMessage() . "\n";
			var_dump($combinationXML);
			file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
			continue;
		}
	}
}

echo 'done';