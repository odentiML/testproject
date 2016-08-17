<?php

require_once dirname(__FILE__) . '/Table.php';

class ClientForumReponse extends Table {
  
  // Champs de la table
  public $id;
	public $id_sujet;
	public $id_client;
	public $id_utilisateur;
  public $message;
  public $date_ajout;
  public $approuve;
	
	public $sujet;
	public $client;
	public $utilisateur;

  
  /**
   * Assignation des valeurs des champs de la table aux client_forum_reponses de l'objet
   * NB: Les champs de la table DOIVENT avoir les mêmes noms que les client_forum_reponses
   */
  protected function charger($id) {
    $query = $this->db->prepare("SELECT * FROM client_forum_reponse WHERE id = :id");
    $query->bindValue(':id', (int) $id, PDO::PARAM_INT);
		
    if (!$query->execute()) {
      $error = $query->errorInfo();
      throw new TableException("An error occured while loading client_forum_reponse : " . $error[2]);
    }
		
    if ($query->rowCount() == 1) {
      $row = $query->fetch(PDO::FETCH_OBJ);

      foreach ($row as $key => $value) {
        $this->$key = $row->$key;
      }
			
			if (!empty($this->id_sujet)) {
				$this->sujet = new ClientForumSujet($this->id_sujet);
			}
			
			if (!empty($this->id_client)) {
				$this->client = $this->getClientNom();
			}
			
			if (!empty($this->id_utilisateur)) {
				$this->utilisateur = $this->getUtilisateurNom();
			}

      return true;
    }

    return false;
  }
  
  
  /**
   * Méthode pour l'ajout
   */
  protected function enregistrerAjout() {
    try {
      $this->db->beginTransaction();
	
			$requete = $this->db->prepare("INSERT INTO client_forum_reponse SET id_sujet = :id_sujet, id_client = :id_client, id_utilisateur = :id_utilisateur, message = :message, date_ajout = :date_ajout, approuve = :approuve");
			$requete->bindValue(':id_sujet', $this->id_sujet, PDO::PARAM_INT);
			$requete->bindValue(':id_client', $this->id_client, PDO::PARAM_INT);
			$requete->bindValue(':id_utilisateur', $this->id_utilisateur, PDO::PARAM_INT);
			$requete->bindValue(':message', $this->message, PDO::PARAM_STR);
			$requete->bindValue(':date_ajout', $this->date_ajout, PDO::PARAM_STR);
			$requete->bindValue(':approuve', $this->approuve, PDO::PARAM_INT);
			$requete->execute();
	
			$requete = $this->db->prepare("UPDATE client_forum_sujet SET reponses = (reponses+1), date_dernier_message = :date_dernier_message WHERE id = :id");
			$requete->bindValue(':date_dernier_message', $this->date_ajout, PDO::PARAM_STR);
			$requete->bindValue(':id', $this->id_sujet, PDO::PARAM_INT);
			$requete->execute();

      $this->db->commit();
    } 
    catch(Exception $e) {
      $this->db->rollback();
      throw new Exception("Erreur " . $e->getCode() . " : " . $e->getMessage());
    }
  }
  
  
  /**
   * Méthode pour la modification
   */
  protected function enregistrerModif() {
		$sql = '
			UPDATE client_forum_reponse SET 
			message = :message, 
			approuve = :approuve
			WHERE id = :id
		';
		
		$requete = $this->db->prepare($sql);
		$requete->bindValue(':message', $this->message, PDO::PARAM_STR);
		$requete->bindValue(':approuve', $this->approuve, PDO::PARAM_INT);
		$requete->bindValue(':id', $this->id, PDO::PARAM_INT);
		
    if (!$requete->execute()) {
      $error = $requete->errorInfo();
      throw new TableException("An error occured while saving client_forum_reponse : " . $error[2]);
    }
  }
  
