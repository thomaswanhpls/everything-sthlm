<?php

	class DB {

		private static $instance = null, $prev_results = [], $mysqli;
		public static $con;
		private $db = ['url' => 'localhost', 'user' => 'root', 'password' => 'root', 'database' => 'everythingSthlm'];

		private function __construct() {
			self::$mysqli = new mysqli($this->db['url'], $this->db['user'], $this->db['password'], $this->db['database']);
			self::$mysqli->query("SET NAMES 'utf8'");
			self::$con = self::con();
		}

		private static function getInstance() {
			if (self::$instance === null) {
				self::$instance = new DB();
			}
			return self::$instance;
		}

		private static function con() {
			return self::$mysqli;
		}

		// funktion för att tvätta det som skickas från ett formulär med POST, används inte utanför klassen
		public static function clean($input) {

			self::getInstance();

			if(is_array($input)) {
			
				// en array med tvättade värden som matas ut
				$clean_data = [];

				// loopa igenom $_POST
				foreach($input as $key => $value) {

					if(is_array($value)) {

						foreach($value as $subkey => $subvalue) {
							$clean_data[$key][$subkey] = self::$mysqli->real_escape_string($subvalue);
						}

					}

					else {

						$clean_data[$key] = self::$mysqli->real_escape_string($value);

					}
					
				}

			} else {

				if(is_array($input)) {
					foreach($input as $key => $value) {
						$clean_data[$key] = self::$mysqli->real_escape_string($value);
					}
				} else {
					$clean_data = self::$mysqli->real_escape_string($input);
				}
			}

			return $clean_data;
		}


		//Hanterar och returnerar resultat av query
		//Returnerar antingen resultat(TRUE) eller FALSE
		//Skicka med parameter TRUE om bara en rad förväntas tillbaka från databas
		public static function query($query, $single = false) {
			self::getInstance();

			$output = [];
			$hash_query = hash('sha1', $query);

			if(array_key_exists($hash_query, self::$prev_results)) {
				$output = self::$prev_results[$hash_query];
			}
			else {
				if($res = self::$mysqli->query($query)) {
					if($res === TRUE){
						if(self::$mysqli->insert_id != 0){
							$output = self::$mysqli->insert_id;
						}
						else {
							$output = TRUE;	
						}
					}
					else{
						if($single) {
							$data = $res->fetch_assoc();
							$output = $data;
						}
						else {
							$output = [];
							while($data = $res->fetch_assoc()) {
								$output[] = $data;
							}
						}
						self::$prev_results[$hash_query] = $output;
					}
				}

				if(!$res) {
					echo self::$mysqli->error; 
					echo $query;
					die();
				}
			}
			return $output;
		}
	}

