<?php 
	include 'moviesetup.php';
	include 'getid3/getid3.php';

	header("Content-Type: text/html; charset=UTF-8");
	
	function getUSRating($movieinfo)
	{
		$rating = "";
		if (empty($movieinfo['releases'])) return $rating;
		if (empty($movieinfo['releases']['countries'])) return $rating;
			
		foreach( $movieinfo['releases']['countries'] as $country ) {
			if ($country['iso_3166_1'] == "US") {
				$rating = $country['certification'];
				return $rating;
			}
		}
		return $rating;
		
	}


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


	function getGenres($movieinfo)
	{
		$genres = "";
		if (empty($movieinfo['genres'])) return $genres;

		foreach( $movieinfo['genres'] as $genre ) {
			if (!empty($genres)) $genres .= " / ";
			$genres .= $genre['name'];
		}
		return $genres;
	}


	function addMovieInfoToLocalDB( $db, $movieinfo )
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
			if ($db->exec("INSERT INTO path (strPath,strContent) VALUES ('" . SQLite3::escapeString($path) . "', 'movies')"))
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
			$insertq = "UPDATE movie set "
				. "c00='" . SQLite3::escapeString($movieinfo['title']) . "'," 
				. "c01='" . SQLite3::escapeString($movieinfo['overview']) . "'," 
				. "c02='" . SQLite3::escapeString($movieinfo['plot']) . "'," 
				. "c03='" . SQLite3::escapeString($movieinfo['tagline']) . "'," 
				. "c06='" . SQLite3::escapeString($movieinfo['writers']) . "'," 
				. "c07='" . SQLite3::escapeString($movieinfo['year']) . "'," 
				. "c08='" . SQLite3::escapeString($movieinfo['allthumbs']) . "'," 
				. "c09='" . SQLite3::escapeString($movieinfo['imdbid']) . "'," 
				. "c11='" . SQLite3::escapeString($movieinfo['duration']) . "'," 
				. "c12='" . SQLite3::escapeString($movieinfo['mpaa']) . "'," 
				. "c14='" . SQLite3::escapeString($movieinfo['genres']) . "'," 
				. "c15='" . SQLite3::escapeString($movieinfo['director']) . "'," 
				. "c16='" . SQLite3::escapeString($movieinfo['original_title']) . "'," 
				. "idFile='$idfile'" 
	
			  . " WHERE idMovie = '" . SQLite3::escapeString ($movieinfo['localdbid']) . "'";

			$localid = $movieinfo['localdbid'];
			$db->exec($insertq);

		}
		else {
			$insertq = "INSERT INTO movie (c00,c01,c02,c03,c06,c07,c08,c09,c11,c12,c14,c15,c16,idFile) VALUES ("
				. "'" . SQLite3::escapeString($movieinfo['title']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['overview']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['plot']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['tagline']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['writers']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['year']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['allthumbs']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['imdbid']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['duration']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['mpaa']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['genres']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['director']) . "'," 
				. "'" . SQLite3::escapeString($movieinfo['original_title']) . "'," 
				. "'$idfile'" 
	
			  . ")";

			if ($db->exec($insertq))
				$localid = $db->lastInsertRowID();
		}

		if ($localid) 
		{

		$movieinfo['localdbid'] = $localid;

		//
		// remove all existing genres
		//
		$db->exec("DELETE FROM genrelinkmovie WHERE idMovie='" . $localid . "'");

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
			$db->exec("INSERT INTO genrelinkmovie (idGenre, idMovie) VALUES ('" . $idgenre . "','" . $localid . "')");
		}


		//
		// remove all existing people
		//
		$db->exec("DELETE FROM actorlinkmovie WHERE idMovie='" . $localid . "'");

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
			$db->exec("INSERT INTO actorlinkmovie (idActor, idMovie, strRole) VALUES ('" . $idactor . "','" . $localid . "','" 
						. SQLite3::escapeString(trim($spl[1])) . "')");
		}

		// associate director
		$db->exec("DELETE FROM directorlinkmovie WHERE idMovie='" . $localid . "'");
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
			$db->exec("INSERT INTO directorlinkmovie (idDirector, idMovie) VALUES ('" . $idactor . "','" . $localid . "')");
		}

		// associate writers
		$db->exec("DELETE FROM writerlinkmovie WHERE idMovie='" . $localid . "'");
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
			$db->exec("INSERT INTO writerlinkmovie (idWriter, idMovie) VALUES ('" . $idactor . "','" . $localid . "')");
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
	function gatherMovieInfoFromLocal( $db, $localdbid ) 
	{
		$movie = $db->querySingle($select = "SELECT * FROM movieview WHERE idMovie='" . $localdbid . "'", true);

		$movieinfo['localdbid'] = $localdbid;
		if ($movie)
		{
			$movieinfo['filepath'] = $movie['strPath'] . $movie['strFileName'];
			$movieinfo['duration'] = $movie['c11'];
			$movieinfo['title'] = $movie['c00'];
			$movieinfo['imdbid'] = $movie['c09'];
			$movieinfo['original_title'] = $movie['c16'];
			$movieinfo['year'] = $movie['c07'];
			$movieinfo['mpaa'] = $movie['c12'];
			$movieinfo['director'] = $movie['c15'];
			$movieinfo['writers'] = $movie['c06'];
			$movieinfo['tagline'] = $movie['c03'];
			$movieinfo['genres'] = $movie['c14'];
			$movieinfo['overview'] = $movie['c01'];
			$movieinfo['plot'] = $movie['c02'];
			$movieinfo['allthumbs'] = $movie['c08'];

			if (preg_match('/\<thumb\>(?P<thumb>.*)\<\/thumb\>/', $movieinfo['allthumbs'], $matches)) {
				$movieinfo['thumb'] = $matches['thumb'];
			}
	
			$actors = $db->query("SELECT * FROM actorlinkmovie JOIN actors ON actorlinkmovie.idActor=actors.idActor WHERE idMovie='" . $localdbid . "'");
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
	function gatherMovieInfoFromPost( $params ) 
	{
		if (!empty($params['localdbid']))
			$movieinfo['localdbid'] = $params['localdbid'];
		$movieinfo['filepath'] = $params['filepath'];
		$movieinfo['duration'] = $params['duration'];
		$movieinfo['title'] = $params['title'];
		$movieinfo['imdbid'] = $params['imdbid'];
		$movieinfo['original_title'] = $params['original_title'];
		$movieinfo['year'] = $params['year'];
		$movieinfo['mpaa'] = $params['mpaa'];
		$movieinfo['director'] = $params['director'];
		$movieinfo['writers'] = $params['writers'];
		$movieinfo['tagline'] = $params['tagline'];
		$movieinfo['genres'] = $params['genres'];
		$movieinfo['overview'] = $params['overview'];
		$movieinfo['plot'] = $params['overview'];
		$movieinfo['actors'] = $params['actors'];
		$movieinfo['thumb'] = urldecode($params['thumb']);
		$movieinfo['allthumbs'] = urldecode($params['allthumbs']);

		return $movieinfo;
	}

	//
	// gather movie info from our file and from the moviedb.
	//
	function gatherMovieInfo($moviefile, $moviedbid)
	{
		$movieinfo['filepath'] = $moviefile;
		$movieinfo['moviedbid'] = $moviedbid;

		
		if ($moviedbid == "0") {
			//
			// adding as a custom movie with no moviedb link. Like a home movie.
			//
			$info = pathinfo($moviefile);
			$title =  basename($moviefile,'.'.$info['extension']);
			
			$movieinfo['title'] = $title;
			$movieinfo['original_title'] = $title;
			$movieinfo['genres'] = 'Home Movies';
		}
		else
		{
		
			// get runtime from file
			$meta= new getID3();
			$file = $meta->analyze($moviefile);
			$duration = $file['playtime_seconds'];
			$movieinfo['duration'] = round($duration / 60);

			$moviedbinfo = getAPI3Result("movie/" . $moviedbid, "append_to_response=casts,releases,images");

	//		$movieinfo['duration'] = $moviedbinfo['runtime'];
			$movieinfo['title'] = $moviedbinfo['title'];
			$movieinfo['imdbid'] = $moviedbinfo['imdb_id'];
			$movieinfo['original_title'] = $moviedbinfo['original_title'];
			$movieinfo['year'] = substr($moviedbinfo['release_date'],0,4);
			$movieinfo['mpaa'] = getUSRating($moviedbinfo);
			$movieinfo['director'] = getDirector($moviedbinfo);
			$movieinfo['writers'] = getWriters($moviedbinfo);
			$movieinfo['tagline'] = $moviedbinfo['tagline'];
			$movieinfo['genres'] = getGenres($moviedbinfo);
			$movieinfo['overview'] = $moviedbinfo['overview'];
			$movieinfo['plot'] = $moviedbinfo['overview'];
			$movieinfo['actors'] = getActors($moviedbinfo);
			$movieinfo['moviedbruntime'] = $moviedbinfo['runtime'];

			$config = getAPI3Result("configuration");

			// get the thumbnails
			$thumbnails = "";
			foreach($moviedbinfo['images']["posters"] as $poster) {
				$fullpath = $config['images']['base_url'] . "original" . $poster['file_path'];
				if (empty($movieinfo['thumb'])) $movieinfo['thumb'] = $fullpath;
				$thumbnails .= "<thumb>" . $fullpath . "</thumb>";
			}				
			$movieinfo['allthumbs'] = $thumbnails;
		}
		
		return $movieinfo;

	}

	//	
	// create the HTML form for editing and adding the movie info to the database
	//
	function showForm( $movieinfo )
	{
		global $API_KEY;

		print "<form method=POST action='addmovie.php'>";

		if (!empty($movieinfo['localdbid']))
			print("<input type=\"hidden\" name=\"localdbid\" value=\"" . urlencode($movieinfo['localdbid']) . "\"/>");

		print "<div class='leftcontent scroll-y editform'>";
		print "<div style='padding:15px;'>";
		print "<span class='formlabel' style=\"display:inline-block\">Title</span><input size=50 type=\"text\" name=\"title\" value=\"" . 
					htmlspecialchars($movieinfo['title']) . "\" />\n";




		print "<BR/><span class='formlabel' style=\"display:inline-block;\">Original Title</span><input type=\"text\" size=50 name=\"original_title\" value=\"" . 
					htmlspecialchars($movieinfo['original_title']) . "\" />\n";

		print "<span class='formLabel'>Year</span> <input type=\"text\" size=4 name=\"year\" value=\"" . 
					htmlspecialchars($movieinfo['year']) . "\" />\n";


		print "<BR/><span class='formlabel' style=\"display:inline-block;\">Director</span><input  size=50 type=\"text\" name=\"director\" value=\"" . 
					htmlspecialchars($movieinfo['director']) . "\" />\n";

		print "<span class='formLabel'>MPAA</span> <input type=\"text\" size=5 name=\"mpaa\" value=\"" . 
					htmlspecialchars($movieinfo['mpaa']) . "\" />\n";


		print "<BR/><span class='formlabel' style=\"display:inline-block;\">Writers</span><input  size=50 type=\"text\" name=\"writers\" value=\"" . 
					htmlspecialchars($movieinfo['writers']) . "\" />\n";

		print "<span class='formLabel'>IMDB id</span> <input type=\"text\" size=10 name=\"imdbid\" value=\"" . 
					htmlspecialchars($movieinfo['imdbid']) . "\" />\n";


		print "<BR/><span class='formlabel' style=\"display:inline-block;\">Tagline</span><input size=50 type=\"text\" name=\"tagline\" value=\"" . 
					htmlspecialchars($movieinfo['tagline']) . "\" />\n";

		print "<span class='formLabel'>Runtime</span> <input type=\"text\" size=4 name=\"duration\" value=\"" . $movieinfo['duration'] . "\" />\n";
		if( isset($movieinfo['moviedbruntime']) && $movieinfo['moviedbruntime'] != $movieinfo['duration']) 
			print "(" . $movieinfo['moviedbruntime'] . ")";


		print "<BR/><span class='formlabel' style=\"display:inline-block;\" title='Separate with /'>Genres</span><input size=50 type=\"text\" name=\"genres\" value=\"" . 
					htmlspecialchars($movieinfo['genres']) . "\" />\n";


		print "<BR/><span class='formlabel' style=\"display:inline-block;width:100px\">File Path</span><input size=50 type=\"text\" name=\"filepath\" value=\"" . 
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

		if (!empty($movieinfo['localdbid']))
			print "<input type=\"submit\" value=\"Save Changes\" />\n";
		else 
			print "<input type=\"submit\" value=\"Add\" />\n";


		print "<br/>";

		print "</div></div>";

		ob_flush();
		flush();
		print "<div class=\"thumbscroller\" style='padding:10px;'>";
		print "<h1>Thumbnail</h1>";
		$xmlthumbs = new SimpleXMLElement( "<thumbs>" . $movieinfo['allthumbs'] . "</thumbs>");

		$cnt = $xmlthumbs->thumb->count(); 
		for( $i=0; $i<$cnt; $i++ ) {
			$url = $xmlthumbs->thumb[$i];
			
			print "<div style=\"float:left;margin:10px\"><label for=\"img" . $i . "\"><IMG height=\"256\" src=\"" . $url . "?api_key=" . $API_KEY . 
			            "\"/></label><BR>";
			print "<input type=\"radio\" name=\"thumb\" id=\"img" . $i . "\" value=\"" . urlencode($url) . "\"" ;
			if ($url == $movieinfo['thumb']) print " checked";
			print "/>&nbsp;Choose</div>";
		}
		print "<input type='hidden' name='allthumbs' value='". urlencode($movieinfo['allthumbs']) . "'/>";
	
		print "</div></FORM>";	
	}


	
    
?>

<html><head><title>Movie Information</title>
<?php include 'styles.css' ?>

</head><body>
<div class='header row'>
<H1>Movie Information</H1>
<a class='navbutton' HREF="moviemanager.php">MANAGER</a>&nbsp;
</div>
<div class="body row">
<?php    
	
	if ($_SERVER['REQUEST_METHOD']=="POST" && !empty($_POST['title'])) {
		// if title is set, assume the reset are, too.

		$movieinfo = gatherMovieInfoFromPost( $_POST );

		if ( isset($_REQUEST['thumb']) ) {
			$thumburl  = urldecode($_REQUEST['thumb']); 
			// cache the thumbnail
			$destpath = dirname($movieinfo['filepath']) . "/" . basename($movieinfo['filepath'],".m4v") . ".tbn";

			$resultfile  = generateCacheThumbnail($thumburl, $destpath);		
			if ($resultfile) 
				print "UPDATED THUMBNAIL<BR/>";
		}


		$db = new RoksDB(true);
		$movieinfo = addMovieInfoToLocalDB($db, $movieinfo);
		if (!empty($movieinfo['localdbid']))
			print "UPDATED DATA<BR/>";
		$db->close();

		showForm($movieinfo);

	}
	else if (!empty($_REQUEST['moviepath'])) {
		$moviefile = urldecode($_REQUEST['moviepath']);
		$moviedbid = urldecode($_REQUEST['moviedbid']);

		$movieinfo = gatherMovieInfo($moviefile, $moviedbid);

		showForm($movieinfo);
		
	}
	else if (!empty($_REQUEST['localdbid'])) {
		$localdbid = urldecode($_REQUEST['localdbid']);

		$db = new RoksDB();
		$movieinfo = gatherMovieInfoFromLocal($db, $localdbid);
		$db->close();

		showForm($movieinfo);
		
	}
	
	print "</div></BODY></HTML>\n";

	
	?>