  /**
   * Méthode pour la suppression
   */
  public function supprimerObjet() {      
    try {  
      $this->db->beginTransaction();
      $this->db->exec("DELETE FROM client_forum_reponse WHERE id = $this->id"); 
      $this->db->exec("UPDATE client_forum_sujet SET reponses = (reponses-1) WHERE id = $this->id_sujet"); 
      $this->db->commit();
    } 
    catch(Exception $e) {
      $this->db->rollback();
      throw new Exception("Erreur " . $e->getCode() . " : " . $e->getMessage());
    }
  }

  
  /**
   * Retourne le nombre d'enregistrements dans la table
   */
  public static function getTotal($id_sujet = null, $only_approuve = true) {
    $db = DB::getConnexion();
		
		$sql = "SELECT COUNT(*) FROM client_forum_reponse WHERE 1";
		
    if (!is_null($id_sujet)) {
      $sql .= " AND id_sujet = $id_sujet";
    }
		
    if ($only_approuve) {
      $sql .= " AND approuve = 1";
    }
		
		return $db->query($sql)->fetchColumn();
  }

  
  /**
   * Méthode pour récupérer la liste des enregistrements de la table
   */
  public static function getCollection($id_sujet = null, $only_approuve = true, $debut = null, $limite = null) {
    $db = DB::getConnexion();
        
    $sql = "SELECT * FROM client_forum_reponse WHERE 1";
		
    if ($only_approuve) {
      $sql .= " AND approuve = 1";
    }
		
    if (!is_null($id_sujet)) {
      $sql .= " AND id_sujet = $id_sujet";
    }
		
		$sql .= " ORDER BY date_ajout DESC";
    
    if (!is_null($debut) && !is_null($limite)) {
      $sql .= " LIMIT $debut,$limite";
    }

    $requete = $db->query($sql);

    $collection = array();
    while ($client_forum_reponse = $requete->fetch(PDO::FETCH_OBJ)) {
      $collection[] = new ClientForumReponse($client_forum_reponse->id);
    }

    $requete->closeCursor();

    return $collection;
  }
 
  
  /**
   * Méthode statique pour savoir si l'enregistrement existe déjà
   */
  public static function idExiste($id) {
    $db = DB::getConnexion();
    
    $requete = $db->prepare("SELECT id FROM client_forum_reponse WHERE id = :id");
    $requete->bindValue(':id', $id, PDO::PARAM_INT);   
    $requete->execute();

    return $requete->rowCount() == 1 ? true : false;
  }
  
	
  /**
   * Méthode retournant le nom du client
   */
	public function getClientNom() {
		require_once dirname(__FILE__) . '/../../config/settings.inc.php';
		
		try {
			$dbh = new PDO('mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_, _DB_USER_, _DB_PASSWD_, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} 
		catch (PDOException $e) {
			throw new Exception("Erreur " . $e->getCode() . " : " . $e->getMessage());
		}
		
		// Récupère l'ID client correspondant à celui de l'interface
		$requete = $dbh->prepare("SELECT firstname, lastname FROM "._DB_PREFIX_."customer WHERE id_customer = :id_customer LIMIT 1");
		$requete->bindValue(':id_customer', $this->id_client, PDO::PARAM_INT);
		$requete->execute();
		
		if ($requete->rowCount() == 1) {
			$item = $requete->fetch(PDO::FETCH_OBJ);
			$name = ucfirst($item->firstname) . ' ' . ucfirst(substr($item->lastname, 0, 1)) . '.';
			return $name;
		}
		
		return null;
	}
	
	
  /**
   * Méthode retournant le nom de l'utilisateur
   */
	public function getUtilisateurNom() {				
		$requete = $this->db->prepare("SELECT CONCAT_WS(' ', prenom, nom) FROM utilisateur WHERE id = :id_utilisateur");
		$requete->bindValue(':id_utilisateur', $this->id_utilisateur, PDO::PARAM_INT);
		$requete->execute();
		
		if ($requete->rowCount() == 1) {
			return $requete->fetchColumn();
		}
		
		return null;
	}
	
  
  /**
   * Règles de validation pour l'enregistrement 
   * @return bool
   */
  public function estValide() {  
    if (empty($this->id_client) && empty($this->id_utilisateur))
      $this->erreurs['message'] = "Informations d'identification manquantes";
    if (empty($this->message))
      $this->erreurs['message'] = "Le message est vide";
    if (empty($this->id_sujet))
      $this->erreurs['message'] = "Le sujet est vide ou invalide";
    if (!empty($this->id_sujet) && !ClientForumSujet::idExiste($this->id_sujet))
      $this->erreurs['message'] = "Le sujet n'existe pas ou plus.";
    
    return count($this->erreurs) == 0 ? true : false;
  }
}
