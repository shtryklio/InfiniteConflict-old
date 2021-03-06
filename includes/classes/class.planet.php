<?php

/*
 *	This class controls all planet related methods
 *
 */
class Planet {

	private static $instance;

	public function __construct() {
		$this->db = db::getInstance();
		$this->Research = Research::getInstance();
	}
	
	public static function getInstance() {
		if (!Planet::$instance instanceof self) {
			 Planet::$instance = new self();
		}
		return Planet::$instance;
	}

	private function __clone() { }

	
	public function LoadPlanetResources($id, $cache = true) {
		$q = "SELECT pr.*, r.creatable, r.transferable, r.name FROM	 planet_has_resource AS pr
				LEFT JOIN resource AS r ON pr.resource_id = r.id
				WHERE planet_id='" . $this->db->esc($id) . "'";
		return $this->db->Select($q, false, true, $cache);
	}
	
	
	
	public function LoadBuildings() {
		$q = "SELECT * FROM building";
		return $this->db->Select($q);
	}
	
	
	public function LoadBuilding($id) {
		return $this->db->QuickSelect('building', $id);
	}
	
	
	public function LoadProduction() {
		$q = "SELECT * FROM production";
		return $this->db->Select($q);
	}
	
	public function LoadProd($id) {
		return $this->db->QuickSelect('production', $id);
	}
	
	public function LoadProduced($planet_id) {
		$q = "SELECT p.*, pp.qty FROM planet_has_production AS pp
				LEFT JOIN production AS p ON pp.production_id = p.id
				WHERE planet_id='" . $this->db->esc($planet_id) . "'";
		return $this->db->Select($q);
	}
	
	public function RulerOwnsPlanet($ruler_id, $planet_id) {
		$planet = IC::getInstance()->LoadPlanet($planet_id);
		
		if ($planet['ruler_id'] == $ruler_id) {
			return true;
		}
		return false;
	}
	
	
	public function CalcBuildingResources($planet_id) {
		$resources = IC::getInstance()->LoadResources();
		$buildings = $this->LoadPlanetBuildings($planet_id);
		
		$out = array();
		
		if ($buildings) {
			foreach ($buildings as $b) {
				$bld        = $this->LoadBuilding($b['building_id']);
				$bld['qty'] = $b['qty'];
				
				$buildingRes = $this->LoadBuildingResources($b['building_id']);
				
				foreach ($resources as $r) {
					$abundance = $this->CalcAbundance($planet_id, $r['id']);
					
					foreach ($buildingRes as $res) {
						if (!$r['global']) {
							if ($r['id'] == $res['resource_id']) {
								$bld['resources'][$r['id']]         = $res;
								$bld['resources'][$r['id']]['name'] = $r['name'];
								
								
								if ($bld['resources'][$r['id']]['output'] > 0) {
									$bld['resources'][$r['id']]['total_output'] = $bld['resources'][$r['id']]['output'] * $b['qty'] * $abundance;
								}
								
								// Buildings with negative output dont take into account abundance
								if ($bld['resources'][$r['id']]['output'] < 0) {
									$bld['resources'][$r['id']]['total_output'] = $bld['resources'][$r['id']]['output'] * $b['qty'];
								}
								
								$bld['resources'][$r['id']]['total_stores'] = $bld['resources'][$r['id']]['stores'] * $b['qty'];
								$bld['resources'][$r['id']]['total_cost']   = $bld['resources'][$r['id']]['cost'] * $b['qty'];
								
								$bld['resources'][$r['id']]['total_output_str'] = ($bld['resources'][$r['id']]['total_output'] > 0 ? '+' : '') . number_format($bld['resources'][$r['id']]['total_output'], 0);
								$bld['resources'][$r['id']]['total_stores_str'] = ($bld['resources'][$r['id']]['total_stores'] > 0 ? '+' : '') . number_format($bld['resources'][$r['id']]['total_stores'], 0);
							}
						}
					}
				}
				$out[] = $bld;
			}
		}
		
		return $out;
	}
	
	
	
	public function CalcPlanetResources($id, $cache = true) {
		$resources = IC::getInstance()->LoadResources();
		$planet    = $this->LoadPlanetResources($id, $cache);
		
		$out = array();
		foreach ($resources as $r) {
			$out[$r['name']] = array(
				'id' => $r['id'],
				'interest' => $r['interest'],
				'req_storage' => $r['req_storage'],
				'creatable' => $r['creatable'],
				'stored' => 0,
				'stored_str' => 0,
				'global' => $r['global'],
				'storage' => $this->CalcStorage($id, $r['id']),
				'storage_str' => number_format($this->CalcStorage($id, $r['id']), 0)
			);
			
			foreach ($planet as $res) {
				if ($r['id'] == $res['resource_id']) {
					$out[$r['name']]['id']             = $r['id'];
					$out[$r['name']]['stored']         = $res['stored'];
					$out[$r['name']]['stored_str']     = number_format($out[$r['name']]['stored'], 0);
					$out[$r['name']]['output']         = $this->CalcOutput($id, $r['id']);
					$out[$r['name']]['output_str']     = ($out[$r['name']]['output'] < 0 ? '' : '+') . number_format($out[$r['name']]['output'], 0);
					$out[$r['name']]['net_output']     = $this->CalcOutput($id, $r['id'], false);
					$out[$r['name']]['net_output_str'] = ($out[$r['name']]['net_output'] < 0 ? '' : '+') . number_format($out[$r['name']]['net_output'], 0);
					$out[$r['name']]['abundance']      = $this->CalcAbundance($id, $r['id']);
					$out[$r['name']]['abundance_str']  = $out[$r['name']]['abundance'] * 100;
					$out[$r['name']]['busy']           = $this->CalcBusy($id, $r['id'], $cache);
					$out[$r['name']]['busy_str']       = number_format($out[$r['name']]['busy'], 0);
				}
			}
		}
		return $out;
	}



