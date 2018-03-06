<?php 

	include 'moviesetup.php';
	
	header("Content-Type: text/html; charset=UTF-8");    
    
	function getActorImages($actor) {
        
        $actorinfo = getAPI3Result( "search/person", "query=" . rawurlencode($actor) );
        $apiresult = NULL;
        
        if ($actorinfo && $actorinfo['total_results'] > 0) {
            for( $i = 0; $i < $actorinfo['total_results']; $i++ )
            {
                $actorid = $actorinfo["results"][$i]['id'];
                $apiresult[$i] = getAPI3Result("person/" . $actorid . "/images");
                $apiresult[$i]["actor"] = $actorinfo["results"][$i];
            }
        }
        return $apiresult;
	}
    
?>
<!DOCTYPE html>
<html><head><title>Add Actor Images</title>

<?php include 'styles.php' ?>

</head><body>
<div class="header row">
<?php      
    
	
	$basepath = dirname($_SERVER['SCRIPT_NAME']) . "/";

    print "<h1>Set Actor Images</h1>";
	print "<a class='navbutton' HREF=\"" . $basepath . "moviemanager.php\">MANAGER</a>&nbsp;";


    print "</div><div class=\"body row scroll-y\">";    
    
	$db = new RoksDB();
    
    
	$mypath = "";
	
	if ($_SERVER['REQUEST_METHOD']=="POST") {
		$actorname = urldecode($_POST['actor']);
		$thumburl = urldecode($_POST['thumb']);
		$query = "UPDATE actors set strThumb='<thumb>" . SQLite3::escapeString($thumburl) . 
			"</thumb>' WHERE strActor = '" . SQLite3::escapeString($actorname) . "'";
		$res = $db->querySingle($query);
        
        //
        // write the image locally
        //
        $filename = $MOVIE_FS_FILES_BASE . "images/" . $actorname . ".jpg";
        generateCacheThumbnail( $thumburl, $filename );
        
		print "UPDATED " . $res . "<BR>";
		print "<br/><a href=\"" . $_SERVER['SCRIPT_NAME'] . "\"> Return </a>";
	}
	
	else if (array_key_exists('PATH_INFO', $_SERVER)) {
        $config = getAPI3Result("configuration");
    
		$mypath = $_SERVER['PATH_INFO'];
		$params = explode('/',$mypath);
	
		if (strcasecmp($params[1],"actor") === 0 ) {
			$actorname = urldecode($params[2]);
			print "<form method=POST action=\"" . $_SERVER['SCRIPT_NAME'] . "\">";
			print "<input type=\"hidden\" name=\"actor\" value=\"" . urlencode($actorname) . "\"/>\n";
			$actorimages = getActorImages($actorname);
            if ($actorimages != NULL ) {
                $cnt = sizeof($actorimages);
                for( $i=0; $i<$cnt; $i++ ) {
                    $pername = $actorimages[$i]["actor"]["name"];
                    print $pername . "<br/>";
					if (isset($actorimages[$i]["profiles"]) {
						$perimgs = $actorimages[$i]["profiles"];
						//var_dump($perimgs);
						print "<table><TR>";
						$ii = 0;
						foreach( $perimgs as $img ) {
							$fullpath = $config['images']['base_url'] . "w185" . $img['file_path'];
							print "<td valign=\"top\"><label for=\"" . $ii . "\"><IMG src=\"" . $fullpath . "\"/></label><br/>";
							print "<input type=\"radio\" name=\"thumb\" id=\"" . $ii . "\" value=\"" . urlencode($fullpath) . "\"/>";
							print "<label for=\"" . $ii . "\" >Choose</label></td>\n";
							$ii = $ii+1;
						}
					}
                    print "</tr></table>";
                }
            }
			print "<input type=\"submit\" value=\"Submit\" />\n";
			print "</form>";
		}
	}
	else {
		
		$actors = $db->query('SELECT actors.*, count(*) as cnt FROM actors JOIN actorlinkmovie on actors.idActor=actorlinkmovie.idActor GROUP BY actors.idActor order by cnt desc');
		
		print "<div class='scroll-y col' style='left:0;width:50%;'>";
		print "<div style='padding:15px;'>";
		print "<h1>ACTORS</h1>";
		while( $actor = $actors->fetchArray() ) {
			if (empty($actor['strThumb']))
	            print "<strong>";
	        print "<a HREF=\"" . $_SERVER['SCRIPT_NAME'] . "/actor/" . urlencode($actor['strActor']) . "\">" . htmlspecialchars($actor['strActor']) . " (" . $actor['cnt'] . ")</a><br/>\n";
			if (empty($actor['strThumb']))
	            print "</strong>";
		}
		
		print "</div></div><div class='scroll-y col' style='border-left:1px solid;left:50%;right:0'>";
		print "<div style='padding:15px;'>";
		print "<h1>DIRECTORS</h1>";
		$actors = $db->query('SELECT strActor, count(*) as cnt, strThumb FROM actors JOIN directorlinkmovie on actors.idActor=directorlinkmovie.idDirector GROUP BY actors.idActor order by cnt desc');
		
		while( $actor = $actors->fetchArray() ) {
			if (empty($actor['strThumb']))
	            print "<B>";
	        print "<A HREF=\"" . $_SERVER['SCRIPT_NAME'] . "/actor/" . urlencode($actor['strActor']) . "\">" . htmlspecialchars($actor['strActor']) . " (" . $actor['cnt'] . ")</A><BR>\n";
			if (empty($actor['strThumb']))
	            print "</B>";
		}
		print "</div></div>";
		
		
		
		$actors->finalize();
	}

	$db->close();
	
	print "</div></body></html>\n";

	
	?>






