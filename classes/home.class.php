<?php

	class Home {

		static public function fallback() {

			$output = [
				'browserTitle'		=> 'Byt och låna prylar nära dig',
				'page' 				=> 'home.twig',
				'user' 				=> User::checkLoginStatus(FALSE),
				'activeAdsAmount' 	=> self::countActiveAds(),
				'usersAmount' 		=> self::countUsers(),
				'latestUpload'		=> self::latestUploadedAd(),
				'newInterests'		=> User::getNewInterests()
			];

			return $output;
			
		}

		//Räknar antalet aktiva annonser
		static private function countActiveAds() {
			$sql = "SELECT COUNT(id) as count
					FROM ads
					WHERE active = 1
			"; 

			$data = DB::query($sql, TRUE);

			$output = $data['count'];

			return $output;
		}

		//Räknar antalet användare
		static private function countUsers() {
			$sql = "SELECT COUNT(id) as count
					FROM user
			";

			$data = DB::query($sql, TRUE);
			$output = $data['count'];

			return $output;
		}

		//Hämtar senast uppladdade annons
		static private function latestUploadedAd() {
			$sql = "SELECT title as title, id
					FROM ads
					WHERE active = 1
					ORDER by date_created DESC
					LIMIT 1
			";
			
			$data = DB::query($sql, TRUE);
			$output = $data;

			return $output;
		}

		//Printar Premium.twig
		static public function premiumPage () {

			$user = User::checkLoginStatus(false);

			$output = [
			'user' => $user,
			'page' => 'premium.twig', 
			'browserTitle' => 'Bli premiumanvändare'
			];

			return $output;
		}

		//Printar Premium.twig
		static public function ohDearyMeQueueTheFourohfour () {

			$user = User::checkLoginStatus(false);

			$output = [
			'user' => $user,
			'page' => '404.twig', 
			'browserTitle' => 'Sidan kan inte visas'
			];

			return $output;
		}

	}