	public function LoadPlanetResources2($id, $cache = true) {
		$resources = IC::getInstance()->LoadResources();
		$planet    = $this->LoadPlanetResources($id, $cache);
		
		$out = array();
		foreach ($resources as $r) {
			$out[$r['name']] = array(
				'id' => $r['id'],
				'interest' => $r['interest'],
				'req_storage' => $r['req_storage'],
				'creatable' => $r['creatable'],
				'stored' => 0,
				'stored_str' => 0,
				'global' => $r['global'],
				'storage' => $this->CalcStorage($id, $r['id']),
				'storage_str' => number_format($this->CalcStorage($id, $r['id']), 0)
			);
			
			foreach ($planet as $res) {
				if ($r['id'] == $res['resource_id']) {
					$out[$r['name']]['id']             = $r['id'];
					$out[$r['name']]['stored']         = $res['stored'];
					$out[$r['name']]['stored_str']     = number_format($out[$r['name']]['stored'], 0);
					$out[$r['name']]['output']         = $res['output'];
					$out[$r['name']]['output_str']     = ($out[$r['name']]['output'] < 0 ? '' : '+') . number_format($out[$r['name']]['output'], 0);
					$out[$r['name']]['abundance']      = $res['abundance'];
					$out[$r['name']]['abundance_str']  = $out[$r['name']]['abundance'] * 100;
					$out[$r['name']]['busy']           = $this->CalcBusy($id, $r['id'], $cache);
					$out[$r['name']]['busy_str']       = number_format($out[$r['name']]['busy'], 0);
				}
			}
		}
		return $out;
	}
	
	
	
	public function CalcOutput($planet_id, $resource_id, $gross = true) {
		$buildings = $this->LoadPlanetBuildings($planet_id);
		$resources = $this->LoadPlanetResources($planet_id);
		$taxes     = $this->LoadResourceTaxOutput($resource_id);
		
		if ($resources) {
			foreach ($resources as $r) {
				if ($r['resource_id'] == $resource_id) {
					$res = $r;
					$res['output'] = 0;
					break;
				}
			}
		}
		
		$extra = 0;
		
		if ($buildings) {
			foreach ($buildings as $b) {
				$build = $this->LoadBuildingResources($b['building_id']);
				foreach ($build as $build_resource) {
					if ($resource_id == $build_resource['resource_id']) {
						if ($build_resource['output'] > 0) {
							$res['output'] += $build_resource['output'] * $b['qty'];
						}
						
						if ($gross) {
							if ($build_resource['output'] < 0) {
								$extra += $build_resource['output'] * $b['qty'];
							}
						}
					}
				}
			}
		}
		
		
		if ($gross) {
			if ($taxes) {
				foreach ($taxes as $t) {
					$stored = $this->LoadPlanetResourcesStored($planet_id, $t['resource_id']);
					$extra += $stored * $t['rate'];
				}
			}
		}
		
		$output = $res['output'] * $this->CalcAbundance($planet_id, $resource_id);
		return $output + $extra;
	}
	
	
	
	public function CalcStorage($planet_id, $resource_id) {
		$buildings = $this->LoadPlanetBuildings($planet_id);
		
		$res = IC::getInstance()->LoadResource($resource_id);
		
		$storage = 0;
		
		if ($buildings) {
			foreach ($buildings as $b) {
				$build = $this->LoadBuildingResources($b['building_id']);
				foreach ($build as $build_resource) {
					if ($resource_id == $build_resource['resource_id']) {
						if ($build_resource['stores'] > 0) {
							$storage += $build_resource['stores'] * $b['qty'];
						}
					}
				}
			}
		}
		
		return $storage;
	}
	
	
	
