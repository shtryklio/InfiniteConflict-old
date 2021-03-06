<?

class Ruler {

	private static $instance;

	public function __construct() {
		$this->db = db::getInstance();
		$this->Research = Research::getInstance();
	}
	
	public static function getInstance() {
		if (!Ruler::$instance instanceof self) {
			 Ruler::$instance = new self();
		}
		return Ruler::$instance;
	}

	private function __clone() { }


	public function LoadRuler($id){
		$ruler = $this->db->QuickSelect('ruler', $id);
		return $ruler;
	}

	public function LoadRulers(){
		$q = "SELECT * FROM ruler";
		return $this->db->Select($q);
	}


	public function LoadRulerPlanets($id){
		$q = "SELECT * FROM planet WHERE ruler_id='" . $this->db->esc($id) . "'
				ORDER BY id ASC";
		if ($r = $this->db->Select($q)){
			$planets = array();
			foreach ($r as $row){
				$planets[] = IC::getInstance()->LoadPlanet($row['id']);
			}
			return $planets;
		}
		return false;
	}


	public function LoadRulerPlanetCount($id){
		$q = "SELECT COUNT(id) AS planets FROM planet WHERE ruler_id='" . $this->db->esc($id) . "'";
		if ($r = $this->db->Select($q)) {
			return $r[0]['planets'];
		}
		return false;
	}


	public function LoadRulerResource($ruler_id, $resource_id){
		$q = "SELECT * FROM ruler_has_resource
				WHERE ruler_id='" . $this->db->esc($ruler_id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "' LIMIT 1";
		if ($r = $this->db->Select($q)){
			return $r[0]['qty'];
		}
		return false;
	}


	public function LoadRulerQL($ruler_id){
		$QL=1;
		if ($research = $this->Research->LoadRulerResearch($ruler_id)){
			foreach($research as $r){
				if (preg_match('/Queue Length (?P<digit>\d+)/', $r['name'], $matches)){
					$QL = $matches['digit'] > $QL ? $matches['digit'] : $QL;
				}
			}
			return $QL;
		}
		return 1;
	}


	public function LoadRulerPL($ruler_id){
		$PL=1;
		if ($research = $this->Research->LoadRulerResearch($ruler_id)){
			foreach($research as $r){
				if (preg_match('/Planet Limit (?P<digit>\d+)/', $r['name'], $matches)){
					$PL = $matches['digit'] > $PL ? $matches['digit'] : $PL;
				}
			}
			return $PL;
		}
		return 1;	
	}


	public function CheckConfirmCode($code){
		$q = "SELECT * FROM ruler WHERE hash='" . $this->db->esc($code) . "' LIMIT 1";
		if ($r = $this->db->Select($q)){
			return $r[0];
		}
		return false;
	}


	public function CheckEmail($email){
		$q = "SELECT * FROM ruler WHERE email='" . $this->db->esc($email) . "' LIMIT 1";
		if ($r = $this->db->Select($q)){
			return false;
		}
		return true;
	}


	public function CheckRulerName($name, $id=false){
		$q = "SELECT * FROM ruler
						WHERE name='" . $this->db->esc($name) . "' ";
		if ($id){
			$q .= "AND id <> '" . $this->db->esc($id) . "' ";	
		}				
		$q .= "LIMIT 1";
		if ($r = $this->db->Select($q)){
			return false;
		}
		return true;
	}


	public function CreateRuler($arr){
		$arr['hash'] = md5(rand(1,42323) . $arr['email'] . time());
		$id = $this->db->QuickInsert('ruler', $arr);
		$this->SendRegEmail($id);
		return $id;
	}


	public function SignupRuler($arr){
		if ($arr['confirmed']){
			$ruler = $arr;
		}else{
			$ruler = $this->CheckConfirmCode($arr['hash']);
		}
		if ($ruler){

			$ruler['name'] = $arr['rulername'];
			$ruler['confirmed'] = 1;
			$ruler['hash'] = NULL;
			$this->db->QuickEdit('ruler', $ruler);

			$planet = $this->LoadNextHomeplanet();
			$planet['name'] = $arr['planetname'];
			$planet['ruler_id'] = $ruler['id'];

			$this->db->QuickEdit('planet', $planet);

			$this->SetHomePlanetBuildings($planet['id']);
			$this->SetStartingResearch($ruler['id']);
			$this->SetStartingResources($ruler['id']);

			return $ruler;
		}
		return false;
	}


	private function SetHomePlanetBuildings($id){
		$planet = $this->LoadPlanet($id);
		$buildings = $this->LoadStartingBuildings();
		$out = array();
		foreach ($buildings as $b){
			$b['planet_id'] = $id;
			$out[] = $b;
		}
		$this->db->MultiInsert('planet_has_building', $out);
	}


	private function SetStartingResearch($ruler_id){
		$q = "SELECT * FROM research WHERE given=1";
		if ($research = $this->db->Select($q)){
			$array = array();
			foreach ($research as $r){
				$array[] = array(
					'research_id' => $r['id'],
					'ruler_id' => $ruler_id
				);
			}
			$this->db->MultiInsert('ruler_has_research', $array);
		}
		return true;
	}


