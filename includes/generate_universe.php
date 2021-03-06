<?
set_time_limit(0);

if ($_SERVER['REMOTE_ADDR']){
	die();
}

$_SERVER['DOCUMENT_ROOT'] = '..';
//$_SERVER['ENVIRONMENT'] = 'beta';

define('HARD_RESET', false); // if TRUE, all rulers and home planets will also be deleted.

#if (is_dir('/Applications/XAMPP/xamppfiles/')){
#	ini_set('mysql.default_socket', '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock');
#	ini_set('mysqli.default_socket', '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock');
#}

require_once('config.php');
$db->recordQueries = false;
$db->cacheQueries = false;
$IC = new IC($db);

ob_end_clean();


// We use this data to re-insert later
$q = "SELECT ruler.id, ruler.confirmed, ruler.name AS rulername, planet.name AS planetname FROM ruler
			RIGHT JOIN planet ON ruler.id = planet.ruler_id
			WHERE confirmed=1
			AND planet.home=1";
$oldrulers = $db->Select($q);

$emptyTables = array(
	'ruler_has_research',
	'ruler_has_resource',
	'ruler_research_queue',
	'planet_has_resource',
	'planet_has_building',
	'planet_has_production',
	'planet_conversion_queue',
	'alliance',
	'fleet',
	'session',
	'galaxy',
	'system',
	'planet'
);


if (HARD_RESET === true) {
	$emptyTables[] = 'ruler';
}else{
	$q = "UPDATE ruler SET name=''";
	$db->Query($q);
	echo 'Clearing ruler names' . "\n";
}

foreach ($emptyTables as $t){
	$q = "DELETE FROM `$t` WHERE 1";
	$db->Query($q);
	$q = "ALTER TABLE `$t` AUTO_INCREMENT = 1";
	$db->Query($q);	
	echo 'Emptying `' . $t . "`\n";
}


for ($i=1; $i<=$IC->config['gals']; $i++){
	$gal = array();
	$gal['home'] = $i % 2 ? 1 : 0;

	if ($gal['home'] == 1){
		$gal['rows'] = $IC->config['home_gal_rows'];
		$gal['cols'] = $IC->config['home_gal_cols'];
	}else{
		$gal['rows'] = $IC->config['free_gal_rows'];
		$gal['cols'] = $IC->config['free_gal_cols'];
	}
	do{
		$gal['type'] = rand(1, $IC->config['gal_types']);
	} while ($gal['type'] == $prev);
	$prev = $gal['type'];

	if ($gal['id'] = $db->QuickInsert('galaxy', $gal)){

		echo "\n";
		echo 'Creating Galaxy ' . $gal['id'] . "\n";
		echo "Creating Systems\n";

		$planets = array();

		for ($j=1; $j<=$gal['rows']; $j++){
			for ($k=1; $k<=$gal['cols']; $k++){
				$sys = array();
				$sys['galaxy_id'] = $gal['id'];
				if ($gal['home'] == 1){
					$sys['rows'] = $IC->config['home_sys_rows'];
					$sys['cols'] = $IC->config['home_sys_cols'];
				}else{
					$sys['rows'] = $IC->config['free_sys_rows'];
					$sys['cols'] = $IC->config['free_sys_cols'];
				}

				do{
					$sys['type'] = rand(1, $IC->config['sys_types']);
				} while ($sys['type'] == $prev2);
				$prev2 = $sys['type'];

				# Insert system
				if ($sys['id'] = $db->QuickInsert('system', $sys)){

					for ($l=1; $l<=$sys['rows']; $l++){
						for ($m=1; $m<=$sys['cols']; $m++){
							$planet = array();

							$planet['galaxy_id'] = $gal['id'];
							$planet['system_id'] = $sys['id'];
							if ($j <= $IC->config['home_sys_hp_rows'] && $gal['home'] == 1){
								$planet['home'] = 1;
							}else{
								$planet['home'] = 0;
							}

							$planet['type'] = rand(1, $IC->config['planet_types']);

							$planets[] = $planet;

						}
					}
				}
			}
		}

		echo "Creating " . sizeof($planets) . " planets\n";

		$db->ExtendedInsert('planet', $planets);

	}
}


/* Set resources for home planets */
$q = "SELECT * FROM planet_starting_resource";
$starting = $db->Select($q);

$q = "SELECT * FROM planet WHERE home=1";
if ($r = $db->Select($q)){
	$resources = array();
	foreach($r as $row){
		foreach ($starting as $res){
			$resources[] = array(
				'planet_id' => $row['id'],
				'resource_id' => $res['resource_id'],
				'stored' => $res['stored'],
				'abundance' => $res['abundance']
			);
		}
	}
	$db->ExtendedInsert('planet_has_resource', $resources);
}
/* END Set resources for home planets */



$q = "SELECT * FROM galaxy_starting_resource";
$starting = $db->Select($q);

/* Set resources for home gal planets */
$q = "SELECT p.* FROM planet AS p
				LEFT JOIN galaxy AS g ON p.galaxy_id = g.id
				WHERE p.home=0 AND g.home=1";
if ($r = $db->Select($q)){
	$resources = array();
	foreach($r as $row){
		foreach ($starting as $res){
			$resources[] = array(
				'planet_id' => $row['id'],
				'resource_id' => $res['resource_id'],
				'stored' => rand($res['home_min_stored'], $res['home_max_stored']),
				'abundance' => round(random_float($res['home_min_abundance'], $res['home_max_abundance']), 2)
			);
		}
	}
	$db->ExtendedInsert('planet_has_resource', $resources);
}
/* END Set resources for home gal planets */




/* Set resources for free gal planets */
$q = "SELECT p.* FROM planet AS p
				LEFT JOIN galaxy AS g ON p.galaxy_id = g.id
				WHERE p.home=0 AND g.home=0";
if ($r = $db->Select($q)){
	$resources = array();
	foreach($r as $row){
		foreach ($starting as $res){
			$resources[] = array(
				'planet_id' => $row['id'],
				'resource_id' => $res['resource_id'],
				'stored' => rand($res['free_min_stored'], $res['free_max_stored']),
				'abundance' => round(random_float($res['free_min_abundance'], $res['free_max_abundance']), 2)
			);
		}
	}
	$db->ExtendedInsert('planet_has_resource', $resources);
}
/* END Set resources for free gal planets */



$q = "UPDATE ruler SET asset_score=0, combat_score=0, asset_rank=0, combat_rank=0, leaving=NULL";
$db->Edit($q);


if ($oldrulers && HARD_RESET !== true){
	foreach ($oldrulers as $newruler){
		$IC->Ruler->SignupRuler($newruler);
	}
}

$q = "UPDATE `config` SET `val`=1 WHERE `key`='turn'";
$db->Edit($q);



#$this->smarty->assign('content', $this->parser->parse('galaxies/index.tpl', array(), true));
#$this->parser->parse('layout.tpl', array());

?>
