<?php 
	//
	// This is unrelated to movielist. Intended as a game for naming actors.
	//

	$API_KEY = '835727baa2a8325eab45362f7fed6f98';
	
	header("Content-Type: text/html; charset=UTF-8");
		
	$APIQUERYCOUNT = 0;
	
	function getAPI3Result($request,$params = NULL)
	{
		global $API_KEY;
		global $APIQUERYCOUNT;
	
		set_time_limit(30);

		$APIQUERYCOUNT = $APIQUERYCOUNT+1;

		$query = "http://api.themoviedb.org/3/" . $request . "?api_key=" . $API_KEY;
		if (!empty($params))  $query .= "&" . $params;
	
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $query);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);

		$content = curl_exec($ch);
		$headers = curl_getinfo($ch);

		$error_number = curl_errno($ch);
		$error_message = curl_error($ch);

		if (!empty($error_message))
			error_log("API error: " . $error_message);

		curl_close($ch);	
	
		return $content;
	}
	
	function strip_str($input)
	{
		return strtoupper(str_replace(array(" ","\t","\n","-","'","."), '', $input));
	}

	function sort_on_lev($first,$second) {
			if ($first['lev'] == $second['lev']) return 0;
			return ($first['lev'] < $second['lev']) ? -1 : 1;
	}


	class MyDB 
	{
		const mysqluser = "talltree_agame";
		const mysqlpass = "3game4";
		const mysqldb   = "talltree_tmdbcache";

		private $mysqli = null;

		function __construct()
		{

			if (empty($this->DBH)) {
				$this->mysqli = new mysqli(ini_get("mysql.default_host"),self::mysqluser,self::mysqlpass,self::mysqldb);
				if ($this->mysqli->connect_errno) {
				    printf("(Connect failed: %s\n)<BR><BR>", $this->mysqli->connect_error);
					$this->mysqli = null;
				}
			}
		}

		function __destruct() {
			if ($this->mysqli)
				$this->mysqli->close();
		}

		function makeRequest($request,$params = NULL) {

			GLOBAL $APIQUERYCOUNT;

			$tmdbquery = $request;
			if (!empty($params))
				$tmdbquery .= "?" . $params;
			$contents = "";
			$usedcache = true;

			if (!empty($this->mysqli)) {
				$query = "SELECT id, TIMESTAMPDIFF(DAY,`when`,CURRENT_TIMESTAMP) as age, response FROM queries WHERE query=\"" . 
					$this->mysqli->real_escape_string($tmdbquery) . "\"";

				$res = $this->mysqli->query($query);
				$row = null;
				$age = 0;
				if ($res) {
					if ($row = $res->fetch_assoc()) {
						// ignore age for now
						$contents = $row['response'];
						$age  = $row['age'];
					}
				}


				if (empty($contents) ||
					($age > 5 && $APIQUERYCOUNT<5)) {   // if result is several days old and
													    // we haven't made that many requests yet, update it
					$contents = getAPI3Result($request,$params);
					if (!empty($contents)) {
						if ($row) {
							$query = "UPDATE queries SET response=\"" . $this->mysqli->real_escape_string($contents) 
										. "\" WHERE id='" . $row['id'] . "'";
						}
						else {
							$query = "INSERT INTO queries (query,response) VALUES (\"" .
									$this->mysqli->real_escape_string($tmdbquery) . "\", \"" .
									$this->mysqli->real_escape_string($contents) . "\")";
						}
						if (!$this->mysqli->query( $query ))
							print "db update fail " . $this->mysqli->error;
					}
					$usedcache = false;
				}
				else {
//					print "retrieved from cache.";
				}
			}
			else {
				$contents = getAPI3Result($request,$params);
			}

			$result = json_decode($contents, true);
			if (!$result) {
				print("querying " . $tmdbquery . "<BR>");
				if ($usedcache) print("from cache!<BR>");
				print("Error decoding: " . $contents . "<BR>");
			}
			return $result;		
		}
	}

	
	
	

	
	print "<HTML><HEAD><TITLE>The Actor Game</Title></HEAD><BODY OnLoad=\"document.mainform.playerentry.focus();\">\n";
	$mypath = "";
	
	$db = new MyDB();


	if ($_SERVER['REQUEST_METHOD']=="POST") {
		$state = urldecode($_POST['actorormovie']);
		$history = urldecode($_POST['gamehistory']);
		$entry = urldecode($_POST['playerentry']);
		$previous = urldecode($_POST['givenvalue']);
		$previousid = urldecode($_POST['givenid']);
		$score    = urldecode($_POST['score']);
//		print ("You entered: " . $entry . "<BR>");


		if (empty($entry)) {
			print("That was not a valid entry. Please try again.");
			if ($state == "actor") {
				print("<BR>Name another actor from <I>" . htmlspecialchars($previous) . "</I><BR>"); 
			}
			else {
				print("<BR>Name another movie with <B>" . htmlspecialchars($previous) . "</B><BR>"); 
			}
		}
		else if ($state == "actor")
		{
			$character = "";
			$actorid = "";
			$valid = false;
			$moviegenreid = 0;
			if (!empty($previous)) {
				//
				// check to make sure this actor was in the given movie.
				//
				$moviecast = $db->makeRequest("movie/" . $previousid . "/casts");
				$entrystripped = strip_str($entry);
				$castmembers = $moviecast['cast'];
				$castlist = array();
				foreach( $castmembers as $castmember ) {
					$castlist[] = $castmember['name'];
					$strippedname = strip_str($castmember['name']);

					// compare names in all kinds of ways
					if( strcasecmp( $strippedname, $entrystripped) == 0 ||
						metaphone($strippedname) == metaphone($entrystripped) ||
						levenshtein($strippedname,$entrystripped) <= 3)
					{
						$character = $castmember['character'];
						if (empty($character)) $character = "a part";
						$entry = $castmember['name']; // update spelling to match database
						$actorid = $castmember['id'];
						break;
					}
				}

				$movie = $db->makeRequest("movie/" . $previousid);
				$config = $db->makeRequest("configuration");
				
				if ( !empty($movie['backdrop_path']) ) {			
					print("<IMG height=256 src=\"" . $config['images']['base_url'] . "original" . 
                                      $movie['backdrop_path']. "\" /><BR>");
				} 
				else if ( !empty($movie['poster_path']) ) {			
					print("<IMG height=256 src=\"" . $config['images']['base_url'] . "original" . 
                                      $movie['poster_path']. "\" /><BR>");
				} 

				if ($movie['belongs_to_collection'])
					$moviegenreid = $movie['belongs_to_collection']['id'];

				if (empty($character)) {
					//
					// GAME OVER
					//
					print("Sorry, <B>" . htmlspecialchars($entry) . "</B> was not found in the movie <I>" . 
								htmlspecialchars($previous) . "</I>.<BR>");
					print("Other actors are: ");
					$first = true;
					foreach( $castlist as $castmember ) {
						if (!$first) print(", ");
						print(htmlspecialchars($castmember) );
						$first = false;
					}
					print("<BR>Your final score was <B>" . $score . "</B><BR><BR> Start a new game. <BR>");
					$state = "movie";
					$history = "";
					$previous = "";
					$previousid = "";
					$score = 0;
				}
				else if ( stristr($history, "|" . $entry . "|") ) {
					print("That actor has already been used during this game. Choose another.");
					print("<BR>Name another actor from <I>" . $previous . "</I><BR>"); 
					$valid = false;
				}
				else {
					print(" YES! <B>" . htmlspecialchars($entry) . "</B> played " . 
						htmlspecialchars($character) . " in <I>" . htmlspecialchars($previous) . "</I><BR>");
					$valid = true;
					$score = $score + 1;
				}
			}
			else {
				$valid = true;
			}
			
			//
			// if the player entered a value that is acceptable, and we are not starting a new game or requesting
			// a new entry, then update our history and find a response.
			//
			if ($valid) {
				//
				// find another movie that actor was in. This should be accurate as the name is
				// usually exact from the movie match.
				//
				if (empty($actorid)) {
					$actorinfo = $db->makeRequest("search/person","query=" . rawurlencode($entry));
					if ($actorinfo["total_results"] > 0) {
						$actorid = $actorinfo["results"][0]["id"];
						$entry = $actorinfo["results"][0]["name"];
					}
				}
				

				if (!empty($actorid)) {	
					$history = $entry . "|" . $history; // prepend the new value.

					// get list of credits
					$actorcredits = $db->makeRequest("person/" . $actorid . "/credits");
					$mostpopularfilmid = 0;
					$mostpopularvalue  = 0;
					$mostpopularfilmname = "";
					$year = "";
					foreach( $actorcredits['cast'] as $credit ) {
						// check history.
						if (stristr($history, "|" . $credit["title"] . "|")) continue;

						//
						// check movie popularity
						//  try to get most popular movie, but with some randomness
						//  so it is not always the same movie.
						//
						$movieinfo = $db->makeRequest("movie/" . $credit['id']);

						// check genre
						if ($movieinfo['belongs_to_collection'] && 
							$moviegenreid == $movie['belongs_to_collection']['id'])
							$popularity = 1;
						else
							$popularity = $movieinfo['popularity'];

						if (empty($movieinfo['release_date']))
							continue;  


						if ( ($popularity - $mostpopularvalue) > 3 ||
							 $mostpopularvalue == 0 ) {
							$mostpopularvalue = $popularity;
							$mostpopularfilmid = $movieinfo["id"];
							$mostpopularfilmname = $movieinfo["title"];
							$year = $movieinfo['release_date'];
						}
						else if ( abs($popularity - $mostpopularvalue ) <= 3 &&
         								rand(0,1) == 0 )
						{
							$mostpopularfilmid = $movieinfo["id"];
							$mostpopularfilmname = $movieinfo["title"];
							$year = $movieinfo['release_date'];
						}

						// don't take too freaking long.
						if ($APIQUERYCOUNT >= 10 && $mostpopularfilmid != 0) break;
					}

					if (empty($mostpopularfilmname)) {
						print("You Win! I could not find another movie with " . htmlspecialchars($entry) . ".<BR>");
						print("<BR>Your final score was <B>" . $score . "</B><BR><BR> Start a new game. <BR>");
						
						$state = "movie";
						$history = "";
						$previous = "";
						$previousid = "";
						$score = 0;
					}
					else
					{
						$computerresponse = $mostpopularfilmname;
						$tmdbcode = $mostpopularfilmid;
						
						print("<B>" . htmlspecialchars($entry) . "</B> was also in <I>" . htmlspecialchars($computerresponse) . "</I><BR>");
						print("<BR>Name another actor from <I>" . htmlspecialchars($computerresponse) . "</I>");
						if (!empty($year)) {
							print( " (" . substr($year,0,4) . ")" );
						}
						print("<BR>"); 

						// set up response.
						$history = $computerresponse . "|" . $history;
						$previous = $computerresponse;	
						$previousid = $tmdbcode;		
					}
				}
				else {
					print("I cannot find any actor by the name of <B>" . htmlspecialchars($entry) . "</B>.<BR>");
					print("Weird, huh? Try again.");
					print("<BR>Name another actor from <I>" . htmlspecialchars($previous) . "</I><BR>"); 
					$valid = false;
				}
				
				
			}
		}
		else if ($state == "movie")
		{
			$movieid = "";
			$valid = false;

			$entrystripped = strip_str($entry);
						
			if (!empty($previous)) {
								
				//
				// check to make sure this actor was in the given movie. First, look
				// for good matches in their credits.
				//
				$moviecast = $db->makeRequest("person/" . $previousid . "/credits");
				$entrystripped = strip_str($entry);
				$castmembers = $moviecast['cast'];
				$credits = "";
				$character = "";

				foreach( $castmembers as $castmember ) {
					$credits[] = $castmember['title'];  // store this just first time through
				
					$strippedname = $castmember['title'];

					// compare names in all kinds of ways
					if( strcasecmp( $strippedname, $entrystripped) == 0 ||
						metaphone($strippedname) == metaphone($entrystripped) ||
						levenshtein($strippedname,$entrystripped) <= 3)
					{
						// we'll take it!
						$character = $castmember['character'];
						if (empty($character)) $character = "a part";
						$entry = $castmember['title']; // update spelling to match database
						$movieid = $castmember['id'];
						break;
					}

				}

				//
				// if not found by comparison to FULL movie name, let's seach for
				// movie and compare
				//
				if (empty($character)) {
					//
					// find the movie
					//
					$movieresults = NULL;

					$movieinfo = $db->makeRequest("search/movie","query=" . rawurlencode($entry));
					if ($movieinfo["total_results"] == 1) {
						$movieresults[] = $movieinfo["results"][0];
						$entry = $movieinfo["results"][0]["title"];
						$movieid = $movieresults[0]["id"];
					}
					else if ($movieinfo["total_results"] > 1) {
						$entrytest = strip_str($entry);
						foreach( ($movieinfo["results"]) as $movie ) {
							$lev = levenshtein(strip_str($movie["title"]), $entrytest);
							$movie['lev'] = $lev;
							$movieresults[] = $movie;
						}
				
						usort($movieresults, sort_on_lev);
						$entry = $movieresults[0]["title"];
						$movieid = $movieresults[0]["id"];
					}
		
					if ($movieresults) {
						foreach( $castmembers as $castmember ) {
							$i = 0;
							while( $i < 3 && $i < count($movieresults) ) {
								if( $castmember['id'] == $movieresults[$i]['id'] ) {
									$character = $castmember['character'];
									$entry = $castmember['title'];
									$movieid = $movieresults[$i]['id'];
									if (empty($character)) $character = "a part";
									break;
								}
								$i = $i + 1;
							}
							if ( !empty($character) ) break;
						}
					}
				}

				if (empty($movieid)) {
					print("Could not find any movie called <I>" . $entry . "</I>. Try a different spelling.<BR>");
					print("Name a movie with <B>" . htmlspecialchars($previous) . "</B><BR>"); 
					$valid = false;
				}
				else if (empty($character)) {
					print("Sorry, <B>" . htmlspecialchars($previous) . 
						"</B> was not found in the movie <I>" . htmlspecialchars($entry) . 
						"</I>.<BR>");
					print("Other credits include: ");
					$first = true;
					foreach( $credits as $filmtitle ) {
						if (!$first) print(", ");
						print( "<I>" . htmlspecialchars($filmtitle) . "</I>");
						$first = false;
					}

					print("<BR/>Your final score was <B>" . $score . "</B><BR><BR> Start a new game. <BR>");
					$state = "actor";
					$history = "";
					$previous = "";
					$previousid = "";
					$score = 0;
				}
				else if ( stristr($history, "|" . $entry . "|") ) {
					print("That movie has already been used during this game. Choose another.");
					print("Name another movie with <B>" . htmlspecialchars($previous) . "</B><BR>"); 
					$valid = false;
				}
				else {
					print(" YES! <B>" . htmlspecialchars($previous) . "</B> played " . htmlspecialchars($character) . " in <I>" . htmlspecialchars($entry) . "</I><BR>");
					$valid = true;
					$score = $score + 1;
				}
			}
			else {
				$valid = true;
			}
			
			//
			// if the player entered a value that is acceptable, and we are not starting a new game or requesting
			// a new entry, then update our history and find a response.
			//
			if ($valid) {
				//
				// find another actor from the given movie
				//
				if (empty($movieid)) {
					$movieinfo = $db->makeRequest("search/movie","query=" . rawurlencode($entry));
					if ($movieinfo["total_results"] > 0) {
						$entry = $movieinfo["results"][0]["title"];
						$movieid = $movieinfo["results"][0]["id"];
					}				
				}


				
				if (!empty($movieid)) {	
					$history = $entry . "|" . $history; // prepend the new value.

					//
					// get list of cast
					//
					$actorcredits = $db->makeRequest("movie/" . $movieid . "/casts");
					$mostpopularactorid = 0;
					$mostpopularvalue  = 0;
					$mostpopularactorname = "";
					$imagepath = "";
					foreach( ($actorcredits['cast']) as $credit ) {
						if (!stristr($history, "|" . $credit["name"] . "|")) {
								$mostpopularactorname = $credit["name"];
								$mostpopularactorid = $credit['id'];
								$imagepath = $credit['profile_path'];
								break;
						}
					}
					if (empty($mostpopularactorname)) {
						print("You Win! I could not find another actor in " . htmlspecialchars($entry) . ".<BR>");
						print("Your final score was <B>" . $score . "</B><BR><BR> Start a new game. <BR>");
						
						$state = "actor";
						$history = "";
						$previous = "";
						$previousid = "";
						$score = 0;
					}
					else
					{
						$computerresponse = $mostpopularactorname;
						$tmdbcode = $mostpopularactorid;
						
						print("<B>" . htmlspecialchars($mostpopularactorname) . "</B> was also in <I>" . 
                                  htmlspecialchars($entry) . "</I><BR>");
						print("Name another movie with <B>" . htmlspecialchars($mostpopularactorname) . "</B><BR>"); 
						if ( !empty($imagepath) ) {
							$config = $db->makeRequest("configuration");
							
							print("<IMG height=256 src=\"" . $config['images']['base_url'] . "original" . 
                                      $imagepath . "\" />");
						}
						// set up response.
						$history = $computerresponse . "|" . $history;
						$previous = $computerresponse;	
						$previousid = $tmdbcode;		
					}
				}
				else {
					print("I cannot find any movie called <I>" . htmlspecialchars($entry) . "</I>.<BR>");
					print("Weird, huh? Try again.<BR>");
					if (!empty($previous)) 
						print("Name another movie with <B>" . htmlspecialchars($previous) . "</B><BR>"); 
					$valid = false;
				}
				
				
			}
		}		

	}
	else {
		$state = "actor";
		$history = "";
		$previous = "";
		$previousid = "";
		$score = 0;
	}

	//
	// check for new game!
	if ( empty($history) ) {
		if ($state == "actor") {
			$movies = $db->makeRequest("movie/popular","language=en");
			$pages = $movies['total_pages'];
			$page = rand(1,$pages);
			$movies = $db->makeRequest("movie/popular","language=en&page=" . $page);

			$count = count($movies['results']);
			$movie = $movies['results'][ rand(0,$count-1) ];
			print ("Name an actor from <I>" . $movie['title'] . "</I> ");
			$year = substr($movie['release_date'],0,4);
			print("(" . $year . ")<BR>");
			$previous = $movie['title'];
			$previousid = $movie['id'];
			$history = $previous . "|";
		}
		else
		{
			print ("Enter the name of a <B>movie</B> to begin<BR>");
		}
	} 
		
	$db = null;


	print "<FORM name=\"mainform\" ID=\"mainform\" METHOD=POST ACTION=\"" . $_SERVER['SCRIPT_NAME'] . "\">";
	print "<input type=\"hidden\" name=\"actorormovie\" value=\"" . urlencode($state) . "\"/>\n";
	print "<input type=\"hidden\" name=\"gamehistory\" value=\"" . urlencode($history) . "\"/>\n";
	print "<input type=\"hidden\" name=\"givenvalue\" value=\"" . urlencode($previous) . "\"/>\n";
	print "<input type=\"hidden\" name=\"givenid\" value=\"" . urlencode($previousid) . "\"/>\n";
	print "<input type=\"hidden\" name=\"score\" value=\"" . urlencode($score) . "\"/>\n";
	print "Your Response: <input type=\"text\" id=\"playerentry\" name=\"playerentry\" />\n";
	print "<input type=\"submit\" value=\"Submit\" />\n";
	print "</FORM>";

	print "<BR><P align=\"center\">";

	$history_array = explode("|",$history);
	foreach( $history_array as $item ) {
		if (!empty($item)) {
			print(htmlspecialchars($item));
			print "<BR>|<BR>";
		}
	}

	print "</P>";
		
	print "<BR><P>Information provided by <A HREF=\"http://www.themoviedb.org/\">The Movie Database</A>, a community-maintained database. If you encounter an information error, please update!</P>";
	
	print "</BODY></HTML>\n";

	
	?>






