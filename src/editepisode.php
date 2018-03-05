<?php 
	include 'moviesetup.php';
	include 'TVDB.php';
	include 'getid3/getid3.php';


	function getActors($movieinfo)
	{
		$actors = "";
		if (empty($movieinfo['casts'])) return $actors;
		if (empty($movieinfo['casts']['cast'])) return $actors;

		foreach( $movieinfo['casts']['cast'] as $member ) {
			if (!empty($actors)) $actors .= " /\n";
			$actors .= $member['name'] . " AS " . $member['character'];
		}
		return $actors;
	}

	function getWriters($movieinfo)
	{
		$director = "";
		if (empty($movieinfo['casts'])) return $director;
		if (empty($movieinfo['casts']['crew'])) return $director;

		foreach( $movieinfo['casts']['crew'] as $member ) {
			if ($member['job'] == "Writer") {
				if (!empty($director)) $director .= " / ";
				$director .= $member['name'];
			}
		}
		return $director;
	}

	function getDirector($movieinfo)
	{
		$director = "";
		if (empty($movieinfo['casts'])) return $director;
		if (empty($movieinfo['casts']['crew'])) return $director;

		foreach( $movieinfo['casts']['crew'] as $member ) {
			if ($member['job'] == "Director") {
				if (!empty($director)) $director .= " / ";
				$director .= $member['name'];
			}
		}
		return $director;
	}


	function addEpisodeInfoToLocalDB( $db, $movieinfo )
	{
		//
		// get fileid and pathid first.
		//
		$filename = $movieinfo['filepath'];
		$path     = dirname($filename) . "/";
		$file     = basename($filename);
		$idpath  = $db->querySingle("SELECT idPath FROM path WHERE strPath like '" . SQLite3::escapeString ($path) . "'");
		if (empty($idpath))
		{
			if ($db->exec("INSERT INTO path (strPath,strContent) VALUES ('" . SQLite3::escapeString($path) . "', 'TV Shows')"))
			{
				$idpath = $db->lastInsertRowID();
			}
		}

		//
		// now add the file
		//
		$idfile = $db->querySingle("SELECT idFile FROM files WHERE idPath='" . $idpath . "' AND strFilename LIKE '" . SQLite3::escapeString ($file) . "'");
		if (empty($idfile)) {
			if ($db->exec($select = "INSERT INTO files (idPath,strFilename) VALUES ('" . $idpath . "', '" . SQLite3::escapeString($file) . "')"))
			{
				$idfile = $db->lastInsertRowID();
			}
		}


		if (!empty($movieinfo['localdbid'])) {
			$insertq = "UPDATE episode set "
				. "c00='" . SQLite3::escapeString($movieinfo['title']) . "'," 
				. "c01='" . SQLite3::escapeString($movieinfo['overview']) . "'," 
				. "c04='" . SQLite3::escapeString($movieinfo['writers']) . "'," 
				. "c05='" . SQLite3::escapeString($movieinfo['year']) . "'," 
				. "c06='" . SQLite3::escapeString($movieinfo['allthumbs']) . "'," 
				. "c09='" . SQLite3::escapeString($movieinfo['duration']) . "'," 
				. "c10='" . SQLite3::escapeString($movieinfo['director']) . "'," 
				. "c12='" . SQLite3::escapeString($movieinfo['season']) . "'," 
				. "c13='" . SQLite3::escapeString($movieinfo['number']) . "'," 
				. "idFile='$idfile'" 
	
			  . " WHERE idEpisode = '" . SQLite3::escapeString ($movieinfo['localdbid']) . "'";

			$localid = $movieinfo['localdbid'];
			$db->exec($insertq);
		}
		else {
			$insertq = "INSERT INTO episode (c00,c01,c04,c05,c06,c09,c10,c12,c13,idFile) VALUES ("
				. "'" . SQLite3::escapeString($movieinfo['title']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['overview']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['writers']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['year']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['allthumbs']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['duration']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['director']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['season']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['number']) . "'," 
				. "'$idfile'" 
	
			  . ")";

			if ($db->exec($insertq))
				$localid = $db->lastInsertRowID();
		}

		if ($localid) 
		{

		$movieinfo['localdbid'] = $localid;

		//
		// add this episode to the SHOW
		//
		if (!empty($movieinfo['showid'])) {
			$linked = $db->querySingle("SELECT * FROM tvshowlinkepisode WHERE idEpisode='" . $localid . "' AND idShow='" .
						SQLite3::escapeString($movieinfo['showid']) . "'");
			if (!$linked) {	
				$db->exec("INSERT INTO tvshowlinkepisode (idShow, idEpisode) VALUES('" . SQLite3::escapeString($movieinfo['showid'])  . "', '" . $localid . "')");
			}
		}

		//
		// remove all existing people
		//
		$db->exec("DELETE FROM actorlinkepisode WHERE idEpisode='" . $localid . "'");

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
			$db->exec("INSERT INTO actorlinkepisode (idActor, idEpisode, strRole) VALUES ('" . $idactor . "','" . $localid . "','" 
						. SQLite3::escapeString(trim($spl[1])) . "')");
		}

		// associate director
		$db->exec("DELETE FROM directorlinkepisode WHERE idEpisode='" . $localid . "'");
		$actors = explode("/", $movieinfo['director']);
		foreach( $actors as $actor )
		{
			$actor = trim($actor);
			if (empty($actor)) continue;

			// search db if this person exists
			$idactor = $db->querySingle("SELECT idActor FROM actors WHERE strActor like '" . SQLite3::escapeString(trim($actor)) . "'");
			if (empty($idactor))
			{
				if ($db->exec("INSERT INTO actors (strActor) VALUES ('" . SQLite3::escapeString(trim($actor)) . "')"))
				{
					$idactor = $db->lastInsertRowID();
				}
			}

			// associate person with movie
			$db->exec("INSERT INTO directorlinkepisode (idDirector, idEpisode) VALUES ('" . $idactor . "','" . $localid . "')");
		}

		// associate writers
		$db->exec("DELETE FROM writerlinkepisode WHERE idEpisode='" . $localid . "'");
		$actors = explode("/", $movieinfo['writers']);
		foreach( $actors as $actor )
		{
			$actor = trim($actor);
			if (empty($actor)) continue;

			// search db if this person exists
			$idactor = $db->querySingle("SELECT idActor FROM actors WHERE strActor like '" . SQLite3::escapeString(trim($actor)) . "'");
			if (empty($idactor))
			{
				if ($db->exec("INSERT INTO actors (strActor) VALUES ('" . SQLite3::escapeString(trim($actor)) . "')"))
				{
					$idactor = $db->lastInsertRowID();
				}
			}

			// associate person with movie
			$db->exec("INSERT INTO writerlinkepisode (idWriter, idEpisode) VALUES ('" . $idactor . "','" . $localid . "')");
		}

		}
		else 
		{
			print "Did not get local episode id in database.";
		}

		return $movieinfo;
	}


	//
	// get our collection of movie info from the local database
	function gatherEpisodeInfoFromLocal( $db, $localdbid ) 
	{
		$movie = $db->querySingle($select = "SELECT * FROM episodeview WHERE idEpisode='" . $localdbid . "'", true);

		$movieinfo['localdbid'] = $localdbid;
		if ($movie)
		{
			$movieinfo['filepath'] = $movie['strPath'] . $movie['strFileName'];
			$movieinfo['showid'] = $movie['idShow'];
			$movieinfo['title'] = $movie['c00'];
			$movieinfo['overview'] = $movie['c01'];
			$movieinfo['writers']  = $movie['c04'];
			$movieinfo['year']     = $movie['c05'];
			$movieinfo['allthumbs']= $movie['c06'];
			$movieinfo['duration'] = $movie['c09'];
			$movieinfo['director'] = $movie['c10'];
			$movieinfo['season']   = $movie['c12'];
			$movieinfo['number']   = $movie['c13'];

	

			$actors = $db->query("SELECT * FROM actorlinkepisode JOIN actors ON actorlinkepisode.idActor=actors.idActor WHERE idEpisode='" . $localdbid . "'");
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
	function gatherEpisodeInfoFromPost( $params ) 
	{
		if (!empty($params['localdbid']))
			$movieinfo['localdbid'] = $params['localdbid'];
		$movieinfo['title'] = $params['title'];
		$movieinfo['filepath'] = $params['filepath'];
		$movieinfo['duration'] = $params['duration'];
		$movieinfo['year'] = $params['year'];
		$movieinfo['showid'] = $params['showid'];
		$movieinfo['season'] = $params['season'];
		$movieinfo['director'] = $params['director'];
		$movieinfo['writers'] = $params['writers'];
		$movieinfo['number'] = $params['number'];
		$movieinfo['overview'] = $params['overview'];
		$movieinfo['actors'] = $params['actors'];
		$movieinfo['thumb'] = urldecode($params['thumb']);
		$movieinfo['allthumbs'] = urldecode($params['allthumbs']);

		return $movieinfo;
	}

	//
	// gather movie info from our file and from the tvdb.
	//
	function gatherEpisodeInfo($db, $moviefile, $showid, $tvdbid, $season, $number)
	{
		$movieinfo['filepath'] = $moviefile;

		
		// get runtime from file
		$meta= new getID3();
		$file = $meta->analyze($moviefile);
		$duration = $file['playtime_seconds'];
		$movieinfo['duration'] = round($duration / 60);

		if (empty($tvdbid)) 
		{
			//
			// get tvdb id from db
			//
			$tvshow = $db->querySingle("SELECT * FROM tvshow WHERE idShow='". $showid . "'", true);
			if ($tvshow) {
				$tvdbid = $tvshow['c12'];
			}
		}

		$movieinfo['tvdbid'] = $tvdbid;
        $showinfo = TV_Shows::findById($tvdbid);

		//
		// find show id from our db.
		//
		if (empty($showid)) {
			// get from db
			$tvshow = $db->querySingle("SELECT * FROM tvshow WHERE c12 = '". SQLite3::escapeString($tvdbid) . "'", true);
			if (!$tvshow)
				$tvshow = $db->querySingle("SELECT * FROM tvshow WHERE c00 like '" . SQLite3::escapeString( $showinfo-> seriesName ) . "'", true);
			
			if ($tvshow)
				$showid = $tvshow['idShow'];
		}

		$episode = $showinfo->getEpisode( $season, $number );

//        var_dump( $showinfo );
		$movieinfo['title'] = $episode->name;
        $movieinfo['year'] = date("Y-m-d", $episode->firstAired );
		$movieinfo['showid'] = $showid;
		$movieinfo['season'] = $season;
		$movieinfo['director'] = implode(" / ", $episode->directors);
		$movieinfo['writers'] = implode(" / ", $episode->writers);
		$movieinfo['number'] = $number;
		$movieinfo['overview'] = $episode->overview;
		$movieinfo['actors'] = implode(" / ", $episode->guestStars);
        $movieinfo['thumb'] = $episode->banner;
        $movieinfo['allthumbs'] = "<thumb>" . $episode->banner . "</thumb>";

		return $movieinfo;
	}

	//	
	// create the HTML form for editing and adding the movie info to the database
	//
	function showForm( $movieinfo )
	{
		print "<FORM METHOD=POST ACTION=\"editepisode.php\">";

		if (!empty($movieinfo['localdbid']))
			print("<input type=\"hidden\" name=\"localdbid\" value=\"" . urlencode($movieinfo['localdbid']) . "\"/>");

		print "<div class='leftcontent scroll-y' style='width:600px;'>";
		print "<div style='padding:15px;'>";
		print "<span style=\"display:inline-block;width:100px\">Title</span><input size=50 type=\"text\" name=\"title\" value=\"" . 
					htmlspecialchars($movieinfo['title']) . "\" />\n";

		if (!empty($movieinfo['localdbid']))
			print "<input type=\"submit\" value=\"Save Changes\" />\n";
		else 
			print "<input type=\"submit\" value=\"Add\" />\n";



		print "<BR/><span style=\"display:inline-block;width:100px\">Director</span><input  size=50 type=\"text\" name=\"director\" value=\"" . 
					htmlspecialchars($movieinfo['director']) . "\" />\n";

		print "<BR/><span style=\"display:inline-block;width:100px\">First Aired</span><input type=\"text\" size=11 name=\"year\" value=\"" . 
					htmlspecialchars($movieinfo['year']) . "\" />\n";

		print "Season <input type=\"text\" size=5 name=\"season\" value=\"" . 
					htmlspecialchars($movieinfo['season']) . "\" />\n";

		print "Episode # <input type=\"text\" size=10 name=\"number\" value=\"" . 
					htmlspecialchars($movieinfo['number']) . "\" />\n";

		print "<BR/><span style=\"display:inline-block;width:100px\">Writers</span><input  size=50 type=\"text\" name=\"writers\" value=\"" . 
					htmlspecialchars($movieinfo['writers']) . "\" />\n";




		print "Runtime <input type=\"text\" size=4 name=\"duration\" value=\"" . 
					$movieinfo['duration'] . "\" />\n";


		print "<BR/><span style=\"display:inline-block;width:100px\">File Path</span><input size=50 type=\"text\" name=\"filepath\" value=\"" . 
					$movieinfo['filepath'] . "\" />\n";

		
		print "<BR/><div style=\"display:inline-block;padding:10px\">";
		print "Overview<BR><textarea style=\"width:400px;height:200px\" name=\"overview\">" .
					htmlspecialchars($movieinfo['overview'])  . 
			  "</textarea><BR>\n";
		print "</div>";

		print "<div style=\"display:inline-block;padding:10px\">";
		print "Cast<BR><textarea style=\"width:400px;height:200px\" name=\"actors\">" .
					htmlspecialchars($movieinfo['actors'])  . 
			  "</textarea>\n";
		print "</div><BR/>";



		print "<BR/>";

		print "</div></div>";

		ob_flush();
		flush();
		print "<div class=\"thumbscroller\" style='left:600px; padding:10px;'>";
		print "<h1>Thumbnail</h1>";
		$xmlthumbs = new SimpleXMLElement( "<thumbs>" . $movieinfo['allthumbs'] . "</thumbs>");

		$cnt = $xmlthumbs->thumb->count(); 
		for( $i=0; $i<$cnt; $i++ ) {
			$url = $xmlthumbs->thumb[$i];
			
			//
			// modify url to use our cache system
			//
			$urlcache = str_replace(TVDB::baseUrl . "banners/", "", $url);
			$urlcache = dirname($_SERVER['SCRIPT_NAME']) . "/tvimageload.php?BANNERPATH=" . urlencode( str_replace(TVDB::baseUrl . "banners/", "", $url) );		

			print "<div style=\"float:left;margin:10px\"><label for=\"img" . $i . "\"><IMG height=\"256\" src=\"" . $urlcache . 			            "\"/></label><BR>";
			print "<input type=\"radio\" name=\"thumb\" id=\"img" . $i . "\" value=\"" . urlencode($url) . "\"" ;
			if ($url == $movieinfo['thumb']) print " checked";
			print "/>&nbsp;Choose</div>";
		}
		print "<input type='hidden' name='allthumbs' value='". urlencode($movieinfo['allthumbs']) . "'/>";
		print "<input type='hidden' name='showid' value='". urlencode($movieinfo['showid']) . "'/>";
	
		print "</div></FORM>";	
	}


	header("Content-Type: text/html; charset=UTF-8");
	
    
?>

<HTML><HEAD><TITLE>TV Show Episode Information</TITLE>
<?php include 'styles.php' ?>

</HEAD><BODY>
<div class='header row'>
<H1>TV Show Episode Information</H1>
<A class='navbutton' HREF="moviemanager.php">MANAGER</A>&nbsp;
<?php    
	
	if ($_SERVER['REQUEST_METHOD']=="POST" && !empty($_POST['title'])) {
		// if title is set, assume the reset are, too.

		$movieinfo = gatherEpisodeInfoFromPost( $_POST );

		if (!file_exists($movieinfo['filepath']))
		{
			print "ERROR: file " . $movieinfo['filepath'] . " does not exist. <BR/>";
		}
		else
		{
			if ( isset($_REQUEST['thumb']) ) {
				$thumburl  = urldecode($_REQUEST['thumb']); 
				// cache the thumbnail
				$destpath = dirname($movieinfo['filepath']) . "/" . basename($movieinfo['filepath'],".m4v") . ".tbn";
	
				$resultfile  = generateCacheThumbnail($thumburl, $destpath);		
				if ($resultfile) 
					print "UPDATED THUMBNAIL<BR/>";
			}
	
			$db = new MyDB();
			$movieinfo = addEpisodeInfoToLocalDB($db, $movieinfo);
			if (!empty($movieinfo['localdbid']))
				print "UPDATED<BR/>";
			$db->close();
		}

		print "</div><div class=\"body row\">";
		showForm($movieinfo);

	}
	else if (!empty($_REQUEST['filepath'])) {
		$episodefile = urldecode($_REQUEST['filepath']);
		$tvdbid = ($_REQUEST['tvdbid']);
		$showid = ($_REQUEST['showid']);
		$season = ($_REQUEST['season']);
		$episode = ($_REQUEST['episode']);

		$db = new MyDB();
		$movieinfo = gatherEpisodeInfo($db, $episodefile, $showid, $tvdbid, $season, $episode);
		$db->close();

		print "</div><div class=\"body row\">";
		showForm($movieinfo);
		
	}
	else if (!empty($_REQUEST['localdbid'])) {
		$localdbid = urldecode($_REQUEST['localdbid']);

		$db = new MyDB();
		$movieinfo = gatherEpisodeInfoFromLocal($db, $localdbid);
		$db->close();

		print "</div><div class=\"body row\">";
		showForm($movieinfo);
		
	}
	
	print "</div></BODY></HTML>\n";

	
	?>






