<?php
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/config.inc.php');
require_once('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/PSWebServiceLibrary.php');

// Here we make the WebService Call
try {
	$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
	
	$opts = array(
		'resource' 							=> 'orders',
		'display' 							=> 'full',
		'filter[current_state]'	=> '[2,9]',
		'sort'									=> '[id_DESC]'
	);

	$xml = $webService->get($opts);
}
catch (PrestaShopWebserviceException $e) {
	echo $error = date('Y-m-d H:i:s') . ' - Error getting orders : ' . $e->getMessage() . "\n";
	file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
	exit(0);
}

// Init total
$count = 0;


// Loop orders
foreach ($xml->orders->order as $order) {	
	// Récupère l'id de la commande
	$OrderId = (int)$order->id;
	
	//if ($OrderId == 6) {

		// Check order already exists in db
		$stmt = $db->prepare('SELECT COUNT(*) FROM commandes_erp WHERE orders_erp_id = ?');
		$stmt->bindParam(1, $OrderId, PDO::PARAM_INT);
		$stmt->execute();
		$total = (int)$stmt->fetchColumn();

		if ($total > 0) {		
			// Si commande existe deja dans la base on ne traite pas
		} else {
			// Here we make the other necessary WebService Calls
			try {
				$webService 		 	= new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
				$addressInvoice  	= $webService->get(array('resource' => 'addresses', 'id' => $order->id_address_invoice));
				$addressDelivery 	= $webService->get(array('resource' => 'addresses', 'id' => $order->id_address_delivery));
				$countryInvoice  	= $webService->get(array('resource' => 'countries', 'id' => $addressInvoice->address->id_country));
				$countryDelivery 	= $webService->get(array('resource' => 'countries', 'id' => $addressDelivery->address->id_country));
				if (isset($addressInvoice->address->id_state) && !empty($addressInvoice->address->id_state)) {
					$stateInvoice  	= $webService->get(array('resource' => 'states', 'id'	=> $addressInvoice->address->id_state));
				}
				if (isset($addressDelivery->address->id_state) && !empty($addressDelivery->address->id_state)) {
					$stateDelivery 	= $webService->get(array('resource' => 'states', 'id'	=> $addressDelivery->address->id_state));
				}
				$customer 				= $webService->get(array('resource' => 'customers', 'id' => $order->id_customer));
				$carrier 					= $webService->get(array('resource' => 'carriers', 'id' => $order->id_carrier));
			}
			catch (PrestaShopWebserviceException $e) {
				$trace = $e->getTrace();
				echo $error = date('Y-m-d H:i:s') . ' - Error getting additionnal infos "'.@$trace[1]['args'][0]['resource'].'" for order #'.$OrderId.' : ' . $e->getMessage() . "\n";
				file_put_contents('/var/www/vhosts/allezdiscount.com/httpdocs/wspresta/logs.txt', $error, FILE_APPEND | LOCK_EX);
				continue; //If missing info skip this order because we dont want an incomplete order in db
			}
			
			// Increment total
			$count = ($count+1);
			
			// Récupère l'adresse facturation
			$realtitre_fact       = "";
			$nom_fact             = utf8_decode($addressInvoice->address->lastname);
			$prenom_fact          = utf8_decode($addressInvoice->address->firstname);
			$societe_fact         = utf8_decode($addressInvoice->address->company);
			$telhome_fact         = utf8_decode($addressInvoice->address->phone);
			$teloffice_fact       = utf8_decode($addressInvoice->address->phone_mobile);
			$adresse_rue1_fact    = utf8_decode($addressInvoice->address->address1);
			$adresse_rue2_fact    = utf8_decode($addressInvoice->address->address2);
			$adresse_cpostal_fact = utf8_decode($addressInvoice->address->postcode);
			$adresse_ville_fact   = utf8_decode($addressInvoice->address->city);
			if (isset($stateInvoice->state->name) && !empty($stateInvoice->state->name)) {
				$adresse_ville_fact .= ' ('.utf8_decode($stateInvoice->state->name).')';
			}
			$adresse_pays_fact    = utf8_decode($countryInvoice->country->name->language);
			$email_fact           = utf8_decode($customer->customer->email);

			// Récupère l'adresse livraison
			$realtitre_livr       = "";
			$nom_livr             = utf8_decode($addressDelivery->address->lastname);
			$prenom_livr          = utf8_decode($addressDelivery->address->firstname);
			$societe_livr         = utf8_decode($addressDelivery->address->company);
			$telhome_livr         = utf8_decode($addressDelivery->address->phone);
			$teloffice_livr       = utf8_decode($addressDelivery->address->phone_mobile);
			$adresse_rue1_livr    = utf8_decode($addressDelivery->address->address1);
			$adresse_rue2_livr    = utf8_decode($addressDelivery->address->address2);
			$adresse_cpostal_livr = utf8_decode($addressDelivery->address->postcode);
			$adresse_ville_livr   = utf8_decode($addressDelivery->address->city);
			if (isset($stateDelivery->state->name) && !empty($stateDelivery->state->name)) {
				$adresse_ville_livr .= ' ('.utf8_decode($stateDelivery->state->name).')';
			}
			$adresse_pays_livr    = utf8_decode($countryDelivery->country->name->language);
			
			
			/**** Check customer already exists in db ****/
			$stmt2 = $db->prepare('SELECT COUNT(*) FROM client WHERE client_mail = ?');
			$stmt2->bindParam(1, $email_fact, PDO::PARAM_STR);
			$stmt2->execute();
			$total2 = $stmt2->fetchColumn();

			if ($total2 == 0) {
				//table customers
				$sql = "INSERT INTO client (client_genre, client_nom, client_prenom, client_mail, client_phone, client_phone2, client_mdp, client_newsletter, client_date_inscription) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
				
				$stmt3 = $db->prepare($sql);
				$stmt3->bindValue(1, $realtitre_fact, PDO::PARAM_STR);
				$stmt3->bindValue(2, $nom_fact, PDO::PARAM_STR);
				$stmt3->bindValue(3, $prenom_fact, PDO::PARAM_STR);
				$stmt3->bindValue(4, $email_fact, PDO::PARAM_STR);
				$stmt3->bindValue(5, $telhome_fact, PDO::PARAM_STR);
				$stmt3->bindValue(6, $teloffice_fact, PDO::PARAM_STR);
				$stmt3->bindValue(7, 'allezdiscount', PDO::PARAM_STR);
				$stmt3->bindValue(8, 1, PDO::PARAM_INT);
				$stmt3->bindValue(9, date("Y-m-d H:i:s"), PDO::PARAM_STR);
				if ($stmt3->execute()) {
					echo 'Client '.$nom_fact.' ajoute en bdd ! <br />';
				}

				$clientLastId = $db->lastInsertId();

				//table adresse					
				$sql  = "INSERT INTO client_adresse (client_adresse_client_id, client_adresse_genre, client_adresse_nom, client_adresse_prenom, client_adresse_societe, client_adresse_adresse1, client_adresse_adresse2, ";
				$sql .= "client_adresse_cp, client_adresse_ville, client_adresse_pays, client_adresse_phone, client_adresse_phone2) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
				
				$stmt4 = $db->prepare($sql);
				$stmt4->bindValue(1,  $clientLastId, PDO::PARAM_INT);
				$stmt4->bindValue(2,  $realtitre_livr, PDO::PARAM_STR);
				$stmt4->bindValue(3,  $nom_livr, PDO::PARAM_STR);
				$stmt4->bindValue(4,  $prenom_livr, PDO::PARAM_STR);
				$stmt4->bindValue(5,  $societe_livr, PDO::PARAM_STR);
				$stmt4->bindValue(6,  $adresse_rue1_livr, PDO::PARAM_STR);
				$stmt4->bindValue(7,  $adresse_rue2_livr, PDO::PARAM_STR);
				$stmt4->bindValue(8,  $adresse_cpostal_livr, PDO::PARAM_STR);
				$stmt4->bindValue(9,  $adresse_ville_livr, PDO::PARAM_STR);
				$stmt4->bindValue(10, $adresse_pays_livr, PDO::PARAM_STR);
				$stmt4->bindValue(11, $telhome_livr, PDO::PARAM_STR);
				$stmt4->bindValue(12, $teloffice_livr, PDO::PARAM_STR);
				if ($stmt4->execute()) {
					echo 'Adresse client '.$nom_livr.' ajoute en bdd ! <br />';
				}

				$adresseLastId = $db->lastInsertId();

				//update de l'adresse ds le compte client
				$stmt5 = $db->prepare('UPDATE client SET client_adresse_defaut = ? WHERE client_num = ?');
				$stmt5->bindParam(1, $adresseLastId, PDO::PARAM_INT);
				$stmt5->bindParam(2, $clientLastId, PDO::PARAM_INT);
				$stmt5->execute();
			} 
			else {
				// Get user id
				$stmt3 = $db->prepare('SELECT client_num FROM client WHERE client_mail = ?');
				$stmt3->bindParam(1, $email_fact, PDO::PARAM_STR);
				$stmt3->execute();	

				$clientLastId = $stmt3->fetchColumn();
			}
			
			
			/**** Store order ****/
			$date 					= $order->date_add; // Date
			$idtransp 		  = 1; // Transporteur
			$payment_method = utf8_decode($order->payment); // Type paiement 
			
			$sql  = "INSERT INTO commandes (orders_client_id, orders_client_nom_fact, orders_client_prenom_fact, orders_client_societe_fact, orders_client_adresse1_fact, orders_client_adresse2_fact, orders_client_cp_fact,";
			$sql .= "orders_client_ville_fact, orders_client_pays_fact, orders_client_mail_fact, orders_client_nom_livr, orders_client_prenom_livr, orders_client_societe_livr, orders_client_adresse1_livr, ";
			$sql .= "orders_client_adresse2_livr, orders_client_cp_livr, orders_client_ville_livr, orders_client_pays_livr, orders_id_transaction, orders_date_achat, orders_status, orders_fianet, orders_controleAD, ";
			$sql .= "orders_num_livraison, orders_type_vente, orders_cc_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			
			$stmt6 = $db->prepare($sql);
			$stmt6->bindValue(1,  $clientLastId, PDO::PARAM_INT);
			$stmt6->bindValue(2,  $nom_fact, PDO::PARAM_STR);
			$stmt6->bindValue(3,  $prenom_fact, PDO::PARAM_STR);
			$stmt6->bindValue(4,  $societe_fact, PDO::PARAM_STR);
			$stmt6->bindValue(5,  $adresse_rue1_fact, PDO::PARAM_STR);
			$stmt6->bindValue(6,  $adresse_rue2_fact, PDO::PARAM_STR);
			$stmt6->bindValue(7,  $adresse_cpostal_fact, PDO::PARAM_STR);
			$stmt6->bindValue(8,  $adresse_ville_fact, PDO::PARAM_STR);
			$stmt6->bindValue(9,  $adresse_pays_fact, PDO::PARAM_STR);
			$stmt6->bindValue(10, $email_fact, PDO::PARAM_STR);
			$stmt6->bindValue(11, $nom_livr, PDO::PARAM_STR);
			$stmt6->bindValue(12, $prenom_livr, PDO::PARAM_STR);
			$stmt6->bindValue(13, $societe_livr, PDO::PARAM_STR);
			$stmt6->bindValue(14, $adresse_rue1_livr, PDO::PARAM_STR);
			$stmt6->bindValue(15, $adresse_rue2_livr, PDO::PARAM_STR);
			$stmt6->bindValue(16, $adresse_cpostal_livr, PDO::PARAM_STR);
			$stmt6->bindValue(17, $adresse_ville_livr, PDO::PARAM_STR);
			$stmt6->bindValue(18, $adresse_pays_livr, PDO::PARAM_STR);
			$stmt6->bindValue(19, $OrderId, PDO::PARAM_STR);
			$stmt6->bindValue(20, $date, PDO::PARAM_STR);
			$stmt6->bindValue(21, 8, PDO::PARAM_INT);
			$stmt6->bindValue(22, 100, PDO::PARAM_INT);
			$stmt6->bindValue(23, 300, PDO::PARAM_INT);
			$stmt6->bindValue(24, $idtransp, PDO::PARAM_INT);
			$stmt6->bindValue(25, 10, PDO::PARAM_INT);
			$stmt6->bindValue(26, $payment_method, PDO::PARAM_STR);
			if ($stmt6->execute()) {
				echo 'Commande client '.$OrderId.' ajoute en bdd ! <br />';
			}

			$commandeLastId = $db->lastInsertId();
			
			// Ajout de la commande dans table commandes_erp
			$sql = "INSERT INTO commandes_erp (orders_allezdiscount_id, orders_erp_id, is_processed) VALUES(?, ?, ?)";
			$stmt7 = $db->prepare($sql);
			$stmt7->bindValue(1, $commandeLastId, PDO::PARAM_INT);
			$stmt7->bindValue(2, $OrderId, PDO::PARAM_STR);
			$stmt7->bindValue(3, 0, PDO::PARAM_STR);
			if ($stmt7->execute()) {
				echo 'Commande erp '.$OrderId.' | '.$commandeLastId.' ajoute en bdd ! <br />';
			}
			
			
			// Initialisation des variables pour les prix			
			foreach($order->associations->order_rows->order_row as $produit) {

				$nom_prod 	= utf8_decode($produit->product_name);				
				$quantite 	= $produit->product_quantity;
				$sku			 	= explode('_', utf8_decode($produit->product_reference));	
				$price 			= floatval($produit->unit_price_tax_incl);
												
				//recuperation des autres infos du produit grace a son SKU
				$stmt8 = $db->prepare('SELECT prod_id, prod_ref FROM produits WHERE id_product_erp = ?');
				$stmt8->bindParam(1, $sku[0], PDO::PARAM_INT);
				if ($stmt8->execute()) {
					$autresInfosProd = $stmt8->fetch(PDO::FETCH_ASSOC);
				}
				
				// Récupère le nm de la taille
				$stmt9 = $db->prepare('SELECT ss_taille_id, ss_taille_nom FROM ss_taille WHERE ss_taille_id_attribute_erp = ?');
				$stmt9->bindValue(1, $sku[1], PDO::PARAM_INT);
				if ($stmt9->execute()) {
					$autresInfosTail = $stmt9->fetch(PDO::FETCH_ASSOC);
				}
				
				$paProduit = 0;
				$id_prod   	 = $autresInfosProd['prod_id'];
				$refProduit  = $autresInfosProd['prod_ref'];
				$id_taille   = $autresInfosTail['ss_taille_id'];
				$nomtProduit = $autresInfosTail['ss_taille_nom'];
				
				$sql  = "INSERT INTO commandes_produits (commandes_produits_orders_id, commandes_produits_id_produit, commandes_produits_ref_produit, commandes_produits_nom_produit, commandes_produits_prix, "; 
				$sql .= "commandes_produits_pa, commandes_produits_qte, commandes_produits_id_taille, commandes_produits_nom_taille, commandes_produits_retour, commandes_produits_echange, commandes_produits_type_promo) ";
				$sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
				
				$stmt10 = $db->prepare($sql);
				$stmt10->bindValue(1,  $commandeLastId, PDO::PARAM_INT);
				$stmt10->bindValue(2,  $id_prod, PDO::PARAM_INT);
				$stmt10->bindValue(3,  $refProduit, PDO::PARAM_STR);
				$stmt10->bindValue(4,  $nom_prod, PDO::PARAM_STR);
				$stmt10->bindValue(5,  $price, PDO::PARAM_INT);
				$stmt10->bindValue(6,  $paProduit, PDO::PARAM_INT);
				$stmt10->bindValue(7,  $quantite, PDO::PARAM_INT);
				$stmt10->bindValue(8,  $id_taille, PDO::PARAM_INT);
				$stmt10->bindValue(9,  $nomtProduit, PDO::PARAM_STR);
				$stmt10->bindValue(10, 0, PDO::PARAM_INT);
				$stmt10->bindValue(11, 0, PDO::PARAM_INT);
				$stmt10->bindValue(12, 1, PDO::PARAM_INT);	
				if ($stmt10->execute()) {
					echo 'Produit commande '.$commandeLastId.' ajoute en bdd ! <br />';
				} 						
				
				//update du stock
				$stmt11 = $db->prepare("UPDATE stock SET stock_qte = (stock_qte-$quantite) WHERE stock_prod_id = ? AND stock_ss_taille_id = ?");
				$stmt11->bindParam(1, $id_prod, PDO::PARAM_INT);
				$stmt11->bindParam(2, $id_taille, PDO::PARAM_INT);
				if ($stmt11->execute()) {
					echo 'Stock produit '.$id_prod.'_'.$id_taille.' commande '.$commandeLastId.' preleve ('.$quantite.') ! <br />';
				}
			}
			
			/**** Totaux commande ****/
			$frais_de_port = (float) $order->total_shipping;
			$totalTTC 		 = (float) $order->total_paid;
			$sous_tot 		 = ($totalTTC-$frais_de_port);
			$TVA 					 = round((($totalTTC/1.2)*0.2),2);
			
			//Ajout des totaux
			$stmt12 = $db->prepare("INSERT INTO commandes_total (commandes_total_orders_id, commandes_total_title, commandes_total_value, commandes_total_class) VALUES (?, ?, ?, ?)");
			$stmt12->bindValue(1, $commandeLastId, PDO::PARAM_INT);
			$stmt12->bindValue(2, 'Sous Total TTC : ', PDO::PARAM_STR);
			$stmt12->bindValue(3, $sous_tot, PDO::PARAM_INT);
			$stmt12->bindValue(4, 'ot_subtotal', PDO::PARAM_STR);
			$stmt12->execute();				

			$stmt13 = $db->prepare("INSERT INTO commandes_total (commandes_total_orders_id, commandes_total_title, commandes_total_value, commandes_total_class) VALUES (?, ?, ?, ?)");
			$stmt13->bindValue(1, $commandeLastId, PDO::PARAM_INT);
			$stmt13->bindValue(2, 'dont TVA 20.0%:', PDO::PARAM_STR);
			$stmt13->bindValue(3, $TVA, PDO::PARAM_INT);
			$stmt13->bindValue(4, 'ot_tax', PDO::PARAM_STR);
			$stmt13->execute();				

			$stmt14 = $db->prepare("INSERT INTO commandes_total (commandes_total_orders_id, commandes_total_title, commandes_total_value, commandes_total_class) VALUES (?, ?, ?, ?)");
			$stmt14->bindValue(1, $commandeLastId, PDO::PARAM_INT);
			$stmt14->bindValue(2, $nom_transport, PDO::PARAM_STR);
			$stmt14->bindValue(3, $frais_de_port, PDO::PARAM_INT);
			$stmt14->bindValue(4, 'ot_shipping', PDO::PARAM_STR);
			$stmt14->execute();				

			$stmt15 = $db->prepare("INSERT INTO commandes_total (commandes_total_orders_id, commandes_total_title, commandes_total_value, commandes_total_class) VALUES (?, ?, ?, ?)");
			$stmt15->bindValue(1, $commandeLastId, PDO::PARAM_INT);
			$stmt15->bindValue(2, 'Total TTD:', PDO::PARAM_STR);
			$stmt15->bindValue(3, $totalTTC, PDO::PARAM_INT);
			$stmt15->bindValue(4, 'ot_total', PDO::PARAM_STR);
			if ($stmt15->execute()) {
				echo 'Commande totaux '.$commandeLastId.' ajoute en bdd ! <br />';
			}

			//AJOUT du statut du la commande
			$stmt16 = $db->prepare("INSERT INTO commandes_status_history (commandes_status_history_orders_id, commandes_status_history_orders_status, commandes_status_history_date) VALUES (?, ?, ?)");
			$stmt16->bindValue(1, $commandeLastId, PDO::PARAM_INT);
			$stmt16->bindValue(2, 8, PDO::PARAM_INT);
			$stmt16->bindValue(3, $date, PDO::PARAM_STR);
			if ($stmt16->execute()) {
				echo 'Commande statut '.$commandeLastId.' ajoute en bdd ! <br />';
			}

			$description_compta = "Facture n°" . $commandeLastId;
			$marchand_ht 				= round(($sous_tot / 1.200), 2);
			$fdp_ht 						= round(($frais_de_port / 1.200), 2);			
			
			//AJOUT compta
			$stmt17 = $db->prepare("INSERT INTO compta (compta_date, debit_lib, debit_value, compta_desc, compta_orders_id) VALUES (?, ?, ?, ?, ?)");
			$stmt17->bindValue(1, $date, PDO::PARAM_STR);
			$stmt17->bindValue(2, '011000', PDO::PARAM_STR);
			$stmt17->bindValue(3, $totalTTC, PDO::PARAM_INT);
			$stmt17->bindValue(4, $description_compta, PDO::PARAM_STR);
			$stmt17->bindValue(5, $commandeLastId, PDO::PARAM_STR);
			$stmt17->execute();

			$stmt18 = $db->prepare("INSERT INTO compta (compta_date, credit_lib, credit_value, compta_desc, compta_orders_id) VALUES (?, ?, ?, ?, ?)");
			$stmt18->bindValue(1, $date, PDO::PARAM_STR);
			$stmt18->bindValue(2, '7071', PDO::PARAM_STR);
			$stmt18->bindValue(3, $marchand_ht, PDO::PARAM_INT);
			$stmt18->bindValue(4, $description_compta, PDO::PARAM_STR);
			$stmt18->bindValue(5, $commandeLastId, PDO::PARAM_STR);
			$stmt18->execute();				

			$stmt19 = $db->prepare("INSERT INTO compta (compta_date, credit_lib, credit_value, compta_desc, compta_orders_id) VALUES (?, ?, ?, ?, ?)");
			$stmt19->bindValue(1, $date, PDO::PARAM_STR);
			$stmt19->bindValue(2, '7081', PDO::PARAM_STR);
			$stmt19->bindValue(3, $fdp_ht, PDO::PARAM_INT);
			$stmt19->bindValue(4, $description_compta, PDO::PARAM_STR);
			$stmt19->bindValue(5, $commandeLastId, PDO::PARAM_STR);
			$stmt19->execute();				

			$stmt20 = $db->prepare("INSERT INTO compta (compta_date, credit_lib, credit_value, compta_desc, compta_orders_id) VALUES (?, ?, ?, ?, ?)");
			$stmt20->bindValue(1, $date, PDO::PARAM_STR);
			$stmt20->bindValue(2, '44571', PDO::PARAM_STR);
			$stmt20->bindValue(3, $TVA, PDO::PARAM_INT);
			$stmt20->bindValue(4, $description_compta, PDO::PARAM_STR);
			$stmt20->bindValue(5, $commandeLastId, PDO::PARAM_STR);
			if ($stmt20->execute()) {
				echo 'Commande compta '.$commandeLastId.' ajoute en bdd ! <br />';
			}


			//////////Compta2/////////////////////////////////////////////////////////////////////////////////////////////////////
			$description_compta = "F";
			$tva = $totalTTC - $marchand_ht - $fdp_ht;
			
			$stmt21 = $db->prepare("INSERT INTO compta2 (compta_date, lib, debit_value, credit_value, compta_desc, compta_orders_id) VALUES (?, ?, ?, ?, ?, ?)");
			$stmt21->bindValue(1, $date, PDO::PARAM_STR);
			$stmt21->bindValue(2, '411', PDO::PARAM_STR);
			$stmt21->bindValue(3, $totalTTC, PDO::PARAM_INT);
			$stmt21->bindValue(4, 0, PDO::PARAM_INT);
			$stmt21->bindValue(5, $description_compta, PDO::PARAM_STR);
			$stmt21->bindValue(6, $commandeLastId, PDO::PARAM_STR);
			$stmt21->execute();				

			$stmt22 = $db->prepare("INSERT INTO compta2 (compta_date, lib, debit_value, credit_value, compta_desc, compta_orders_id) VALUES (?, ?, ?, ?, ?, ?)");
			$stmt22->bindValue(1, $date, PDO::PARAM_STR);
			$stmt22->bindValue(2, '7071', PDO::PARAM_STR);
			$stmt22->bindValue(3, 0, PDO::PARAM_INT);
			$stmt22->bindValue(4, $marchand_ht, PDO::PARAM_INT);
			$stmt22->bindValue(5, $description_compta, PDO::PARAM_STR);
			$stmt22->bindValue(6, $commandeLastId, PDO::PARAM_STR);
			$stmt22->execute();				

			$stmt23 = $db->prepare("INSERT INTO compta2 (compta_date, lib, debit_value, credit_value, compta_desc, compta_orders_id) VALUES (?, ?, ?, ?, ?, ?)");
			$stmt23->bindValue(1, $date, PDO::PARAM_STR);
			$stmt23->bindValue(2, '7081', PDO::PARAM_STR);
			$stmt23->bindValue(3, 0, PDO::PARAM_INT);
			$stmt23->bindValue(4, $fdp_ht, PDO::PARAM_INT);
			$stmt23->bindValue(5, $description_compta, PDO::PARAM_STR);
			$stmt23->bindValue(6, $commandeLastId, PDO::PARAM_STR);
			$stmt23->execute();				

			$stmt24 = $db->prepare("INSERT INTO compta2 (compta_date, lib, credit_value, compta_desc, compta_orders_id) VALUES (?, ?, ?, ?, ?)");
			$stmt24->bindValue(1, $date, PDO::PARAM_STR);
			$stmt24->bindValue(2, '44571', PDO::PARAM_STR);
			$stmt24->bindValue(3, $tva, PDO::PARAM_INT);
			$stmt24->bindValue(4, $description_compta, PDO::PARAM_STR);
			$stmt24->bindValue(5, $commandeLastId, PDO::PARAM_STR);
			if ($stmt24->execute()) {
				echo 'Commande compta2 '.$commandeLastId.' ajoute en bdd ! <br />';
			}
			
			
			// Here we make the WebService Call
			try {
				$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
								
				$xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/order_histories?schema=blank'));
				$xml->order_history->id_order_state = 3;
				$xml->order_history->id_order = $OrderId;
				unset($xml->order_history->id);
				unset($xml->order_history->id_employee);
				unset($xml->order_history->date_add);
				$opt = array(
					//'resource' => 'order_histories?sendemail=1',
					'resource' => 'order_histories',
					'postXml'  => $xml->asXML()
				);
				$reponse = $webService->add($opt);
			}
			catch (PrestaShopWebserviceException $e) {
				echo $e->getMessage();
			}
			
			if (isset($reponse->order_history->id) && !empty($reponse->order_history->id)) {
				$stmt25 = $db->prepare('UPDATE commandes_erp SET is_processed = 1, orders_import_date = NOW() WHERE orders_allezdiscount_id = ? AND orders_erp_id = ?');
				$stmt25->bindValue(1, $commandeLastId, PDO::PARAM_INT);
				$stmt25->bindValue(2, $OrderId, PDO::PARAM_STR);
				if ($stmt25->execute()) {
					echo 'Commande erp '.$commandeLastId.' processed ! <br />';
				}
			} else {
				$stmt25 = $db->prepare('UPDATE commandes_erp SET is_processed = -1, orders_import_date = NOW() WHERE orders_allezdiscount_id = ? AND orders_erp_id = ?');
				$stmt25->bindValue(1, $commandeLastId, PDO::PARAM_INT);
				$stmt25->bindValue(2, $OrderId, PDO::PARAM_STR);
				if ($stmt25->execute()) {
					echo 'Commande erp '.$commandeLastId.' unprocessed ! <br />';
				}
			}
		}
	//}
}

// Alert if no unprocessed orders found
if ($count == 0) {
	echo 'Aucun commande a traiter <br />';
}
?>
