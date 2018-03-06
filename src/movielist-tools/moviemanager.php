<?php
	// Must emit header first.
	header("Content-Type: text/html; charset=UTF-8");
?>

<html><head><title>RoksBox Movie Manager</title>

<link rel="stylesheet" href="styles.css" >

</head><body>
<div class="header row">
<h1>Movie Manager</h1>


<?php 

	require 'moviesetup.php';
	require 'TVDB.php';
	
	$db = new RoksDB(true);
	
	//
	// we form parameters that look like a path, so relative links
	// will not work. We put the basepath in the URL links
	//
	$basepath = dirname($_SERVER['SCRIPT_NAME']) . "/";

	
	function getTVShowThumbs($show) {

		if (!empty( $show['c12'] ))
	        $showinfo[0] = TV_Shows::findById($show['c12']);
		else
			$showinfo = TV_Shows::search($show['c00']);
			

		if ($showinfo) {
	        $banners = "";
			foreach( $showinfo as $oneshow ) {
		        $bannerxml = $oneshow->getBanners();
		        if ($bannerxml) {
		            
		            foreach( $bannerxml->Banner as $banner ) {
		                $banners .= "<thumb>" . TVDB::baseUrl . "banners/" . $banner->BannerPath . "</thumb>";
		            }
		        }
			}
	
			return new SimpleXMLElement( "<thumbs>" . $banners . "</thumbs>");
		}
		else {
			return NULL;
		}
	}
	
	function getMovieThumbs($movie) {
		$thumbnails = "";
		
		$apimovie = getAPI3Result("search/movie", "query=" . rawurlencode($movie['c00']) . "&year=" . rawurlencode($movie['c07']));
		if ($apimovie && $apimovie['total_results'] > 0) {
			
			$movieapiid = $apimovie["results"][0]['id'];
			
			$config = getAPI3Result("configuration");
			
			$movieimages = getAPI3Result("movie/" . $movieapiid . "/images");
		
			if ($movieimages) {
				foreach($movieimages["posters"] as $poster) {
					$fullpath = $config['images']['base_url'] . "original" . $poster['file_path'];
					$thumbnails .= "<thumb>" . $fullpath . "</thumb>";
				}				
			}
			else {
				error_log("API call failed: getting images: " . $movie['c00'] );
			}
		
		}
		else {
			error_log("API call failed: searching: " . $movie['c00'] );
		}
		
		return new SimpleXMLElement( "<thumbs>" . $thumbnails . "</thumbs>");
	}
	


	//
	// generate a thumbnail from a movie, tvepisode or tvshow
	//
	function generateThumbnail($db, $movie, $thumb) {

		$filepath = $movie['strPath'] . basename($movie['strFileName'],".m4v") . ".tbn";
		
        return generateCacheThumbnail( $thumb, $filepath );
	}
	
	
	

	print "Tools: <a class='navbutton' HREF=\"" . $basepath . "moviescan.php\">SCAN NEW</a>&nbsp;";
	print "<a class='navbutton' HREF=\"" . $basepath . "actorimages.php\">PEOPLE</a>";
    print "</div><div class=\"body row scroll-y\"><div class='content'>";

	$mypath = "";
	
	if ($_SERVER['REQUEST_METHOD']=="POST") {
		$thumburl = urldecode($_POST['thumb']);
		$allthumbs = urldecode($_POST['allthumbs']);
		$resultfile = NULL;
		if( isset($_POST['movieid']) ) 
		{
			$movieid = urldecode($_POST['movieid']);
			$movie = $db->querySingle('SELECT * from movieview WHERE idMovie="' . SQLite3::escapeString($movieid) . '"', true );
			if (!empty($allthumbs)) {
				if ($db->exec("UPDATE movie SET c08 = '" . SQLite3::escapeString($allthumbs) . "' WHERE idMovie = '" .
							SQLite3::escapeString($movieid) . "'" ))
					print "UPDATED LIST<BR>";
			}
			if (!empty($thumburl))
				$resultfile  = generateThumbnail($db, $movie, $thumburl);
		}
		else if( isset($_POST['showid']) ) {
			$movieid = urldecode($_POST['showid']);
			$show = $db->querySingle('SELECT * from tvshow JOIN tvshowlinkpath ON tvshow.idShow = tvshowlinkpath.idShow JOIN path ON tvshowlinkpath.idPath = path.idPath WHERE tvshow.idShow="' . SQLite3::escapeString($movieid) . '"', true );

			if (!empty($allthumbs)) {
				if ($db->exec("UPDATE tvshow SET c06 = '" . SQLite3::escapeString($allthumbs) . "' WHERE idShow = '" .
							SQLite3::escapeString($movieid) . "'" ))
					print "UPDATED LIST<BR>";
			}
			
			if (!empty($thumburl))
				$resultfile  = generateCacheThumbnail($thumburl, substr($show['strPath'],0,-1) . ".tbn");
		}
		if ($resultfile) {
			print "UPDATED THUMB<BR>";
			print "<IMG SRC='". fs2httppath( $resultfile ) . "?test=" . rand() . "'/>";
		}
		print "<BR><A HREF=\"" . $_SERVER['SCRIPT_NAME'] . "\"> Return </A>";
	}
	
	else if (array_key_exists('PATH_INFO', $_SERVER)) {
		$mypath = $_SERVER['PATH_INFO'];
		$params = explode('/',$mypath);
	
        //
        // check if we are editing a movie now
        //
		if (strcasecmp($params[1],"movie") === 0 ) {
			$movieid = urldecode($params[2]);
			print "<FORM METHOD=POST ACTION=\"" . $_SERVER['SCRIPT_NAME'] . "\">";
			print "<input type=\"hidden\" name=\"movieid\" value=\"" . urlencode($movieid) . "\"/>\n";
			

			$movie = $db->querySingle('SELECT * from movieview WHERE idMovie="' . SQLite3::escapeString($movieid) . '"', true );
			
			print "<div class='leftcontent' >";
			print "<div style='padding:15px;'>";
			print "Choose new thumbnail for <B>" . htmlspecialchars($movie['c00']) . "</B><BR/>";
			print "(new image will be stored in same folder as video file with extension .tbn)<BR/>";
			print "Current Image:<BR/>";
			print "<img  src=\"" . filepathencode( fs2httppath($movie['strPath'] . basename($movie['strFileName'],".m4v") . ".tbn")) . "\"/><BR>";
			print "</div></div>";


			print "<div class='thumbscroller'>";			
			print "<BR/>Choose one of the following and click SUBMIT:<BR/>";
			print "<input type=\"submit\" value=\"Submit\" /><BR/>";
			$xmlthumbs = getMovieThumbs($movie);

			$cnt = $xmlthumbs->thumb->count(); 
			$allthumbs = "";
			for( $i=0; $i<$cnt; $i++ ) {
				$url = $xmlthumbs->thumb[$i];

				$allthumbs .= "<thumb>" . $url . "</thumb>";
				
				print "<div style=\"float:left;margin:10px\"><label for=\"img" . $i . "\"><IMG height=\"256\" src=\"" . $url . "?api_key=" . $API_KEY . 
				            "\"/></label><BR>";
				print "<input type=\"radio\" name=\"thumb\" id=\"img" . $i . "\" value=\"" . urlencode($url) . "\"/>&nbsp;Choose</div>";
			}

			print "<input type='hidden' name='allthumbs' value='" . urlencode($allthumbs) . "'/>";


			print "</div>";
			print "</FORM>";
		}
        else if (strcasecmp($params[1],"tvshow") === 0 ) {
			$movieid = urldecode($params[2]);
			print "<FORM METHOD=POST ACTION=\"" . $_SERVER['SCRIPT_NAME'] . "\">";
			print "<input type=\"hidden\" name=\"showid\" value=\"" . urlencode($movieid) . "\"/>\n";
			
			$movie = $db->querySingle('SELECT * from tvshow JOIN tvshowlinkpath ON tvshow.idShow = tvshowlinkpath.idShow JOIN path ON tvshowlinkpath.idPath = path.idPath WHERE tvshow.idShow="' . SQLite3::escapeString($movieid) . '"', true );
			
			print "<div class='leftcontent'>";
			print "<div style='padding:15px;'>";
			print "Choose new thumbnail for <B>" . htmlspecialchars($movie['c00']) . "</B><BR/>";
			print "(new image will be stored in same folder as video file with extension .tbn)<BR/>";
			print "Current Image:<BR/>";
			print "<img  src=\"" . filepathencode( fs2httppath(substr($movie['strPath'],0,-1) . ".tbn")) . "\"/><BR>";
			print "</div></div><div class='thumbscroller'>";

			print "<BR/>Choose one of the following and click SUBMIT:<BR/>";
			print "<input type=\"submit\" value=\"Submit\" /><BR/>\n";
			
			$xmlthumbs = getTVShowThumbs($movie);

			$cnt = $xmlthumbs->thumb->count(); 
			$allthumbs = "";
			for( $i=0; $i<$cnt; $i++ ) {
				$url = $xmlthumbs->thumb[$i];

				$allthumbs .= "<thumb>" . $url . "</thumb>";
				
				//
				// modify url to use our cache system
				//
				$urlcache = str_replace(TVDB::baseUrl . "banners/", "", $url);
				$urlcache = dirname($_SERVER['SCRIPT_NAME']) . "/tvimageload.php?BANNERPATH=" . urlencode( str_replace(TVDB::baseUrl . "banners/", "", $url) );		

				print "<div style=\"float:left;margin:10px\"><label for=\"img" . $i . "\"><IMG height=\"256\" src=\"" . $urlcache . 				            "\"/></label><BR>\n";
				print "<input type=\"radio\" name=\"thumb\" id=\"img" . $i . "\" value=\"" . urlencode($url) . "\"/>&nbsp;Choose</div>";
			}
			print "<input type='hidden' name='allthumbs' value='" . urlencode($allthumbs) . "'/>";

			print "</div>";
			print "</FORM>";
		}        
	}
	else {
		$movies = $db->query('SELECT idMovie, c00 FROM movie order by c00');
		
        print "<div style=\"position:absolute;padding:20px;left:0;right:50%;top:0;bottom:0;overflow-y: auto;\">  ";      
        
		print "<H1>Movies</H1>";
		while( $movie = $movies->fetchArray() ) {
			print " <A HREF=\"" . $_SERVER['SCRIPT_NAME'] . "/movie/" . urlencode($movie['idMovie']) . "\">" . "Thumb" . 
			      "</A>\n";
			print "<A HREF=\"addmovie.php?localdbid=" . urlencode($movie['idMovie']) . "\">" . "Edit" . 
			      "</A>\n";
			print htmlspecialchars($movie['c00']);
            print "<BR/>";
		}
		$movies->finalize();
		print "</div><div style=\"position:absolute;padding:20px;left:50%;right:0;top:0;bottom:0;overflow-y:auto;border-left:1px solid;\">";

		$shows = $db->query('SELECT idShow, c00 FROM tvshow order by c00');
		print "<H1>TV Shows</H1>";
		while( $show = $shows->fetchArray() ) {
			print " <A HREF=\"" . $_SERVER['SCRIPT_NAME'] . "/tvshow/" . urlencode($show['idShow']) . "\">" . "Thumb" . 
			      "</A>\n";
			print "<A HREF=\"edittvshow.php?localdbid=" . urlencode($show['idShow']) . "\">" . "Edit" . 
			      "</A>\n";
			print htmlspecialchars($show['c00']);
            print "<BR/>";
		}
		$shows->finalize();
        
        print "</div></div>";

	}	
	
	
	$db->close();
	
	
	?>
	
	</div>
	</body>
	</html>
