<?php

function __autoload($class_name) {
    require_once 'classes/class.' . strtolower($class_name) . '.php';
}

function fetch_url_parts() {
	if (substr($_SERVER['REQUEST_URI'], 0, 1) == '/') {
		$_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 1);
	}
	if (substr($_SERVER['REQUEST_URI'], -1) == '/') {
		$_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 0, -1);
	}
	return explode('/', $_SERVER['REQUEST_URI']);
}

function template_data() {
	global $db, $smarty, $request, $config, $IC, $Ruler, $Fleet;

	$smarty->assign('site_url', SITE_LOCATION);

	$now = time();
	$future = mktime(date('H'), date('i')+1, 0);
	$smarty->assign('time_till_update', ($future - $now));

  if ($sess = $IC->LoadSession($_COOKIE['ic_session'])){
    $_SESSION['ruler'] = $IC->LoadRuler($sess['ruler_id']);
    //$_SESSION['ruler']['avatar'] = '/images/avatar.png';
    $_SESSION['ruler']['resources'] = $Ruler->LoadRulerResources($sess['ruler_id']);
    
    $_SESSION['ruler']['avatar'] = 'http://www.gravatar.com/avatar/' . md5(strtolower($_SESSION['ruler']['email']));
    
    $_SESSION['ruler']['planets'] = $Ruler->LoadRulerPlanetCount($sess['ruler_id']);
    $_SESSION['ruler']['fleets'] = $Fleet->LoadRulerFleetCount($sess['ruler_id']);

    if (!$_SESSION['ruler']['QL']){
    	$_SESSION['ruler']['QL'] = $Ruler->LoadRulerQL($sess['ruler_id']);
    }
    if (!$_SESSION['ruler']['PL']){
    	$_SESSION['ruler']['PL'] = $Ruler->LoadRulerPL($sess['ruler_id']);
    }
  }
  

	//$smarty->assign('title', $config['meta_title']);
	//$smarty->assign('description', $config['meta_description']);
	//$smarty->assign('keywords', $config['meta_keywords']);
	
  # Breadcrumbs
  if (is_array($request) && !empty($request) && $request[0]){
    $breadcrumbs = array(0=>array('url'=>'/', 'text'=>'Home'));
    $parts = array();
    foreach ($request as $url){
      $parts[] = $url;
      if ($url != 'detail' && $url){
        $breadcrumbs[] = array('url'=>"/" . implode("/", $parts) . "/", 'text'=>ucwords(str_replace('-', ' ', urldecode($url))));
      }
    }
    $smarty->assign('breadcrumbs', $breadcrumbs);
  }

	
	if (array_key_exists('onload_message', $_SESSION)) {
		$smarty->assign('onload_message', $_SESSION['onload_message']);
		unset($_SESSION['onload_message']);
	} else {
		$smarty->assign('onload_message', false);
	}
}

function page_heirarchy($pages, $parent=0, $urlbase=''){
  global $request;
  if ($pages){
    $out = array();
    foreach ($pages as $p){
      foreach ($request as $r){
        if ($r == $p['urlname']){
          $p['active'] = true;
        }
      }
      
      $p['urlname'] = $urlbase . $p['urlname'];
      if ($p['parent'] ==  $parent){
        if ($children = page_heirarchy($pages, $p['id'], $p['urlname'] . '/')){
          $p['children'] = $children;
        }
        $out[] = $p;
      }
    }
  }
  if ($out)
    return $out;
  return false;
}

function snippet($s, $words=10){
	$s = trim(str_replace("&nbsp;"," ",(strip_tags(str_replace("<br />"," ",$s)))));
	$startstr = $s;
	$s = @explode(" ",$s);

	if ((@count($s) - 1) < $words){
		$words = @count($s) - 1;
	}
	for ($i=0; $i<=$words; $i++){
		if (trim(strlen($s[$i]))>0){
			$newstr[] = trim($s[$i]);
		}
	}

	$newstr = @implode(" ",$newstr);
    if (strlen($newstr) < strlen($startstr)){
		return $newstr.'...';
	}else{
		return $startstr;
	}
}


function error_page($code) {
	global $smarty;
	if ($code == 404) {
		header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
		$smarty->assign('error_about', "The page you're looking for could not be found.");
	}
	else if ($code == 403) {
		header($_SERVER["SERVER_PROTOCOL"]." 403 Forbidden");
		$smarty->assign('error_about', "You cannot view this content from this location.");
	}
	else if ($code == 401) {
		header($_SERVER["SERVER_PROTOCOL"]." 401 Unauthorized");
		$smarty->assign('error_about', "Please enter the correct username and password to view this page.");
	}
	$smarty->assign('error_title', $code.' Error');
	$smarty->assign('content', $smarty->fetch('error.tpl'));
}

function random_float ($min,$max) {
   return ($min+lcg_value()*(abs($max-$min)));
}

?>
