<?

class Fleet extends IC {


	function __construct(&$db){
		$this->db = $db;
		$this->Planet = new Planet($db);
	}
	
	
	public function RulerOwnsFleet($ruler_id, $fleet_id){
		$fleet = $this->LoadFleet($fleet_id);
		
		if($fleet['ruler_id'] == $ruler_id){
			return true;
		}
		return false;	
	}
	
	
	public function LoadFleet($fleet_id){	
		$q = "SELECT f.*, p.name AS planet_name , p.ruler_id AS planet_ruler, p.galaxy_id, p.system_id FROM fleet AS f
						LEFT JOIN planet AS p ON f.planet_id = p.id
						WHERE f.id='" . $this->db->esc($fleet_id) . "'";
		if ($r = $this->db->Select($q)){
			$r[0]['resources'] = $this->LoadResources($fleet_id);
			$r[0]['produced'] = $this->LoadProduced($fleet_id);
			return $r[0];
		}
		return false;
	}
	
	
	public function CreateFleet($ruler_id, $planet_id, $fleet_name){
		if ($this->Planet->RulerOwnsPlanet($ruler_id, $planet_id)){
			$arr = array(
				'name' => $fleet_name,
				'ruler_id' => $ruler_id,
				'planet_id' => $planet_id
			);
			return $this->db->QuickInsert('fleet', $arr);
		}
		return false;
	}
	
	
	public function LoadRulerFleets($ruler_id){
		$q = "SELECT f.*, p.name AS planet_name , p.ruler_id AS planet_ruler, p.galaxy_id, p.system_id FROM fleet AS f 
						LEFT JOIN planet AS p ON f.planet_id = p.id
						WHERE f.ruler_id='" . $this->db->esc($ruler_id) . "'
						ORDER BY f.id ASC";
		if ($r = $this->db->Select($q)){
			$fleets = array();
			foreach ($r as $row){
				if ($resources = $this->LoadResources($row['fleet_id'])){
					foreach ($resources as $res){
						$row['resources'][$res['id']] = $res;
					}
				}
				
				$queue = $this->LoadQueue($row['fleet_id']);
				
				$fleets[] = $row;
			}
			return $fleets;
		}
		return false;
	}
	
	
	public function LoadPlanetFleets($planet_id, $ruler_id=false){
		$q = "SELECT f.*, p.name AS planet_name , p.ruler_id AS planet_ruler, p.galaxy_id, p.system_id FROM fleet AS f 
						LEFT JOIN planet AS p ON f.planet_id = p.id
						WHERE f.planet_id='" . $this->db->esc($planet_id) . "'
						AND moving IS NULL";
		if ($ruler_id){
			$q .= " AND f.ruler_id='" . $this->db->esc($ruler_id) . "' ";
		}
		$q .= "ORDER BY f.id ASC";
		
		if ($r = $this->db->Select($q)){
			$fleets = array();
			foreach ($r as $row){
				$fleets[] = $row;
			}
			return $fleets;
		}
		return false;
	}	
	
	
	public function LoadResources($fleet_id){
		$q = "SELECT fr.*, r.name, r.transferable FROM fleet_has_resource AS fr
						LEFT JOIN resource AS r ON fr.resource_id = r.id
						WHERE fleet_id='" . $this->db->esc($fleet_id) . "'";
		if ($r = $this->db->Select($q)){
			$resources = array();
			foreach ($r as $row){
				$resources[$row['resource_id']] = $row;
			}
			return $resources;
		}	
	}
	
	
	public function LoadStorage($fleet_id, $resource_id){
		$storage = 0;
		if ($produced = $this->LoadProduced($fleet_id)){
			foreach($produced as $p){
				if ($res = $this->Planet->LoadProductionResources($p['id'])){
					foreach ($res as $r){
						if ($r['resource_id'] == $resource_id){
							$storage += $r['storage'] * $p['qty'];
						}
					}	
				}
			}
		}
		return $storage;
	}
	
	
	public function LoadStored($fleet_id, $resource_id){
		if ($res = $this->LoadResources($fleet_id)){
			foreach ($res as $r){
				if ($r['resource_id'] == $resource_id){
					return $r['stored'];
				}
			}
		}
		return 0;
	}


