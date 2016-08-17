<?php	
	// Prevent cache
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");

  // Chargement configuration et fonctions
  require_once dirname(__FILE__) . '/DB.php';
	$dba = DB::getConnexion();
	
	// Sanitize post data
	$apptoken = filter_input(INPUT_SERVER, 'HTTP_X_APP_TOKEN', FILTER_DEFAULT);
	
	// Verif Token
	if (!$apptoken || empty($apptoken)) {
		echo json_encode(array("error" => 1, "message" => "Token d'identification manquant, la requête ne peut aboutir"));
		exit(0);
	}
	
	// Récup id client via token
	$requete = $dba->prepare("SELECT * FROM a1neovapo2.client_token WHERE apptoken = :apptoken");
	$requete->bindValue('apptoken', $apptoken, PDO::PARAM_STR);
	$requete->execute();
	$client = $requete->fetch(PDO::FETCH_ASSOC);
	
	// Verif id client
	if (!isset($client['id_customer'])) {
		echo json_encode(array("error" => 1, "message" => "Votre session a expiré, veuillez vous reconnecter"));
		exit(0);
	}
	
	// Si ici on est ok
	echo json_encode(array("error" => 0, "message" => "ok"));
	exit(0);
?>