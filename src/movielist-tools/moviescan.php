<?php 
	/**
	 * Author Garr Godfrey
	 * Scan files, look for .mp4 and look up information on movie or TV database to guess the movie.
	 *
	 */
	include 'moviesetup.php';
	include 'TVDB.php';
	

	header("Content-Type: text/html; charset=UTF-8");
		
	function parseFilename( $filename ) {
		$title =  removeExtension($filename);
		$info['title'] = $title;
		if (preg_match('/^(?P<title>.+) \((?P<year>.+)\)$/', $title, $matches)) {
			$info['title'] = $matches['title'];
			if (preg_match('/^\d{4}$/',$matches['year']))
				$info['year'] = $matches['year'];
			else
				$info['qualifier'] = $matches['year'];
			// look for both. in case of "title (year) (qualifier)" or "title (qualifier) (year)"
			if (preg_match('/^(?P<title>.+) \((?P<year>.+)\)$/', $info['title'], $matches)) {
				$info['title'] = $matches['title'];
				if (preg_match('/^\d{4}$/',$matches['year']))
					$info['year'] = $matches['year'];
				else
					$info['qualifier'] = $matches['year'];
			}
		}

		if (preg_match('/(?P<series>.*)S(?P<season>[0-9]+)[-_ ]*E(?P<episode>[0-9]+)[-_ ]*(?P<title>.*)/ix', $title, $matches)) {
			$info['titleend'] = $matches['title'];
			$seriestitle = trim($matches['series']);
			// remove trailing dash and spaces.

			while (preg_match('/(?P<title>.*)[\s-]+$/',$seriestitle, $matches2)) {
				$seriestitle = $matches2['title'];
			}

			$info['seriestitle'] = $seriestitle;
			
			$info['season'] = $matches['season'];
			$info['episode'] = $matches['episode'];
		}

		if (!isset($info['episode'])) {
			if (preg_match('/E[ _\t\.]*(?P<episode>[0-9]+)/ix', $title, $matches)) 
				$info['episode'] = $matches['episode'];
			else if (preg_match('/Ep[ _\t\.]*(?P<episode>[0-9]+)/ix', $title, $matches)) 
				$info['episode'] = $matches['episode'];
			else if (preg_match('/Episode[ _\t\.]*(?P<episode>[0-9]+)/ix', $title, $matches)) 
				$info['episode'] = $matches['episode'];
		}

		$filename = str_replace("\\","/", $filename);

		// check for season in the directory path
		if ( preg_match('/\/(?P<series>.+)\/\/SEASON (?P<season>[0-9]+)\//ix', $filename, $matches)) {
			$info['season'] = $matches['season'];  // we could get conflict...
			$info['series'] = $matches['series'];
		}

		// check for series name in the directory path
		if (!isset($info['series'])) {
			if (preg_match('/\/TV Series\/(?P<series>[^\/]+)\//', $filename, $matches)) 
				$info['series'] = $matches['series'];
		}


		return $info;
	}

	//
	// look at file name and try to determine what it is.
	//
	function searchAndShowForm( $db, $name, $search, $lookup ) {
		$fileinfo = parseFilename($search);
//		var_dump($fileinfo);
		if ($lookup) {
			$foundMatch = false;
			$query = "query=" . rawurlencode($fileinfo['title']) ;
	
			if ( !empty($fileinfo['year']) )
				$query .= "&year=" . rawurlencode($fileinfo['year']);
	
			$matches = getAPI3Result("search/movie", $query );
			if ($matches['total_results'] > 0) {
				$foundMatch = true;
				print "<FORM METHOD=POST ACTION=\"addmovie.php\">";
						
				print "<input type=\"hidden\" name=\"moviepath\" value=\"" . urlencode($name) . "\"/>\n";
	
				$res = $matches['results'];
				$i = 1;
				foreach ($res as $match) {
					print "<input type=\"radio\" name=\"moviedbid\" id=\"" . $match['id']  . 
											"\" value=\"" . $match['id'] . "\"";
					if ($i==1) print " checked";
					print "/>";
					print $match['title'] . " (" . substr($match['release_date'],0,4) . ")<BR/>";
					$i++;
				}
				print "<input type=\"submit\" value=\"Add\" />\n";
				print "</FORM>";
			}
	
			//
			// check for possible TV show
			if ((isset($fileinfo['series']) || isset($fileinfo['seriestitle'])) && isset($fileinfo['episode'])) 
			{
				// see if show already exists in DB, otherwise add option to add show.
				if (isset($fileinfo['series']))
					$seriesname = $fileinfo['series'];
				else
					$seriesname = $fileinfo['seriestitle'];

				// see if show already exists in DB, otherwise add option to add show.
				$tvshows = $db->query("SELECT * FROM tvshow WHERE c00 like '" .  
							SQLite3::escapeString($seriesname) . "'");

				print "<BR>Identified series as $seriesname:<BR>";
				print "<FORM METHOD=POST ACTION='editepisode.php'>";
				print "<input type=\"hidden\" name=\"filepath\" value=\"" . urlencode($name) . "\"/>\n";
				print "<input type=\"hidden\" name=\"season\" value=\"" . $fileinfo['season'] . "\"/>\n";
				print "<input type=\"hidden\" name=\"episode\" value=\"" . $fileinfo['episode'] . "\"/>\n";
				$i = 0;

				if ($tvshows) {
					print '<BR>Add To Existing TV Show:<BR>';
	
					while( $tvshow = $tvshows->fetchArray() ) {
						$foundMatch = true;
						print "<input type=\"radio\" name=\"showid\" id=\"" . $tvshow['idShow']  . 
												"\" value=\"" . $tvshow['idShow'] . "\"";
						if ($i==0) print " checked";
						print "/>";
						print "<label for='" . $tvshow['idShow'] . "'>";
						print htmlspecialchars($seriesname) . " (" . substr($tvshow['c05'],0,4) . ")</label><BR/>";

						$i++;
					}	
					$tvshows->finalize(); 
				}
				else {
					print '<BR>No Matching TV Shows in DB<BR>';
				}	
				
				// search online db for matches
				$showinfos = TV_Shows::search($seriesname);
//				var_dump($showinfos);
				$newshows = NULL;
				foreach( $showinfos as $showinfo ) {
					$tvdbid = $showinfo->seriesid;
					// see if show already exists in DB, otherwise add option to add show.
					$tvshow = $db->querySingle("SELECT * FROM tvshow WHERE c12 = '" . SQLite3::escapeString($tvdbid) . "'", true);
					if ($tvshow) {
						// if it is an exact match, we should already have shown it.
						if (strcasecmp( $showinfo->seriesName, $seriesname ) != 0) {
							$foundMatch = true;
							print "<input type=\"radio\" name=\"showid\" id=\"" . $tvshow['idShow']  . 
													"\" value=\"" . $tvshow['idShow'] . "\"";
							if ($i==0) print " checked";
							print "/>";
							print "<label for='" . $tvshow['idShow'] . "'>";
							print htmlspecialchars($seriesname) . " (" . substr($tvshow['c05'],0,4) . ")</label><BR/>";
	
							$i++;
						}
					}
					else {
						$newshows[] = $showinfo;
					}
				}
				
				if ($i > 0) print "<input type=\"submit\" value=\"Add\" />\n";
				print "</FORM>";

				if (count($newshows) > 0) {
					print "<BR>Add a New TV Show for this file:<BR>";
					print "<FORM METHOD=POST ACTION='edittvshow.php'>";
					print "<input type=\"hidden\" name=\"showpath\" value=\"" . urlencode(dirname($name) . "/") . "\"/>\n";

					$i = 0;
					foreach( $newshows as $showinfo ) {
						$foundMatch = true;
						print "<input type=\"radio\" name=\"tvdbid\" id=\"" . $showinfo->seriesid  . 
												"\" value=\"" . $showinfo->seriesid . "\"";
						if ($i==0) print " checked";
						print "/>";
						print "<label for='" . $showinfo->seriesid . "'>";
						if ($showinfo->FirstAired)
							$released = substr($showinfo->FirstAired,0,4);
						else
							$released = "unaired";
						print htmlspecialchars($showinfo->SeriesName) . " (" . $released . ")</label><BR/>";

						$i++;
					}
					print "<input type=\"submit\" value=\"Add\" />\n";
					print "</FORM>";
				}
				
			}
			//
			// if we didn't match ANYTHING, then support adding this with custom settings.
			if (!$foundMatch) {
				
				print "<form method=POST action=\"addmovie.php\">";
						
				print "<input type=\"hidden\" name=\"moviepath\" value=\"" . urlencode($name) . "\"/>\n";
	
				print "<input type=\"radio\" name=\"moviedbid\" value=\"0\" checked=checked />";
				print "Add as custom video <br/>";
				print "<input type=\"submit\" value=\"Add\" />\n";
				print "</form>";
				
				
			}			
		}
	}

	
	$searchstring = NULL;
    if ( array_key_exists('srch', $_REQUEST) ) {
        $searchstring = $_REQUEST['srch'];
    }
        
	if ( empty($searchstring) ) {


        $count = 0;
        $dir = $MOVIE_FS_FILES_BASE;

        $path = realpath($dir);
	
        $db = new RoksDB();

        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), 
        RecursiveIteratorIterator::SELF_FIRST);