	public function LoadQueue($fleet_id){
		$q = "SELECT * FROM fleet_queue WHERE fleet_id='" . $this->db->esc($fleet_id) . "' ORDER BY rank ASC";
		return $this->db->Select($q);
	}	


  public function LoadProduced($fleet_id){
    $q = "SELECT p.*, fp.qty FROM fleet_has_production AS fp
    				LEFT JOIN production AS p ON fp.production_id = p.id
    				WHERE fleet_id='" . $this->db->esc($fleet_id) . "'";
    return $this->db->Select($q);
  }
  
  
  public function PlanetToFleetResource($planet_id, $fleet_id, $resource_id, $qty){
    	
  	$max = $qty;
  	if ($resources = $this->Planet->CalcPlanetResources($planet_id, false)){
  		foreach ($resources as $res){
  			if ($res['id'] == $resource_id){
  				if ($res['stored'] - $res['busy'] < $max){
  					$max = $res['stored'] - $res['busy'];
  				}
  			}
  		}
  	}
  	  	
  	$storage = $this->LoadStorage($fleet_id, $resource_id);
		$stored = $this->LoadStored($fleet_id, $resource_id);
		
		$free = $storage - $stored;
		if ($free < $max){
			$max = $free;
		}
				
		if ($max > 0){
			$this->Planet->VaryResource($planet_id, $resource_id, -$max);
			$this->VaryResource($fleet_id, $resource_id, $max);
		}
		return true; 	
  }


  public function PlanetToFleetProduction($planet_id, $fleet_id, $production_id, $qty){
  	if ($produced = $this->Planet->LoadProduced($planet_id)){
  		foreach ($produced as $p){
  			if ($p['id']==$production_id){
  				if ($p['qty'] <= $qty){
  					$qty = $p['qty'];
  					$q = "DELETE FROM planet_has_production
  									WHERE planet_id='" . $this->db->esc($planet_id) . "'
  									AND production_id='" . $this->db->esc($production_id) . "'";
  					$this->db->Edit($q);
  				}else{
  					$q = "UPDATE planet_has_production
  									SET qty = qty - '" . $this->db->esc($qty) . "'
  									WHERE planet_id='" . $this->db->esc($planet_id) . "'
  									AND production_id='" . $this->db->esc($production_id) . "'";
  					$this->db->Edit($q);
  				}
  				
  				if ($qty > 0){
  					$q = "SELECT * FROM fleet_has_production
  									WHERE fleet_id='" . $this->db->esc($fleet_id) . "'
  									AND production_id='" . $this->db->esc($production_id) . "'";
  					if ($r = $this->db->Select($q)){
  						$r[0]['qty'] += $qty;
  						$this->db->QuickEdit('fleet_has_production', $r[0]);
  					}else{
  						$arr = array(
  							'fleet_id' => $fleet_id,
  							'production_id' => $production_id,
  							'qty' => $qty
  						);
  						$this->db->QuickInsert('fleet_has_production', $arr);
  					}
  				}	
  			}  			
  		}
  	}
  }



  public function FleetToPlanetResource($fleet_id, $planet_id, $resource_id, $qty){
  	$stored = $this->LoadStored($fleet_id, $resource_id);
  	if ($stored < $qty){
  		$qty = $stored;
  	}
  	  	
  	if ($qty){
	  	if ($resources = $this->Planet->CalcPlanetResources($planet_id, false)){
	  		foreach ($resources as $res){
	  			if ($res['id'] == $resource_id){
	  				if ($qty > $res['storage'] - $res['stored'] && $res['req_storage']){
	  					$qty = $res['storage'] - $res['stored'];
	  				}
	  				if ($qty > 0){
	  					$this->Planet->VaryResource($planet_id, $resource_id, $qty);
	  					$this->VaryResource($fleet_id, $resource_id, -$qty);
	  				}
	  			}
	  		}
	  	}
  	}
  }


