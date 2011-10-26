<?php
//IMathAS:  Drill Assess player (updated quickdrill)
//(c) 2011 David Lippman
require("../validate.php");

$cid = intval($_GET['cid']);
$daid = intval($_GET['daid']);

if (!isset($teacherid)) {
	echo 'You are not authorized to view this page';
	exit;
}

$query = "SELECT * FROM imas_drillassess WHERE id='$daid' AND courseid='$cid'";
$result = mysql_query($query) or die("Query failed : " . mysql_error());
if (mysql_num_rows($result)==0) {
	echo 'Invalid drill id.';
	exit;
}
$dadata = mysql_fetch_array($result, MYSQL_ASSOC);
$n = $dadata['n'];
$showtype = $dadata['showtype'];
$scoretype = $dadata['scoretype'];
if ($scoretype{0}=='t') {
	$mode = 'cntdown';
	$torecord = 'cc';   //count  correct
} else {
	$mode = 'cntup';
	$stopattype = $scoretype{1};  //a: attempted, c: correct, s: streak
	$torecord = $scoretype{2}; //t: time, c: total count
}
$showtostu = $dadata['showtostu'];
if ($dadata['itemids']=='') {
	$itemids = array();
} else {
	$itemids = explode(',',$dadata['itemids']);
}
if ($dadata['itemdescr']=='') {
	$itemdescr = array();
} else {
	$itemdescr = explode(',',$dadata['itemdescr']);
}
$classbests = explode(',',$dadata['classbests']);

$studata = array();
$query = "SELECT iu.LastName,iu.FirstName,ids.scorerec FROM imas_drillassess_sessions AS ids ";
$query .= "JOIN imas_users AS iu ON iu.id=ids.userid WHERE ids.drillassessid=$daid ORDER BY iu.LastName, iu.FirstName";
$result = mysql_query($query) or die("Query failed : " . mysql_error());
while ($row = mysql_fetch_row($result)) {
	$scorerec = unserialize($row[2]);
	$rowdata = array($row[0].', '.$row[1]);
	foreach ($itemids as $qn=>$v) {
		if (isset($scorerec[$qn])) {
			if ($torecord=='cc') {
				$score =  dispscore(max($scorerec[$qn]));
			} else {
				$score =  dispscore(min($scorerec[$qn]));
			}
			$score .= '('.count($scorerec[$qn]).')';
			$score .= '<br/>';
			$score .= dispscore($scorerec[$qn][count($scorerec[$qn])-1]); 
		} else {
			$score = 'N/A';
		}
		$rowdata[] = $score;
	}
	$studata[] = $rowdata;
}
$placeinhead = '<script type="text/javascript">function highlightrow(el) { el.setAttribute("lastclass",el.className); el.className = "highlight";}';
$placeinhead .= 'function unhighlightrow(el) { el.className = el.getAttribute("lastclass");}</script>';
$placeinhead .= "<script type=\"text/javascript\" src=\"$imasroot/javascript/tablesorter.js?v=012811\"></script>\n";
	
require("../header.php");
echo "<div class=breadcrumb>$breadcrumbbase <a href=\"course.php?cid={$_GET['cid']}\">$coursename</a> &gt; Drill Assessment Results</div>";
echo "<h2>Drill Assessment Results</h2>";

echo '<table id="myTable" class="gb">';
echo '<thead><tr><th>Best (# tries)<br/>Last</th>';
$sarr = "'S'";
foreach ($itemdescr as $qn=>$v) {
	echo '<th>'.$v.'</th>';
	$sarr .= ",'N'";
}
echo '</tr></thead><tbody>';
foreach ($studata as $i=>$sturow) {
	if ($i%2==0) {
		echo '<tr class="even" onMouseOver="highlightrow(this)" onMouseOut="unhighlightrow(this)">';
	} else {
		echo '<tr class="odd" onMouseOver="highlightrow(this)" onMouseOut="unhighlightrow(this)">';
	}
	foreach ($sturow as $stuval) {
		echo '<td>'.$stuval.'</td>';
	}
	echo '</tr>';
}
echo '</tbody></table>';

echo "<script>initSortTable('myTable',Array($sarr),false);</script>\n";
	
require("../footer.php");

function dispscore($sc) {
	global $torecord;
	if ($torecord=='t') {
		return formattime($sc);
	} else if ($torecord=='cc') {
		return $sc . ' correct';
	} else {
		return $sc . ' attempts';
	}
}


function formattime($cur) {
	if ($cur > 3600) {
		$hours = floor($cur/3600);
		$cur = $cur - 3600*$hours;
	} else { $hours = 0;}
	if ($cur > 60) {
		$minutes = floor($cur/60);
		if ($minutes<10) { $minutes = '0'.$minutes;}
		$cur = $cur - 60*$minutes;
	} else {$minutes='00';}
	$seconds = $cur;
	if ($seconds<10) { $seconds = '0'.$seconds;}
	return "$hours:$minutes:$seconds";
}
?>
