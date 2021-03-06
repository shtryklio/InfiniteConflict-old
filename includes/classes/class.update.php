<?

class Update { 
	var $config;
	var $resources = array();
	var $pagestart;
	
	function __construct(){
		$this->db = db::getInstance();
		$this->IC = IC::getInstance();
		#$this->db->useCache = false;
		#$this->db->cacheQueries = false;
		$this->config = IC::getInstance()->LoadConfig();
		
		$this->Ruler = Ruler::getInstance();
		$this->Research = Research::getInstance();
		$this->Planet = Planet::getInstance();
		$this->Fleet = Fleet::getInstance();
	}
	
	
	// Main method to process update	
	public function process(){
		$updateStart = time_tracker();
		$this->pageStart = time_tracker();
		$this->SetUpdate();
		echo 'SetUpdate() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->pageStart = time_tracker();
		$this->ResearchQueues();
		echo 'ResearchQueues() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->pageStart = time_tracker();
		$this->BuildingQueues();
		echo 'BuildingQueues() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->pageStart = time_tracker();
		$this->ProductionQueues();
		echo 'ProductionQueues() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->pageStart = time_tracker();
		$this->ConversionQueues();
		echo 'ConversionQueues() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->pageStart = time_tracker();
		$this->FleetQueues();
		echo 'FleetQueues() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->pageStart = time_tracker();
		$this->LocalInterest();
		echo 'LocalInterest() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->pageStart = time_tracker();
		$this->GlobalInterest();
		echo 'GlobalInterest() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->pageStart = time_tracker();
		$this->LocalOutputs();
		echo 'LocalOutputs() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->pageStart = time_tracker();
		$this->GlobalOutputs();	
		echo 'GlobalOutputs() -> ' . time_tracker($this->pageStart) . " " . time_tracker($updateStart) . "\n";
		
		$this->EndUpdate();
		return true;
	}
	
	
	private function ResearchQueues(){
		
		// Queues about to start
		$q = "SELECT rq.id, rq.ruler_id, rq.research_id, r.turns FROM ruler_research_queue AS rq
						LEFT JOIN research AS r ON rq.research_id = r.id
						WHERE rq.started IS NULL
						AND rq.turns IS NULL
						AND rank=1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				if ($this->Research->ResearchIsAvailable($row['ruler_id'], $row['research_id'], false)){
					$afford = true;
					if ($resources = $this->Research->LoadResearchResources($row['research_id'])){
						foreach ($resources as $res){
							if ($res['cost'] > $this->Ruler->LoadRulerResource($row['ruler_id'], $res['resource_id'])){
								$afford = false;
							}
						}
					}
					
					if ($afford === true){
						if ($resources){
							foreach ($resources as $res){
								$this->Ruler->VaryResource($row['ruler_id'], $res['resource_id'], -$res['cost']);
								$newrow = array(
									'id' => $row['id'],
									'started' => 1,
									'turns' => $row['turns']
								);
								$this->db->QuickEdit('ruler_research_queue', $newrow);
							}
						}
					}
				}
			}
		}
		

		// Already Started Queues
		$q = "UPDATE ruler_research_queue SET turns = turns-1
						WHERE started=1
						AND turns IS NOT NULL";
		$this->db->Edit($q);
		

		// Queues about to finish
		$q = "SELECT * FROM ruler_research_queue
						WHERE started =1
						AND turns=0
						AND rank=1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				$arr = array(
					'ruler_id' => $row['ruler_id'],
					'research_id' => $row['research_id']
				);
				$this->db->QuickInsert('ruler_has_research', $arr);
				$this->db->QuickDelete('ruler_research_queue', $row['id']);
				$this->db->SortRank('ruler_research_queue', 'rank', 'id', "WHERE ruler_id='" . $this->db->esc($row['ruler_id']) . "'");
			}
		}		

	}
	
	
	private function BuildingQueues(){

		// Queues about to start
		$q = "SELECT * FROM planet_building_queue
						WHERE started IS NULL
						AND rank=1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){

				$afford = true;
				if ($resources = $this->Planet->LoadBuildingResources($row['building_id'])){
					$output = $this->Planet->CalcPlanetResources($row['planet_id'], false);
					
					foreach ($resources as $res){
					
						foreach($output as $k => $res2){
							if ($res['resource_id'] == $res2['id']){
								if ($res['cost'] > $res2['stored'] && !$row['demolish']){
									$afford = false;
								}
								
								if ($res['cost'] > $res2['stored'] - $res2['busy'] && !$row['demolish']){
									$afford = false;
								}
							}
						}
						
						if ($res['output'] != 0){
							foreach ($output as $o){
								if ($o['id'] == $res['resource_id']){
									if ($o['output'] + $res['output'] < 0 && $res['output'] < 0 && !$row['demolish']){
										$afford = false;
									}
									if ($o['output'] - $res['output'] < 0 && $res['output'] > 0 && $row['demolish']){
										$afford = false;
									}
								}
							}
						}
						
						
						if ($res['stores'] > 0 && $row['demolish']){
							foreach ($output as $o){
								if ($o['id'] == $res['resource_id'] && $o['req_storage']){
									if ($o['stored'] > $o['storage'] - $res['stores'] && $o['stored'] > 0){
										$afford = false;
									}
								}
							}
						}
					}
				}
				
				if ($afford === true){
					foreach ($resources as $res){
						if (!$res['refund'] && !$row['demolish']){
							$this->Planet->VaryResource($row['planet_id'], $res['resource_id'], -$res['cost']);
						}
					}
					
					$newrow = array(
						'id' => $row['id'],
						'started' => 1,
					 	'turns' => $row['turns']
					);
					$this->db->QuickEdit('planet_building_queue', $newrow);
					
				}
				
			}
		}		
		
				
		// Already Started Queues
		$q = "UPDATE planet_building_queue SET turns = turns-1
						WHERE started=1
						AND turns IS NOT NULL";
		$this->db->Edit($q);


		// Queues about to finish
		$q = "SELECT * FROM planet_building_queue
						WHERE started=1
						AND turns<=0
						AND rank=1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				
				$q = "SELECT pb.*, p.ruler_id FROM planet_has_building AS pb
								LEFT JOIN planet AS p ON pb.planet_id = p.id
								LEFT JOIN ruler AS r ON r.id = p.ruler_id
								WHERE pb.planet_id='" . $this->db->esc($row['planet_id']) . "'
								AND pb.building_id='" . $this->db->esc($row['building_id']) . "'";
				if ($r2 = $this->db->Select($q)){
								
					if ($row['demolish'] == 0){
						$r2[0]['qty'] += 1;
					}else{
						$r2[0]['qty'] -= 1;
					}
					
					$out = $r2[0];
					unset($out['ruler_id']);
										
					$this->db->QuickEdit('planet_has_building', $out);
				}else{
					if ($row['demolish'] == 0){
						$arr = array(
							'planet_id' => $row['planet_id'],
							'building_id' => $row['building_id'],
							'qty' => 1
						);
						$this->db->QuickInsert('planet_has_building', $arr);	
					}			
				}
				
				if ($row['demolish'] == 1){
					$q = "DELETE FROM planet_has_building WHERE qty<=0";
					$this->db->Edit($q);
				}
				
				if ($resources = $this->Planet->LoadBuildingResources($row['building_id'])){
					foreach ($resources as $res){
						if ($res['single_output']){
							if ($this->IC->ResourceIsGlobal($res['resource_id'])){
								if ($row['demolish'] == 1){
									$this->Ruler->VaryResource($row['ruler_id'], $res['resource_id'], -$res['single_output']);
								}else{
									$this->Ruler->VaryResource($row['ruler_id'], $res['resource_id'], $res['single_output']);
								}						
							}else{
								if ($row['demolish'] == 1){
									$this->Planet->VaryResource($row['planet_id'], $res['resource_id'], -$res['single_output']);
								}else{
									$this->Planet->VaryResource($row['planet_id'], $res['resource_id'], $res['single_output']);
								}
							}
						}
						
						if ($res['output'] && !$this->IC->ResourceIsGlobal($res['resource_id'])){
							if ($row['demolish'] == 1){
								$this->Planet->VaryOutput($row['planet_id'], $res['resource_id'], -$res['output']);							
							}else{
								$this->Planet->VaryOutput($row['planet_id'], $res['resource_id'], $res['output']);
							}
						}

						if ($res['stores'] && !$this->IC->ResourceIsGlobal($res['resource_id'])){
							if ($row['demolish'] == 1){
								$this->Planet->VaryStorage($row['planet_id'], $res['resource_id'], -$res['stores']);							
							}else{
								$this->Planet->VaryStorage($row['planet_id'], $res['resource_id'], $res['stores']);
							}
						}

						if ($res['abundance'] && !$this->IC->ResourceIsGlobal($res['resource_id'])){
							if ($row['demolish'] == 1){
								$this->Planet->VaryAbundance($row['planet_id'], $res['resource_id'], -$res['abundance']);							
							}else{
								$this->Planet->VaryAbundance($row['planet_id'], $res['resource_id'], $res['abundance']);
							}
						}
						
						if ($row['demolish'] && $res['cost']){
							if ($this->IC->ResourceIsGlobal($res['resource_id'])){
								$this->Ruler->VaryResource($row['ruler_id'], $res['resource_id'], $res['cost']);
							}else{
								$this->Planet->VaryResource($row['planet_id'], $res['resource_id'], $res['cost']);
							}
						}
						
						
					}
				}

				$this->db->QuickDelete('planet_building_queue', $row['id']);
				$this->db->SortRank('planet_building_queue', 'rank', 'id', "WHERE planet_id='" . $this->db->esc($row['planet_id']) . "'");
			}
		}	
		
		
	}
	
	
	private function ProductionQueues(){

  		// Queues about to start
		$q = "SELECT * FROM planet_production_queue
						WHERE started IS NULL
						AND rank=1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				// Work out max
				
				$max = $row['qty'];
				
				if ($productionResources = $this->Planet->LoadProductionResources($row['production_id'])){				
					if ($planetResources = $this->Planet->CalcPlanetResources($row['planet_id'], false)){
						
						foreach ($productionResources as $res1){
							foreach ($planetResources as $k => $res2){
								if ($res1['resource_id'] == $res2['id'] && $res1['cost']){

									$newmax = floor(($res2['stored'] - $res2['busy']) / $res1['cost']);
									if ($newmax < 0){
										$newmax = 0;
									}
									
									if ($newmax < $max){
										$max = $newmax;
									}
								}
							}
						}
					}
				}
				
				if ($max > 0){
					foreach ($productionResources as $res1){
						if (!$res1['refund']){
							$this->Planet->VaryResource($row['planet_id'], $res1['resource_id'], -$max * $res1['cost']);
						}
					}
					$row['started'] = 1;
					$row['qty'] = $max;
					$this->db->QuickEdit('planet_production_queue', $row);
				}				
			}
		}	

		// Already Started Queues
		$q = "UPDATE planet_production_queue SET turns = turns-1
						WHERE started=1
						AND turns IS NOT NULL";
		$this->db->Edit($q);

		// Queues about to finish
		$q = "SELECT * FROM planet_production_queue
						WHERE started=1
						AND turns<=0
						AND rank=1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				
				$q = "SELECT * FROM planet_has_production WHERE production_id='" . $this->db->esc($row['production_id']) . "' AND planet_id='" . $row['planet_id'] . "'";
				if ($res = $this->db->Select($q)){
					$q = "UPDATE planet_has_production SET qty = qty + " . $this->db->esc($row['qty']) . "
									WHERE production_id='" . $this->db->esc($row['production_id']) . "'
									AND planet_id='" . $row['planet_id'] . "'";
					$this->db->Edit($q);	
				}else{
					$res = array(
						'planet_id' => $row['planet_id'],
						'production_id' => $row['production_id'],
						'qty' => $row['qty']
					);
										
					$this->db->QuickInsert('planet_has_production', $res);
				}
				
				$this->db->QuickDelete('planet_production_queue', $row['id']);
			  $this->db->SortRank('planet_production_queue', 'rank', 'id', "WHERE planet_id='" . $this->db->esc($row['planet_id']) . "'");
				
			}
		}
		
	}
	
	
	private function ConversionQueues(){

  	// Queues about to start
		$q = "SELECT * FROM planet_conversion_queue
						WHERE started IS NULL
						AND rank=1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				// Work out max
				
				$max = $row['qty'];
				
				if ($conversionResources = $this->Planet->LoadConversionResources($row['resource_id'])){
					if ($planetResources = $this->Planet->CalcPlanetResources($row['planet_id'], false)){
												
						foreach ($conversionResources as $res1){
							foreach ($planetResources as $k => $res2){
								if ($res1['cost_resource'] == $res2['id'] && $res1['cost']){

									$newmax = floor(($res2['stored'] - $res2['busy']) / $res1['cost']);
									if ($newmax < 0){
										$newmax = 0;
									}
									
									if ($newmax < $max){
										$max = $newmax;
									}
								}
								
								if ($res1['resource_id'] == $res2['id']){
									if ($max > 0 && $res2['req_storage']){
										$space = $res2['storage'] - $res2['stored'];
										if ($space < $max){
											$max = $space;
										}
									}
								}
							}
						}
					}
				}
				
				if ($max > 0){
					foreach ($conversionResources as $res1){
						if (!$res1['refund']){
							$this->Planet->VaryResource($row['planet_id'], $res1['cost_resource'], -$max * $res1['cost']);
						}
					}
					$row['started'] = 1;
					$row['qty'] = $max;
					$this->db->QuickEdit('planet_conversion_queue', $row);
				}				
			}
		}	

		// Already Started Queues
		$q = "UPDATE planet_conversion_queue SET turns = turns-1
						WHERE started=1
						AND turns IS NOT NULL";
		$this->db->Edit($q);

		// Queues about to finish
		$q = "SELECT * FROM planet_conversion_queue
						WHERE started=1
						AND turns<=0
						AND rank=1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				
				$q = "SELECT * FROM planet_has_resource WHERE resource_id='" . $this->db->esc($row['resource_id']) . "' AND planet_id='" . $row['planet_id'] . "'";
				if ($res = $this->db->Select($q)){
					$q = "UPDATE planet_has_resource SET stored = stored + " . $this->db->esc($row['qty']) . "
									WHERE resource_id='" . $this->db->esc($row['resource_id']) . "'
									AND planet_id='" . $row['planet_id'] . "'";
					$this->db->Edit($q);	
				}else{
					$res = array(
						'planet_id' => $row['planet_id'],
						'resource_id' => $row['resource_id'],
						'stored' => $row['qty'],
						'abundance' => 0
					);
										
					$this->db->QuickInsert('planet_has_resource', $res);
				}
				
				$this->db->QuickDelete('planet_conversion_queue', $row['id']);
			    $this->db->SortRank('planet_conversion_queue', 'rank', 'id', "WHERE planet_id='" . $this->db->esc($row['planet_id']) . "'");
				
			}
		}
		
	}
	
	
	private function FleetQueues(){
	
		// Clear empty fleets
		$q = "DELETE FROM fleet WHERE id NOT IN (SELECT DISTINCT fleet_id FROM fleet_has_production)";
		$this->db->Edit($q);
	
		// Advance all queues started
		//$q = "UDPATE fleet_queue SET turns=turns-1 WHERE turns > 0 AND rank=1";
		//$this->db->Edit($q);
		
	
		// Find all fleets with orders
		$q = "SELECT DISTINCT fleet_id FROM fleet_queue";
		if ($fleets = $this->db->Select($q)){
			foreach ($fleets as $f){

				$repeats = array();
				
				$moved = false;
				$newpos = false;

				// Loop through fleet until we find something with orders
				$q = "SELECT fq.*, p.home, f.planet_id AS pos, p.ruler_id AS planet_owner, f.ruler_id AS fleet_owner FROM fleet_queue AS fq
						LEFT JOIN fleet AS f ON fq.fleet_id = f.id
						LEFT JOIN planet AS p ON fq.planet_id = p.id
						WHERE fq.fleet_id='" . $f['fleet_id'] . "' ORDER BY rank ASC";
				if ($queue = $this->db->Select($q)){
					foreach ($queue as $row){
					
						// we've just arrived at a new planet
						if ($newpos){
							$row['pos'] = $newpos;
						}
						
						switch($row['type']){
							 case 'load':
							 		if ($this->Planet->RulerOwnsPlanet($row['fleet_owner'], $row['pos'])){
								 		if ($row['resource_id']){
									 		$this->Fleet->PlanetToFleetResource($row['pos'], $row['fleet_id'], $row['resource_id'], $row['qty']);
								 		}
								 		if ($row['production_id']){
								 			$this->Fleet->PlanetToFleetProduction($row['pos'], $row['fleet_id'], $row['production_id'], $row['qty']);
								 		}
							 		}
							 		
							 		$this->db->QuickDelete('fleet_queue', $row['id']);
							 		
							 		if ($row['repeat']){
							 			$repeats[] = $row;
							 		}
							 	break;
							 		
							 case 'unload':
							 		if ($this->Planet->RulerOwnsPlanet($row['fleet_owner'], $row['pos'])){
							 			if ($row['resource_id']){
							 				$this->Fleet->FleetToPlanetResource($row['fleet_id'], $row['pos'], $row['resource_id'], $row['qty']);
							 			}
							 			if ($row['production_id']){
							 				$this->Fleet->FleetToPlanetProduction($row['fleet_id'], $row['pos'], $row['production_id'], $row['qty']);
							 			}
							 		}
							 		
							 		$this->db->QuickDelete('fleet_queue', $row['id']);
							 		
							 		if ($row['repeat']){
							 			$repeats[] = $row;
							 		}
							 	break;
							 		
							 case 'unloadall':
							 		if ($this->Planet->RulerOwnsPlanet($row['fleet_owner'], $row['pos'])){
							 			if ($res = $this->Fleet->LoadFleetResources($row['fleet_id'])){
							 				foreach ($res as $r){
							 					if ($r['transferable']){
							 						$this->Fleet->FleetToPlanetResource($row['fleet_id'], $row['pos'], $r['resource_id'], $r['stored']);
							 					}
							 				}
							 			}
							 		}
							 		
									$this->db->QuickDelete('fleet_queue', $row['id']);							 		
							 		
							 		if ($row['repeat']){
							 			$repeats[] = $row;
							 		}
							 	break;
							 	
							 case 'wait':
							 
								 	if ($moved){
								 		break 2;
								 	}
							 

									$row['turns'] -= 1;
									if ($row['turns'] > 0){
										unset($row['pos']);
										unset($row['home']);
										unset($row['fleet_owner']);
										unset($row['planet_owner']);
										$this->db->QuickEdit('fleet_queue', $row);
									}else{
										$this->db->QuickDelete('fleet_queue', $row['id']);
										if ($row['repeat']){
											$repeats[] = $row;
										}	
									}
									$moved = true;
									break 2;
									
							 	break;
							 		
							 case 'move':
							 		if ($row['home']==1 && $row['planet_owner'] != $row['fleet_owner']){
							 			break 2;
							 		}
							 		
							 		if ($moved){
							 			break 2;
							 		}
							 		
							 		
							 		if (!$row['started']){
							 		
								 		$row['turns'] = $this->Fleet->TravelTime($row['fleet_id'], $row['pos'], $row['planet_id']) - 1;
								 		
								 		unset($row['pos']);
								 		unset($row['home']);
								 		unset($row['fleet_owner']);
								 		unset($row['planet_owner']);
								 		$row['started']=1;
								 		$this->db->QuickEdit('fleet_queue', $row);
								 		
								 		$q = "UPDATE fleet SET moving=1, planet_id=NULL WHERE id='".$this->db->esc($f['fleet_id'])."'";
								 		$this->db->Edit($q);
								 		$moved = true;
							 			break 2;				 		
								 		
							 		}else{

										$row['turns'] -= 1;
										if ($row['turns'] > 0){

											unset($row['pos']);
											unset($row['home']);
											unset($row['fleet_owner']);
											unset($row['planet_owner']);

											$this->db->QuickEdit('fleet_queue', $row);
											$moved = true;
							 				break 2;				 		
										}else{
										
											$newpos = $row['planet_id'];
										
											$q = "UPDATE fleet SET moving=NULL, planet_id='".$this->db->esc($row['planet_id'])."' WHERE id='".$this->db->esc($row['fleet_id'])."'";
											$this->db->Edit($q);
											$this->db->QuickDelete('fleet_queue', $row['id']);
											
											if ($row['repeat']){
												$repeats[] = $row;
											}
											
											$moved = true;
											
										}
							 			
							 		}
							 	break;
						}
						
					}
					
				}
				
				$this->db->SortRank('fleet_queue', 'rank', 'id', "WHERE fleet_id='" . $this->db->esc($f['fleet_id']) . "'");
								
				if ($repeats){
					foreach ($repeats as $rep){
						unset($rep['pos']);
						unset($rep['home']);
						unset($rep['fleet_owner']);
						unset($rep['planet_owner']);
						if ($rep['type'] != 'wait'){
							unset($rep['turns']);
						}else{
							$rep['turns'] = $rep['qty'];
						}
						unset($rep['started']);
						$rep['rank'] = $this->db->NextRank('fleet_queue', 'rank', "WHERE fleet_id='" . $this->db->esc($f['fleet_id']) . "'");	
						$this->db->QuickInsert('fleet_queue', $rep);	
					}
				}				
				
			}	
		}
		
	
	}
	

	private function LocalInterest(){
		if ($res = $this->IC->LoadResources()){
			foreach ($res as $r){
				if ($r['interest'] != 0 && $r['global'] == 0){
					
					$q = "SELECT pb.planet_id, ROUND(SUM(interest),3) AS interest FROM planet_has_resource AS pr
						LEFT JOIN planet_has_building AS pb ON pr.planet_id = pb.planet_id
						LEFT JOIN building_has_resource AS br ON pr.resource_id = br.resource_id AND br.building_id = pb.building_id
						WHERE pr.resource_id='".$this->db->esc($r['id'])."'
						AND interest > 0
						GROUP BY pb.planet_id";
					if ($r2 = $this->db->Select($q)){
						foreach ($r2 as $row){
							$q = "UPDATE planet_has_resource SET stored = stored * (1+" . $this->db->esc($row['interest']) . ")
											WHERE resource_id='" . $this->db->esc($r['id']) . "'
											AND planet_id='".$row['planet_id']."'";
							$this->db->Edit($q);

						}
					}
					
					$this->db->Edit($q);
				}
			}
			
			
			// The following code block resets stored resources back to max storage value, if for some reason we gave exceeded it.
			$q = "SELECT table1.*, SUM(total_stores) as total, pr.stored FROM
							(
								SELECT planet_id, pb.building_id, pb.qty, br.resource_id, stores, pb.qty * stores AS total_stores FROM planet_has_building AS pb
								LEFT JOIN building AS b ON pb.building_id = b.id
								JOIN building_has_resource AS br ON b.id = br.building_id
								WHERE stores > 0
							) AS table1
							
							LEFT JOIN planet_has_resource AS pr ON table1.planet_id = pr.planet_id AND table1.resource_id = pr.resource_id
							
							GROUP BY table1.planet_id, table1.resource_id
							HAVING stored > total";
			if ($r = $this->db->Select($q)){
				foreach ($r as $row){
					if (!$this->IC->ResourceIsGlobal($row['resource_id'])){
						$this->Planet->SetResource($row['planet_id'], $row['resource_id'], $row['total']);
						//echo "Setting resource ".$row['resource_id']." from ".$row['stored']." to ".$row['total']." on PID: " . $row['planet_id'] . "\n";
					}
				}
			}
		}
	}

	
	private function GlobalInterest(){
		$res = $this->IC->LoadResources();
		foreach ($res as $r){
			if ($r['interest'] != 0 && $r['global'] == 1){
				$q = "UPDATE ruler_has_resource SET qty = qty * (1+" . $this->db->esc($r['interest']) . ") WHERE resource_id='" . $this->db->esc($r['id']) . "'";
				$this->db->Edit($q);
			}
		}		
	}


	private function LocalOutputs(){
	
		//$q = "SELECT * FROM planet WHERE ruler_id IS NOT NULL";
		//if ($r = $this->db->Select($q)){
		//	foreach ($r as $row){
		//		$this->Planet->ResetOutputsCache($row['id']);
		//	}
		//}
		
		// Standard outputs
		$q = "UPDATE planet_has_resource SET stored = stored + output";
		$this->db->Edit($q);

		// Reset minus resources to 0
		$q = "UPDATE planet_has_resource SET stored = 0 WHERE stored < 0";
		$this->db->Edit($q);
		
		// Reset resources over storage
		$q = "UPDATE planet_has_resource SET stored = storage
				WHERE stored > storage
				AND resource_id IN (SELECT id FROM resource WHERE req_storage = 1)";
		$this->db->Edit($q);
		
		// Loop through any resources which have taxes, to update values where needed (i.e. reset food output after pop growth)
		$q = "SELECT pr.*, resource_tax.output_resource FROM planet_has_resource AS pr
				JOIN resource_tax ON resource_tax.resource_id = pr.resource_id
				LEFT JOIN resource ON resource.id = resource_tax.output_resource
				WHERE stored > 0
				AND resource.global <> 1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				$output = $this->Planet->CalcOutput($row['planet_id'], $row['resource_id'], true);
				if ($output != $row['output']){
					$this->Planet->SetOutput($row['planet_id'], $row['resource_id'], $output);
				}
			}
		}
		
	}

	
	private function GlobalOutputs(){
		$q = "SELECT pr.*, planet.ruler_id FROM planet_has_resource AS pr
					LEFT JOIN resource ON pr.resource_id = resource.id
					LEFT JOIN planet ON pr.planet_id = planet.id
					WHERE output > 0
					AND resource.global = 1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				$this->Ruler->VaryResource($row['ruler_id'], $res['resource_id'], $res['output']);				
			}
		}
		
		
		$q = "SELECT pr.*, resource_tax.output_resource, resource_tax.rate, planet.ruler_id FROM planet_has_resource AS pr
				JOIN resource_tax ON resource_tax.resource_id = pr.resource_id
				LEFT JOIN planet ON pr.planet_id = planet.id				
				LEFT JOIN resource ON resource.id = resource_tax.output_resource
				WHERE stored > 0
				AND resource.global = 1";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				$this->Ruler->VaryResource($row['ruler_id'], $row['output_resource'], $row['stored'] * $row['rate']);
			}
		}		
		
		$q = "SELECT * FROM ruler_has_resource AS rr
				LEFT JOIN resource ON resource.id = rr.resource_id
				WHERE resource.global = 1
				AND rr.resource_id IN (
					SELECT resource_id FROM resource_tax
					LEFT JOIN resource ON resource.id = resource_tax.output_resource
					WHERE resource.global = 1
				)";
		if ($r = $this->db->Select($q)){
			foreach ($r as $row){
				if ($taxes = $this->LoadResourceTaxes($row['resource_id'])){
					foreach ($taxes as $tax){
						$this->Ruler->VaryResource($row['ruler_id'], $tax['output_resource'], $row['qty'] * $tax['rate']);						
					}					
				}				
			}
		}
	}	

	
	private function SetUpdate(){
		$this->config['update'] = 1;
		$q = "UPDATE config SET `val`=1 WHERE `key`='update'";
		$this->db->Edit($q);
	}

	
	private function EndUpdate(){
		$this->config['turn'] += 1;
		$this->config['update'] = 0;

		$q = "UPDATE config SET `val`='" . $this->db->esc($this->config['turn']) . "' WHERE `key`='turn'";
		$this->db->Edit($q);
		
		$q = "UPDATE config SET `val`=0 WHERE `key`='update'";
		$this->db->Edit($q);	
	}
	
	
	
	
}

?>