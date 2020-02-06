<?php
class_exists('Setup', false) or include('classes/Setup.class.php');
class_exists('Utilities', false) or include('classes/Utilities.class.php');
class_exists('_MySQL', false) or include('classes/_MySQL.class.php');

if(Utilities::isLoggedIn()===false){
	header('Location: login.php?location='.urlencode('hosts.php'));
	exit();
}
$tghost = array_key_exists('tghost', $_POST) ? $_POST['tghost'] : '';
$rmhost = array_key_exists('rmhost', $_POST) ? $_POST['rmhost'] : '';
$toggle = array_key_exists('toggle', $_POST) ? (int)$_POST['toggle'] : 0;

$titlePreFix = "Block Lists";
$message = array();

$newhost = array_key_exists('host', $_POST) ? substr(trim($_POST['host']),0,100) : '';
$newmonitorType = array_key_exists('monitorType', $_POST) ? substr($_POST['monitorType'],0,8000) : '';
$newdescription = array_key_exists('description', $_POST) ? substr($_POST['description'],0,8000) : '';
$newwebsite = array_key_exists('website', $_POST) ? substr($_POST['website'],0,8000) : '';
$newimportance = array_key_exists('importance', $_POST) && is_numeric(substr($_POST['importance'],0,1)) ? substr($_POST['importance'],0,1) : '0';

