<?php
	date_default_timezone_set('Europe/Paris');
	
	// Here we define constants /!\ You need to replace this parameters
	define('DEBUG', false);
	//define('PS_SHOP_PATH', 'http://www.allezdiscount.com/_prestashop1610/');
	define('PS_SHOP_PATH', 'http://www.corner-sport.com');
	//define('PS_WS_AUTH_KEY', 'ZJKP2G77RJNFBTSGXKSPMASSUPQG63G9');
	define('PS_WS_AUTH_KEY', 'JFCYCZFZGSFHI5DD9GV73688IHCJRZJH');
		
	// Database connexion
	try {
		$db = new PDO('mysql:host=94.23.16.44;dbname=allezdiscount', 'allezdiscount', 'yJF926nq');
	} catch (Exception $e) {
		die('erreur de connexion à la base de données V1');
	}
	
	try {
		$erp = new PDO('mysql:host=91.121.179.54;dbname=cornersport', 'cornersport', '6z6g4EqL');
	} catch (Exception $e) {
		die('erreur de connexion à la base de données ERP');
	}

	
	function strip_spaces($string) {	
		return preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', trim($string));
	}
	
	
		
	function recup_folder_erp($id_prod){
		$crypt_folder=md5($id_prod);

		$car1=substr($crypt_folder,0,1);
		$car2=substr($crypt_folder,1,1);
		$car3=substr($crypt_folder,2,1);

		$baseDir = "/images/produits/".$car1."/".$car2."/".$car3."/";
		return $baseDir;
	}
	

	function checkEAN13($barcode) {	
		// check to see if barcode is 13 digits long
		if (!preg_match("/^[0-9]{13}$/", $barcode)) {
			return false;
		}
		
		if ($barcode == '0000000000000') {
			return false;
		}

		$digits = $barcode;

		// 1. Add the values of the digits in the 
		// even-numbered positions: 2, 4, 6, etc.
		$even_sum = $digits[1] + $digits[3] + $digits[5] + $digits[7] + $digits[9] + $digits[11];

		// 2. Multiply this result by 3.
		$even_sum_three = $even_sum * 3;

		// 3. Add the values of the digits in the 
		// odd-numbered positions: 1, 3, 5, etc.
		$odd_sum = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8] + $digits[10];

		// 4. Sum the results of steps 2 and 3.
		$total_sum = $even_sum_three + $odd_sum;

		// 5. The check character is the smallest number which,
		// when added to the result in step 4, produces a multiple of 10.
		$next_ten = (ceil($total_sum / 10)) * 10;
		$check_digit = $next_ten - $total_sum;

		// if the check digit and the last digit of the 
		// barcode are OK return true;
		if ($check_digit == $digits[12]) {
				return true;
		}

		return false;
	}
	
	
	function str2url($str){
		$array_str = array();
		$allow_accented_chars = null;
		$has_mb_strtolower = null;

		if ($has_mb_strtolower === null) {
			$has_mb_strtolower = function_exists('mb_strtolower');
		}

		if (isset($array_str[$str])) {
			return $array_str[$str];
		}

		if (!is_string($str)) {
			return false;
		}

		if ($str == '') {
			return '';
		}

		if ($allow_accented_chars === null) {
			$allow_accented_chars = false;
		}

		$return_str = trim($str);

		if ($has_mb_strtolower) {
			$return_str = mb_strtolower($return_str, 'utf-8');
		}
		if (!$allow_accented_chars) {
			$return_str = replaceAccentedChars($return_str);
		}

		// Remove all non-whitelist chars.
		if ($allow_accented_chars) {
			$return_str = preg_replace('/[^a-zA-Z0-9\s\'\:\/\[\]\-\p{L}]/u', '', $return_str);
		} else {
			$return_str = preg_replace('/[^a-zA-Z0-9\s\'\:\/\[\]\-]/', '', $return_str);
		}

		$return_str = preg_replace('/[\s\'\:\/\[\]\-]+/', ' ', $return_str);
		$return_str = str_replace(array(' ', '/'), '-', $return_str);

		// If it was not possible to lowercase the string with mb_strtolower, we do it after the transformations.
		// This way we lose fewer special chars.
		if (!$has_mb_strtolower) {
			$return_str = strtolower($return_str);
		}

		$array_str[$str] = $return_str;
		return $return_str;
	}


	function replaceAccentedChars($str) {
		/* One source among others:
			http://www.tachyonsoft.com/uc0000.htm
			http://www.tachyonsoft.com/uc0001.htm
			http://www.tachyonsoft.com/uc0004.htm
		*/
		$patterns = array(

		/* Lowercase */
		/* a  */ '/[\x{00E0}\x{00E1}\x{00E2}\x{00E3}\x{00E4}\x{00E5}\x{0101}\x{0103}\x{0105}\x{0430}\x{00C0}-\x{00C3}\x{1EA0}-\x{1EB7}]/u',
		/* b  */ '/[\x{0431}]/u',
		/* c  */ '/[\x{00E7}\x{0107}\x{0109}\x{010D}\x{0446}]/u',
		/* d  */ '/[\x{010F}\x{0111}\x{0434}\x{0110}]/u',
		/* e  */ '/[\x{00E8}\x{00E9}\x{00EA}\x{00EB}\x{0113}\x{0115}\x{0117}\x{0119}\x{011B}\x{0435}\x{044D}\x{00C8}-\x{00CA}\x{1EB8}-\x{1EC7}]/u',
		/* f  */ '/[\x{0444}]/u',
		/* g  */ '/[\x{011F}\x{0121}\x{0123}\x{0433}\x{0491}]/u',
		/* h  */ '/[\x{0125}\x{0127}]/u',
		/* i  */ '/[\x{00EC}\x{00ED}\x{00EE}\x{00EF}\x{0129}\x{012B}\x{012D}\x{012F}\x{0131}\x{0438}\x{0456}\x{00CC}\x{00CD}\x{1EC8}-\x{1ECB}\x{0128}]/u',
		/* j  */ '/[\x{0135}\x{0439}]/u',
		/* k  */ '/[\x{0137}\x{0138}\x{043A}]/u',
		/* l  */ '/[\x{013A}\x{013C}\x{013E}\x{0140}\x{0142}\x{043B}]/u',
		/* m  */ '/[\x{043C}]/u',
		/* n  */ '/[\x{00F1}\x{0144}\x{0146}\x{0148}\x{0149}\x{014B}\x{043D}]/u',
		/* o  */ '/[\x{00F2}\x{00F3}\x{00F4}\x{00F5}\x{00F6}\x{00F8}\x{014D}\x{014F}\x{0151}\x{043E}\x{00D2}-\x{00D5}\x{01A0}\x{01A1}\x{1ECC}-\x{1EE3}]/u',
		/* p  */ '/[\x{043F}]/u',
		/* r  */ '/[\x{0155}\x{0157}\x{0159}\x{0440}]/u',
		/* s  */ '/[\x{015B}\x{015D}\x{015F}\x{0161}\x{0441}]/u',
		/* ss */ '/[\x{00DF}]/u',
		/* t  */ '/[\x{0163}\x{0165}\x{0167}\x{0442}]/u',
		/* u  */ '/[\x{00F9}\x{00FA}\x{00FB}\x{00FC}\x{0169}\x{016B}\x{016D}\x{016F}\x{0171}\x{0173}\x{0443}\x{00D9}-\x{00DA}\x{0168}\x{01AF}\x{01B0}\x{1EE4}-\x{1EF1}]/u',
		/* v  */ '/[\x{0432}]/u',
		/* w  */ '/[\x{0175}]/u',
		/* y  */ '/[\x{00FF}\x{0177}\x{00FD}\x{044B}\x{1EF2}-\x{1EF9}\x{00DD}]/u',
		/* z  */ '/[\x{017A}\x{017C}\x{017E}\x{0437}]/u',
		/* ae */ '/[\x{00E6}]/u',
		/* ch */ '/[\x{0447}]/u',
		/* kh */ '/[\x{0445}]/u',
		/* oe */ '/[\x{0153}]/u',
		/* sh */ '/[\x{0448}]/u',
		/* shh*/ '/[\x{0449}]/u',
		/* ya */ '/[\x{044F}]/u',
		/* ye */ '/[\x{0454}]/u',
		/* yi */ '/[\x{0457}]/u',
		/* yo */ '/[\x{0451}]/u',
		/* yu */ '/[\x{044E}]/u',
		/* zh */ '/[\x{0436}]/u',

		/* Uppercase */
		/* A  */ '/[\x{0100}\x{0102}\x{0104}\x{00C0}\x{00C1}\x{00C2}\x{00C3}\x{00C4}\x{00C5}\x{0410}]/u',
		/* B  */ '/[\x{0411}]/u',
		/* C  */ '/[\x{00C7}\x{0106}\x{0108}\x{010A}\x{010C}\x{0426}]/u',
		/* D  */ '/[\x{010E}\x{0110}\x{0414}]/u',
		/* E  */ '/[\x{00C8}\x{00C9}\x{00CA}\x{00CB}\x{0112}\x{0114}\x{0116}\x{0118}\x{011A}\x{0415}\x{042D}]/u',
		/* F  */ '/[\x{0424}]/u',
		/* G  */ '/[\x{011C}\x{011E}\x{0120}\x{0122}\x{0413}\x{0490}]/u',
		/* H  */ '/[\x{0124}\x{0126}]/u',
		/* I  */ '/[\x{0128}\x{012A}\x{012C}\x{012E}\x{0130}\x{0418}\x{0406}]/u',
		/* J  */ '/[\x{0134}\x{0419}]/u',
		/* K  */ '/[\x{0136}\x{041A}]/u',
		/* L  */ '/[\x{0139}\x{013B}\x{013D}\x{0139}\x{0141}\x{041B}]/u',
		/* M  */ '/[\x{041C}]/u',
		/* N  */ '/[\x{00D1}\x{0143}\x{0145}\x{0147}\x{014A}\x{041D}]/u',
		/* O  */ '/[\x{00D3}\x{014C}\x{014E}\x{0150}\x{041E}]/u',
		/* P  */ '/[\x{041F}]/u',
		/* R  */ '/[\x{0154}\x{0156}\x{0158}\x{0420}]/u',
		/* S  */ '/[\x{015A}\x{015C}\x{015E}\x{0160}\x{0421}]/u',
		/* T  */ '/[\x{0162}\x{0164}\x{0166}\x{0422}]/u',
		/* U  */ '/[\x{00D9}\x{00DA}\x{00DB}\x{00DC}\x{0168}\x{016A}\x{016C}\x{016E}\x{0170}\x{0172}\x{0423}]/u',
		/* V  */ '/[\x{0412}]/u',
		/* W  */ '/[\x{0174}]/u',
		/* Y  */ '/[\x{0176}\x{042B}]/u',
		/* Z  */ '/[\x{0179}\x{017B}\x{017D}\x{0417}]/u',
		/* AE */ '/[\x{00C6}]/u',
		/* CH */ '/[\x{0427}]/u',
		/* KH */ '/[\x{0425}]/u',
		/* OE */ '/[\x{0152}]/u',
		/* SH */ '/[\x{0428}]/u',
		/* SHH*/ '/[\x{0429}]/u',
		/* YA */ '/[\x{042F}]/u',
		/* YE */ '/[\x{0404}]/u',
		/* YI */ '/[\x{0407}]/u',
		/* YO */ '/[\x{0401}]/u',
		/* YU */ '/[\x{042E}]/u',
		/* ZH */ '/[\x{0416}]/u');

		// ö to oe
		// å to aa
		// ä to ae

		$replacements = array(
			'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 'ss', 't', 'u', 'v', 'w', 'y', 'z', 'ae', 'ch', 'kh', 'oe', 'sh', 'shh', 'ya', 'ye', 'yi', 'yo', 'yu', 'zh',
			'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'V', 'W', 'Y', 'Z', 'AE', 'CH', 'KH', 'OE', 'SH', 'SHH', 'YA', 'YE', 'YI', 'YO', 'YU', 'ZH'
		);

		return preg_replace($patterns, $replacements, $str);
	}