	public function CalcBusy($planet_id, $resource_id, $cache = true) {
		$bldQueue        = $this->LoadBuildingsQueue(NULL, $planet_id, $cache);
		$productionQueue = $this->LoadProductionQueue(NULL, $planet_id, $cache);
		$conversionQueue = $this->LoadConversionQueue(NULL, $planet_id, $cache);
		
		$busy = 0;
		
		if ($bldQueue) {
			foreach ($bldQueue as $b) {
				if ($b['started'] == 1) {
					if ($res = $this->LoadBuildingResources($b['building_id'])) {
						foreach ($res as $r) {
							if ($r['resource_id'] == $resource_id && $r['refund'] == 1) {
								$busy += $r['cost'];
							}
						}
					}
				}
			}
		}
		
		if ($productionQueue) {
			foreach ($productionQueue as $p) {
				if ($p['started'] == 1) {
					if ($res = $this->LoadProductionResources($p['production_id'])) {
						foreach ($res as $r) {
							if ($r['resource_id'] == $resource_id && $r['refund'] == 1) {
								$busy += $r['cost'] * $p['qty'];
							}
						}
					}
				}
			}
		}
		
		if ($conversionQueue) {
			foreach ($conversionQueue as $c) {
				if ($c['started'] == 1) {
					if ($res = $this->LoadConversionResources($c['resource_id'])) {
						foreach ($res as $r) {
							if ($r['cost_resource'] == $resource_id && $r['refund'] == 1) {
								$busy += $r['cost'] * $c['qty'];
							}
						}
					}
				}
			}
		}
		
		return $busy;
	}
	
	
	
	public function CalcAbundance($planet_id, $resource_id) {
		$buildings = $this->LoadPlanetBuildings($planet_id);
		$resources = $this->LoadPlanetResources($planet_id);
		
		foreach ($resources as $r) {
			if ($r['resource_id'] == $resource_id) {
				$res = $r;
				break;
			}
		}
		
		if ($buildings) {
			foreach ($buildings as $b) {
				$build = $this->LoadBuildingResources($b['building_id']);
				foreach ($build as $build_resource) {
					if ($resource_id == $build_resource['resource_id']) {
						if ($build_resource['abundance'] > 0) {
							$res['abundance'] += $build_resource['abundance'];
						}
					}
				}
			}
		}
		
		return $res['abundance'];
	}
	
	
	
	public function LoadPlanetResourcesStored($planet_id, $resource_id) {
		$res = $this->LoadPlanetResources($planet_id);
		foreach ($res as $r) {
			if ($r['resource_id'] == $resource_id) {
				return $r['stored'];
			}
		}
		return false;
	}
	
	
	
	public function LoadResourceTax($resource_id) {
		$q = "SELECT * FROM resource_tax WHERE resource_id='" . $this->db->esc($resource_id) . "'";
		return $this->db->Select($q);
	}
	
	
	
	public function LoadResourceTaxOutput($resource_id) {
		$q = "SELECT * FROM resource_tax WHERE output_resource='" . $this->db->esc($resource_id) . "'";
		return $this->db->Select($q);
	}
	
	
	
	public function LoadPlanetBuildings($id) {
		$q = "SELECT * FROM planet_has_building WHERE planet_id='" . $this->db->esc($id) . "' ORDER BY id ASC";
		return $this->db->Select($q);
	}
	
	
	
	public function LoadBuildingResources($id) {
		$q = "SELECT * FROM building_has_resource WHERE building_id='" . $this->db->esc($id) . "' ORDER BY resource_id ASC";
		return $this->db->Select($q);
	}
	
	
	
	public function LoadBuildingResource($id, $resource_id) {
		$q = "SELECT * FROM building_has_resource
				WHERE building_id='" . $this->db->esc($id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "'";
		return $this->db->Select($q);
	}
	
	
	
	public function LoadConversionResources($resource_id) {
		$q = "SELECT * FROM conversion_cost WHERE resource_id='" . $this->db->esc($resource_id) . "'";
		return $this->db->Select($q);
	}
	
	
	public function LoadProductionResources($production_id) {
		$q = "SELECT * FROM production_has_resource WHERE production_id='" . $this->db->esc($production_id) . "'";
		return $this->db->Select($q);
	}
	
	
	public function LoadBuildingsQueue($ruler_id, $planet_id, $cache = true) {
		$q = "SELECT planet_building_queue.*, building.name, MD5(CONCAT(planet_building_queue.id, '" . $ruler_id . "', '" . $planet_id . "','".$this->config['salt']."')) AS hash FROM planet_building_queue
				LEFT JOIN building ON planet_building_queue.building_id = building.id
				WHERE planet_id='" . $this->db->esc($planet_id) . "'
				ORDER BY rank ASC";
		if ($r = $this->db->Select($q, false, true, $cache)) {
			return $r;
		}
		return false;
	}
	
	
	public function LoadProductionQueue($ruler_id, $planet_id, $cache = true) {
		$q = "SELECT planet_production_queue.*, production.name, MD5(CONCAT(planet_production_queue.id, '" . $ruler_id . "', '" . $planet_id . "','".$this->config['salt']."')) AS hash FROM planet_production_queue
				LEFT JOIN production ON planet_production_queue.production_id = production.id
				WHERE planet_id='" . $this->db->esc($planet_id) . "'
				ORDER BY rank ASC";
		if ($r = $this->db->Select($q, false, true, $cache)) {
			return $r;
		}
		return false;
	}
	
	
	
	public function LoadConversionQueue($ruler_id, $planet_id) {
		$q = "SELECT planet_conversion_queue.*, resource.name, MD5(CONCAT(planet_conversion_queue.id, '" . $ruler_id . "', '" . $planet_id . "','".$this->config['salt']."')) AS hash FROM planet_conversion_queue
				LEFT JOIN resource ON planet_conversion_queue.resource_id = resource.id
				WHERE planet_id='" . $this->db->esc($planet_id) . "'
				ORDER BY rank ASC";
		if ($r = $this->db->Select($q, false, true, $cache)) {
			return $r;
		}
		return false;
	}
	
	
	
