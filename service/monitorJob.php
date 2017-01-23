#!/usr/bin/php
<?php
set_time_limit(0);
ini_set('display_errors', 'true');
ini_set('memory_limit', '64M');

//prevent non cli access
if(php_sapi_name()!=='cli') exit();

$dir = dirname(dirname(__FILE__));
class_exists('Setup', false) or include($dir.'/classes/Setup.class.php');
class_exists('Utilities', false) or include($dir.'/classes/Utilities.class.php');
class_exists('_MySQL', false) or include($dir.'/classes/_MySQL.class.php');
class_exists('_Logging', false) or include($dir.'/classes/_Logging.class.php');

$options = getopt("h:");
$options['h'] = isset($options['h']) ? trim($options['h']) : '';

if($options['h'] == ''){
	_Logging::appLog("monitorJob called without params");
	exit();
}

function searchFunction($array, $index, $value)
{
   foreach($array as $key => $array)
   {
      if ( $array[$index] === $value )
         return $key;
   }
   return false;
}

$mysql = new _MySQL();
$mysql->connect(Setup::$connectionArray);

$rs = $mysql->runQuery("
	select *
	from monitors
	where ipDomain = '".$mysql->escape($options['h'])."'");
while($row = mysqli_fetch_array($rs)) {
	$monitor = $row;
}

// get blacklists
Utilities::setBlockLists();

if( (empty(Utilities::$domainBlacklists)===true) && (empty(Utilities::$ipBlacklists)===true) ){
	_Logging::appLog("no blacklists configured");
	exit();
}

//update monitor
$preResult = Utilities::checkBlacklists($monitor['ipDomain']);
// print_r($preResult, false);
// print_r($preResult, false);
$dex = searchFunction($preResult, 0, "rf.senderbase.org");
// echo($dex);
$senderbaseScore = 0;
if (!empty($preResult) && $dex !== FALSE) {
	// echo("INSIDE");
	 print_r($preResult[$dex][1], false);
	$senderbaseScore = floatval($preResult[$dex][1]);
	$mysql->runQuery("
		update monitors
		set
		senderbaseScore = '$senderbaseScore' 
		where ipDomain = '".$mysql->escape($monitor['ipDomain'])."'
		");
	// $dex = array_search("rf.senderbase.org", array_column($preResult, 0));
	unset($preResult[$dex]);
}
$dex = searchFunction($preResult, 0, "score.senderscore.com");
// echo($dex);
$senderScore = 0;
if (!empty($preResult) && $dex !== FALSE) {
	// echo("INSIDE");
	 print_r($preResult[$dex][1], false);
	$scoreExtract = explode('.', $preResult[$dex][1]);

	$senderScore = intval($scoreExtract[3]);
	$mysql->runQuery("
		update monitors
		set
		senderScore = '$senderScore' 
		where ipDomain = '".$mysql->escape($monitor['ipDomain'])."'
		");
	// $dex = array_search("rf.senderbase.org", array_column($preResult, 0));
	unset($preResult[$dex]);
}
$result = serialize($preResult);
$isBlocked = Utilities::$isBlocked;
$rdns = Utilities::lookupHostDNS($monitor['ipDomain']);
$ctime = date('Y-m-d H:i:s');
$mysql->runQuery("
update monitors
set
lastStatusChanged = 0,
rDNS = '".$mysql->escape($rdns)."', 
isBlocked = $isBlocked,
lastUpdate = '$ctime', 
status = '".$mysql->escape($result)."'
where ipDomain = '".$mysql->escape($monitor['ipDomain'])."'
");

if(intval($senderScore) != intval($monitor['senderScore'])) {

		$mysql->runQuery("
		insert into monitorHistory
		(monitorTime, isBlocked, ipDomain, rDNS, status, senderScore)
		values(
		'".date('Y-m-d H:i:s')."',
		0,
		'".$mysql->escape($monitor['ipDomain'])."',
		'".$mysql->escape($rdns)."',
		'SenderScore change from ".$monitor['senderScore']." to ".$senderScore."',
		".$senderScore.")");

};
if(floatval($senderbaseScore) != floatval($monitor['senderbaseScore'])) {

		$mysql->runQuery("
		insert into monitorHistory
		(monitorTime, isBlocked, ipDomain, rDNS, , status, senderbaseScore)
		values(
		'".date('Y-m-d H:i:s')."',
		0,
		'".$mysql->escape($monitor['ipDomain'])."',
		'".$mysql->escape($rdns)."',
		'SenderBaseScore change from ".$monitor['senderbaseScore']." to ".$senderbaseScore."',
		".$senderbaseScore.")");

};

// status change on this host
if(strcasecmp($result, $monitor['status']) != 0){
	//update current status
	$mysql->runQuery("
		update monitors
		set
			lastStatusChanged = 1,
			lastStatusChangeTime = '".date('Y-m-d H:i:s')."'
		where ipDomain = '".$mysql->escape($monitor['ipDomain'])."'
		");

	//log history
	$mysql->runQuery("
		insert into monitorHistory
		(monitorTime, isBlocked, ipDomain, rDNS, status)
		values(
		'".date('Y-m-d H:i:s')."',
		$isBlocked,
		'".$mysql->escape($monitor['ipDomain'])."',
		'".$mysql->escape($rdns)."',
		'".$mysql->escape($result)."')");

	//make api callback
	$user = Utilities::getAccount();
	if($user['apiCallbackURL']!=''){
		Utilities::makeAPICallback($user['apiCallbackURL'],
			$monitor['ipDomain'],
			$isBlocked,
			$rdns,
			$result
			);
		_Logging::appLog("api callback made: {$user['apiCallbackURL']}");
	}
}





