<?php
session_start();

// Activate MySQL Connection
$MysqlConnnection = mysqli_connect("localhost","philipp","KURZpraezision1#","MachineControl");
if (!$MysqlConnnection) {
  die('Unable to connect: ' . mysqli_error($MysqlConnnection));
}

$MySqlTables = ["Machines","TelegramUsers"];

foreach ($MySqlTables as $Table)
{	
    $MySqlQuery = "SELECT name from ".$Table;
    $MySqlResult = mysqli_query($MysqlConnnection, $MySqlQuery);
    // var_dump($MySqlResult);
    // echo "<br>";
    foreach ($MySqlResult as $MySqlRow) {

		$Name = $MySqlRow['name'];
        
        $NewEnable = $_POST[$Name];
        
        $UpdateQuery = "UPDATE " . $Table . " SET enable = " . $NewEnable . " WHERE name = '" . $Name . "'";
        $UpdateResult = mysqli_query($MysqlConnnection, $UpdateQuery);
	} 
}	

$StartDateFormat = isset($_POST['StartDate']) ? $_POST['StartDate'] : '2000-01-01 00:00:00';

switch ($StartDateFormat) {
    case "1h":          $StartDate = date('Y-m-d H:i:s', strtotime("-1 hours")); break;
    case "6h":          $StartDate = date('Y-m-d H:i:s', strtotime("-6 hours")); break;
    case "12h":         $StartDate = date('Y-m-d H:i:s', strtotime("-12 hours")); break;
    case "today":       $StartDate = date('Y-m-d 00:00:00'); break;
    case "24h":         $StartDate = date('Y-m-d H:i:s',strtotime("-1 day")); break;
    case "yesterday":   $StartDate = date("Y-m-d 00:00:00",strtotime("-1 day")); break;
    case "3days":       $StartDate = date("Y-m-d 00:00:00",strtotime("-3 days")); break;
    case "all":         $StartDate = "2000-01-01 00:00:00"; break;
}

//$lockfile = fopen("/home/pi/Betrieb/lockfiles/request_update", "w") or die("Unable to open file!");

echo $StartDate;
header("Location:index.php?d=".$StartDate."&f=".$StartDateFormat);

?>