	public function LoadAvailableBuildings($ruler_id, $planet_id) {
		$buildings = $this->LoadBuildings();
		$research  = $this->Research->LoadRulerResearch($ruler_id);
		$current   = $this->LoadPlanetBuildings($planet_id);
		$queue     = $this->LoadBuildingsQueue($ruler_id, $planet_id);
		
		$canBuild = array();
		
		// First check for building prereq
		foreach ($buildings as $b) {
			$theCurrent = $b;
			
			
			// Load the current building from $b first in case it doesn't exist on planet
			$cur2 = array();
			if ($current) {
				foreach ($current as $cur) {
					$cur2[$cur['building_id']] = $cur;
				}
			}
			$theCurrent['qty']         = $cur2[$b['id']]['qty'];
			$theCurrent['building_id'] = $theCurrent['id'];
			
			$prereq = $this->LoadBuildingPrereq($b['id']);
			
			if ($prereq['building']) {
				foreach ($prereq['building'] as $id) {
					$found = false;
					if ($current) {
						foreach ($current as $cur) {
							if ($cur['building_id'] == $id) {
								$found = true;
							}
						}
					}
					if ($queue){
						foreach ($queue as $q) {
							if ($q['building_id'] == $id) {
								$found = true;
							}
						}						
					}
					if ($found === false) {
						continue 2;
					}
				}
			}
			
			
			if ($prereq['research']) {
				foreach ($prereq['research'] as $id) {
					$found = false;
					foreach ($research as $r) {
						if ($r['id'] == $id) {
							$found = true;
						}
					}
					if ($found === false) {
						continue 2;
					}
				}
			}
			
			// If we have one in the queue, effectively add one to the quantity to stop people queueing too many
			if ($queue) {
				foreach ($queue as $q) {
					if ($q['building_id'] == $theCurrent['building_id']) {
						$theCurrent['qty'] += 1;
					}
				}
			}
			
			if ($theCurrent['qty'] < $b['max'] || !$b['max']) {
				$resources = $this->LoadBuildingResources($b['id']);
				foreach ($resources as $r) {
					$r['output_str']                   = ($r['output'] > 0 ? '+' : '') . number_format($r['output'], 0);
					$r['cost_str']                     = number_format($r['cost'], 0);
					$b['resources'][$r['resource_id']] = $r;
				}
				$canBuild[] = $b;
			}
		}
		
		return $canBuild;
	}
	
	
	
	public function LoadBuildingPrereq($building_id) {
		$prereq = array();
		
		$q = "SELECT * FROM building_prereq WHERE building_id='" . $this->db->esc($building_id) . "'";
		if ($r = $this->db->Select($q)) {
			foreach ($r as $row) {
				$prereq['building'][] = $row['prereq'];
			}
		}
		
		$q = "SELECT * FROM building_res_prereq WHERE building_id='" . $this->db->esc($building_id) . "'";
		if ($r = $this->db->Select($q)) {
			foreach ($r as $row) {
				$prereq['research'][] = $row['research_id'];
			}
		}
		
		if (empty($prereq)) {
			return false;
		}
		
		return $prereq;
	}
	
	
	
	public function QueueBuilding($ruler_id, $planet_id, $building_id, $demolish = false) {
		$queue = false;
		
		if ($q = $this->LoadBuildingsQueue($ruler_id, $planet_id)) {
			if (!$this->Ruler) {
				$this->Ruler = new Ruler($this->db);
			}
			$QL = $this->Ruler->LoadRulerQL($ruler_id);
			if (sizeof($q) >= $QL) {
				return false;
			}
		}
		
		if ($building = $this->LoadBuilding($building_id)) {
			if ($demolish) {
				if ($avail = $this->LoadPlanetBuildings($planet_id)) {
					foreach ($avail as $bld) {
						if ($bld['building_id'] == $building['id']) {
							$queue   = true;
							$details = $bld;
							break;
						}
					}
				}
			}
			
			else {
				if ($avail = $this->LoadAvailableBuildings($ruler_id, $planet_id)) {
					foreach ($avail as $bld) {
						if ($bld['id'] == $building['id']) {
							$queue   = true;
							$details = $bld;
							break;
						}
					}
				}
			}
			
			if ($demolish === true && $building['demolish'] < 1) {
				$queue = false;
			}
		}
		
		if ($queue === true) {
			$arr = array(
				'planet_id' => $planet_id,
				'building_id' => $building['id'],
				'turns' => $building['turns'],
				'rank' => $this->db->NextRank('planet_building_queue', 'rank', "WHERE planet_id='" . $this->db->esc($planet_id) . "'")
			);
			
			if ($demolish === true) {
				$arr['demolish'] = 1;
				$arr['turns']    = $building['demolish'];
			}
			
			return $this->db->QuickInsert('planet_building_queue', $arr);
		}
		
		return false;
	}
	
	
	
