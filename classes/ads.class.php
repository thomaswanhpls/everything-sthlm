<?php

class Ads {

	private 	$id, 
				$title, 
				$content, 
				$dateCreated,
				$createdDaysAgo, 
				$dateExpire, 
				$userId,
				$userFirstname, 
				$imageName, 
				$tags, 
				$typeName,
				$typeId,
				$address,
				$latitude,
				$longitude,
				$payment,
				$interestedUsers,
				$expireTimestamp,
				$active,
				$image,
				$distance;

	//Property för förlängning av annons
	private static $daysForward = 7;

	//$input kommer från getAllAds, getSpecificAd eller getUserAds
	function __construct($input) { 
		
		$this->id 				= $input['id'];
		$this->title 			= $input['title'];
		$this->content 			= $input['content'];
		$this->dateCreated 		= date('Y-m-d', $input['date_created']);
		$this->dateExpire 		= date('Y-m-d', $input['date_expire']);
		$this->expireTimestamp	= $input['date_expire'];
		$this->userId 			= $input['user_id'];
		$this->userFirstname	= $input['user_firstname'];
		$this->typeId 			= $input['ad_type'];
		$this->typeName			= self::getSpecificAdType($this->id);
		$this->address 		 	= $input['address'];
		$this->latitude 		= $input['latitude'];
		$this->longitude	  	= $input['longitude'];
		$this->tags 			= self::getSpecificTags($this->id);
		$this->createdDaysAgo	= round((time()-$input['date_created'])/60/60/24);
		$this->payment			= $input['payment'];
		$this->interestedUsers	= self::getInterestedUsers($this->id, $this->userId);
		$this->active 			= $this->checkActive($input['active']);

		if($input['image'] != '') {
			$this->image = '/uploads/'.$input['image'];
		}
		else {
			$this->image = '/img/portfolio/roundicons.png';
		}

		if(isset($input['distance'])) {
			$this->distance = self::getDistance($input['distance']);
		}
		else {
			$this->distance = FALSE;
		}
	
	}

	function __get($var) {
		if ($this->$var) {
			return $this->$var;
		}
	}

	//Behövs för att Twig ska kunna använda magisk get.
	function __isset($var) { 
		if ($this->$var) {
			return TRUE; 
		}
		return FALSE; 
	}

	//Körs om man inte har angett en specifik metod i URL.
	static public function fallback($input) { 
		if (isset($input['id'])){ //annonsid
			return self::showSpecificAd($input);
		} else { 
			return self::getAllAds($input);
		}
	}

	//Kollar om en annons är aktiv eller ej
	private function checkActive($active) {
		if ($active) {
			$output = TRUE;
			
			if ($this->expireTimestamp <= time()) {
				$this->active = FALSE;

				$sql = "UPDATE ads 
						SET active = '0'
						WHERE id = ".$this->id;

				DB::query($sql);
			}
		}
		else {
			$output = FALSE;
		}

		return $output;
	}

	//FALSE eftersom $input är valfri. Har ingen sökning skett visas alla Ads
	static public function getAllAds($input = FALSE) { 

		$myaddress = User::getAddress();

		if($myaddress) {
			$user = User::checkLoginStatus(false);
			$earthRadius = 6371000;
			$sqlReturnDistance = " ROUND(
				".$earthRadius." * ACOS(  
					SIN( ".$user->latitude."*PI()/180 ) * SIN( ads.latitude*PI()/180 )
					+ COS( ".$user->latitude."*PI()/180 ) * COS( ads.latitude*PI()/180 )  *  COS( (ads.longitude*PI()/180) - (".$user->longitude."*PI()/180) )   
				) 
			, 0) AS distance, ";
		}
		else {
			$sqlReturnDistance = "";
		}
	
