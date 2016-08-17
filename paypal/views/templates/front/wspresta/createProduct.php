<?php
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/config.inc.php');
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/PSWebServiceLibrary.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$manufacturers = array();
$categories    = array();
$features    	 = array();

// Get all manufacturers
try {
	$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
	$xml = $webService->get(array('resource' => 'manufacturers', 'display' => 'full'));
	
	foreach ($xml->manufacturers->manufacturer as $manufacturer) {
		$manufacturers[(int) $manufacturer->id] = (string) utf8_decode($manufacturer->name);
	}
}
catch (PrestaShopWebserviceException $e) {
	echo $error = date('Y-m-d H:i:s') . ' - Error webservice getting manufacturers : ' . $e->getMessage() . "\n";
	file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
	exit(0);
}


// Get all categories
try {
	$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
	$xml = $webService->get(array('resource' => 'categories', 'display' => 'full'));
	
	foreach ($xml->categories->category as $category) {
		$categories[(int) $category->id] = (string) utf8_decode($category->name->language[0]);
	}
}
catch (PrestaShopWebserviceException $e) {
	echo $error = date('Y-m-d H:i:s') . ' - Error webservice getting categories : ' . $e->getMessage() . "\n";
	file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
	exit(0);
}


// Get all features (gender)
try {
	$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
	$xml = $webService->get(array('resource' => 'product_feature_values', 'display' => 'full', 'filter[id_feature]' => '[1]'));
	
	foreach ($xml->product_feature_values->product_feature_value as $feature) {
		$features[(int) $feature->id] = (string) utf8_decode($feature->value->language[0]);
	}
}
catch (PrestaShopWebserviceException $e) {
	echo $error = date('Y-m-d H:i:s') . ' - Error webservice getting features : ' . $e->getMessage() . "\n";
	file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
	exit(0);
}


//boucle sur toutes les produits qui ont un code EAN
$sql = "
	SELECT p.*, g.genre_id, g.genre_nom, a.couleur, c.categorie_id, c.categorie_nom, m.marque_name
	FROM produits p
	INNER JOIN categorie c ON c.categorie_id = p.prod_cat_id
	INNER JOIN genre g ON p.prod_genre_id = g.genre_id
	INNER JOIN marques m ON p.prod_marque_id = m.marque_id
	LEFT JOIN attribut a ON a.prod_id = p.prod_id
	WHERE p.marketplace_erp = 1
	AND p.id_product_erp = 0
	ORDER BY p.prod_id DESC
	LIMIT 150
";
$stmt = $db->query($sql);
$stmt->execute();