  public function FleetToPlanetProduction($fleet_id, $planet_id, $production_id, $qty){
  	if ($produced = $this->LoadProduced($fleet_id)){
  		foreach ($produced as $p){
  			if ($p['id'] == $production_id){
  				if ($qty > $p['qty']){
  					$qty = $p['qty'];
  					break;
  				}
  			}
  		}
  	}else{
  		return false;
  	}
  
		if ($res = $this->Planet->LoadProductionResources($production_id)){
			foreach ($res as $r){
				if ($r['storage']){
					$curStorage = $this->LoadStorage($fleet_id, $r['resource_id']);
					if ($curStored = $this->LoadStored($fleet_id, $r['resource_id'])){

						$space = $curStorage - $curStored;
						
						$less = $qty * $r['storage'];
						if ($space < $less){
							$qty = floor($space / $r['storage']);
						}
					}
				}
			}	
		}
		
		if ($qty > 0){
			$q = "SELECT * FROM fleet_has_production
							WHERE fleet_id='" . $this->db->esc($fleet_id) . "'
							AND production_id='" . $this->db->esc($production_id) . "'";
			if ($r = $this->db->Select($q)){
				if ($r[0]['qty'] > $qty){
					$r[0]['qty'] -= $qty;
					$this->db->QuickEdit('fleet_has_production', $r[0]);
				}else{
					$q = "DELETE FROM fleet_has_production
									WHERE fleet_id='" . $this->db->esc($fleet_id) . "'
									AND production_id='" . $this->db->esc($production_id) . "'";
					$this->db->Edit($q);
				}
			}
			
			
			$q = "SELECT * FROM planet_has_production
							WHERE planet_id='" . $this->db->esc($planet_id) . "'
							AND production_id='" . $this->db->esc($production_id) . "'";
			if ($r = $this->db->Select($q)){
				$r[0]['qty'] += $qty;
				$this->db->QuickEdit('planet_has_production', $r[0]);
			}else{
				$arr = array(
					'planet_id' => $planet_id,
					'production_id' => $production_id,
					'qty' => $qty
				);
				$this->db->QuickInsert('planet_has_production', $arr);
			}
  							
		}

  }



  public function FleetToFleetResource($fleet_src_id, $fleet_dest_id, $resource_id, $qty){
  
  }  
  
  
  public function FleetToFleetProduction($fleet_src_id, $fleet_dest_id, $production_id, $qty){
  
  }




  public function VaryResource($fleet_id, $resource_id, $qty){
 		$q = "SELECT * FROM fleet_has_resource WHERE fleet_id='" . $this->db->esc($fleet_id) . "'
 						AND resource_id='" . $this->db->esc($resource_id) . "'";
 					//FB::log($q);
 		if ($r = $this->db->Select($q, false, false, false)){
	    $r[0]['stored'] += $qty;
	    return $this->db->QuickEdit('fleet_has_resource', $r[0]);
    }else{
    	$arr = array(
    		'fleet_id' => $fleet_id,
    		'resource_id' => $resource_id,
    		'stored' => $qty
    	);
    	return $this->db->QuickInsert('fleet_has_resource', $arr);
    }
  }

  
  public function SetResource($fleet_id, $resource_id, $qty){
 		$q = "SELECT * FROM fleet_has_resource WHERE fleet_id='" . $this->db->esc($fleet_id) . "'
 						AND resource_id='" . $this->db->esc($resource_id) . "'";
 		if ($r = $this->db->Select($q, false, false, false)){
	    $r[0]['stored'] = $qty;
	    return $this->db->QuickEdit('fleet_has_resource', $r[0]);
    }else{
    	$arr = array(
    		'fleet_id' => $fleet_id,
    		'resource_id' => $resource_id,
    		'stored' => $qty
    	);
    	return $this->db->QuickInsert('fleet_has_resource', $arr);
    }
  }


}

?>