	public function QueueBuildingRemove($ruler_id, $planet_id, $hash) {
		$q = "SELECT * FROM planet_building_queue
				WHERE MD5(CONCAT(id, '" . $ruler_id . "', '" . $planet_id . "','".$this->config['salt']."')) = '" . $this->db->esc($hash) . "'
					AND started IS NULL LIMIT 1";
					
		if ($r = $this->db->Select($q)){
			$q = "DELETE FROM planet_building_queue
					WHERE building_id IN (SELECT building_id FROM building_prereq WHERE prereq = '" . $this->db->esc($r[0]['building_id']) . "')
					OR id = '" . $this->db->esc($r[0]['id']) . "'
					AND planet_id = '" . $this->db->esc($planet_id) . "'";
			$this->db->Edit($q);
		}
							
		$this->db->SortRank('planet_building_queue', 'rank', 'id', "WHERE planet_id='" . $this->db->esc($planet_id) . "'");
	}
	
	
	
	public function QueueBuildingReorder($ruler_id, $planet_id, $hashes) {
		$currentQueue = $this->LoadBuildingsQueue($ruler_id, $planet_id);
		$current   = $this->LoadPlanetBuildings($planet_id);
		$queuePrereqs = array();
		$queries = array();

		$i            = 1;
		
		if ($currentQueue[0]['started']) {
			$i = 2;
		}
				
		foreach ($hashes as $hash) {

			$q = "SELECT * FROM planet_building_queue
					WHERE MD5(CONCAT(id, '" . $ruler_id . "', '" . $planet_id . "','".$this->config['salt']."')) = '" . $this->db->esc($hash) . "'
					AND planet_id = '" . $this->db->esc($planet_id) . "'";
					
			if ($thisQueue = $this->db->Select($q)){
				$queue = $thisQueue[0];
				$queuePrereqs[] = $queue['building_id'];				
				$prereq = $this->LoadBuildingPrereq($queue['building_id']);
				$found = false;
				
				if ($prereq['building']) {
					foreach ($prereq['building'] as $id) {
					
						$found = false;
						if ($current) {
							foreach ($current as $cur) {
								if ($cur['building_id'] == $id) {
									$found = true;
								}
							}
						}
						if ($queue){
							foreach ($queuePrereqs as $building_id) {
								if ($building_id == $id) {
									$found = true;
								}
							}					
						}					
					}
					
					if ($found === false){
						$this->lastError = 'ERROR: Your items cannot be queued in this order due to missing pre-requisites.';
						return false;
					}else{
						$queue['rank'] = $i;
						$i++;
						$queries[] = "UPDATE planet_building_queue
										SET rank='" . $this->db->esc($queue['rank']) . "'
										WHERE id='" . $queue['id'] . "' LIMIT 1";
					}
				}
			}
		}
		
		
		if ($queries){
			foreach ($queries as $q){
				$this->db->Edit($q);
			}
		}
		return true;
	}
	
	
	
	
	public function LoadAvailableConversions($ruler_id, $planet_id) {
		$resources = IC::getInstance()->LoadResources();
		$buildings = $this->LoadPlanetBuildings($planet_id);
		$research  = $this->Research->LoadRulerResearch($ruler_id);
		$queue     = $this->LoadConversionQueue($ruler_id, $planet_id);
		
		$canBuild = array();
		
		// First check for building prereq
		foreach ($resources as $res) {
			$theCurrent = $r;
			
			$prereq = $this->LoadConversionPrereq($res['id']);
			
			if ($prereq['building']) {
				$found = false;
				foreach ($prereq['building'] as $id) {
					foreach ($buildings as $r) {
						if ($r['building_id'] == $id) {
							$found = true;
						}
					}
					if ($found === false) {
						continue 2;
					}
				}
			}
			
			
			if ($prereq['research']) {
				$found = false;
				foreach ($prereq['research'] as $id) {
					$found = false;
					foreach ($research as $r) {
						if ($r['id'] == $id) {
							$found = true;
						}
					}
					if ($found === false) {
						continue 2;
					}
				}
			}
			
			
			
			if ($resources = $this->LoadConversionResources($res['id'])) {
				foreach ($resources as $r) {
					$r['cost_str']                         = number_format($r['cost'], 0);
					$res['resources'][$r['cost_resource']] = $r;
				}
				$canBuild[] = $res;
			}
			
		}
		
		return $canBuild;
	}
	
	
	
	public function LoadConversionPrereq($resource_id) {
		$prereq = array();
		
		$q = "SELECT * FROM conversion_bld_prereq WHERE resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q)) {
			foreach ($r as $row) {
				$prereq['building'][] = $row['building_id'];
			}
		}
		
