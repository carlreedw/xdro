<?php
namespace Vanderbilt\ARIES;
class ARIES extends \ExternalModules\AbstractExternalModule {

	public $log_desc = "ARIES Module";
	public $patient_field_names = [
		'patient_street_address_1',
		'patientid',
		'patient_first_name',
		'patient_last_name',
		'patient_dob',
		'patient_current_sex'
	];
	
	public function __construct() {
		parent::__construct();
		$this->nlog();
		
		$this->auth_data_raw = $this->framework->getSystemSetting('auth_data');
		if (empty($this->auth_data_raw)) {
			$this->auth_data_raw = "{}";
		}
		$this->auth_data = json_decode($this->auth_data_raw);
		
		$pid = $this->framework->getProjectId();
		if (!empty($pid))
			$this->project = new \Project($pid);
	}
	
	// sign-in / authentication
	function userIsAuthenticated() {
		if ($_SESSION['aries_authenticated']) {
			// $this->llog("user is authenticated");
			return true;
		} else {
			// $this->llog("user is not authenticated");
			return false;
		}
	}
	
	function authenticateUser() {
		if ($redcap_username = $_SESSION['username']) {
			// redcap user auth
			$this->authenticateREDCapUser($redcap_username);
		} else {
			// non-redcap user (aries user) auth
			$username = db_escape(trim($_POST['username']));
			if (empty($username)) { 
				$this->signInAlertMessage = "Please provider a username to login";
				return;
			}
			
			$password = db_escape(trim($_POST['password']));
			if (empty($password)) {
				$this->signInAlertMessage = "Please provider a password to login";
				return;
			}
			
			foreach($this->auth_data->users as $i => $user) {
				if ($user->username == $username) {
					if (password_verify($password, $user->pw_hash)) {
						$_SESSION['aries_authenticated'] = true;
						$_SESSION['aries_username'] = $username;
						$this->llog("authenticated aries user");
						$this->rlog("ARIES user '$username' logged in successfully");
					} else {
						$this->rlog("ARIES user '$username' failed to login - wrong password");
						$this->signInAlertMessage = "Authentication failed -- password incorrect";
					}
				}
			}
			$this->rlog("ARIES user '$username' failed to login - not a valid username");
			$this->signInAlertMessage = "ARIES user '$username' failed to login - not a valid username";
		}
	}
	
	function getSignInMessage() {
		if ($message = $this->signInAlertMessage)
			return $message;
		
		if ($redcap_username = $_SESSION['username']) {
			// redcap user auth
			$this->authenticateREDCapUser($redcap_username);
			return $this->signInAlertMessage;
		}
	}
	
	function authenticateREDCapUser($username) {
		// $this->llog("in authenticateREDCapUser");
		$rights = \REDCap::getUserRights($username);
		
		// $this->llog("redcap user rights: " . print_r($rights, true));
		
		if (empty($rights)) {
			$this->rlog("Authentication failed for REDCap user '$username' -- user does not have access");
			$this->signInAlertMessage = "Unauthorized REDCap user detected -- user does not have access";
			return;
		}
		
		if ($rights[$username]['design']) {
			$_SESSION['aries_authenticated'] = true;
			$_SESSION['aries_redcap_username'] = $username;
			$this->rlog("Authenticated REDCap user '$username'");
			$this->signInAlertMessage = "Authorized REDCap user detected";
			return;
		}
		$this->rlog("Authentication failed for REDCap user '$username' -- user does not have Project Design rights");
		$this->signInAlertMessage = "Unauthorized REDCap user detected -- user does not have Project Design rights";
	}
	
	function makeCSRFToken() {
		$username = $_SESSION['aries_username'];
		if (empty($username))
			$username = $_SESSION['username'];
		
		if (empty($username))
			throw new \Exception('ARIES module expected username to be stored in SESSION data.');
		
		global $salt;
		if (empty($salt))
			throw new \Exception('ARIES module expected non-empty password salt value.');
		
		$_SESSION['aries_csrf_ts'] = time();
		$token = md5($salt . $_SESSION['aries_csrf_ts'] . $username);
		return $token;
	}
	
	function checkCSRFToken($token) {
		$username = $_SESSION['aries_username'];
		if (empty($username))
			$username = $_SESSION['username'];
		
		$csrf_ts = $_SESSION['aries_csrf_ts'];
		global $salt;
		if (empty($salt) or empty($csrf_ts) or empty($username)) {
			// $this->llog("csrf NOT OK: " . date('c'));
			return [false, "ARIES module couldn't verify CSRF Token due to an empty precursor"];
		}
		
		$token_expectation = md5($salt . $csrf_ts . $username);
		if ($token === $token_expectation) {
			// $this->llog("csrf OK: " . date('c'));
			return [true, null];
		} else {
			// $this->llog("csrf NOT OK: " . date('c'));
			// $this->llog("got $token, expected $token_expectation");
			return [false, "ARIES module couldn't verify CSRF Token due to token mismatch"];
		}
	}
	
