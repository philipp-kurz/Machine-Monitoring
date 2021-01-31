<?php

include 'credentials.php';

// Activate MySQL Connection
$MysqlConnnection = mysqli_connect($creds['mysql_host'], $creds['mysql_username'], $creds['mysql_password'], $creds['mysql_database']);
if (!$MysqlConnnection) {
  die('Unable to connect: ' . mysqli_error($MysqlConnnection));
}

$Statuses = array();

for ($MachineNumber = 1; $MachineNumber <= 4; $MachineNumber++) {
	$StatusQuery = "SELECT status from machine_data WHERE machine = ".$MachineNumber." ORDER BY message_time DESC LIMIT 1";
	$StatusResult = mysqli_query($MysqlConnnection, $StatusQuery);
	foreach($StatusResult as $StatusRow) {
		$Statuses[$MachineNumber] = $StatusRow['status'];
	}
}

echo json_encode($Statuses);

?>