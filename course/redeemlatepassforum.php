<?php
//IMathAS:  Redeem latepasses
//(c) 2007 David Lippman

	require("../validate.php");
	$cid = $_GET['cid'];
	$fid = $_GET['fid'];
	$from = $_GET['from'];
	
	$query = "SELECT latepasshrs FROM imas_courses WHERE id='$cid'";
	$result = mysql_query($query) or die("Query failed : " . mysql_error());
	$hours = mysql_result($result,0,0);
	$now = time();
	
	if (isset($_GET['undo'])) {
		require("../header.php");
		echo "<div class=breadcrumb>$breadcrumbbase ";
		if ($cid>0 && (!isset($sessiondata['ltiitemtype']) || $sessiondata['ltiitemtype']!=0)) {
			echo " <a href=\"../course/course.php?cid=$cid\">$coursename</a> &gt; ";
		}
		echo "Un-use LatePass</div>";
		$query = "SELECT startdate,enddate,islatepass,itemtype FROM imas_exceptions WHERE userid='$userid' AND assessmentid='$fid' AND (itemtype='F' OR itemtype='R' OR itemtype='P')";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
		if (mysql_num_rows($result)==0) {
			echo '<p>Invalid</p>';
		} else {
			$row = mysql_fetch_row($result);
			if ($row[2]==0) {
				echo '<p>Invalid</p>';
			} else {
				$now = time();
				$query = "SELECT allowlate,postby,replyby FROM imas_forums WHERE id='$fid'";
				$result = mysql_query($query) or die("Query failed : " . mysql_error());
				list($allowlate,$postby,$replyby) = mysql_fetch_row($result);
				$allowlaten = $allowlate%10;
				$allowlateon = floor($allowlate/10)%10;
				//allowlateon:  0: both together, 1: both separate (not used), 2: posts, 3: replies
				//if it's past original due date and latepass is for less than latepasshrs past now, too late
				$postn = 0; $replyn = 0; $newpostend = 0; $newreplyend = 0;
				if ($row[3]=='F' && $row[0]==0) { //only extended reply - treat like reply exception
					$row[3] = 'R';	
				} else if ($row[3]=='F' && $row[1]==0) { //only extended reply - treat like reply exception
					$row[3] = 'P';	
				}
				if ($row[3]=='F' || $row[3]=='P') {
					if ($now > $postby && $row[0] < $now + $hours*60*60) {
						$postn = 0;  //too late to un-use
					} else if ($now < $postby) {//before postbydate, return all latepasses
						$postn = $row[2];
					} else {
						$postn = floor(($row[0] - $now)/($hours*60*60));  //if ==$row[2] then returning all
					}
				}
				if ($row[3]=='F' || $row[3]=='R') {
					if ($now > $replyby && $row[1] < $now + $hours*60*60) {
						$replyn = 0;  //too late to un-use
					} else if ($now < $replyby) {//before replybydate, return all latepasses
						$replyn = $row[2];
					} else {
						$replyn = floor(($row[1] - $now)/($hours*60*60));  //if ==$row[2] then returning all
					}
				}
				
				if ($postn==0 && $replyn==0) {
					echo '<p>Too late to un-use this LatePass</p>';
				} else {
					if (($row[3]=='F' && $postn==$row[2] && $replyn==$row[2]) || ($row[3]=='R' && $replyn==$row[2]) || ($row[3]=='P' && $postn==$row[2])) {
						//returning all the latepasses
						$query = "DELETE FROM imas_exceptions WHERE userid='$userid' AND assessmentid='$fid' AND (itemtype='F' OR itemtype='R' OR itemtype='P')";
						mysql_query($query) or die("Query failed : " . mysql_error());
						$toreturn = $row[2];
					} else {
						if ($row[3]=='F') {
							$toreturn = min($postn,$replyn);
							$newpostend = $row[0] - $toreturn*$hours*60*60;
							$newreplyend = $row[1] - $toreturn*$hours*60*60;
						} else if ($row[3]=='R') {
							$toreturn = $replyn;
							$newreplyend = $row[1] - $replyn*$hours*60*60;
						} else if ($row[3]=='P') {
							$toreturn = $postn;
							$newpostend = $row[0] - $postn*$hours*60*60;
						}
						$query = "UPDATE imas_exceptions SET islatepass=islatepass-$toreturn,startdate=$newpostend,enddate=$newreplyend WHERE userid='$userid' AND assessmentid='$fid' AND (itemtype='F' OR itemtype='R' OR itemtype='P')";
						mysql_query($query) or die("Query failed : " . mysql_error());
					}
					echo "<p>Returning $toreturn LatePass".($toreturn>1?"es":"")."</p>";
					$query = "UPDATE imas_students SET latepass=latepass+$toreturn WHERE userid='$userid' AND courseid='$cid'";
					mysql_query($query) or die("Query failed : $query " . mysql_error());
				}
			}
		}
		if ($from=='forum') {
			echo "<p><a href=\"../forums/thread.php?cid=$cid&forum=$fid\">Continue</a></p>";
		} else {
			echo "<p><a href=\"course.php?cid=$cid\">Continue</a></p>";
		}
		
		require("../footer.php");
		
	} else if (isset($_GET['confirm'])) {
		$addtime = $hours*60*60;
		$query = "SELECT allowlate,postby,replyby FROM imas_forums WHERE id='$fid'";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
		list($allowlate,$postby,$replyby) = mysql_fetch_row($result);
		$allowlaten = $allowlate%10;
		$allowlateon = floor($allowlate/10)%10;
		
		$query = "SELECT startdate,enddate,islatepass,itemtype FROM imas_exceptions WHERE userid='$userid' AND assessmentid='$fid' AND (itemtype='F' OR itemtype='R' OR itemtype='P')";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
		$hasexception = false;
		$usedlatepassespost = 0; $usedlatepassesreply = 0;
		if (mysql_num_rows($result)==0) {
			$thispostby = $postby;
			$thisreplyby = $replyby;
			if ($allowlateon==0) {
				$itemtype = 'F';
			} else if ($allowlateon==2) {
				$itemtype = 'P';
			} else if ($allowlateon==3) {
				$itemtype = 'R';
			}
		} else {
			$hasexception = true;
			$r = mysql_fetch_row($result);
			$usedlatepassespost = min(max(0,round(($r[0] - $postby)/($hours*3600))), $r[2]);
			$thispostby = $r[0];
			$usedlatepassesreply = min(max(0,round(($r[1] - $replyby)/($hours*3600))), $r[2]);
			$thisreplyby = $r[1];
			$itemtype = $r[3];
		}
		
		$addtimepost = 0; $addtimereply = 0; $startdate = 0; $enddate = 0;
		if (($itemtype=='F' || $itemtype=='P') && ($allowlaten==1 || $allowlaten-1>$usedlatepassespost) && ($now<$thispostby || ($allowlate>100 && ($now-$thispostby)<$hours*3600))) {
			$addtimepost = $addtime;
			$startdate = $postby + $addtimepost;
		}
		if (($itemtype=='F' || $itemtype=='R') && ($allowlaten==1 || $allowlaten-1>$usedlatepassesreply) && ($now<$thisreplyby || ($allowlate>100 && ($now-$thisreplyby)<$hours*3600))) {
			$addtimereply = $addtime;
			$enddate = $replyby + $addtimereply;
		}
		if ($addtimepost>0 || $addtimereply>0) {
			if ($hasexception) {
				$query = "UPDATE imas_exceptions SET startdate=startdate+$addtimepost,enddate=enddate+$addtimereply,islatepass=islatepass+1 WHERE userid='$userid' AND assessmentid='$fid' AND itemtype='$itemtype'";
				mysql_query($query) or die("Query failed : " . mysql_error());
			} else {
				$query = "INSERT INTO imas_exceptions (userid,assessmentid,startdate,enddate,islatepass,itemtype) VALUES ('$userid','$fid','$startdate','$enddate',1,'$itemtype')";
				mysql_query($query) or die("Query failed : " . mysql_error());
			}
		}
		if ($from=='forum') {
			header('Location: ' . $urlmode  . $_SERVER['HTTP_HOST'] . $imasroot . "/forums/thread.php?cid=$cid&forum=$fid");
		} else {
			header('Location: ' . $urlmode  . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/course.php?cid=$cid");
		}

	} else {
		//TO HERE - TODO keep going
		require("../header.php");
		echo "<div class=breadcrumb>$breadcrumbbase ";
		if ($cid>0 && (!isset($sessiondata['ltiitemtype']) || $sessiondata['ltiitemtype']!=0)) {
			echo " <a href=\"../course/course.php?cid=$cid\">$coursename</a> &gt; ";
		}
		echo "Redeem LatePass</div>\n"; 
		//$curBreadcrumb = "$breadcrumbbase <a href=\"course.php?cid=$cid\"> $coursename</a>\n";
		//$curBreadcrumb .= " Redeem LatePass\n";
		//echo "<div class=\"breadcrumb\">$curBreadcrumb</div>";
		
		$query = "SELECT latepass FROM imas_students WHERE userid='$userid' AND courseid='$cid'";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
		$numlatepass = mysql_result($result,0,0);
		
		$query = "SELECT allowlate,postby,replyby FROM imas_forums WHERE id='$fid'";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
		list($allowlate,$postby,$replyby) = mysql_fetch_row($result);
		$allowlaten = $allowlate%10;
		$allowlateon = floor($allowlate/10)%10;
		
		$query = "SELECT startdate,enddate,islatepass,itemtype FROM imas_exceptions WHERE userid='$userid' AND assessmentid='$fid' AND (itemtype='F' OR itemtype='R' OR itemtype='P')";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
		$hasexception = false;
		$usedlatepassespost = 0; $usedlatepassesreply = 0;
		if (mysql_num_rows($result)==0) {
			$thispostby = $postby;
			$thisreplyby = $replyby;
			if ($allowlateon==0) {
				$itemtype = 'F';
			} else if ($allowlateon==2) {
				$itemtype = 'P';
			} else if ($allowlateon==3) {
				$itemtype = 'R';
			}
		} else {
			$hasexception = true;
			$r = mysql_fetch_row($result);
			$usedlatepassespost = min(max(0,round(($r[0] - $postby)/($hours*3600))), $r[2]);
			$thispostby = $r[0];
			$usedlatepassesreply = min(max(0,round(($r[1] - $replyby)/($hours*3600))), $r[2]);
			$thisreplyby = $r[1];
			$itemtype = $r[3];
		}
		$canuselatepass = false;
		if (($itemtype=='F' || $itemtype=='P') && $postby!=2000000000 && ($allowlaten==1 || $allowlaten-1>$usedlatepassespost) && ($now<$thispostby || ($allowlate>100 && ($now-$thispostby)<$hours*3600))) {
			$canuselatepasspost = true;
		}
		if (($itemtype=='F' || $itemtype=='R') && $replyby!=2000000000 && ($allowlaten==1 || $allowlaten-1>$usedlatepassesreply) && ($now<$thisreplyby || ($allowlate>100 && ($now-$thisreplyby)<$hours*3600))) {
			$canuselatepassreply = true;
		}
		
		if ($numlatepass==0) { //shouldn't get here if 0
			echo "<p>You have no late passes remaining.</p>";
		} else if ($canuselatepasspost || $canuselatepassreply) {
			echo '<div id="headerredeemlatepass" class="pagetitle"><h2>Redeem LatePass</h2></div>';
			echo "<form method=post action=\"redeemlatepassforum.php?cid=$cid&fid=$fid&from=$from&confirm=true\">";
			if ($allowlaten>1) {
				echo '<p>You may use up to '.($allowlaten-1-$usedlatepasses).' more LatePass(es) on this forum assignment.</p>'; 
			}
			echo "<p>You have $numlatepass LatePass(es) remaining.</p>";
			echo "<p>You can redeem one LatePass for a $hours hour extension on ";
			if ($canuselatepasspost) {
				echo " the <b>New Threads</b> due date ";
				if ($canuselatepassreply) {
					echo "and";
				}
			}
			if ($canuselatepassreply) {
				echo " the <b>Replies</b> due date ";
			}
			echo "for this forum assignment.</p><p>Are you sure you want to redeem a LatePass?</p>";
			echo "<input type=submit value=\"Yes, Redeem LatePass\"/> ";
			if ($from=='forum') {
				echo "<input type=button value=\"Nevermind\" class=\"secondarybtn\" onclick=\"window.location='../forums/thread.php?cid=$cid&forum=$fid'\"/>";
			} else {
				echo "<input type=button value=\"Nevermind\" class=\"secondarybtn\" onclick=\"window.location='course.php?cid=$cid'\"/>";
				
			}
			
			echo "</form>";
		} else {
			echo "<p>You are not allowed to use additional latepasses on this forum assignment.</p>";
		}
		require("../footer.php");
	}
	
?>