	// given a user supplied string, search for records in our patient registry that might match
	function search($query_string, $limit = null) {
		$query_obj = $this->structure_string_query($query_string);
		return $this->structured_search($query_obj, $limit);
	}
	
	function structured_search($query_obj, $limit = null) {
		// get all records (only some fields though)
		$params = [
			"project_id" => $_GET['pid'],
			// "return_format" => "json",
			"return_format" => "array",
			"fields" => [
				'patientid',
				'patient_dob',
				'patient_first_name',
				'patient_last_name',
				'patient_current_sex',
				'patient_street_address_1',
				'patient_last_change_time'
			]
		];
		$records = \REDCap::getData($params);
		$records = $this->squish_demographics($records);
		
		// add relevance score to each record
		foreach ($records as &$record) {
			$this->score_record_by_array($record, (array) $query_obj);
		}
		
		// remove records with zero score
		$records = array_filter($records, function($record) {
			return $record['score'] != 0;
		});
		
		if (empty($records)) {
			return [];
		}
		
		// sort remaining records by score descending
		usort($records, function($a, $b) {
			return $a['score'] < $b['score'] ? 1: -1;
		});
		
		if (empty($limit)) {
			return $records;
		} else {
			return array_slice($records, 0, $limit);
		}
		return [];
	}
	
	function score_record_by_array(&$record, $tokens_arr) {
		// final score should be a relevance score of [0, 100] where 0 is not relevant and 100 is exact match
		$score = 0;
		$scores = [];
		$sum = 0;
		
		// create patient_name, remove patient_first_name, patient_last_name
		if ($record['patient_first_name'] || $record['patient_last_name']) {
			$record['patient_name'] = trim($record['patient_first_name'] . ' ' . $record['patient_last_name']);
		}
		if ($tokens_arr['patient_first_name'] || $tokens_arr['patient_last_name']) {
			$tokens_arr['patient_name'] = trim($tokens_arr['patient_first_name'] . ' ' . $tokens_arr['patient_last_name']);
			unset($tokens_arr['patient_first_name']);
			unset($tokens_arr['patient_last_name']);
		}
		
		
		// compare query fields with record field values to update score
		foreach($record as $field => $value) {
			$similarity = 0;
			if (empty($tokens_arr[$field]))
				continue;
			
			// patient_current_sex should score is 0 or 1, matched exactly or not
			$a = strtolower(strval($value));
			$b = strtolower(strval($tokens_arr[$field]));
			if ($field == 'patient_current_sex') {
				if ($b == 'm')
					$b = 'male';
				if ($b == 'f')
					$b = 'female';
				
				if ($a == $b) {
					$similarity = 100;
				} else {
					$similarity = 0;
				}
			} elseif ($field == "patient_dob") {
				$this->similar_date($a, $b, $similarity);
			} else {
				similar_text($a, $b, $similarity);
			}
			
			$scores[$field] = $similarity;
			$score += $similarity;
			// $this->llog("$field similarity ($a vs $b) = $similarity");
			$sum++;
		}
		
		if ($sum > 0)
			$score = $score / $sum;
		
		$record["score"] = $score;	// score should be in range [0, 1], 0 if no matching params, 1 if all match exactly
		$record["scores"] = $scores;
	}
	
	// written to work like PHP's similar_text
	function similar_date($datestring1, $datestring2, &$percent) {
		try {
			$ymd1 = new \DateTime($datestring1);
			$ymd2 = new \DateTime($datestring2);
			$ymd1 = $ymd1->format("Y-m-d");
			$ymd2 = $ymd2->format("Y-m-d");
			return similar_text($ymd1, $ymd2, $percent);
		} catch(Exception $e) {
			return similar_text($datestring1, $datestring2, $percent);
		}
	}
	