?>
		<HTML><HEAD><TITLE>Scan for New Movies</TITLE>
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
					console.log( url + "?srch=" + encodeURIComponent(field.val()) + "&file=" + encodeURIComponent(fname.val()) );
                div.load(url + "?srch=" + encodeURIComponent(field.val()) + "&file=" + encodeURIComponent(fname.val()));
                return false;   
            });            
            
            sp.hide();
			field.show();
		}
        
    
        
		</script>
		</head><b>
<div class='header row'><div style='padding-left:15px;'>
<h1>Scan for New Files</h1>
        <a class='navbutton' HREF="moviemanager.php">MANAGER</a><br/>
</div></div><div class='body row scroll-y'><div style='padding:15px'>
<?php

		$divcount = 1;
		foreach($objects as $name => $object){
			if (preg_match('/\/DVD Extras\//', $name)) continue;

			if (preg_match('/\.m4v$/', $name) != 0 ||
			  preg_match('/\.mp4$/', $name) != 0 ||
			preg_match('/\.mkv$/', $name) != 0) {
				$res = $db->querySingle($select = 'SELECT * FROM files JOIN path ON files.idPath = path.idPath WHERE strFileName like \'' 
						. SQLite3::escapeString(basename($name)) . '\' AND strPath like \'' .
						SQLite3::escapeString(dirname($name)) . '/\'');  

				// test if this file exists in the DB
				if (empty($res)) {
					print "<div style='border:1px solid; margin:5px;'>";

					print "<input style=\"display:none\" type='text'  id='file" . $divcount . "_field' value='" . htmlentities( basename($name) ) . "'/>";
					print "<input  type='hidden'  id='file" . $divcount . "_filename' value='" . urlencode( $name ) . "'/>";
					print "<span id='file" . $divcount . "_span'>" .  htmlentities( basename($name) ) . "</span>";
					print "<button id='file" . $divcount . "_button' onclick=\"change(" . $divcount . ");\" type='button'>Edit</button>";
					print( "<BR/>" );
					print( "<div id='file" . $divcount . "'>" );
					searchAndShowForm(  $db, $name, $name, true );
					print( "</div></div>" );
					$divcount++;
					ob_flush();
					flush();
				}
				else {
					//searchAndShowForm( $db, $name, $name, false );
				}
			}
		}
		
		print "</div></div></body></html>";

		$db->close();
	}
	else {
        $db = new RoksDB();
		// just return the single form for this one element
		$name = $_REQUEST['file'];
		searchAndShowForm( $db, $name, $searchstring,true );
		ob_flush();
		flush();
		$db->close();
	}
