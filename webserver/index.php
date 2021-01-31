<html>
    <head>
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <meta name="apple-mobile-web-app-capable" content="yes">
   
   
        <title>Monitoring</title>
        <link rel="shortcut icon" type="image/png" href="/monitoring.png"/>
        <link rel="apple-touch-icon" sizes="114x114" href="apple-icon-114x114.png" />
<!--==========================================================================================================================================================================-->
	


<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" />
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.12.2/css/bootstrap-select.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.12.2/js/bootstrap-select.min.js"></script>
     
<!--==========================================================================================================================================================================-->

<link rel="stylesheet" href="styles/main.css">    
   
<header class="navbar-inverse">
        
    <nav class="navbar">
      <div class="container-fluid">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="http://10.0.0.250/index.php">Monitoring</a>
        </div>
        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
          <ul class="nav navbar-nav">
            <li><a href="http://10.0.0.250/phpmyadmin" target="_blank">phpMyAdmin <span class="sr-only">(current)</span></a></li>
          </ul>
        </div><!-- /.navbar-collapse -->
      </div><!-- /.container-fluid -->
    </nav>
    
</header>
<script type="text/javascript"> // RELOADS WEBPAGE WHEN MOBILE ORIENTATION CHANGES  
    window.onorientationchange = function() { 
        var orientation = window.orientation; 
            switch(orientation) { 
                case 0:
                case 90:
                case -90: window.location.reload(); 
                break; } 
    };
    
    </script>
    <?php
        // Get Date Request String (e.g. '12h')
        $StartDateFormat = $_GET['f'];
        // Set start date of data 
        if(!session_id()) session_start();
        if ($_GET['d']!="") $_SESSION['date'] = $_GET['d'];
        else                $_SESSION['date'] = date('Y-m-d H:i:s', strtotime("-6 hours")); 
    ?>
        
   <!-- JavaScript section for timeline diagram -->
        <script src="http://ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
        <script type="text/javascript" src="https://www.google.com/jsapi"></script>
        <script type="text/javascript">
            
        google.load('visualization', '1.1', {'packages':['timeline']});      
        google.setOnLoadCallback(drawChart);
		
		setInterval(drawChart, 10000);

        function drawChart() 
        {
            var jsonData = $.ajax({
                url: "getData.php",
                dataType:"json",
                async: false
            }).responseText;

            var data = new google.visualization.DataTable(jsonData);
			var container = document.getElementById('chart_div');
            var chart = new google.visualization.Timeline(container);
            var formatter = new google.visualization.DateFormat({pattern: 'MMM'});
            formatter.format(data, 0);

            var options = 
            {
                // timeline: {showBarLabels: false, tooltipDateFormat: 'd.M. HH:mm'},
                timeline: {showBarLabels: false, tooltipDateFormat: 'HH:mm'},
                avoidOverlappingGridLines: false,                  
          
                // Set horizontal axis format
                <?php
                    switch ($StartDateFormat) 
                    {
                        case "1h":          echo("hAxis: {format: 'H:mm'}"); 
                                            break;
                        case "6h":          echo("hAxis: {format: 'H:mm'}"); 
                                            break;
                        case "12h":         echo("hAxis: {format: 'H:mm'}"); 
                                            break;
                        case "today":       echo("hAxis: {format: 'H:mm'}"); 
                                            break;
                        case "24h":         echo("hAxis: {format: 'H:mm'}"); 
                                            break;
                        case "yesterday":   //echo("hAxis: {format: 'EEE H \'Uhr\''}"); 
                                            echo("hAxis: {format: 'EEE H:mm'}"); 
                                            break;
                        case "3days":       echo("hAxis: {format: 'EEE HH:mm'}"); 
                                            break;
                        case "all":         echo("hAxis: {format: 'EEE, d.M.'}"); 
                                            break;
                    }
                ?>                
            };
			
			  google.visualization.events.addListener(chart, 'ready', function () {
				var rects = Array.from(container.getElementsByTagName('rect'));
				var rows = rects.filter(rect => (rect.getAttribute('x') === '0') && (rect.getAttribute('stroke') === 'none'));
				rows.sort((a,b) => (a.getAttribute('y') > b.getAttribute('y')) ? 1 : 0);
				
				var rowStatusesJson = $.ajax({
					url: "getStatuses.php",
					dataType:"json",
					async: false
				}).responseText;
				
				var rowStatuses = JSON.parse(rowStatusesJson);
				
				
				for (const [machine_number, status] of Object.entries(rowStatuses)) {
				  var row = rows[machine_number - 1];
				  if (status == "0") {
					  row.setAttribute('fill', '#f7d7d4'); // very light red
				  } else if (status == "1"){
					  row.setAttribute('fill', '#e7f0fe'); // very light blue
				  } else {
					  row.setAttribute('fill', '#ffffff'); // white
				  }
				}

			  });
            
            chart.draw(data, options);
        }

        </script>
        

    
    </head>

  <body>    

    <?php 
		include 'credentials.php';
        // Activate MySQL Connection
        $MysqlConnnection = mysqli_connect($creds['mysql_host'], $creds['mysql_username'], $creds['mysql_password'], $creds['mysql_database']);
        if (!$MysqlConnnection) {
          die('Unable to connect: ' . mysqli_error($MysqlConnnection));
        }
        
        // Get machines with surveillance enabled
        $MachineQuery = "SELECT COUNT(*) FROM Machines WHERE enable=1";
        $NumberEnabledMachines = mysqli_query($MysqlConnnection, $MachineQuery)->fetch_array()["COUNT(*)"];
        
        // Calculate height of timeline from number of machines
        $TimelineHeight = (45 * ($NumberEnabledMachines -1) + 100) . "px; ";
        
        // Create div for timeline and set height and width
        echo "<div id='chart_div' style='height: " . $TimelineHeight . "width: 95%;'></div>";        
    ?>

    <form action="changeSettings.php" method="post">
    
    <div align="center" class="col-sm-3 col-md-3">
        <h3 >Daten</h3>
        <select name="StartDate" class="selectpicker" title="Datenbereich">
          <option value="1h">der letzten Stunde</option>
          <option value="6h">der letzten 6 Stunden</option>
          <option value="12h">der letzten 12 Stunden</option>
          <option value="today">von heute</option>
          <option value="24h">der letzten 24 Stunden</option>
          <option value="yesterday">seit gestern</option>
          <option value="3days">seit drei Tagen</option>
          <option value="all">alle</option>
        </select>
    </div>
   
    <div align="center" class="col-sm-3 col-md-3"><br><br>
        <input type="submit" name="submit" class="btn btn-info" value="Aktualisieren" />        
    </div>
    
    <div align="center" class="col-sm-3 col-md-3">
        <h3> Maschinen</h3>
        <ul class="list-unstyled">
       
        <?php       
        $MachineQuery = "SELECT id,name,enable from Machines";
        $MachineResult = mysqli_query($MysqlConnnection, $MachineQuery);
        
        foreach($MachineResult as $MachineRow)
        { 
            $MachineId      = $MachineRow["id"];
            $MachineName    = $MachineRow["name"];
            $MachineEnable  = $MachineRow["enable"];
            
            echo "<li>";            
                echo "<div class='btn-group' data-toggle='buttons'><label class='btn btn-info ";
                if ($MachineEnable != 1) {echo "active";}
                echo "'><input type='radio' name='";
                echo $MachineName;
                echo "' value=0 autocomplete='off'";
                if ($MachineEnable != 1) {echo " checked=checked ";}
                echo ">0</label><label class='btn btn-info ";
                if ($MachineEnable == 1) {echo "active";}	
                echo "'><input type='radio' name='";
                echo $MachineName;
                echo "' value=1 autocomplete='off' ";
                if ($MachineEnable == 1) {echo " checked=checked ";}
                echo ">1</label></div><br>";
                echo $MachineName;
            echo "</li><br>"; 
        }  
        $MachineResult->free();        
        ?>
        </ul>
    </div> 
   
    <div align="center" class="col-sm-3 col-md-3">
        <h3> Telegram-User</h3>
        <ul class="list-unstyled">
        
        <?php       
        $TelegramQuery = "SELECT id,name,enable from TelegramUsers";
        $TelegramResult = mysqli_query($MysqlConnnection, $TelegramQuery);
        
        foreach($TelegramResult as $TelegramRow)
        { 
            $TelegramId      = $TelegramRow["id"];
            $TelegramName    = $TelegramRow["name"];
            $TelegramEnable  = $TelegramRow["enable"];
            
            echo "<li>";            
                echo "<div class='btn-group' data-toggle='buttons'><label class='btn btn-info ";
                if ($TelegramEnable != 1) {echo "active";}
                echo "'><input type='radio' name='";
                echo $TelegramName;
                echo "' value=0 autocomplete='off'";
                if ($TelegramEnable != 1) {echo " checked=checked ";}
                echo ">0</label><label class='btn btn-info ";
                if ($TelegramEnable == 1) {echo "active";}	
                echo "'><input type='radio' name='";
                echo $TelegramName;
                echo "' value=1 autocomplete='off' ";
                if ($TelegramEnable == 1) {echo " checked=checked ";}
                echo ">1</label></div><br>";
                echo $TelegramName;
            echo "</li><br>";        
        }
        $TelegramResult->free();        
        ?>        
        </ul>
    </div>
    

    </form>
  </body>
</html>
