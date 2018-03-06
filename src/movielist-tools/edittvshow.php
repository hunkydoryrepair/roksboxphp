<?php 
	include 'moviesetup.php';
	include 'TVDB.php';
	

	function getBanners($movieinfo)
	{
        $bannerxml = $movieinfo->getBanners();
//        var_dump( $actorxml );
        $banners = "";
        if ($bannerxml) {
            
            foreach( $bannerxml->Banner as $banner ) {
                $banners .= "<thumb>" . TVDB::baseUrl . "banners/" . $banner->BannerPath . "</thumb>";
            }
        }
        return $banners;
	}


	function getActors($movieinfo)
	{
        $actorxml = $movieinfo->getActors();
//        var_dump( $actorxml );
        $actors = "";
        if ($actorxml) {
            
            foreach( $actorxml->Actor as $actor ) {
                if (!empty($actors)) $actors .= " /\n";
                $actors .= $actor->Name . " AS " . $actor->Role;
            }
        }
        return $actors;
	}



	function addShowInfoToLocalDB( $db, $movieinfo )
	{

		if (!empty($movieinfo['localdbid'])) {
			$insertq = "UPDATE tvshow set "
				. "c00='" . SQLite3::escapeString($movieinfo['title']) . "'," 
				. "c01='" . SQLite3::escapeString($movieinfo['summary']) . "'," 
				. "c02='" . SQLite3::escapeString($movieinfo['status']) . "'," 
				. "c05='" . SQLite3::escapeString($movieinfo['year']) . "'," 
				. "c06='" . SQLite3::escapeString($movieinfo['allthumbs']) . "'," 
				. "c08='" . SQLite3::escapeString($movieinfo['genres']) . "'," 
				. "c12='" . SQLite3::escapeString($movieinfo['tvdbid']) . "'," 
				. "c13='" . SQLite3::escapeString($movieinfo['rating']) . "'," 
				. "c14='" . SQLite3::escapeString($movieinfo['network']) . "' "
	
			  . " WHERE idShow = '" . SQLite3::escapeString ($movieinfo['localdbid']) . "'";

			$localid = $movieinfo['localdbid'];
			$db->exec($insertq);

		}
		else {
			$insertq = "INSERT INTO tvshow (c00,c01,c02,c05,c06,c08,c14,c12,c13) VALUES ("
				. "'" . SQLite3::escapeString($movieinfo['title']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['summary']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['status']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['year']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['allthumbs']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['genres']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['network']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['tvdbid']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['rating']) . "'"
			  . ")";

			if ($db->exec($insertq))
				$localid = $db->lastInsertRowID();
		}

		if ($localid) 
		{

		$movieinfo['localdbid'] = $localid;

		//
		// check link to path
		//
		$filepath = $movieinfo['filepath'];
		$idpath  = $db->querySingle("SELECT idPath FROM path WHERE strPath like '" . SQLite3::escapeString ($filepath) . "'");
		if (empty($idpath))
		{
			if ($db->exec("INSERT INTO path (strPath,strContent) VALUES ('" . SQLite3::escapeString($filepath) . "', 'TV Shows')"))
			{
				$idpath = $db->lastInsertRowID();
			}
		}

		$idlink = $db->querySingle("SELECT * from tvshowlinkpath WHERE idShow = '" . $localid . "' AND idPath = '" . $idpath . "'", true );
		if (!$idlink) {
			$db->exec("INSERT INTO tvshowlinkpath (idShow, idPath) VALUES (" . $localid . ", " . $idpath . ")" );
		}
		$db->exec("DELETE FROM tvshowlinkpath WHERE idShow='$localid' AND idPath <> '$idpath'" );


		

		//
		// remove all existing genres
		//
		$db->exec("DELETE FROM genrelinktvshow WHERE idShow='" . $localid . "'");

		//
		// update genres
		//
		$genres = explode("/", $movieinfo['genres']);
		foreach( $genres as $genre )
		{
			$genre = trim($genre);
			if (empty($genre)) continue;

			// search db if this genre exists
			$idgenre = $db->querySingle("SELECT idGenre FROM genre WHERE strGenre like '" . SQLite3::escapeString($genre) . "'");
			if (empty($idgenre))
			{
				if ($db->exec("INSERT INTO genre (strGenre) VALUES ('" . SQLite3::escapeString($genre) . "')"))
				{
					$idgenre = $db->lastInsertRowID();
				}
			}

			// associate genre with movie
			$db->exec("INSERT INTO genrelinktvshow (idGenre, idShow) VALUES ('" . $idgenre . "','" . $localid . "')");
		}


		//
		// remove all existing people
		//
		$db->exec("DELETE FROM actorlinktvshow WHERE idShow='" . $localid . "'");

		//
		// update actors
		//
		$actors = explode("/", $movieinfo['actors']);
		foreach( $actors as $actor )
		{
			$actor = trim($actor);
			if (empty($actor)) continue;

			// split actor and role
			$spl = explode(" AS ", $actor);

			// search db if this person exists
			$idactor = $db->querySingle("SELECT idActor FROM actors WHERE strActor like '" . SQLite3::escapeString(trim($spl[0])) . "'");
			if (empty($idactor))
			{
				if ($db->exec("INSERT INTO actors (strActor) VALUES ('" . SQLite3::escapeString(trim($spl[0])) . "')"))
				{
					$idactor = $db->lastInsertRowID();
				}
			}

			// associate person with movie
			$db->exec("INSERT INTO actorlinktvshow (idActor, idShow, strRole) VALUES ('" . $idactor . "','" . $localid . "','" 
						. SQLite3::escapeString(trim($spl[1])) . "')");
		}

		}
		else 
		{
			print "Did not get local movie id in database.";
		}

		return $movieinfo;
	}


	//
	// get our collection of movie info from the local database
	function gatherShowInfoFromLocal( $db, $localdbid ) 
	{
		$movie = $db->querySingle($select = "SELECT * FROM tvshow JOIN tvshowlinkpath on tvshow.idShow=tvshowlinkpath.idShow JOIN path ON tvshowlinkpath.idPath = path.idPath WHERE tvshow.idShow='" . SQLite3::escapeString($localdbid) . "'", true);

		$movieinfo['localdbid'] = $localdbid;
		if ($movie)
		{
			$movieinfo['title'] = $movie['c00'];
			$movieinfo['summary'] = $movie['c01'];
			$movieinfo['tvdbid'] = $movie['c12'];
			$movieinfo['year'] = $movie['c05'];
			$movieinfo['rating'] = $movie['c13'];
			$movieinfo['status'] = $movie['c02'];
			$movieinfo['genres'] = $movie['c08'];
			$movieinfo['network'] = $movie['c14'];
			$movieinfo['allthumbs'] = $movie['c06'];
			$movieinfo['filepath'] = $movie['strPath'];

			if (preg_match('/\<thumb\>(?P<thumb>.*)\<\/thumb\>/', $movieinfo['allthumbs'], $matches)) {
				$movieinfo['thumb'] = $matches['thumb'];
			}
	
			$actors = $db->query("SELECT * FROM actorlinktvshow JOIN actors ON actorlinktvshow.idActor=actors.idActor WHERE idShow='" . $localdbid . "'");
			$movieinfo['actors'] = "";
			while ($actor = $actors->fetchArray()) {
				if (!empty($movieinfo['actors'] )) $movieinfo['actors'] .= " /\n";
				$movieinfo['actors'] .= $actor['strActor'] . " AS " . $actor['strRole'];
			}
			$actors->finalize();
		}


		return $movieinfo;
	}

	//
	// get our collection of movie info from the post
	function gatherShowInfoFromPost( $params ) 
	{
		if (!empty($params['localdbid']))
			$movieinfo['localdbid'] = $params['localdbid'];
		$movieinfo['title'] = $params['title'];
		$movieinfo['summary'] = $params['summary'];
		$movieinfo['tvdbid'] = $params['tvdbid'];
		$movieinfo['year'] = $params['year'];
		$movieinfo['rating'] = $params['rating'];
		$movieinfo['genres'] = $params['genres'];
		$movieinfo['status'] = $params['status'];
		$filepath = $params['filepath'];
		if (!empty($filepath) && substr($filepath,-1) != "/" && substr($filepath,-1) != "\\" )
			$filepath .= "/";
		$movieinfo['filepath'] = $filepath;

		$movieinfo['network'] = $params['network'];
		$movieinfo['actors'] = $params['actors'];
		$movieinfo['thumb'] = urldecode($params['thumb']);

		$movieinfo['allthumbs'] = urldecode($params['allthumbs']);

		return $movieinfo;
	}

	//
	// gather movie info from the tvdb.
	//
	function gatherShowInfo($filepath, $tvdbid)
	{
		$movieinfo['tvdbid'] = $tvdbid;

        $showinfo = TV_Shows::findById($tvdbid);

//        var_dump( $showinfo );
		$movieinfo['title'] = $showinfo->seriesName;
		$movieinfo['tvdbid'] = $showinfo->id;
		$movieinfo['network'] = $showinfo->network;
        $movieinfo['year'] = date("Y-m-d", $showinfo->firstAired );
		$movieinfo['rating'] = $showinfo->content;
		$movieinfo['status'] = $showinfo->status;
		$movieinfo['genres'] = implode(" / ", $showinfo->genres);
		$movieinfo['summary'] = $showinfo->overview;
		$movieinfo['actors'] = getActors($showinfo);
        $movieinfo['allthumbs'] = getBanners($showinfo);
		$movieinfo['filepath'] = $filepath;
        $movieinfo['thumb'] = $showinfo->banner;


		// get the thumbnails
        if (empty($movieinfo['thumb'])) $movieinfo['thumb'] = $movieinfo['allthumbs'];

		return $movieinfo;

	}


	function showEpisodes( $db, $movieinfo )
	{
		$episodes = $db->query("SELECT * FROM episodeview WHERE idShow = '" . SQLite3::escapeString($movieinfo['localdbid']) . "' " .
			"ORDER BY CAST(c12 AS INTEGER), CAST(c13 AS INTEGER)");

		print '<div style="padding:5px;">';

		print "<H1>Episodes</H1>";
		while( $ep = $episodes->fetchArray() ) {
			print "<A HREF='editepisode.php?localdbid=" . $ep['idEpisode'] . "'>";
			print( " S" . $ep['c12'] . " E" . $ep['c13'] . " - " . $ep['c00'] . "</A><BR/>" );
		}

		$episodes->finalize();
		print "</div>";
	}

	//	
	// create the HTML form for editing and adding the movie info to the database
	//
	function showForm( $movieinfo )
	{
		global $API_KEY;

		print "<FORM METHOD=POST ACTION=\"edittvshow.php\">";

		if (!empty($movieinfo['localdbid']))
			print("<input type=\"hidden\" name=\"localdbid\" value=\"" . urlencode($movieinfo['localdbid']) . "\"/>");

		print "<div class='scroll-y leftcontent' style='width:450px'>";
		print "<div style='padding:15px'>";

		print "<div style='float:right'>";
		if (!empty($movieinfo['localdbid']))
			print "<input type=\"submit\" value=\"Save Changes\" />\n";
		else 
			print "<input type=\"submit\" value=\"Add\" />\n";
		print "</div><div style='clear:both'>&nbsp;</div>";
		
		print "<span style=\"display:inline-block;width:100px\">Title</span><input style=\"width:300px\" type=\"text\" name=\"title\" value=\"" . 
					htmlspecialchars($movieinfo['title']) . "\" />\n";


		print "<BR/><span style=\"display:inline-block;width:100px\">Folder</span><input style=\"width:300px\" type=\"text\" name=\"filepath\" value=\"" . 
					htmlspecialchars($movieinfo['filepath']) . "\" />\n";


		print "<BR/><span style=\"display:inline-block;width:100px\">Genres</span><input style=\"width:300px\" type=\"text\" name=\"genres\" value=\"" . 
					htmlspecialchars($movieinfo['genres']) . "\" />\n";

		print "<BR/>";
		print "First Aired <input type=\"text\" size=10 name=\"year\" value=\"" . 
					htmlspecialchars($movieinfo['year']) . "\" />\n";

		print "Rating <input type=\"text\" size=5 name=\"rating\" value=\"" . 
					htmlspecialchars($movieinfo['rating']) . "\" />\n";


		print "TVDB id <input type=\"text\" size=10 name=\"tvdbid\" value=\"" . 
					htmlspecialchars($movieinfo['tvdbid']) . "\" />\n";


		print "Status <input size=12 type=\"text\" name=\"status\" value=\"" . 
					htmlspecialchars($movieinfo['status']) . "\" />\n";

		print "Network <input type=\"text\" size=5 name=\"network\" value=\"" . 
					htmlspecialchars($movieinfo['network']) . "\" /><BR>\n";


		
		print "<div style=\"display:inline-block;padding:10px\">";
		print "Overview<BR><textarea style=\"width:400px;height:200px\" name=\"summary\">" .
					htmlspecialchars($movieinfo['summary'])  . 
			  "</textarea><BR>\n";
		print "</div>";

		print "<div style=\"display:inline-block;padding:10px\">";
		print "Cast<BR><textarea style=\"width:400px;height:200px\" name=\"actors\">" .
					htmlspecialchars($movieinfo['actors'])  . 
			  "</textarea>\n";
		print "</div></div><BR/>";



		print "</div>";

		ob_flush();
		flush();
		$xmlthumbs = new SimpleXMLElement( "<thumbs>" . $movieinfo['allthumbs'] . "</thumbs>");

        print "<div class='thumbscroller' style='left:450px;'>";
		print "<H1>Thumbnails</H1>";
		$cnt = $xmlthumbs->thumb->count(); 
		for( $i=0; $i<$cnt; $i++ ) {
			$url = $xmlthumbs->thumb[$i];
			
			//
			// modify url to use our cache system
			//
			$urlcache = str_replace(TVDB::baseUrl . "banners/", "", $url);
			$urlcache = dirname($_SERVER['SCRIPT_NAME']) . "/tvimageload.php?BANNERPATH=" . urlencode( str_replace(TVDB::baseUrl . "banners/", "", $url) );		


			print "<div style=\"float:left;margin:10px\"><label for=\"img" . $i . "\"><IMG height=\"256\" src=\"" . $urlcache . 
			            "\"/></label><BR/>";
			print "<input type=\"radio\" name=\"thumb\" id=\"img" . $i . "\" value=\"" . urlencode($url) . "\"" ;
//			if ($url == $movieinfo['thumb']) print " checked";
			print "/>&nbsp;Choose</div>\n";
		}
		print "<input type='hidden' name='allthumbs' value='" . urlencode($movieinfo['allthumbs']) . "'/>";
        print "</div>";
        
		print "</FORM>";	
	}


	header("Content-Type: text/html; charset=UTF-8");
	
    