		//Gör det möjligt att söka på Ads med fritext
		if(isset($input['search'])) {
			$searchString = DB::clean($input['search']);
			$searchString = strtolower($searchString);
			$sqlSearch = " AND (LOWER(ads.title) LIKE '%".$searchString."%' 
						OR LOWER(ads.content) LIKE '%".$searchString."%') ";	
		} else {
			$searchString = FALSE;
			$sqlSearch = "";
		}

		//Gör det möjligt att söka på Ads med taggar
		if(isset($input['tags'])) {
			$searchTags = DB::clean($input['tags']);
			$sqlTags = " AND ads.id IN ( 
						SELECT DISTINCT(ad_id)
						FROM ad_has_tag
						WHERE ";
			foreach($searchTags as $searchTag) {
				$sqlTags .= "tag_id = $searchTag OR "; 
			}  

			$sqlTags = trim($sqlTags, "OR ");

			$sqlTags .= ")";

		} else {
			$searchTags = FALSE;
			$sqlTags = "";
		}

		//Gör det möjligt att söka på Adtype 
		if(isset($input['adtype']) && $input['adtype']!= "") {
			$searchAdType = DB::clean($input['adtype']);
			$sqlAdType = " AND ad_type = $searchAdType "; 

		} else {
			$searchAdType = FALSE;
			$sqlAdType = "";
		}

		if(isset($input['distance']) && $input['distance'] != '') {
			$searchDistance = DB::clean($input['distance']);
			$sqlSearchDistance = " HAVING distance <= $searchDistance ";
		} else {
			$searchDistance = FALSE;
			$sqlSearchDistance = "";
		}

		$sql = "SELECT ".$sqlReturnDistance."
				ads.id 				as id,
				ads.title 			as title, 
				ads.content 		as content, 
				ads.date_created 	as date_created, 
				ads.date_expire 	as date_expire, 
				ads.user_id 		as user_id, 
				user.firstname 		as user_firstname,
				ads.address 	 	as address, 
				ads.latitude 		as latitude, 
				ads.longitude 	 	as longitude, 
				ads.ad_type 		as ad_type,
				ads.payment 		as payment,
				ads.active 			as active,
				ads.image 			as image
			FROM ads, user 
			WHERE user.id = ads.user_id ".$sqlSearch.$sqlTags.$sqlAdType.$sqlSearchDistance. " AND date_expire >= ".time()."
			ORDER BY date_updated DESC";
		
		$dataArray = DB::query($sql);
		
		$ads = []; 
		foreach ($dataArray as $data) {
			$ads[] = new Ads($data); 
		}

		$output = [
		'ads' 			=> $ads,
		'page' 			=> 'ads.getallads.twig',
		'browserTitle' 	=> 'Alla annonser',
		'search' 		=> $searchString,
		'tags'			=> self::getAllTags(),
		'searchTags'	=> $searchTags,
		'adTypes'		=> self::getAllAdTypes(),
		'user'			=> User::checkLoginStatus(FALSE),
		'distance'		=> $searchDistance,
		'newInterests'	=> User::getNewInterests()
		];

		return $output;
	}

	//$input kommer utsprungligen från $GET 
	static public function getSpecificAd($input){ // 
		
		$id = DB::clean($input['id']);

		$sql = 	"SELECT ads.id 		as id, 
				ads.title 			as title, 
				ads.content 		as content, 
				ads.date_created 	as date_created, 
				ads.date_expire 	as date_expire, 
				user.id 			as user_id,
				user.firstname 		as user_firstname, 
				ads.address 	 	as address, 
				ads.latitude	  	as latitude, 
				ads.longitude 	 	as longitude, 
				ads.ad_type 		as ad_type, 
				ads.payment 		as payment,
				ads.active 			as active,
				ads.image 			as image
				FROM ads, user
				WHERE user.id = ads.user_id AND ads.id = $id
				";

		//TRUE för att hämta en rad från DB
		$data = DB::query($sql, TRUE);

		$output = new Ads($data);
		
		return $output;
	}

	//Skickar getSpecificAd return till Twig 
	public static function showSpecificAd($input) {
		
		$user = User::checkLoginStatus(FALSE);
		$ad = self::getSpecificAd($input);

		if($user) {

			$sql = "UPDATE user_interested_in_ad 
					INNER JOIN ads
					ON user_interested_in_ad.ad_id = ads.id
					SET new = '0'
					WHERE user_interested_in_ad.ad_id = ".$ad->id."
					AND ads.user_id = ".$user->id;

			DB::query($sql);

		}


		$output = [
		'ad' 			=> $ad,
		'page' 			=> 'ads.showspecificad.twig',
		'browserTitle'	=> $ad->title,
		'tags'			=> self::getAllTags(),
		'user'			=> $user,
		'userInterest' 	=> self::getUserInterest($ad->id, FALSE),
		'countInterest' => self::countUserInterest($ad->id),
		'newInterests'	=> User::getNewInterests()
		];
		
		return $output;
	}

	//Kollar först om man är inloggad, ifall inloggning är TRUE 
	//visas de anonnser som är skapade av den inloggade användaren
	static public function getUserAds($input = FALSE) {
		
		$cleanInput = DB::clean($input);
		$user = User::checkLoginStatus(); 

		if ($input) {
			$id = $cleanInput; 
		} else { 
			$id = $user->id;
		}

		$sql = "SELECT 
				ads.id 				as id, 
				ads.title 			as title, 
				ads.content 		as content, 
				ads.date_created 	as date_created, 
				ads.date_expire 	as date_expire, 
				ads.user_id 		as user_id, 
				ads.address 	 	as address, 
				ads.latitude	  	as longitude, 
				ads.longitude 	 	as latitude, 
				ads.ad_type 		as ad_type, 
				ads.payment 		as payment, 
				ads.active 			as active, 
				ads.image 			as image,
				user.firstname 		as user_firstname
				FROM ads, user
				WHERE ads.user_id = user.id
				AND user.id = ".$id."
				ORDER BY date_updated DESC";

		$dataArray = DB::query($sql);

		$ads = []; 
		foreach ($dataArray as $data) {
			$ads[] = new Ads($data); 
		}

		return $ads;
		
	}

	//Om man är inloggad får man möjlighet att skapa ny annons 
	public static function newAdForm() {
		
		$user = User::checkLoginStatus();
		$dateExpire = date('Y-m-d', time()+(60*60*24*7));

		$output = [
		'browserTitle' 	=> 'Skapa ny annons', 
		'page' 			=> 'ads.newadform.twig',
		'user' 			=> $user,
		'dateExpire' 	=> $dateExpire,
		'tags'			=> self::getAllTags(),
		'adTypes'		=> self::getAllAdTypes()
		];

		return $output;
	}

	//Är man inloggad kan man redigera en annons. getSpecificAd() hämtar vald anonns och skriver ut dess
	//information i ett formulär i Twig
	public static function editAdForm($input) {
		
		$user = User::checkLoginStatus();
		$ad = self::getSpecificAd($input);

		$output = [
		'browserTitle' 	=> 'Redigera annons', 
		'page' 			=> 'ads.editadform.twig',
		'user' 			=> $user,
		'ad' 			=> $ad,
		'tags'			=> self::getAllTags(),
		'adTypes'		=> self::getAllAdTypes()
		];

		return $output;
	}

	//Printar template för att aktivera annons
	public static function activateAdForm($input) {
		
		$user = User::checkLoginStatus();
		$ad = self::getSpecificAd($input);

		$output = [
		'browserTitle' 		=> 'Aktivera annons', 
		'page' 				=> 'ads.activateadform.twig',
		'user' 				=> $user,
		'ad' 				=> $ad,
		'newExpireDate' 	=> date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') + self::$daysForward, date('Y')))
		];

		return $output;
	}

	//Metod för att spara ny annons
	public static function saveAd($input) {
		$user = User::checkLoginStatus();

		$cleanInput = DB::clean($input);

		$title 			= $cleanInput['title'];
		$content 		= $cleanInput['content'];
		$address 	 	= $cleanInput['address'];
		$dateExpire 	= time()+(60*60*24*self::$daysForward);
		$userId 		= $user->id;
		$adTypes		= $cleanInput['ad_type'];
		$dateCreated	= time();
		$payment		= $cleanInput['payment'];
		$active 		= '1';
		$latitude 		= $cleanInput['latitude'];
		$longitude 		= $cleanInput['longitude'];
	

		if(!isset($cleanInput['tags'])) {
			$tags = [];
		} else {
			$tags = $cleanInput['tags'];
		}

		$sql = "INSERT INTO ads 
				(title, 
				content, 
				user_id, 
				address, 
				date_expire, 
				date_created, 
				date_updated, 
				ad_type, 
				payment, 
				active, 
				latitude, 
				longitude)
				VALUES
				('$title', 
				'$content',
				'$userId',
				'$address',
				'$dateExpire',
				'$dateCreated',
				'$dateCreated',
				'$adType',
				'$payment',
				'$active',
				'$latitude',
				'$longitude')
		";

		$data = DB::query($sql);

		if($data) {

			$adId = $data;
			
			// spara alla taggar som hör till annonsen
			foreach($tags as $tagId) {
				$sql = "INSERT INTO ad_has_tag 
						(ad_id, tag_id) 
						VALUES 
						($adId, $tagId)
						";

				DB::query($sql);

				if($_FILES['image']['name'] != '') {
					self::uploadFile($_FILES['image'], $adId);
				}
			}

			// Spara ner att användaren har skapat en annons så vi kan räkna totalt antal publicerade annonser
			$sql = "INSERT INTO user_has_created_ads 
					(ad_id, user_id, date) 
					VALUES 
					($adId, $userId, $dateCreated)
					";

			DB::query($sql);

			$output = ['redirect_url' => '//'.ROOT.'/user'];
				
		} 

		return $output;
	}
	
	//$input = id för den annons som ska redigeras. 
	//Körs för för att spara den redigerade versionen av en anonns som har skpats i editAdForm()
	public static function updateAd($input) { 
		//Kollar först om användaren är inloggad.
		$user = User::checkLoginStatus();
		
		$cleanInput = DB::clean($input);
		
		$adId 			= $cleanInput['id'];
		$title 			= $cleanInput['title'];
		$content 		= $cleanInput['content'];
		$address 	 	= $cleanInput['address'];
		$userId 		= $user->id;
		$adType			= $cleanInput['ad_type'];
		$payment		= $cleanInput['payment'];
		$latitude 		= $cleanInput['latitude'];
		$longitude 		= $cleanInput['longitude'];

		if(!isset($cleanInput['tags'])) {
			$tags = [];
		} else {
			$tags = $cleanInput['tags'];
		}

		// samma som ovan
		// $tags = isset($cleanInput['tags']) ? $cleanInput['tags'] : [];

		$sql = 	"UPDATE ads SET
				title 			= '$title', 
				content 		= '$content',
				address 	 	= '$address',
				ad_type 		= '$adType',
				payment			= '$payment',
				latitude		= '$latitude',
				longitude		= '$longitude'
				WHERE id = ".$adId;

		$data = DB::query($sql);

		if($data) {

			$sql = "DELETE FROM ad_has_tag 
					WHERE ad_id = ".$adId;
			
			DB::query($sql);
			
			foreach($tags as $tagId) {
				$sql = "INSERT INTO ad_has_tag 
						(ad_id, tag_id) 
						VALUES 
						($adId, $tagId)
						";

				DB::query($sql);
			}

			if($_FILES['image']['name'] != '') {
				
				self::uploadFile($_FILES['image'], $adId);
			}

			$output = ['redirect_url' => '//'.ROOT.'/user'];
				
		} 

		return $output;
	}

	//Uppdaterar annons till aktiv i databasen
	public static function activateAd($input) {
		$user = User::checkLoginStatus();

		$cleanInput = DB::clean($input);

		$adId = $cleanInput['id'];
		$dateExpire = time()+(60*60*24*self::$daysForward);
		$dateUpdated = time();

		$sql = "UPDATE ads 
				SET date_expire = '$dateExpire', active = '1', date_updated = $dateUpdated
				WHERE id = ".$adId;

		DB::query($sql);

		$output = ['redirect_url' => '//'.ROOT.'/user/'];

		return $output;
						
	}


	//Tillåter en användare att någon annans intresse på sin egen annons
	public static function denyUser($input) {
		$user = User::checkLoginStatus();

		$cleanInput = DB::clean($input);

		$adId = $cleanInput['ad_id'];
		$userId = $cleanInput['user_id'];

		$sql = "UPDATE user_interested_in_ad
				SET denied = '1'
				WHERE ad_id = $adId
				AND user_id = $userId";

		DB::query($sql);

		$output = ['redirect_url' => '//'.ROOT.'/ads/?id='.$adId];

		return $output;

	}

	//Inaktiverar annons i databas
	public static function inactivateAd($input) {
		$user = User::checkLoginStatus();

		$cleanInput = DB::clean($input);

		$adId = $cleanInput['id'];

		$sql = "UPDATE ads 
				SET active = '0'
				WHERE id = ".$adId;

		DB::query($sql);

		$output = ['redirect_url' => '//'.ROOT.'/user/'];

		return $output;
	}

	//Hämtar alla taggar från DB
	public static function getAllTags() {

		$sql = "SELECT id, name 
				FROM tags 
				ORDER BY name";

		$output = DB::query($sql);

		return $output;
	}

	//Hämtar taggar som är kopplade till en specifik annons
	private static function getSpecificTags($adId) {

		$cleanAdId = DB::clean($adId);

		$output = [];

		$sql = "SELECT tag_id 
				FROM ad_has_tag 
				WHERE ad_id = ".$cleanAdId;

		$array = DB::query($sql);
		
		foreach($array as $data) {
			$output[] = $data['tag_id'];
		}

		return $output;
	}

	//Är man inloggad kan ta bort sin egen annons
	public static function deleteAd($input) {
		$user = User::checkLoginStatus();
		$cleanId = DB::clean($input['id']);

		$sql = "DELETE FROM ads 
				WHERE id = $cleanId 
				AND user_id = ".$user->id;

		DB::query($sql);

		//Gör att man skickas vidare till den adressen som står efter =>
		//redirect_url finns i index.php-filen
		$output = ['redirect_url' => '/user/'];

		return $output;
	}	

	//Hämtar alla annonstyper
	private static function getAllAdTypes() {
		$sql = "SELECT id, name 
				FROM ad_types";

		$output = DB::query($sql);

		return $output;
	} 

	//Hämtar specifik adtype kopplad till vald annons
	private static function getSpecificAdType($adId) {
		$cleanId = DB::clean($adId);
		
		$sql = "SELECT ad_types.name as name 
				FROM ad_types, ads 
				WHERE ads.ad_type = ad_types.id 
				AND ads.id = $cleanId";
		
		$data = DB::query($sql, TRUE);
		$output = $data['name'];

		return $output;
	}


	//Metod för att anmäla sitt intresse för någon annans annons
	public static function setUserInterest($input){

		if (is_array($input)) {
			$cleanAdId = DB::clean($input['id']);
		}
		else {
			$cleanAdId 	= DB::clean($input);
		}

		$user = User::checkLoginStatus();

		if ($user) {

			$userId = $user->id;

			// Kolla så att inte den som gör intresseanmälan är den som gjort annonsen
			$sql = "SELECT user_id FROM ads WHERE id = ".$cleanAdId;
			$data = DB::query($sql, TRUE);

			if($data['user_id'] != $userId) {

				$date 			= time();

				$sql = "SELECT * 
						FROM user_interested_in_ad
						WHERE ad_id = $cleanAdId
						AND user_id = $userId";

				$data = DB::query($sql, TRUE);

				// Om vi får tillbaka en rad från databasen (vilket gör $data till TRUE)
				if ($data) {

					$sql = "DELETE 
							FROM user_interested_in_ad
							WHERE ad_id = $cleanAdId
							AND user_id = $userId";

					$data = DB::query($sql);

				} 

				// Om vi inte får tillbaka någon rad från databasen, dvs användaren har inte visat intresse för en specifik annons
				else {

					$sql = "INSERT INTO user_interested_in_ad
							(ad_id, user_id, date, new)
							VALUES
							($cleanAdId, $userId, $date, 1)";

					$data = DB::query($sql);
					
				}

			}

		}

		$output = ['redirect_url'=>'/ads/?id='.$cleanAdId];
		return $output;

	}

	//Hämtar status för intresse på specifik annons
	public static function getUserInterest($input){

		// Skickar med FALSE för att inte skicka användaren till 
		// inloggningsformuläret
		$user = User::checkLoginStatus(FALSE);

		if ($user) {
			
			if (is_array($input)) {
				$cleanAdId = DB::clean($input['id']);
			}
			else {
				$cleanAdId 	= DB::clean($input);
			}

			$userId 		= $user->id;
			$date 			= time();

			$sql = "SELECT * 
					FROM user_interested_in_ad
					WHERE ad_id = $cleanAdId
					AND user_id = $userId";

			$data = DB::query($sql, TRUE);

			// Om vi får tillbaka en rad från databasen (vilket gör $data till TRUE)
			if ($data) {

				$output = TRUE;

			} 

			// Om vi inte får tillbaka någon rad från databasen, dvs användaren har inte visat intresse för en specifik annons
			else {

				$output = FALSE;
				
			}
		}

		else {

			$output = FALSE;
		
		}

		return $output;

	}


	//Räknar antal användare som är intresserade av specifik annons
	private static function countUserInterest($adId) {
		//Kollar om anv är inloggad + skickar med FALSE för att inte skickas
		//direkt t loginformulär om man ej är det. 
		$user = User::checkLoginStatus(FALSE);
		 
		if($user) { 

			$userId = $user->id;

			$sql = "SELECT COUNT(user_id) as count 
					FROM user_interested_in_ad
					WHERE ad_id = $adId 
					AND user_id != $userId";
			
			$data = DB::query($sql, TRUE); 

			$output = $data['count'];
		} else {
			$output = FALSE;
		}

		return $output;
	}

	//Hämtar annonser man har visat intresse för
	public static function getInterestingAds() {
		$user = User::checkLoginStatus(); 

		$sql = "SELECT 
				ads.id 				as id, 
				ads.title 			as title, 
				ads.content 		as content, 
				ads.date_created 	as date_created, 
				ads.date_expire 	as date_expire, 
				ads.user_id 		as user_id, 
				ads.address 	 	as address, 
				ads.latitude	  	as longitude, 
				ads.longitude 	 	as latitude, 
				ads.ad_type 		as ad_type, 
				ads.payment 		as payment, 
				ads.active 			as active, 
				ads.image 			as image,
				user.firstname 		as user_firstname
				FROM ads, user_interested_in_ad, user
				WHERE ads.id = user_interested_in_ad.ad_id
				AND user.id = ads.user_id
				AND user_interested_in_ad.user_id = ".$user->id." 
				ORDER BY date_updated DESC";

		$dataArray = DB::query($sql);

		$ads = []; 
		foreach ($dataArray as $data) {
			$ads[] = new Ads($data); 
		}

		$output = $ads;
		
		return $output;
	}

	//Hämta användare som är intresserad av en annons
	private static function getInterestedUsers($adId, $userId) {

		//Kollar först om anv är inloggad, om nej ska man inte bli skickad till loginfomulär därför skickas FALSE med.
		$user = User::checkLoginStatus(FALSE);

		$cleanAdId = DB::clean($adId);
		$cleanUserId = DB::clean($userId);

		if($user && $user->id == $cleanUserId) {
			
			$sql = "SELECT user.id 					AS id, 
					user.firstname 					AS firstname, 
					user.lastname 					AS lastname, 
					user.email 						AS email,
					user_interested_in_ad.denied	AS denied
					FROM user, user_interested_in_ad
					WHERE user.id = user_interested_in_ad.user_id
					AND user_interested_in_ad.ad_id = ".$cleanAdId."
					ORDER BY user_interested_in_ad.date DESC";

			$data = DB::query($sql);

			$output = $data;
		} 
		else {	
			$output = FALSE;
		}

		return $output;
	}

	//Laddar upp bild på servern
	private static function uploadFile($tmpFile, $adId) {

		$directory = 'uploads/';

		$pathinfo = pathinfo($tmpFile['name']);
		$name = $pathinfo['filename'];
		$ext = $pathinfo['extension'];

		$file = $name.'_'.time().'.'.$ext;
		$fileWithDirectory = $directory.$file;
		// samma som ovan
		// $fileWithDirectory = 'uploads/filname .ext';

		if (file_exists($fileWithDirectory)) {
			echo 'Filen finns redan.'; exit;
		}

		$allowedExt = array('jpg', 'jpeg', 'png', 'gif');

		if(!in_array($ext, $allowedExt)) {
			echo 'Fel filtyp.'; exit;
		}

		if (move_uploaded_file($tmpFile['tmp_name'], $fileWithDirectory)) {
			
			$sql = "UPDATE ads SET image = '".$file."' WHERE id = ".$adId;
			DB::query($sql);

		}
		else {
			echo 'Det här gick snett!'; exit;
		}

	}

	//Hämtar distans
	private static function getDistance($meters){
		$kilometers = round(($meters / 1000),1);

			if ($kilometers >= 10) {
				$output = round($meters / 10000)." mil";
			} 

			else if ($kilometers >= 1 && $kilometers < 10) {
				$output = $kilometers." kilometer";
			}

			else {
				$output = $meters." meter";
			}
			return $output;
	}

}