	public function SetStartingResources($ruler_id){
		if ($r = $this->LoadRulerStartingResources()){
			foreach($r as $row){
				$arr = array(
					'ruler_id' => $ruler_id,
					'resource_id' => $row['resource_id'],
					'qty' => $row['qty']
				);
				$this->db->QuickInsert('ruler_has_resource', $arr);
			}
			return true;
		}
		return false;
	}

	public function LoadRulerStartingResources(){
		$q = "SELECT * FROM ruler_starting_resource";
		return $this->db->Select($q);
	}


	public function LoadPlanetStartingResources(){
		$q = "SELECT * FROM planet_starting_resource";
		return $this->db->Select($q);
	}
	

	public function LoadStartingBuildings(){
		$q = "SELECT * FROM planet_starting_building";
		return $this->db->Select($q);
	}
	
	
	public function LoadRulerResources($ruler_id){
		$q = "SELECT r.*, rr.qty FROM ruler_has_resource AS rr
				LEFT JOIN resource AS r ON rr.resource_id = r.id
				WHERE rr.ruler_id = '" . $this->db->esc($ruler_id) . "'
				AND r.global = 1";
		return $this->db->Select($q);
	}


	public function CheckLogin($email, $password){
		$q = "SELECT * FROM ruler
				WHERE email='" . $this->db->esc($email) . "'
				AND `password`='" . $this->db->esc($this->CreatePassword($email, $password)) . "'
				AND `confirmed`=1 LIMIT 1";
		if ($r = $this->db->Select($q)){
			return $r[0];
		}
		return false;
	}


	public function Login($ruler){
		setcookie(COOKIE_NAME, session_id(), (time()+COOKIE_LIFETIME), '/');
		$arr = array(
			'ruler_id'  => $ruler,
			'session_id' => session_id(),
			'session_ip' => $_SERVER['REMOTE_ADDR']
		);

		$this->db->QuickInsert('session', $arr);
		return true;
	}


	public function SendRegEmail($id){
		$ruler = $this->LoadRuler($id);
		$this->smarty->assign('ruler', $ruler);
		$subject = 'Your registration on InfiniteConflict.com';
		return $this->SendEmail($ruler['email'], $subject, 'register.tpl');
	}


	public function CreatePassword($email, $pass){
		return md5(md5($email) . md5($pass));
	}


	public function VaryResource($ruler_id, $resource_id, $qty){
	
		$q = "SELECT * FROM ruler_has_resource 
				WHERE ruler_id = '" . $this->db->esc($ruler_id) . "'
				AND resource_id = '" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q)){  
			$q = "UPDATE ruler_has_resource SET qty = qty + '" . $this->db->esc($qty) . "'
					WHERE ruler_id = '" . $this->db->esc($ruler_id) . "'
					AND resource_id = '" . $this->db->esc($resource_id) . "'";
			return $this->db->Edit($q);
		}
		
		else{
			$q = "INSERT INTO ruler_has_resource SET qty = '" . $this->db->esc($qty) . "',
					ruler_id = '" . $this->db->esc($ruler_id) . "',
					resource_id = '" . $this->db->esc($resource_id) . "'";
			return $this->db->Insert($q);    	
		}
	}


	public function SetResource($ruler_id, $resource_id, $qty){
		$q = "SELECT * FROM ruler_has_resource 
				WHERE ruler_id = '" . $this->db->esc($ruler_id) . "'
				AND resource_id = '" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q)){  
			$q = "UPDATE ruler_has_resource SET qty = '" . $this->db->esc($qty) . "'
					WHERE ruler_id = '" . $this->db->esc($ruler_id) . "'
					AND resource_id = '" . $this->db->esc($resource_id) . "'";
			return $this->db->Edit($q);
		}
		
		else{
			$q = "INSERT INTO ruler_has_resource SET
					qty = '" . $this->db->esc($qty) . "',
					ruler_id = '" . $this->db->esc($ruler_id) . "',
					resource_id = '" . $this->db->esc($resource_id) . "'";
			return $this->db->Insert($q);    	
		}

	}


	public function LoadAlliance($id){
		$alliance = $this->db->QuickSelect('alliance', $id);
		return $alliance;
	}


	public function LoadAlliances(){
		$q = "SELECT * FROM alliance ORDER BY name ASC";
		return $this->db->Select($q);		
	}


	public function CreateAlliance($name, $founder){
		$arr = array(	
			'name' => $name,
			'founder' => $founder
		);
		if ($id = $this->db->QuickInsert('alliance', $arr)){
			$this->AddRulerToAlliance($founder, $id, 0);
			return $this->LoadAlliance($id);
		}
		return false;
	}
	
	private function AddRulerToAlliance($ruler_id, $alliance_id, $level=0){
		$arr = array(
			'alliance_id' => $alliance_id,
			'id' => $ruler_id,
			'leaving' => NULL,
			'alliance_level' => $level
		);
		return $this->db->QuickEdit('ruler', $arr);
	}

	public function LoadAllianceMembers($id){
		$q = "SELECT * FROM ruler
				WHERE alliance_id = '" . $this->db->esc($id) . "'
				ORDER BY alliance_level DESC";
		return $this->db->Select($q);
	}

}
?>