		$q = "SELECT * FROM conversion_res_prereq WHERE resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q)) {
			foreach ($r as $row) {
				$prereq['research'][] = $row['research_id'];
			}
		}
		
		if (empty($prereq)) {
			return false;
		}
		
		return $prereq;
	}
	
	
	public function QueueConversion($ruler_id, $planet_id, $resource_id, $qty) {
		if ((int) $qty < 1) {
			return false;
		}
		
		
		if ($q = $this->LoadConversionQueue($ruler_id, $planet_id)) {
			if (!$this->Ruler) {
				$this->Ruler = new Ruler($this->db);
			}
			$QL = $this->Ruler->LoadRulerQL($ruler_id);
			if (sizeof($q) >= $QL) {
				return false;
			}
		}
		
		$queue = false;
		
		if ($avail = $this->LoadAvailableConversions($ruler_id, $planet_id)) {
			foreach ($avail as $res) {
				if ($res['id'] == $resource_id) {
					$queue = true;
					break;
				}
			}
		}
		
		
		if ($queue === true) {
			$arr = array(
				'planet_id' => $planet_id,
				'resource_id' => $res['id'],
				'qty' => $qty,
				'turns' => $res['turns'],
				'rank' => $this->db->NextRank('planet_conversion_queue', 'rank', "WHERE planet_id='" . $this->db->esc($planet_id) . "'")
			);
			
			return $this->db->QuickInsert('planet_conversion_queue', $arr);
		}
		
		return true;
	}
	
	
	public function QueueConversionRemove($ruler_id, $planet_id, $hash) {
		$q = "DELETE FROM planet_conversion_queue
				WHERE MD5(CONCAT(id, '" . $ruler_id . "', '" . $planet_id . "','".$this->config['salt']."')) = '" . $this->db->esc($hash) . "' AND started IS NULL LIMIT 1";
		$this->db->Edit($q);
		
		$this->db->SortRank('planet_conversion_queue', 'rank', 'id', "WHERE planet_id='" . $this->db->esc($planet_id) . "'");
	}
	
	
	
	public function QueueConversionReorder($ruler_id, $planet_id, $hashes) {
		$currentQueue = $this->LoadConversionQueue($ruler_id, $planet_id);
		$i            = 1;
		
		if ($currentQueue[0]['started']) {
			$i = 2;
		}
		
		foreach ($hashes as $hash) {
			foreach ($currentQueue as $queue) {
				if ($hash == $queue['hash']) {
					$queue['rank'] = $i;
					$q             = "UPDATE planet_conversion_queue SET rank='" . $this->db->esc($queue['rank']) . "' WHERE id='" . $queue['id'] . "' LIMIT 1";
					$this->db->Edit($q);
					$i++;
					continue 2;
				}
			}
			
		}
	}
	
	
	public function LoadAvailableProduction($ruler_id, $planet_id) {
		$production = $this->LoadProduction();
		$buildings  = $this->LoadPlanetBuildings($planet_id);
		$research   = $this->Research->LoadRulerResearch($ruler_id);
		$queue      = $this->LoadProductionQueue($ruler_id, $planet_id);
		
		$canBuild = array();
		
		// First check for building prereq
		foreach ($production as $prod) {
			$prereq = $this->LoadProductionPrereq($prod['id']);
			
			if ($prereq['building']) {
				$found = false;
				foreach ($prereq['building'] as $id) {
					foreach ($buildings as $r) {
						if ($r['building_id'] == $id) {
							$found = true;
						}
					}
					if ($found === false) {
						continue 2;
					}
				}
			}
			
			
			if ($prereq['research']) {
				$found = false;
				foreach ($prereq['research'] as $id) {
					$found = false;
					foreach ($research as $r) {
						if ($r['id'] == $id) {
							$found = true;
						}
					}
					if ($found === false) {
						continue 2;
					}
				}
			}
			
			
			
			if ($resources = $this->LoadProductionResources($prod['id'])) {
				foreach ($resources as $r) {
					$r['cost_str']                        = number_format($r['cost'], 0);
					$prod['resources'][$r['resource_id']] = $r;
				}
				$canBuild[] = $prod;
			}
			
		}
		
		return $canBuild;
	}
	
	
	
	public function LoadProductionPrereq($production_id) {
		$prereq = array();
		
		$q = "SELECT * FROM production_bld_prereq WHERE production_id='" . $this->db->esc($production_id) . "'";
		if ($r = $this->db->Select($q)) {
			foreach ($r as $row) {
				$prereq['building'][] = $row['building_id'];
			}
		}
		
		$q = "SELECT * FROM	 production_res_prereq WHERE production_id='" . $this->db->esc($production_id) . "'";
		if ($r = $this->db->Select($q)) {
			foreach ($r as $row) {
				$prereq['research'][] = $row['research_id'];
			}
		}
		
		if (empty($prereq)) {
			return false;
		}
		
		return $prereq;
	}
	
	
	public function QueueProduction($ruler_id, $planet_id, $production_id, $qty) {
		if ((int) $qty < 1) {
			return false;
		}
		
		
		if ($q = $this->LoadProductionQueue($ruler_id, $planet_id)) {
			if (!$this->Ruler) {
				$this->Ruler = new Ruler($this->db);
			}
			$QL = $this->Ruler->LoadRulerQL($ruler_id);
			if (sizeof($q) >= $QL) {
				return false;
			}
		}
		
		$queue = false;
		
		if ($avail = $this->LoadAvailableProduction($ruler_id, $planet_id)) {
			foreach ($avail as $prod) {
				if ($prod['id'] == $production_id) {
					$queue = true;
					break;
				}
			}
		}
		
		
		if ($queue === true) {
			$arr = array(
				'planet_id' => $planet_id,
				'production_id' => $prod['id'],
				'qty' => $qty,
				'turns' => $prod['turns'],
				'rank' => $this->db->NextRank('planet_production_queue', 'rank', "WHERE planet_id='" . $this->db->esc($planet_id) . "'")
			);
			
			return $this->db->QuickInsert('planet_production_queue', $arr);
		}
		
		return true;
	}
	
	
	public function QueueProductionRemove($ruler_id, $planet_id, $hash) {
		$q = "DELETE FROM planet_production_queue
				WHERE MD5(CONCAT(id, '" . $ruler_id . "', '" . $planet_id . "','".$this->config['salt']."')) = '" . $this->db->esc($hash) . "' AND started IS NULL LIMIT 1";
		$this->db->Edit($q);
		
		$this->db->SortRank('planet_production_queue', 'rank', 'id', "WHERE planet_id='" . $this->db->esc($planet_id) . "'");
	}
	
	
	
	public function QueueProductionReorder($ruler_id, $planet_id, $hashes) {
		$currentQueue = $this->LoadProductionQueue($ruler_id, $planet_id);
		$i            = 1;
		
		if ($currentQueue[0]['started']) {
			$i = 2;
		}
		
		foreach ($hashes as $hash) {
			foreach ($currentQueue as $queue) {
				if ($hash == $queue['hash']) {
					$queue['rank'] = $i;
					$q             = "UPDATE planet_production_queue SET rank='" . $this->db->esc($queue['rank']) . "' WHERE id='" . $queue['id'] . "' LIMIT 1";
					$this->db->Edit($q);
					$i++;
					continue 2;
				}
			}
			
		}
	}
	
	
	public function VaryResource($planet_id, $resource_id, $qty) {
		$q = "SELECT * FROM planet_has_resource WHERE planet_id='" . $this->db->esc($planet_id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q, false, false, false)) {
			$r[0]['stored'] += $qty;
			return $this->db->QuickEdit('planet_has_resource', $r[0]);
		} else {
			$arr = array(
				'planet_id' => $planet_id,
				'resource_id' => $resource_id,
				'stored' => $qty
			);
			return $this->db->QuickInsert('planet_has_resource', $arr);
		}
	}
	
	
	public function SetResource($planet_id, $resource_id, $qty) {
		$q = "SELECT * FROM planet_has_resource WHERE planet_id='" . $this->db->esc($planet_id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q, false, false, false)) {
			$r[0]['stored'] = $qty;
			return $this->db->QuickEdit('planet_has_resource', $r[0]);
		} else {
			$arr = array(
				'planet_id' => $planet_id,
				'resource_id' => $resource_id,
				'stored' => $qty
			);
			return $this->db->QuickInsert('planet_has_resource', $arr);
		}
	}

	public function VaryStorage($planet_id, $resource_id, $qty) {
		$q = "SELECT * FROM planet_has_resource WHERE planet_id='" . $this->db->esc($planet_id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q, false, false, false)) {
			$r[0]['storage'] += $qty;
			return $this->db->QuickEdit('planet_has_resource', $r[0]);
		} else {
			$arr = array(
				'planet_id' => $planet_id,
				'resource_id' => $resource_id,
				'storage' => $qty
			);
			return $this->db->QuickInsert('planet_has_resource', $arr);
		}
	}
	
	public function SetStorage($planet_id, $resource_id, $qty) {
		$q = "SELECT * FROM planet_has_resource WHERE planet_id='" . $this->db->esc($planet_id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q, false, false, false)) {
			$r[0]['storage'] = $qty;
			return $this->db->QuickEdit('planet_has_resource', $r[0]);
		} else {
			$arr = array(
				'planet_id' => $planet_id,
				'resource_id' => $resource_id,
				'storage' => $qty
			);
			return $this->db->QuickInsert('planet_has_resource', $arr);
		}
	}

	public function VaryOutput($planet_id, $resource_id, $qty) {
		$q = "SELECT * FROM planet_has_resource WHERE planet_id='" . $this->db->esc($planet_id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q, false, false, false)) {
			$r[0]['output'] += $qty;
			return $this->db->QuickEdit('planet_has_resource', $r[0]);
		} else {
			$arr = array(
				'planet_id' => $planet_id,
				'resource_id' => $resource_id,
				'output' => $qty
			);
			return $this->db->QuickInsert('planet_has_resource', $arr);
		}
	}
	
	// Function not yet used
	public function SetOutput($planet_id, $resource_id, $qty) {
		$q = "SELECT * FROM planet_has_resource WHERE planet_id='" . $this->db->esc($planet_id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q, false, false, false)) {
			$r[0]['output'] = $qty;
			return $this->db->QuickEdit('planet_has_resource', $r[0]);
		} else {
			$arr = array(
				'planet_id' => $planet_id,
				'resource_id' => $resource_id,
				'output' => $qty
			);
			return $this->db->QuickInsert('planet_has_resource', $arr);
		}
	}


	// Function not yet used
	public function VaryAbundance($planet_id, $resource_id, $qty) {
		$q = "SELECT * FROM planet_has_resource WHERE planet_id='" . $this->db->esc($planet_id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q, false, false, false)) {
			$r[0]['abundance'] += $qty;
			return $this->db->QuickEdit('planet_has_resource', $r[0]);
		} else {
			$arr = array(
				'planet_id' => $planet_id,
				'resource_id' => $resource_id,
				'abundance' => $qty
			);
			return $this->db->QuickInsert('planet_has_resource', $arr);
		}
	}
	
	// Function not yet used
	public function SetAbundance($planet_id, $resource_id, $qty) {
		$q = "SELECT * FROM planet_has_resource WHERE planet_id='" . $this->db->esc($planet_id) . "'
				AND resource_id='" . $this->db->esc($resource_id) . "'";
		if ($r = $this->db->Select($q, false, false, false)) {
			$r[0]['abundance'] = $qty;
			return $this->db->QuickEdit('planet_has_resource', $r[0]);
		} else {
			$arr = array(
				'planet_id' => $planet_id,
				'resource_id' => $resource_id,
				'abundance' => $qty
			);
			return $this->db->QuickInsert('planet_has_resource', $arr);
		}
	}
	
	
	public function Colonise($ruler_id, $planet_id, $planet_name, $fleet_id){
		$q = "UPDATE planet SET ruler_id = '".$this->db->esc($ruler_id)."',
								name = '" . $this->db->esc($planet_name) . "'
				WHERE id='".$this->db->esc($planet_id)."' LIMIT 1";
		$this->db->Edit($q);
		
		
		$q = "SELECT * FROM planet_colo_building";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				$arr = array(
					'planet_id' => $planet_id,
					'building_id' => $row['building_id'],
					'qty' => $row['qty']
				);
			
				$this->db->QuickInsert('planet_has_building', $arr);
			}
		}

		$q = "SELECT * FROM planet_colo_resource";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
			
				$q = "SELECT * FROM planet_has_resource WHERE resource_id='" . $this->db->esc($row['resource_id']) . "'
						AND planet_id='" . $this->db->esc($planet_id) . "'";
				if ($this->db->Select($q)){
					$q = "UPDATE planet_has_resource SET
								stored='" . $this->db->esc($row['stored']) . "',
								storage='" . $this->CalcStorage($planet_id, $row['resource_id']) . "'
								WHERE resource_id='" . $this->db->esc($row['resource_id']) . "'
								AND planet_id='" . $this->db->esc($planet_id) . "' LIMIT 1";
					$this->db->Edit($q);
				}else{
					$q = "INSERT INTO planet_has_resource SET
								stored='" . $this->db->esc($row['stored']) . "',
								abundance='" . $this->db->esc($row['abundance']) . "',
								storage='" . $this->CalcStorage($planet_id, $row['resource_id']) . "',
								resource_id='" . $this->db->esc($row['resource_id']) . "',
								planet_id='" . $this->db->esc($planet_id) . "'";
					$this->db->Insert($q);
				}
			}
		}
		
				
		if (!$this->Fleet){
			$this->Fleet = new Fleet($this->db);
		}
		
		$produced = $this->Fleet->LoadProduced($fleet_id);
		foreach ($produced as $p){
			if ($p['can_colonise']){
				$q = "SELECT * FROM fleet_has_production
						WHERE fleet_id='" . $this->db->esc($fleet_id) . "'
						AND production_id='" . $this->db->esc($p['id']) . "'";
				if ($r = $this->db->Select($q)){
					if ($r[0]['qty'] > 1){
						$r[0]['qty'] -= 1;
						$this->db->QuickEdit('fleet_has_production', $r[0]);
					}else{
						$q = "DELETE FROM fleet_has_production
								WHERE fleet_id='" . $this->db->esc($fleet_id) . "'
								AND production_id='" . $this->db->esc($p['id']) . "'";
						$this->db->Edit($q);
					}
				}	
			}
		}
		
		$this->ResetOutputsCache($planet_id);
		
		return $planet_id;	
		
	}



	public function LoadNextPlanet ($ruler_id, $planet_id){
		$q = "SELECT id FROM planet
				WHERE ruler_id='" . $this->db->esc($ruler_id) . "'
				AND id > '" . $planet_id . "'
				ORDER BY id ASC
				LIMIT 1";
		if ($r = $this->db->Select($q)){
			return $r[0]['id'];
		}
		return false;
	}



	public function LoadPreviousPlanet ($ruler_id, $planet_id){
		$q = "SELECT id FROM planet
				WHERE ruler_id='" . $this->db->esc($ruler_id) . "'
				AND id < '" . $planet_id . "'
				ORDER BY id DESC
				LIMIT 1";
		if ($r = $this->db->Select($q)){
			return $r[0]['id'];
		}
		return false;
	}


	public function ResetOutputsCache($planet_id) {
		if ($outputs = $this->CalcPlanetResources($planet_id, false)){
			foreach ($outputs as $resource => $res){
				if (!$res['global']){
					$this->SetStorage($planet_id, $res['id'], $res['storage']);
					$this->SetOutput($planet_id, $res['id'], $res['output']);
					$this->SetAbundance($planet_id, $res['id'], $res['abundance']);
				}
			}
		}
	}






	
	
}
?>
