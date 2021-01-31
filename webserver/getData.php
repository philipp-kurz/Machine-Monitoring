<?php

// Set up table structure
$DataTable = array();
$DataTable['cols'] = array(
array('id' => '', 'label' => 'Machine', 'type' => 'string'),
array('id' => '', 'label' => 'Type',    'type' => 'string'),
array('role' => 'style','label' => '',  'type' => 'string'),
array('id' => '', 'label' => 'Start',   'type' => 'date'),
array('id' => '', 'label' => 'End',     'type' => 'date')
);

// Array for all JSON data table rows
$arrDataTableRows = array();

// Convert Time from MySQL to JavaScript
function MySqlDateToJsDate($p_MySqlDate)
{          //position:    0123456789012345678
    // MySQL      format: 2018-07-30 09:22:02
    // Timeline   format: Date(2018,07,04,11,23,08)
    
    $Year   = substr($p_MySqlDate,  0, 4);    
    $Month  = substr($p_MySqlDate,  5, 2)-1;
    $Day    = substr($p_MySqlDate,  8, 2);
    $Hour   = substr($p_MySqlDate, 11, 2);
    $Minute = substr($p_MySqlDate, 14, 2);
    $Second = substr($p_MySqlDate, 17, 2);
    
    if ($Month < 10) $Month = '0' . $Month;
    else             $Month = (string) $Month;
    
    return "Date(".$Year.",".$Month.",".$Day.",".$Hour.",".$Minute.",".$Second.")";    
}

// class Malfunction is used to store all malfunctions of a given machine in an array
class Malfunction
{
    public $m_Date;
    public $m_Status;    
    public function __construct($p_Date, $p_Status) {$this->m_Date = $p_Date; $this->m_Status = $p_Status;}
}

// Set start of time window
if(!session_id()) session_start();
$StartDate = $_SESSION['date']; 

include 'credentials.php';
// Activate MySQL Connection
$MysqlConnnection = mysqli_connect($creds['mysql_host'], $creds['mysql_username'], $creds['mysql_password'], $creds['mysql_database']);
if (!$MysqlConnnection) {
  die('Unable to connect: ' . mysqli_error($MysqlConnnection));
}

// Get machines with surveillance enabled
$MachineQuery = "SELECT id, name from Machines WHERE enable = 1";
// $MachineQuery = "SELECT * FROM Machines";
$MachineResult = mysqli_query($MysqlConnnection, $MachineQuery);


// Get malfunctions for each surveillance enabled machine
foreach($MachineResult as $MachineRow)
{ 
    // Array that holds all malfunctions of the current machine
    $arrMachineMalfunctions = array();
	
    // Store current machine name for easier processing
	$MachineNumber = $MachineRow['id'];
    $MachineName = $MachineRow['name'];

    $MalfunctionArrayIndex = 0;
    
    // Set inital malfunction to start diagram with
    $InitialStatusQuery = "SELECT status from machine_data 
                           WHERE machine = " . $MachineNumber . "
                           AND message_time < '" . $StartDate . "' 
                           ORDER BY message_time DESC 
                           LIMIT 1";
				   
    $InitialStatusResult = mysqli_query($MysqlConnnection, $InitialStatusQuery);
    $InitialStatus = $InitialStatusResult->fetch_array()["status"];

    if ($InitialStatus != NULL) // if there was a database entry older than $StartDate
    {
        $arrMachineMalfunctions[0] = new Malfunction($StartDate, $InitialStatus);
    }        
    else // if there was no database entry older than $StartDate
    {   
        // get earliest entry after $StartDate (message_time and status)
        $InitialStateQuery = "SELECT status, message_time from machine_data 
                              WHERE machine = " . $MachineNumber . "
                              AND message_time >= '" . $StartDate . "' 
                              ORDER BY message_time ASC 
                              LIMIT 1";

        $InitialStateResult = mysqli_query($MysqlConnnection, $InitialStateQuery);
        $InitialState = $InitialStateResult->fetch_array();  
        $arrMachineMalfunctions[0] = new Malfunction($InitialState['message_time'], $InitialState['status']);
        $InitialStateResult->free();        
    }
    $InitialStatusResult->free();


    // Select all malfunctions in desired time window of current machine
    $MalfunctionQuery = "SELECT machine, message_time, status FROM machine_data 
                         WHERE machine = " . $MachineNumber . "
                         AND message_time > '" . $StartDate . "' 
                         ORDER BY message_time ASC";
        
    $MalfunctionResult = mysqli_query($MysqlConnnection, $MalfunctionQuery); 
    
    // Append all malfunctions to $arrMachineMalfunctions[]
    foreach($MalfunctionResult as $MalfunctionRow)
    {   
       
        // Make sure that new status is different from last status
        if ($MalfunctionRow['status'] != $arrMachineMalfunctions[$MalfunctionArrayIndex]->m_Status &&
            $MalfunctionRow['message_time']   != $arrMachineMalfunctions[$MalfunctionArrayIndex]->m_Date)
        {   
            $MalfunctionArrayIndex++;
            
            // Append current malfunction to $arrMachineMalfunctions array
            $arrMachineMalfunctions[$MalfunctionArrayIndex] = new Malfunction($MalfunctionRow['message_time'], $MalfunctionRow['status']);
        } 
    } // foreach($MalfunctionResult as $MalfunctionRow)

    // Enter all malfunctions of current machine into JSON data table
    for ($i = 0; $i <= $MalfunctionArrayIndex; $i++)
    {   
        // Set timeline label and style depending on status
        switch ($arrMachineMalfunctions[$i]->m_Status) 
        {
            case 0: $type = "Störung";    				$color = "#DB4437"; break; // red
			case 1: $type = "Bearbeitung"; 				$color = "#4285F4"; break; // blue
            case 2: $type = "Störung (inaktiv)";    	$color = "#EFAFA9"; break; // light red
			case 3: $type = "Bearbeitung (inaktiv)";   	$color = "#B7D1FB"; break; // light blue
			case 4: $type = "Störung (Verzögerung)";   	$color = "#CC00FF"; break; // purple
        }  
        
        // Set end time for current entry
        if ($i < $MalfunctionArrayIndex)
        {
            $EndDate = MySqlDateToJsDate($arrMachineMalfunctions[$i+1]->m_Date);
        }
        else 
        {
            $EndDate = date('\D\a\t\e(Y,m,d,H,i,s)', strtotime("-1 months"));
        }  
        
        $strStartDate = MySqlDateToJsDate($arrMachineMalfunctions[$i]->m_Date);
        // Create new data table row
        $arrTemp = array();
        $arrTemp[] = array('v' => $MachineName);    // Machine
        $arrTemp[] = array('v' => $type);           // Type
        $arrTemp[] = array('v' => $color);          // Style
        $arrTemp[] = array('v' => $strStartDate);   // Start
        $arrTemp[] = array('v' => $EndDate);        // End
        $arrDataTableRows[] = array('c' => $arrTemp);                  
        
    } // for ($i = 0; $i <= $MalfunctionArrayIndex; $i++)
		
} // foreach($MachineResult as $MachineRow)


// Close MySQL connection and free allocated memory
mysqli_close($MysqlConnnection);
$MachineResult->free();
$MalfunctionResult->free();

$DataTable['rows'] = $arrDataTableRows;

$jsonTable = json_encode($DataTable, true);
echo $jsonTable;



// easier for debugging:
// echo '<pre>';
// echo json_encode($DataTable, JSON_PRETTY_PRINT);
// echo '</pre>';

?>