	function structure_string_query($str_query) {
		// general strategy is to tokenize query,
		// then extract patient_current_sex tokens (m/f/male/female)
		// then extract patientid tokens (has 'psn' or 'tn', more than 3 chars, contains alphabetic and numeric chars)
		// then extract dates (using try/catch and DateTime)
		// finally, try to determine which remaining tokens are name/address tokens
		// the first token containing numeric chars is considered to be the first part of an address, with tokens that follow joined with ' '
		// all other tokens will be joined with ' ' and compared with patient_first_name + ' ' + patient_last_name
		
		$query_obj = new \stdClass();
		
		// lowercase, remove commas, tokenize
		$str_query = strtolower($str_query);
		$str_query = str_replace(',', '', $str_query);
		$tokens = explode(' ', $str_query);
		
		if (empty($tokens))
			return false;
		
		// extract patient_current_sex
		foreach ($tokens as $i => $token) {
			if (in_array($token, ['m', 'f', 'male', 'female'])) {
				$query_obj->patient_current_sex = $token;
				array_splice($tokens, $i, 1);
				break;
			}
		}
		
		// extract patientid
		foreach ($tokens as $i => $token) {
			$alphabetic = preg_match('/[A-Za-z]/', $token);
			$numeric = preg_match('/[0-9]/', $token);
			$psn = strpos($token, 'psn') === false ? false : true;
			$tn = strpos($token, 'tn') === false ? false : true;
			$long = strlen($token) > 3;
			if (($psn || $tn) && $alphabetic && $numeric && $long) {
				$query_obj->patientid = $token;
				array_splice($tokens, $i, 1);
				break;
			}
		}
		
		// extract patient_dob
		foreach ($tokens as $i => $token) {
			if (strtotime($token)) {
				$query_obj->patient_dob = $token;
				array_splice($tokens, $i, 1);
				break;
			}
		}
		
		foreach ($tokens as $i => $token) {
			$numeric = preg_match('/[0-9]/', $token);
			
			if ($numeric) {
				if ($i > 0) {
					$query_obj->patient_name = implode(' ', array_slice($tokens, 0, $i));
					// array_splice($tokens, $i, 1);
				}
				$query_obj->patient_street_address_1 = implode(' ', array_slice($tokens, $i));
				break;
			}
		}
		
		if (empty($query_obj->patient_name)) {
			$query_obj->patient_name = implode(' ', $tokens);
		}
		
		return $query_obj;
	}
	
	//	return array of flat arrays -- each flat array is a $record that also has values from latest demographics instance
	function squish_demographics($records) {
		$ret_array = [];
		$eid = $this->getFirstEventId();
		foreach($records as $rid => $record) {
			// first lets find the most recent demographics instance
			$last_instance = null;
			$last_instance_date = null;
			foreach($record["repeat_instances"][$eid]["demographics"] as $demo_index => $demo) {
				if (empty($last_instance_date)) {
					$last_instance = $demo;
					$last_instance_date = $demo["patient_last_change_time"];
				} elseif (strtotime($demo["patient_last_change_time"]) > strtotime($last_instance_date)) {
					$last_instance = $demo;
					$last_instance_date = $demo["patient_last_change_time"];
				}
			}
			if (empty($last_instance))
				continue;
			
			// add values to $record array
			$flat_array = [];
			foreach($record[$eid] as $key => $val) {
				$flat_array[$key] = $val;
				// $this->llog("setting \$flat_array[$key] = $val");
			}
			foreach($last_instance as $key => $val) {
				if (!empty($val))
					$flat_array[$key] = $val;
				// $this->llog("setting \$flat_array[$key] = $val");
			}
			$ret_array[] = $flat_array;
		}
		return $ret_array;
	}
	
	function getFieldLabels($field) {
		$labels = [];
		
		$label_pattern = "/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/";
		$label_string = $this->project->metadata[$field]["element_enum"];
		preg_match_all($label_pattern, $label_string, $matches);
		if (!empty($matches[2]))
			return $matches[2];
	}
	
	function save_auth_data() {
		$this->framework->setSystemSetting('auth_data', json_encode($this->auth_data));
		// $this->llog("saved auth_data: " . print_r($this->auth_data, true));
	}
	
	function get_next_user_id() {
		$maxid = 1;
		foreach($this->auth_data->users as $user) {
			$maxid = max($user->id + 1, $maxid);
		}
		return $maxid;
	}
	
	function get_next_facility_id() {
		$maxid = 1;
		foreach($this->auth_data->facilities as $fac) {
			$maxid = max($fac->id + 1, $maxid);
		}
		return $maxid;
	}
	
	function nlog() {
		// if (file_exists("C:/vumc/log.txt")) {
			// file_put_contents("C:/vumc/log.txt", "constructing ARIES instance\n");
		// }
	}
	
	function llog($text) {
		// if (file_exists("C:/vumc/log.txt")) {
			// file_put_contents("C:/vumc/log.txt", "$text\n", FILE_APPEND);
		// }
	}
	
	function rlog($msg) {
		\REDCap::logEvent("ARIES Module", $msg);
	}
}


if ($_GET['action'] == 'predictPatients') {
	$module = new ARIES();
	session_start();
	if (!$_SESSION['aries_authenticated']) {
		$response = ['error' => "The ARIES module can't return patient information without first authenticating -- please sign in"];
		echo(json_encode((object) $response));
	} else {
		$query = filter_var($_GET['searchString'], FILTER_SANITIZE_STRING);
		$recs = $module->search($query, 7);	// limit to 7 records for autocomplete
		echo(json_encode($recs));
	}
}
