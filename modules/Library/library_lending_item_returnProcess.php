<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

include "../../functions.php" ;
include "../../config.php" ;

//New PDO DB connection
try {
  	$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
	$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
  echo $e->getMessage();
}

@session_start() ;

//Set timezone from session variable
date_default_timezone_set($_SESSION[$guid]["timezone"]);

$gibbonLibraryItemEventID=$_GET["gibbonLibraryItemEventID"] ;
$gibbonLibraryItemID=$_GET["gibbonLibraryItemID"] ;

if ($gibbonLibraryItemID=="") {
	print "Fatal error loading this page!" ;
}
else {
	$URL=$_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_POST["address"]) . "/library_lending_item_return.php&gibbonLibraryItemID=$gibbonLibraryItemID&gibbonLibraryItemEventID=$gibbonLibraryItemEventID&name=" . $_GET["name"] . "&gibbonLibraryTypeID=" . $_GET["gibbonLibraryTypeID"] . "&gibbonSpaceID=" . $_GET["gibbonSpaceID"] . "&status=" . $_GET["status"] ;
	$URLSuccess=$_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_POST["address"]) . "/library_lending_item.php&gibbonLibraryItemID=$gibbonLibraryItemID&gibbonLibraryItemEventID=$gibbonLibraryItemEventID&name=" . $_GET["name"] . "&gibbonLibraryTypeID=" . $_GET["gibbonLibraryTypeID"] . "&gibbonSpaceID=" . $_GET["gibbonSpaceID"] . "&status=" . $_GET["status"] ;
	
	if (isActionAccessible($guid, $connection2, "/modules/Library/library_lending_item_return.php")==FALSE) {
		//Fail 0
		$URL.="&updateReturn=fail0" ;
		header("Location: {$URL}");
	}
	else {
		//Proceed!
		//Check if event specified
		if ($gibbonLibraryItemEventID=="" OR $gibbonLibraryItemID=="") {
			//Fail1
			$URL.="&updateReturn=fail1" ;
			header("Location: {$URL}");
		}
		else {
			try {
				$data=array("gibbonLibraryItemEventID"=>$gibbonLibraryItemEventID, "gibbonLibraryItemID"=>$gibbonLibraryItemID); 
				$sql="SELECT * FROM gibbonLibraryItemEvent JOIN gibbonLibraryItem ON (gibbonLibraryItemEvent.gibbonLibraryItemID=gibbonLibraryItem.gibbonLibraryItemID) WHERE gibbonLibraryItemEventID=:gibbonLibraryItemEventID AND gibbonLibraryItem.gibbonLibraryItemID=:gibbonLibraryItemID" ;
				$result=$connection2->prepare($sql);
				$result->execute($data);
			}
			catch(PDOException $e) { 
				//Fail 2
				$URL.="&updateReturn=fail2" ;
				header("Location: {$URL}");
				break ;
			}
			
			if ($result->rowCount()!=1) {
				//Fail 2
				$URL.="&updateReturn=fail2" ;
				header("Location: {$URL}");
			}
			else {
				//Validate Inputs
				$returnAction=$_POST["returnAction"] ;
				$status="" ;
				if ($returnAction=="Reserve") {
					$status="Reserved" ;
				}
				else if ($returnAction=="Decommission") {
					$status="Decommissioned" ;
				}
				else if ($returnAction=="Repair") {
					$status="Repair" ;
				}
				$gibbonPersonIDReturnAction=NULL ;
				if ($_POST["gibbonPersonIDReturnAction"]!="") {
					$gibbonPersonIDReturnAction=$_POST["gibbonPersonIDReturnAction"] ;
				}
				
				//Write to database
				try {
					$data=array("timestampReturn"=>date('Y-m-d H:i:s', time()), "gibbonLibraryItemEventID"=>$gibbonLibraryItemEventID, "gibbonPersonIDIn"=>$_SESSION[$guid]["gibbonPersonID"]); 
					$sql="UPDATE gibbonLibraryItemEvent SET status='Returned', timestampReturn=:timestampReturn, gibbonPersonIDIn=:gibbonPersonIDIn WHERE gibbonLibraryItemEventID=:gibbonLibraryItemEventID" ;
					$result=$connection2->prepare($sql);
					$result->execute($data);
				}
				catch(PDOException $e) { 
					//Fail 2
					$URL.="&updateReturn=fail2" ;
					header("Location: {$URL}");
					break ;
				}
				
				//No return action, so just mark the item
				if ($returnAction=="") {
					try {
						$data=array("gibbonLibraryItemID"=>$gibbonLibraryItemID, "gibbonPersonIDStatusRecorder"=>$_SESSION[$guid]["gibbonPersonID"], "timestampStatus"=>date('Y-m-d H:i:s', time())); 
						$sql="UPDATE gibbonLibraryItem SET status='Available', gibbonPersonIDStatusResponsible=NULL, gibbonPersonIDStatusRecorder=:gibbonPersonIDStatusRecorder, timestampStatus=:timestampStatus, returnExpected=NULL, returnAction='', gibbonPersonIDReturnAction=NULL WHERE gibbonLibraryItemID=:gibbonLibraryItemID" ;
						$result=$connection2->prepare($sql);
						$result->execute($data);
					}
					catch(PDOException $e) { 
						//Fail 2
						$URL.="&updateReturn=fail2" ;
						header("Location: {$URL}");
						break ;
					}
				}
				//Return action, so mark the item, and create a new event
				else {
					try {
						$data=array("gibbonLibraryItemID"=>$gibbonLibraryItemID, "status"=>$status, "gibbonPersonIDStatusResponsible"=>$gibbonPersonIDReturnAction, "gibbonPersonIDOut"=>$_SESSION[$guid]["gibbonPersonID"], "timestampOut"=>date('Y-m-d H:i:s', time())); 
						$sql="INSERT INTO gibbonLibraryItemEvent SET gibbonLibraryItemID=:gibbonLibraryItemID, status=:status, gibbonPersonIDStatusResponsible=:gibbonPersonIDStatusResponsible, gibbonPersonIDOut=:gibbonPersonIDOut, timestampOut=:timestampOut, returnExpected=NULL, returnAction='', gibbonPersonIDReturnAction=NULL" ;
						$result=$connection2->prepare($sql);
						$result->execute($data);
					}
					catch(PDOException $e) { 
						//Fail 2
						$URL.="&addReturn=fail2" . $e->getMessage() ;
						header("Location: {$URL}");
						break ;
					}
					
					try {
						$data=array("gibbonLibraryItemID"=>$gibbonLibraryItemID, "status"=>$status, "gibbonPersonIDStatusResponsible"=>$gibbonPersonIDReturnAction, "gibbonPersonIDStatusRecorder"=>$_SESSION[$guid]["gibbonPersonID"], "timestampStatus"=>date('Y-m-d H:i:s', time())); 
						$sql="UPDATE gibbonLibraryItem SET status=:status, gibbonPersonIDStatusResponsible=:gibbonPersonIDStatusResponsible, gibbonPersonIDStatusRecorder=:gibbonPersonIDStatusRecorder, timestampStatus=:timestampStatus, returnExpected=NULL, returnAction='', gibbonPersonIDReturnAction=NULL WHERE gibbonLibraryItemID=:gibbonLibraryItemID" ;
						$result=$connection2->prepare($sql);
						$result->execute($data);
					}
					catch(PDOException $e) { 
						//Fail 2
						$URL.="&addReturn=fail2" ;
						header("Location: {$URL}");
						break ;
					}
				}
						
				//Success 0
				$URL=$URLSuccess . "&updateReturn=success0" ;
				header("Location: {$URL}");
			}
		}
	}
}
?>