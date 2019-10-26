<?php 
    /**
     * Author Garr Godfrey
     * Scan files, look for .mp4 and look up information on movie or TV database to guess the movie.
     *
     */
    include 'moviesetup.php';
    include 'TVDB.php';
    

    header("Content-Type: text/html; charset=UTF-8");
        
        
    
    $searchstring = NULL;
    if ( array_key_exists('srch', $_REQUEST) ) {
        $searchstring = $_REQUEST['srch'];
    }
    
    $db = new RoksDB();

?><HTML><HEAD><TITLE>Scan for Missing Files</TITLE>
<?php include 'styles.css' ?>
<script src="http://code.jquery.com/jquery-1.4.2.min.js"></script> 

<script>

      function change(divnum) {
            var field = $("#file" + divnum + "_field");
            var sp = $("#file" + divnum + "_span");
            var but = $("#file" + divnum + "_button"); 
            sp.hide();
            but.hide();
            field.css('width', '' + sp.width() + "px" );
            field.change(function () {
                var url = "<?php echo $_SERVER['SCRIPT_NAME'];?>";
                var div = $("#file" + divnum);
                var fname = $("#file" + divnum + "_filename");
                if (console.log)
                    console.log( url + "?srch=" + encodeURIComponent(field.val()) + "&file=" + (fname.val()) );
                div.load(url + "?srch=" + encodeURIComponent(field.val()) + "&file=" + (fname.val()));
                return false;   
            });            
            
            sp.hide();
            field.show();
        }
        
    
        
        </script>
        </head><b>
<div class='header row'><div style='padding-left:15px;'>
<h1>Scan for Missing Files</h1>
<a class='navbutton' HREF="moviemanager.php">MANAGER</a><br/>
</div></div><div class='body row scroll-y'><div style='padding:15px'>
<?php

        $movies = $db->query('select * from movieview order by strPath');

        $divcount = 1;
		if (!empty($movies)) {
			while ($row = $movies->fetchArray()) {
                if (!file_exists($row['strPath'] . $row['strFileName'])) {
                    print "<div><input type='checkbox' name='movies[]' value='" . $row['idMovie'] . "' /> ";
                    print $row['strPath'] . $row['strFileName'] . "</div>";
                }
            }
        }

        print "<br/>Scan Completed";
        print "</div></div></body></html>";

        $db->close();
