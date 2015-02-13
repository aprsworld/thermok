<?
$station_id=strtoupper($_REQUEST["station_id"]);

require_once $_SERVER["DOCUMENT_ROOT"] . "/world_config.php";
$db=_open_mysql("worldData");

/* if not public, then we need to be authorized */
if ( 0==authPublic($station_id,$db) ) {
	require $_SERVER["DOCUMENT_ROOT"] . "/auth.php";
}



/* Determine our title and display name */
$sql=sprintf("SELECT * FROM deviceInfo WHERE serialNumber='%s'",$station_id);
$query=mysql_query($sql,$db);
$deviceInfo=mysql_fetch_array($query);

/* display displayName if it is not null */
if ( "" != $deviceInfo["displayName"] ) $displayName=$deviceInfo["displayName"]; else $displayName=$station_id;
$displayName=htmlspecialchars($displayName);


$title=$headline=$displayName . " <br />Current Conditions";
$refreshable=1;
require $_SERVER["DOCUMENT_ROOT"] . "/world_head.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/winddata/windFunctions.php";

$sql=sprintf("SELECT status.packet_date, sec_to_time(unix_timestamp()-unix_timestamp(packet_date)) AS ageTime,(unix_timestamp()-unix_timestamp(packet_date)) AS ageSeconds,deviceInfo.owner, deviceInfo.updateRate, deviceInfo.timeZone, deviceInfo.timeZoneOffsetHours, DATE_ADD(status.packet_date,INTERVAL deviceInfo.timeZoneOffsetHours HOUR) AS packet_date_local FROM status LEFT JOIN (deviceInfo) ON (status.serialNumber=deviceInfo.serialNumber) WHERE status.serialNumber='%s'",$station_id);
$query=mysql_query($sql,$db);
$deviceInfo=mysql_fetch_array($query);

/* calculate a human readable report received at */
$s="";
if ( $deviceInfo["ageSeconds"] > 59 ) {
	$rr=sprintf("Report received %s (hours:minutes:seconds) ago.",$deviceInfo["ageTime"]);
} else {
	if ( 1 != $deviceInfo["ageSeconds"] ) 
		$s="s";
	else
		$s='';

	$rr=sprintf("Report received %d second%s ago.",$deviceInfo["ageSeconds"],$s);
}
?>

<? if ( $deviceInfo["ageSeconds"] > 15*60 ) { ?>
<div class="caution">
<p>
This station is marked as supplying live data, however the data appears to be old. Please check the age of the data carefully before using it!
</p>
</div>
<?
} 

/* pull actual last record */
$sql=sprintf("SELECT * FROM thermok4_%s WHERE packet_date='%s'",$station_id,$deviceInfo["packet_date"]);
$sql=sprintf("SELECT * FROM thermok4_%s ORDER BY packet_date DESC LIMIT 1",$station_id);
$query=mysql_query($sql,$db);
$r=mysql_fetch_array($query);

/* column labels and units */
$sql=sprintf("SELECT * FROM thermok4_labels WHERE serialNumber='%s'",$station_id);
$query=mysql_query($sql,$db);
$l=mysql_fetch_array($query);

?>
<div align="center">
<table>
	<tr>
		<th colspan="2">Current Values</th>
	</tr>
	<tr>
		<th>Report Date:</th>
		<td>
			<? echo $deviceInfo["packet_date_local"] . " " . $deviceInfo["timeZone"]; ?>
			(<? echo $r["packet_date"]; ?> UTC)<br />
			<? echo $rr; ?>
		</td>
	</tr>
<? 
/* anemometers / pulse measurement */
if ( ""!=$l["t0L"] || ""!=$l["t1L"] || ""!=$l["t2L"] || ""!=$l["t3L"] ) { 
?>
	<tr>
		<th colspan="2">Temperature</th>
	</tr>
<? 
} 
$colors=array("Red","Blue","Yellow","Green");
for ( $i=0 ; $i<4 ; $i++ ) {
	$colLabel=$l["t" . $i . "L"];
	$colUnits=$l["t" . $i . "U"];

	if ( ""==$colLabel )
		continue;
?>
	<tr>
		<th><? printf("%s (%s): ",$colLabel,$colors[$i]); ?></th>
		<td>
<? 
$c=sprintf("%0.1f",$r["t" . $i]);
$k=sprintf("%0.1f",$r["t" . $i]+273.15);
$f=sprintf("%0.1f",1.8*$r["t" . $i]+32);


/* replace our units string with our temperature values */
$t=str_replace('c',$c,$colUnits);
$t=str_replace('f',$f,$t);
$t=str_replace('k',$k,$t);

if ( "-99.9" == $r["t" . $i] || "-1000.0" == $r["t" . $i] )
	echo "Not Connected";
else
	echo $t;

//	printf("%.1f %s %s",$r["t" . $i],$colUnits,$du); 
?>
		</td>
	</tr>
<?
}

/* analog channels */
if ( ""!=$l["v0L"] || ""!=$l["v1L"] || ""!=$l["v2L"] || ""!=$l["v3L"] ) { 
?>
	<tr>
		<th colspan="2">Voltage</th>
	</tr>
<? 
} 

/* analog channels */
for ( $i=0 ; $i<4 ; $i++ ) {
	$colLabel=$l["v" . $i . "L"];
	$colUnits=$l["v" . $i . "U"];

	if ( ""==$colLabel )
		continue;

	if ( 1 == $l["dualUnits"] ) 
		$colUnits="";

?>
	<tr>
		<th><? echo $colLabel; ?>:</th>
		<td><? printf("%0.2f %s",$r["vin" . $i],$colUnits);  ?></td>
	</tr>
<?
}

/* relay channels */
if ( ""!=$l["r0L"] || ""!=$l["r1L"] || ""!=$l["r2L"] ) { 
?>
	<tr>
		<th colspan="2">Relay States</th>
	</tr>
<? 
} 

/* relays */
for ( $i=0 ; $i<4 ; $i++ ) {
	$colLabel=$l["r" . $i . "L"];

	if ( ""==$colLabel )
		continue;


	if ( 1 == $r["relay" . $i] ) 
		$state='On';
	else
		$state='Off';

?>
	<tr>
		<th><? echo $colLabel; ?>:</th>
		<td><? echo $state; ?></td>
	</tr>
<?
}


$counterLabel=$l["c0L"];
$counterUnits=$l["c0U"];
if ( "" != $counterLabel || "" != $counterUnits ) {
?>
	<tr>
		<th colspan="2">Event Counter</th>
	</tr>
	<tr>
		<th><? echo $counterLabel; ?>:</th>
		<td><? printf("%d %s",$r["pulseCount"],$counterUnits); ?></td>
	</tr>
<?
}
?>
</table>
<p>
<img src="temperaturePlot.php?hours=24&amp;station_id=<? echo $station_id; ?>" />
</p>
</div>


<?
require $_SERVER["DOCUMENT_ROOT"] . "/world_foot.php";
?>