$cpt = 0;
while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {

	// Trim values from DB in case
	$row->categorie_nom = trim($row->categorie_nom);
	$row->marque_name 	= trim($row->marque_name);
		
	// Create category if not exists or get ID from array
	if (!in_array($row->categorie_nom, $categories)) {
		$categoryXML = '<?xml version="1.0" encoding="utf-8"?>
			<prestashop>
				<category>
					<id></id>
					<name><language id="1"><![CDATA['.utf8_encode($row->categorie_nom).']]></language></name>
					<description><language id="1"></language></description>
					<link_rewrite><language id="1"><![CDATA['.str2url($row->categorie_nom).']]></language></link_rewrite>
					<meta_description><language id="1"></language></meta_description>
					<meta_keywords><language id="1"></language></meta_keywords>
					<meta_title><language id="1"><![CDATA['.utf8_encode($row->categorie_nom).']]></language></meta_title>
					<active>1</active>
					<id_parent>2</id_parent>
					<is_root_category>0</is_root_category>
				</category>
			</prestashop>';
	
		// Add category
		try {
			$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
			$xml = new SimpleXMLElement($categoryXML);
			$opt = array( 'resource' => 'categories' );
			$opt['postXml'] = $xml->asXML();
			$response = $webService->add( $opt );
			
			// Get new ID from response
			$id_category  = (int) $response->category->id;
			$categories[$id_category] = $row->categorie_nom;
		}
		catch (PrestaShopWebserviceException $e) {
			echo $error = date('Y-m-d H:i:s') . ' - Error webservice creating category : ' . $e->getMessage() . "\n";
			file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
			continue;
		}
		catch (Exception $e) {
			echo $error = date('Y-m-d H:i:s') . ' - Error creating category : ' . $e->getMessage() . "\n";
			file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
			continue;
		}
	} else {
		$id_category = array_search($row->categorie_nom, $categories);
	}
	
				
	// Create manufacturer if not exists or get ID from array
	if (!in_array($row->marque_name, $manufacturers)) {
		$manufacturerXML = '<?xml version="1.0" encoding="utf-8"?>
			<prestashop>
				<manufacturer>
					<id></id>
					<name><![CDATA['.utf8_encode($row->marque_name).']]></name>
					<meta_title><language id="1"><![CDATA['.utf8_encode($row->marque_name).']]></language></meta_title>
					<active>1</active>
				</manufacturer>
			</prestashop>';
	
		// Add manufacturer
		try {
			$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
			$xml = new SimpleXMLElement($manufacturerXML);
			$opt = array( 'resource' => 'manufacturers' );
			$opt['postXml'] = $xml->asXML();
			$response = $webService->add( $opt );
			
			// Get new ID from response
			$id_manufacturer = (int) $response->manufacturer->id;
			$manufacturers[$id_manufacturer] = $row->marque_name;
		}
		catch (PrestaShopWebserviceException $e) {
			echo $error = date('Y-m-d H:i:s') . ' - Error webservice creating manufacturer : ' . $e->getMessage() . "\n";
			file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
			continue;
		}
		catch (Exception $e) {
			echo $error = date('Y-m-d H:i:s') . ' - Error creating manufacturer : ' . $e->getMessage() . "\n";
			file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
			continue;
		}
	} else {
		$id_manufacturer = array_search($row->marque_name, $manufacturers);
	}
	
	// Get feature value
	$id_feature_value = array_search($row->genre_nom, $features);

	$productXML = '<?xml version="1.0" encoding="utf-8"?>
	<prestashop>
		<product>
			<id></id>
			<id_manufacturer>'.$id_manufacturer.'</id_manufacturer>
			<id_supplier></id_supplier>
			<id_category_default>'.$id_category.'</id_category_default>
			<new></new>
			<cache_default_attribute></cache_default_attribute>
			<id_default_image></id_default_image>
			<id_default_combination></id_default_combination>
			<id_tax_rules_group></id_tax_rules_group>
			<position_in_category></position_in_category>
			<type></type>
			<id_shop_default>1</id_shop_default>
			<reference><![CDATA['.strip_spaces($row->prod_ref).']]></reference>
			<supplier_reference></supplier_reference>
			<location></location>
			<width></width>
			<height></height>
			<depth></depth>
			<weight></weight>
			<quantity_discount></quantity_discount>
			<ean13></ean13>
			<upc></upc>
			<cache_is_pack></cache_is_pack>
			<cache_has_attachments></cache_has_attachments>
			<is_virtual></is_virtual>
			<on_sale>1</on_sale>
			<online_only></online_only>
			<ecotax></ecotax>
			<minimal_quantity></minimal_quantity>
			<price>'.(float)$row->prixmoment.'</price>
			<wholesale_price></wholesale_price>
			<unity></unity>
			<unit_price_ratio></unit_price_ratio>
			<additional_shipping_cost></additional_shipping_cost>
			<customizable></customizable>
			<text_fields></text_fields>
			<uploadable_files></uploadable_files>
			<active>1</active>
			<redirect_type></redirect_type>
			<id_product_redirected></id_product_redirected>
			<available_for_order>1</available_for_order>
			<available_date></available_date>
			<condition></condition>
			<show_price>1</show_price>
			<indexed></indexed>
			<visibility></visibility>
			<advanced_stock_management></advanced_stock_management>
			<date_add></date_add>
			<date_upd></date_upd>
			<meta_description><language id="1"><![CDATA['.utf8_encode($row->prod_nom).']]></language></meta_description>
			<meta_keywords><language id="1"></language></meta_keywords>
			<meta_title><language id="1"><![CDATA['.utf8_encode($row->prod_nom).']]></language></meta_title>
			<link_rewrite><language id="1"><![CDATA['.str2url(preg_replace('/\s+/S', '', $row->prod_ref).'-'.$row->prod_nom).']]></language></link_rewrite>
			<name><language id="1"><![CDATA['.utf8_encode($row->prod_nom).']]></language></name>
			<description><language id="1"><![CDATA['.utf8_encode($row->prod_caracteristique).']]></language></description>
			<description_short><language id="1"><![CDATA['.utf8_encode($row->prod_composition).']]></language></description_short>
			<available_now><language id="1"></language></available_now>
			<available_later><language id="1"></language></available_later>
			<associations>
				<categories>
					<category>
						<id>'.$id_category.'</id>
					</category>
				</categories>
				<images>
					<image>
						<id></id>
					</image>
				</images>
				<combinations>
					<combination>
						<id></id>
					</combination>
				</combinations>
				<product_option_values>
					<product_option_value>
						<id></id>
					</product_option_value>
				</product_option_values>
				<product_features>
					<product_feature>
						<id>1</id>
						<id_feature_value>'.$id_feature_value.'</id_feature_value>
					</product_feature>
				</product_features>
				<tags>
					<tag>
						<id></id>
					</tag>
				</tags>
				<stock_availables>
					<stock_available>
						<id></id>
						<id_product_attribute></id_product_attribute>
					</stock_available>
				</stock_availables>
				<accessories>
					<product>
						<id></id>
					</product>
				</accessories>
				<product_bundle>
					<product>
						<id></id>
						<quantity></quantity>
					</product>
				</product_bundle>
			</associations>
		</product>
	</prestashop>';
	
	// Here we make the WebService Call
	try {
		$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
		
		$xml = new SimpleXMLElement($productXML);
		$opt = array( 'resource' => 'products' );
		$opt['postXml'] = $xml->asXML();
		$objResponse = $webService->add( $opt );

		// Get new Presta ID
		$id_product_erp = (int) $objResponse->product->id;
		
		if ($id_product_erp) {
			$cpt++;
			$stmt2 = $db->prepare('UPDATE produits SET id_product_erp = ? WHERE prod_id = ?');
			$stmt2->bindParam(1, $id_product_erp, PDO::PARAM_INT);
			$stmt2->bindParam(2, $row->prod_id, PDO::PARAM_INT);
			$stmt2->execute();
		}
	}
	catch (PrestaShopWebserviceException $e) {
		echo $error = date('Y-m-d H:i:s') . ' - Error webservice creating product '.$row->prod_id.' : ' . $e->getMessage() . "\n";
		file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
		continue;
	}
	catch (Exception $e) {
		echo $error = date('Y-m-d H:i:s') . ' - Error creating product '.$row->prod_id.' : ' . $e->getMessage() . "\n";
		var_dump($productXML);
		file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
		continue;
	}
}

echo "$cpt produits crees";