<?php	
	// Prevent cache
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	
	// Chargement configuration et fonctions
	require_once dirname(__FILE__) . '/../interface/includes/config.php';
	require_once dirname(__FILE__) . '/../interface/includes/functions.php'; 
	require_once dirname(__FILE__) . '/../interface/lib/DB.php';
	require_once dirname(__FILE__) . '/../interface/lib/autoload.inc.php';
		
	// Récupération de la connexion à la base de données du presta
	$db = DB::getConnexion();
	
	// Sanitize post data
	$apptoken = filter_input(INPUT_SERVER, 'HTTP_X_APP_TOKEN', FILTER_DEFAULT);
	$id_sujet = filter_input(INPUT_GET, 'id_sujet', FILTER_VALIDATE_INT);

	// Verif Token
	if (!$apptoken || empty($apptoken)) {
		echo json_encode(array("error" => 1, "message" => "Token d'identification manquant, la requête ne peut aboutir"));
		exit(0);
	}
		
	// Récup id client via token
	$requete = $db->prepare("SELECT * FROM client_token WHERE apptoken = :apptoken");
	$requete->bindValue('apptoken', $apptoken, PDO::PARAM_STR);
	$requete->execute();
	$client = $requete->fetch(PDO::FETCH_ASSOC);
	
	// Verif id client
	if (!isset($client['id_customer'])) {
		echo json_encode(array("error" => 1, "message" => "ID client manquant, la requête ne peut aboutir"));
		exit(0);
	}
	
	// Verif id_sujet
	if (!$id_sujet || empty($id_sujet)) {
		echo json_encode(array("error" => 1, "message" => "ID du sujet manquant, la requête ne peut aboutir"));
		exit(0);
	}
	
	// Prends que sujets actifs
	$reponses = ClientForumReponse::getCollection($id_sujet, true);
	
	$collection = array();
	foreach ($reponses as $reponse) {
		$item['client'] 			= $reponse->client;
		$item['message'] 			= filter_var($reponse->message, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		$item['utilisateur'] 	= $reponse->utilisateur;
		$item['date_ajout'] 	= userFriendlyDateDisplay($reponse->date_ajout);
		
		$collection[] = $item;
	}

	// Ajout pagination
	$limit = 10;
	$count = count($collection);
	$pages = ceil($count/$limit);
	
	echo json_encode(array('error' => '0', 'count' => $count, 'pages' => $pages, 'limit' => $limit, 'reponses' => $collection));
	exit(0);