$user = Utilities::getAccount();
$mysql = new _MySQL();
$mysql->connect(Setup::$connectionArray);
// $passwd = array_key_exists('passwd', $_POST) ? substr($_POST['passwd'],0,32) : '';
if (isset($_POST["submit"])) {
	
	//TODO: make sure blacklists are domains with an ip address on them
	// if(count($message) == 0){
		//update
		$mysql->runQuery("
			insert into blockLists
			(host, monitorType, functionCall, description, website, importance, isActive)
			values(
			'".$newhost."',
			'".$newmonitorType."',
			'rbl',
			'".$newdescription."',
			'".$newwebsite."',
			".$newimportance.",
			1)");

		// $message[] = "Account updated.";
		header('Location: blockLists.php');
	// }
};



if($tghost != ''){
	if($toggle==0){
		$mysql->runQuery("
			update blockLists
			set isActive = '0'
			where md5(host) = '".$mysql->escape($tghost)."'");
	}else{
		$mysql->runQuery("
			update blockLists
			set isActive = '1'
			where md5(host) = '".$mysql->escape($tghost)."'");
	}
	exit();
}

if($rmhost != ''){
	$mysql->runQuery("
		delete from blockLists
		where md5(host) = '".$mysql->escape($rmhost)."'");
}

$sql = "
select *
from blockLists
order by isActive desc, blocksToday desc
";
$rs = $mysql->runQuery($sql);


include('header.inc.php');
include('accountSubnav.inc.php');
?>

<script src="js/jquery.tablesorter.min.js"></script>

<script>
$(document).ready(function() {
	$("#blockListTable").tablesorter();
	$(".blockListLinks").click( function(event) {
		var host = $("#"+event.target.id).data("host");
		toggleBlacklist(host);
		return false;
	});
	$(".blockListRemove").click( function(event) {
		var host = $("#"+event.target.id).data("host");
		removeBlacklist(host);
		return false;
	});
});

function toggleBlacklist(host){
	var status = $("#tg"+host).data("blstatus");
	if(status == 1) {
		status = 0;
	}else{
		status = 1;
	}
	$.post("blockLists.php", {tghost: host, toggle: status} )
		.done(function( data ) {
			if(status==1){
				$("#tg"+host).removeClass('glyphicon-remove');
				$("#tg"+host).addClass('glyphicon-ok');
			}else{
				$("#tg"+host).removeClass('glyphicon-ok');
				$("#tg"+host).addClass('glyphicon-remove');
			}
			$("#tg"+host).data("blstatus", status);
		});
}

function removeBlacklist(host){
	$.post("blockLists.php", {rmhost: host, remove: "1"} )
		.done(function( data ) {
			$("#tg"+host).closest('tr').remove();
		});
}
</script>

<div class="panel panel-default">
	<div class="panel-body">
		<a class="glyphicon glyphicon-ok"></a> - Enabled<br>
		<a class="glyphicon glyphicon-remove"></a> - Disabled<br>
	</div>
</div>
<?php
foreach($message as $m){
	echo("<div class=\"alert alert-info alert-dismissable\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-hidden=\"true\">&times;</button>$m</div>");
}
?>
<div class="table-responsive">
	<table id="blockListTable" class="tablesorter table table-bordered table-striped">
		<thead>
			<tr>
				<th>Opt</th>
				<th>Status</th>
				<th>Blacklist</th>
				<th>Type</th>
				<th>Description</th>
				<th>Importance</th>
				<th>Blocks Today</th>
				<th>Clean Today</th>
				<th>Blocks Yest</th>
				<th>Clean Yest</th>
			</tr>
		</thead>
		<tbody>
		<?php
		while($row = mysqli_fetch_array($rs)){
			echo('<tr>');
			echo('<td style="text-align: center;"><a data-host="'.md5($row['host']).'" id="rm'.md5($row['host']).'" class="blockListRemove glyphicon glyphicon-trash" href="#"></a></td>');
			echo('<td style="text-align: center;">');
			if($row['isActive']==0){
				echo('<a data-blstatus="0" data-host="'.md5($row['host']).'" id="tg'.md5($row['host']).'" class="blockListLinks glyphicon glyphicon-remove" href="#"></a></td>');
			}else{
				echo('<a data-blstatus="1" data-host="'.md5($row['host']).'" id="tg'.md5($row['host']).'" class="blockListLinks glyphicon glyphicon-ok" href="#"></a></td>');
			}
			echo('<td style="white-space: nowrap"><a target="_blank" href="'.$row['website'].'">'.$row['host'].'</a></td>');
			echo('<td style="white-space: nowrap">'.($row['monitorType']=='ip' ? 'IP' : 'Domain').'</td>');
			echo('<td>'.$row['description'].'</td>');
			echo('<td style="text-align: center;">');
			switch($row['importance']){
				case 3: echo('<span class="label label-primary">High</span>'); break;
				case 2: echo('<span class="label label-info">Medium</span>'); break;
				case 1: echo('<span class="label label-default">Low</span>'); break;
			}
			echo('</td>');
			echo('<td style="white-space: nowrap">'.number_format($row['blocksToday'],0).'</td>');
			echo('<td style="white-space: nowrap">'.number_format($row['cleanToday'],0).'</td>');
			echo('<td style="white-space: nowrap">'.number_format($row['blocksYesterday'],0).'</td>');
			echo('<td style="white-space: nowrap">'.number_format($row['cleanYesterday'],0).'</td>');
			echo('</tr>');
		}
		$mysql->close();
		?>
		</tbody>
	</table>
	</div>
	<form id="accountForm" class="form-horizontal" role="form" action="blockLists.php" method="post">
		<div class="form-group">
			<label class="col-sm-3 control-label" for="host">Host</label>
			<div class="col-sm-6">
				<input class="form-control" type="text" id="host" name="host" placeholder="hostname">
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-3 control-label" for="monitorType">Monitor Type</label>
			<div class="col-sm-6">
				<select id="monitorType" name="monitorType" class="form-control">
					<option value="ip">IP</option>
					<option value="domain">Domain</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-3 control-label" for="description">Description</label>
			<div class="col-sm-6">
				<input class="form-control" type="text" id="description" name="description" placeholder="description">
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-3 control-label" for="website">Website</label>
			<div class="col-sm-6">
				<input class="form-control" type="text" id="website" name="website" placeholder="website">
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-3 control-label" for="importance">Importance</label>
			<div class="col-sm-6">
				<select id="importance" name="importance" class="form-control">
					<option value="1">Low</option>
					<option value="2">Medium</option>
					<option value="3">High</option>
				</select>
			</div>
		</div>



		<div class="form-group">
			<div class="col-sm-offset-3 col-sm-6">
				<button type="submit" name="submit" value="submit" class="btn btn-primary">Add Blacklist</button>
			</div>
		</div>
		</form>
</div>

<?php include('footer.inc.php'); ?>