?>

<HTML><HEAD><TITLE>TV Show Information</TITLE>
<?php include 'styles.php' ?>

</HEAD><BODY>
<div class='header row' >
<H1>TV Show Information</H1>
<A class='navbutton' HREF="moviemanager.php">MANAGER</A>&nbsp;
</div>
<div class="body row" >
<?php    
	
	if ($_SERVER['REQUEST_METHOD']=="POST" && !empty($_POST['title'])) {
		// if title is set, assume the reset are, too.

		$resultfile = NULL;
		$movieinfo = gatherShowInfoFromPost( $_POST );

		if ( isset($_REQUEST['thumb']) ) {
			// cache the thumbnail
			$resultfile  = generateCacheThumbnail($thumburl, substr($movieinfo['filepath'],0,-1) . ".tbn");		
		}


		$db = new RoksDB(true);
		$movieinfo = addShowInfoToLocalDB($db, $movieinfo);


		// put form to the right, episodes to the left
		print "<div style='position:absolute;left:200px;top:0;bottom:0;right:0'>";
		if (!empty($movieinfo['localdbid']))
			print "UPDATED CONTENT<BR/>";
		if ($resultfile) 
			print "UPDATED THUMBNAIL<BR/>";

		showForm($movieinfo);
		print "</div>";
		
		print "<div class='scroll-y' style='position:absolute;background:#cccccc;left:0;top:0;bottom:0;width:200px;border-right:1px solid'>";
		showEpisodes( $db, $movieinfo );
		$db->close();
		print "</div>";

	}
	else if (!empty($_REQUEST['showpath'])) {
		$moviefile = urldecode($_REQUEST['showpath']);
		$tvdbid = urldecode($_REQUEST['tvdbid']);

		$movieinfo = gatherShowInfo($moviefile, $tvdbid);

		showForm($movieinfo);


	}
	else if (!empty($_REQUEST['localdbid'])) {
		$localdbid = urldecode($_REQUEST['localdbid']);

		$db = new MyDB();
		$movieinfo = gatherShowInfoFromLocal($db, $localdbid);

		// put form to the right, episodes to the left
		print "<div style='position:absolute;left:200px;top:0;bottom:0;right:0'>";
		showForm($movieinfo);
		print "</div>";

		print "<div class='scroll-y' style='position:absolute;background:#cccccc;left:0;top:0;bottom:0;width:200px;border-right:1px solid'>";
		showEpisodes( $db, $movieinfo );
		print "</div>";

		$db->close();
		
	}
	
	print "</div></BODY></HTML>\n";

	
	?>






