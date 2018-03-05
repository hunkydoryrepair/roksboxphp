<?php 

	// for file paths in the DB that use the file system location, we replace
	// the MOVIE_FS_FILES_BASE with MOVIE_HTTP_FILES_BASE
	$MOVIE_HTTP_FILES_BASE = "/media/movies/";
	$MOVIE_FS_FILES_BASE = "/Volumes/Bolivia/movies/";
	// specify where the database location is. If it is relative to the .php file,
	// set $MOVIE_DB_LOCATION_IS_RELATIVE to true
	$MOVIE_DB_LOCATION = "/../../db/Movies.db";
	$MOVIE_DB_LOCATION_IS_RELATIVE = true;
	// GENREs can be redundant (like SUSPENSE and THRILLER, or SPORT and SPORTS FILM). 
	// This array contains GENRE to show or not show. 
	$GENRE_FILTER = array ("default" => true,
	                 "Suspense" => false,
					"Thriller"=>true,
					"Comedy"=>true,
					"Crime"=>true,
					 "Sport"=>false,
					"Road Movie"=>false,
					"Neo-noir"=>false,
					"Mystery"=>false,
					"Music"=>false,
					"History"=>false,
					"Erotic"=>false);
	// GENREs can be redundant (like SUSPENSE and THRILLER, or SPORT and SPORTS FILM). 
	// This array contains GENRE to show or not show. 
	$ACTOR_MOVIE_COUNT = 7;  // 4 or more movies and we include actor. Override below
	$ACTOR_FILTER = array ("Jim Carrey" => true,
						   "Adam Sandler"=>true,
						   "Allen Covert"=>false,
						   "Harrison Ford"=>false,
						   "Alan Rickman"=>false,
						   "Kenny Baker"=>false,
						   "Frank Oz"=>false,
						   "Tom Felton"=>false,
						   "Julie Walters"=>false,
						   "Michael Gambon"=>false,
						   "Rupert Grint"=>false,
						   "Emma Watson"=>false,
						   "Carrie Fisher"=>false,
						   "Robbie Coltrane"=>false,
						   "Matthew Lewis"=>false,
						   "Steve Buscemi"=>false,
						   "Anthony Daniels"=>false);
	
	
	$ROKSBOX_MODE =  stripos($_SERVER['HTTP_USER_AGENT'],'Roku/DVP') !== false;
	$GOOGLETV     =  stripos($_SERVER['HTTP_USER_AGENT'],'GoogleTV') !== false;
	
	
	
	
	function ShiftedThe($string) {
		$res = strtoupper($string);
		if (strcasecmp(substr($res,0,4),"THE ")==0) {
			$res = substr($res,4);
		}
		$res = str_replace(',','',str_replace('\'','',str_replace(':','',str_replace('.','',str_replace('-','',$res)))));
		
		return $res;
	}		
	
	class MyDB extends SQLite3
	{
	
		
		function __construct()
		{
			global $MOVIE_DB_LOCATION_IS_RELATIVE;
			global $MOVIE_DB_LOCATION;
			if ($MOVIE_DB_LOCATION_IS_RELATIVE) {
				$dbFile = __DIR__ . $MOVIE_DB_LOCATION;
			} else {
				$dbFile = $MOVIE_DB_LOCATION;
			}
				
			$this->open($dbFile, SQLITE3_OPEN_READONLY);
			$this->createFunction('ShiftedThe','ShiftedThe');
		}
	}
	
	function fs2httppath( $path ) {
		global $MOVIE_FS_FILES_BASE;
		global $MOVIE_HTTP_FILES_BASE;
		if (stripos($path, $MOVIE_FS_FILES_BASE) === 0) {
			return $MOVIE_HTTP_FILES_BASE . substr($path, strlen($MOVIE_FS_FILES_BASE));
		} else {
			// return untouched.
			return $path;
		}
		
	}

	
	//
	// generate a new JPEG as a placeholder for a missing graphic.
	// In particular, for years, genres, actors, etc.
	//
	function generateTextThumbnail( $text, $filepath ) {
		$font = "arial.ttf";
		$width = 256;
		$height = 306;
		
		$image = imagecreatetruecolor($width, $height);

		$gray = imagecolorallocate( $image, 0x99,0x99,0x99 );
		imagefilledrectangle($image, 0,0, $width-1, $height-1, $gray );
		
		imagesetthickness( $image, 4 );
		$blue = imagecolorallocate( $image, 0x00,0x00,0x33 );
		imagerectangle($image, 1,1, $width-2, $height-2, $blue );

		$size = 5;
		$text_width = imagefontwidth($size)*strlen($text); 
		while( $size > 1 && $text_width > $width-8) {
			$size = $size - 1;
			$text_width = imagefontwidth($size)*strlen($text); 
//			$box = imageftbbox($size,0,$font,$text);
		}
		imagestring($image, $size, ($width-$text_width)/2, $height/3, $text, $blue);
		
		imagejpeg($image, $filepath, 80);
		imagedestroy($image);
		return $filepath;
	}
	
    
    //
    // generate a thumbnail for an actor type.
    //
    function generateActorThumbnail($db, $actorname, $filebase)
    {
        $thumb = $db->querySingle($select = "SELECT strThumb from actors WHERE strActor='" . SQLite3::escapeString($actorname) . "'");
        if (!empty($thumb)) {
            $filepath = $filebase . ".tbn";
            
            $xmlthumbs = new SimpleXMLElement("<thumbs>" . $thumb . "</thumbs>");
            $thumb = $xmlthumbs->thumb[0];
            
            $image = imagecreatefromjpeg($thumb);
            if (!$image) {
                $image = imagecreatefrompng($thumb);
                if (!$image) {
                    // delete it.
                    error_log("Unable to read as JPEG: " . $thumb);
                    return NULL;
                }
            }
            //
            // resize image to appropriate size.
            $w = imagesx($image);
            $h = imagesy($image);
            if ($h > $w) {
                $neww = ($w * 256)/$h;
                $newh = 256;
            } else {
                $newh = ($h * 256)/$w;
                $neww = 256;
            }
            $image_p = imagecreatetruecolor($neww, $newh);
            imagecopyresampled($image_p, $image, 0, 0, 0, 0, $neww, $newh, $w, $h);
            $success = imagejpeg($image_p, $filepath, 80);
            imagedestroy($image);
            imagedestroy($image_p);
            
            if (!$success) {
                error_log("Unable to save JPEG: " . $filepath);
                return NULL;
            }
            return $filepath;

        }
        return NULL;
    }
    
	//
	// generate a thumbnail from a movie, tvepisode or tvshow
	//
	function generateThumbnail($db, $movie, $filebase) {
		if (array_key_exists("idMovie", $movie)) {
			$xmlthumbs = new SimpleXMLElement("<thumbs>" . $movie['c08'] . "</thumbs>");
		} else {
			$xmlthumbs = new SimpleXMLElement("<thumbs>" . $movie['c06'] . "</thumbs>");
		}
		
		$thumb = NULL;
		// if we have some indicator of a TVSHOW season, search for that season.
		if (preg_match('/Season [0-9]+$/', $filebase)) {
			// grab the season and search for correct thumb
			$idx = strrpos($filebase,' ');  // must find one as it was in our REGEX
			$season = substr($filebase,$idx+1);
			foreach( $xmlthumbs->thumb as $th ) {
				if (strcasecmp( $th['type'], "SEASON" ) === 0) {
					if (strcasecmp($th['season'], $season) === 0) {
						$thumb = $th;
						break;
					}
				}
			}
		}

		if (empty($thumb))
			$thumb = $xmlthumbs->thumb[0];

		$filepath = $filebase . ".tbn";

/*		
		//
		// open URL and write contents to our filepath.
		//
		$fpsrc = fopen($thumb, 'rb');
		if (!$fpsrc) {
			error_log("Cannot open URL: " . $thumb);
			return NULL;
		}
		$fpdest = fopen($filepath, 'wb');
		if (!$fpdest) {
			error_log("Cannot open file: " . $filepath);
			fclose($fpsrc);
			return NULL;  // will fail if web user doesn't have write access to this directory
		}
		while (!feof($fpsrc)) {
			$chunk = fread($fpsrc, 8192);
			fwrite($fpdest, $chunk);
		}
		fclose($fpsrc);
		fclose($fpdest);
*/
		
		$image = imagecreatefromjpeg($thumb);
		if (!$image) {
			$image = imagecreatefrompng($thumb);
			if (!$image) {
				// delete it.
				error_log("Unable to read as JPEG: " . $thumb);
//				unlink($filepath);
				return NULL;
			}
		}
		$w = imagesx($image);
		$h = imagesy($image);
		if ($h > $w) {
			$neww = ($w * 256)/$h;
			$newh = 256;
		} else {
			$newh = ($h * 256)/$w;
			$neww = 256;
		}
		$image_p = imagecreatetruecolor($neww, $newh);
		imagecopyresampled($image_p, $image, 0, 0, 0, 0, $neww, $newh, $w, $h);
		$success = imagejpeg($image_p, $filepath, 80);
		imagedestroy($image);
		imagedestroy($image_p);
		
		if (!$success) {
			error_log("Unable to save JPEG: " . $filepath);
			return NULL;
		}
		return $filepath;
	}
	
	function printEpisodes($episodes)
	{
		global $ROKSBOX_MODE;
		if (!empty($episodes)) {
			while ($row = $episodes->fetchArray()) {
//				$name = utf8_decode($row['c00']);
				$name = $row['c00'];
				$path = filepathencode($row['c12'] . "." . $row['c13'] . ")%20" . $name );
				// include a jpg
				if ($ROKSBOX_MODE) {
					print("<TR><TD><A HREF=\"" . $path . ".m4v\">" . htmlspecialchars($row['c12'] . "." . $row['c13'] . ") " . $name) . "</A></TD></TR>\n");
					print("<TR><TD><A HREF=\"" . $path . ".jpg\"> <img border=0 src=\"" . $path . ".jpg\"/></A></TD></TR>\n");
				} else {
					print("<TR><TD COLSPAN=2><HR/></TD></TR>\n");
					print("<TR><TD valign=\"top\"><A HREF=\"" . $path . ".m4v\"><img  class=\"thumbnail\" src=\"" . $path . ".jpg\"/></A></TD>\n");
					print("<TD valign=\"top\"><span class=\"movietitle\">" . htmlspecialchars($row['c12'] . "." . $row['c13'] . ") " . $name) . "</span>");
					print("<P class=\"description\">" . htmlspecialchars($row['c01']) . "</P>\r");
					print("<A class=\"playlink\" HREF=\"" . $path . ".m4v\">PLAY</A></TD></TR>\n");
				}
			}
		}
	}	
	
	function printMovies($movies)
	{
		global $ROKSBOX_MODE;
		if (!empty($movies)) {
			while ($row = $movies->fetchArray()) {
				//
				// any change to how the name of the file is formatted must be accounted for in
				// findOrExtractID.
				//
//				$name = utf8_decode($row['c00']);
				$name = $row['c00'];
				$path = filepathencode(str_replace('/','_',$name) . " (" . $row['c07'] . ")");
				// include a jpg. We MUST put at least a space between the <A></A> tags (another tag won't do it), or Roksbox
				// will not display it.
				if ($ROKSBOX_MODE) {
					print("<TR><TD><A HREF=\"" . $path . ".m4v\">" . htmlspecialchars($name) . "</A></TD></TR>\n");
					print("<TR><TD><A HREF=\"" . $path . ".jpg\"> <img border=0 src=\"" . $path . ".jpg\"/></A></TD></TR>\n");
				} else {
					print("<TR><TD COLSPAN=2><HR/></TD></TR>\n");
					print("<TR><TD><A HREF=\"" . $path . ".m4v\"><img  class=\"thumbnail\" src=\"" . $path . ".jpg\"/></A></TD>\n");
					print("<TD valign=\"top\"><span class=\"movietitle\">" . htmlspecialchars($name)  . "</span><P class=\"description\">" . htmlspecialchars($row['c01']) . "</P>\r");
					print("<span class=\"director\">Director: " . htmlspecialchars($row['c15']) . "</span>\r");
					$time = $row['c11'] + 0;
					if ($time > 60)
						$strtime = floor($time/60) . "h " . ($time % 60) . "m";
					else
						$strtime = $time . "m";
					print("<span class=\"runtime\">Run Time: " . $strtime . "</span>\r");
					print("<span class=\"playlink\"><A class=\"playlink\" HREF=\"" . $path . ".m4v\">PLAY</A></span></TD></TR>\n");
				}
			}
		}
	}
	
	function filepathencode($path) {
		return str_replace('#','%23',str_replace(" ","%20",str_replace("'","%27",str_replace("?","%3f",str_replace(":","%3a",$path)))));
	}
	
	//
	// find a TV show or MOVIE id given the playlist entry. We
	// will need to parse the playlist, find the title, grab the associated
	// filename and find an episode or movie that matches that filename.
	//
	function findIDFromPlaylist($db, $playlist, $title) {
		$playlist = $playlist . ".m3u";
		if ($fp = fopen( dirname(__FILE__) . "/" . $playlist, "r" ))
		{
			while( $str = fgets($fp) ) {
				$str = trim($str);
				if (stripos($str,"#EXTINF:") === 0) {
					// get the title
					$idx = strpos($str,',');
					$title2 = substr($str,$idx+1);
					$filename = fgets($fp);  // filename follows immediately
					$filename = basename(trim($filename));

					if (strcasecmp($title2, $title) === 0)
					{
						// find movie or episode id by filename
						$fileid = $db->querySingle($select = "SELECT idFile from files WHERE strFileName='" . SQLite3::escapeString($filename) . "'");
						if ($fileid) {
							$movieid = $db->querySingle("SELECT * from movieview WHERE idFile='" . $fileid . "'", true);
							if ($movieid) {
								fclose($fp);
								return $movieid;
							} else {
								$movieid = $db->querySingle("SELECT * from episodeview WHERE idFile='" . $fileid . "'", true);
								if ($movieid) {
									fclose($fp);
									return $movieid;
								}
							}
						}
						else {
							error_log("No fileid for: " . $select );
						}
					}
				}
			}
			error_log("No line in playlist matching: " . $title );
			fclose($fp);
		}
		else {
			error_log("Unable to open playlist file: " . $playlist );
		}
		return NULL;
	}
	
	
	// use the information in URL to find which movie we really mean. We can't put
	// the actual ID in there without it being displayed on Roksbox UI, which is quite
	// ugly. Many TV episodes are called "pilot" so for TV shows we use the show name
	// $db - or sqlite3 db connection
	// $params - array of params from URL. first is always empty, last should contain the "filename"
	//    which is really the formatted title.
	// RETURN: object from episodeview or movieview matching the given name
	function findOrExtractID($db,$params) {
		$cmd = $params[1];
		if (strcasecmp($cmd,"PLAYLISTS")===0) {
			$list = $params[2];
			$title = $params[3];
			$title = substr($title, 0, strlen($title)-4); // trim off extension
			return findIDFromPlaylist( $db, $list, $title );
		}
		else if (strcasecmp($cmd,"TELEVISION")===0) {
			$show = $params[2];
			$title = $params[sizeof($params)-1]; // last parameter, no trailing slash
			if (preg_match('/^[0-9]+\.[0-9]+\) /', $title) != 0 ) {  // check for SEASON.EPISODE prefix
				$idx   = strpos($title,')');  // get episode number.
				$episode = substr($title,0,$idx);
				$title = substr($title, $idx+2, strlen($title)-6-$idx); // trim off extension AND beginning info
				$idx  = strpos($episode,'.');
				$season = substr($episode,0,$idx);
				$episode = substr($episode,$idx+1);
				$select = 'SELECT * from episodeview WHERE c12=\'' . SQLite3::escapeString($season) . 
					'\' AND c13=\'' . SQLite3::escapeString($episode) . '\' AND strTitle=\'' .
					SQLite3::escapeString($show) . '\' AND c00=\'' .
					SQLite3::escapeString($title) . '\'';			
				return $db->querySingle($select, true);
			} else {
				// we have a TV Show folder. No EPISODE. Just get TV Show.
				if (preg_match('/^Season [0-9]+/',$title) !== 0)
					$title = $show; // if we have SEASON list, grab the name of show
				else
					$title = substr($title, 0, strlen($title)-4); // trim off extension
				$select = 'SELECT * from tvshowlinkpath JOIN tvshow on tvshowlinkpath.idShow=tvshow.idShow JOIN path ON tvshowlinkpath.idPath=path.idPath WHERE c00=\'' .
					SQLite3::escapeString($title) . '\'';
				return $db->querySingle($select, true);
			}
		} else {
			$title = $params[sizeof($params)-1]; // last parameter, no trailing slash
			$title = substr($title, 0, strlen($title)-4); // trim off extension
			$title = str_replace("_","/",$title);
			if (preg_match('/ \([0-9]+\)$/', $title) != 0 ) {
				$idx = strrpos($title,' (');
				$year = substr($title,$idx+2,strlen($title)-$idx-3);
				$title = substr($title,0,$idx);
				$select = "SELECT * from movieview WHERE c07='" . SQLite3::escapeString($year) . 
				                    "' AND c00='" . SQLite3::escapeString($title) . "'";
//				error_log($select);
			} else {
				$select = "SELECT * from movieview WHERE c00='" . SQLite3::escapeString($title) . "'";
			}
			return $db->querySingle($select, true);
		}
		return NULL;

	}
	
	function sendFileContents($filepath, $originalname) {
//		redirect method
//		$path = fs2httppath($path);
//		header("Location: http://" . $_SERVER['SERVER_NAME'] . filepathencode($path) );
		
// X-sendfile method		
		header("X-Sendfile: " . $filepath);
		header("Content-type: application/octet-stream");
		header('Content-Disposition: attachment; filename="' . basename($originalname) . '"');		
	}
	
	
	//
	// check for necessary trailing slash
	//
	$mypath = "";
	
	if (array_key_exists('PATH_INFO', $_SERVER))
		$mypath = $_SERVER['PATH_INFO'];
	
	if (empty($mypath))  // no trailing slash! 
	{
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . "/");
		exit();
	}
	
	$params = explode('/',$mypath);
	
	$lastparam = $params[sizeof($params)-1];
	
	// redirect if last parameter doesn't have slash or extension of exactly 3 characters
	if (!empty($lastparam) && strrpos($lastparam,'.') !== strlen($lastparam)-4 ) {
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . "/");
		exit();
	}
	
	$cmd = $params[1];
	
	
	$db = new MyDB();
	
	//
	// check last parameter for xml info.
	//
	if ($params && sizeof($params) > 2 && (strcasecmp($cmd,"FILES")!==0  && strcasecmp($cmd,"XTRAS")!==0)) {
		$lastparam = $params[sizeof($params)-1];
		if (!empty($lastparam)) {
			//
			// need to get id of movie
			//
			$movie = findOrExtractID($db, $params);
			
			if (!$movie) {
				if (preg_match('/\.jpg$/', $lastparam) != 0 ) {
					$filename = substr($lastparam,0,strlen($lastparam)-4);  // strip off extension
					$path = $MOVIE_FS_FILES_BASE . rawurldecode($filename) . ".jpg"; // look in base of movie files and in images/
					if (file_exists( $path )) {
						sendFileContents($path,$lastparam);
					}
					else if (file_exists( $path = $MOVIE_FS_FILES_BASE . "images/" . rawurldecode($filename) . ".jpg")) {
						sendFileContents($path,$lastparam);
					} else if (file_exists( $path = $MOVIE_FS_FILES_BASE .  rawurldecode($filename) . ".tbn")) {
						sendFileContents($path,$lastparam);
                    } 
                    else {
                        //
                        // try to generate it from actor name
                        $path = generateActorThumbnail($db,$filename,$MOVIE_FS_FILES_BASE . "images/" . rawurldecode($filename));
                        if ($path) {
                            sendFileContents($path,$lastparam);
                        }
                        else if (stripos($filename, "sheet") === false) {  // check if we should generate one. We can do this whenever there is not the word SHEET
                            $path = generateTextThumbnail($filename, $MOVIE_FS_FILES_BASE . "images/" . rawurldecode($filename) . ".jpg");
                            sendFileContents($path,$lastparam);
                        } else {
                            error_log("No corresponding movie or file for: " . $_SERVER['PATH_INFO']);
                            header("HTTP/1.1 404 Not Found");
                            header("Status: 404 Not Found");
                            print( "Not Found: generic file not found on file system.");
                        }
                    }
				} else {
					header("HTTP/1.1 404 Not Found");
					header("Status: 404 Not Found");
					print( "Not Found: Not a JPEG file and no movie or tv information found.");
				}
			} else if (preg_match('/\.xml$/', $lastparam) != 0 ) {
				
				//
				// look up the movie
				//
				if (array_key_exists('idEpisode',$movie)) {
					print("<video><title>" . htmlspecialchars($movie['c00']) . "</title>\n");
					print("<year>" . htmlspecialchars("S" . $movie['c12'] . ".E" . $movie['c13']) . "</year>\n");
					print("<description>" . htmlspecialchars($movie['c01']) . "</description>\n");
					print("<director>" . htmlspecialchars($movie['c10']) . "</director>\n");
					print("<length>" . htmlspecialchars(trim($movie['c09'])) . "</length>\n");
					
					$actors = $db->query('select strActor from actorlinkepisode left join actors on (actorlinkepisode.idActor=actors.idActor) where idEpisode="' . $movie['idEpisode'] . '"');
					print("<actors>");
					$first = true;
					while( $row = $actors->fetchArray() ) {
						if (!$first) print(", ");
						print(htmlspecialchars($row['strActor']));
						$first = false;
					}
					$actors->finalize();
					print("</actors>");
					
					
					print("</video>");
				} else {
					print("<movie><title>" . htmlspecialchars($movie['c00']) . "</title>\n");
					
					print("<year>" . htmlspecialchars($movie['c07']) . "</year>\n");
					print("<director>" . htmlspecialchars($movie['c15']) . "</director>\n");
					$rating = $movie['c12'];
					$rating = str_replace('Rated ','',$rating);
					print("<mpaa>" . $rating . "</mpaa>\n");
					print("<description>" . htmlspecialchars($movie['c01']) . "</description>\n");
					print("<length>" . htmlspecialchars(trim($movie['c11'])) . "</length>\n");
					$genre = $movie['c14'];
					$genre = str_replace(' /',',',$genre);
					print("<genre>" . htmlspecialchars($genre) . "</genre>\n");
					
					$actors = $db->query('select strActor from actorlinkmovie left join actors on (actorlinkmovie.idActor=actors.idActor) where idMovie="' . $movie['idMovie'] . '"');
					print("<actors>");
					$first = true;
					while( $row = $actors->fetchArray() ) {
						if (!$first) print(", ");
						print(htmlspecialchars($row['strActor']));
						$first = false;
					}
					$actors->finalize();
					print("</actors>");
						
					print("</movie>");
				}
				
			} else   if (preg_match('/\.m4v$/', $lastparam) != 0 ) {
				$path = $movie['strPath'];
				$path = fs2httppath($path);
				$strfile = $movie['strFileName'];
				// use redirect for .m4v to handle complicated scanning requests.
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: http://" . $_SERVER['SERVER_NAME'] . filepathencode($path) . filepathencode($movie['strFileName']));
			} else   if (preg_match('/\.jpg$/', $lastparam) != 0 ) {
				if (!array_key_exists('strFileName',$movie)) {
					// why don't we have a filename in our $movie? Because it may be a TVSHOW record.
					$path = $movie['strPath'];
					// tack on extra Season information
					if (preg_match('/^Season [0-9]+/', $lastparam) != 0 ) {
						// look for a season graphic. This is a folder within the show that is
						// NOT an episode (otherwise we'd have a strFileName key). So, must be
						// a SEASON.
						$file = $lastparam;
						if ($idx = strrpos($lastparam,".")) {
							$file = substr($file, 0, $idx);
						}
						$path .= $file;
					}
					else {
						$path = substr($path,0,strlen($path)-1); // strip off trailing slash
					}
				} else {
					$file = $movie['strFileName'];
					$idx = strrpos($file,".");
					if ($idx) $file = substr($file, 0, $idx);
					$path = $movie['strPath'] . $file;
				}
				if (file_exists( $path . ".tbn" )) {
					sendFileContents($path . ".tbn",$lastparam);
				}
				else if (file_exists( $path . ".jpg" )) {
					sendFileContents($path . ".jpg",$lastparam);
				} else if (file_exists( $path . ".png" )) {
					sendFileContents($path . ".png",$lastparam);
				} else {
					// let's try to generate it!
					$fullpath = generateThumbnail( $db, $movie, $path );
					if ($fullpath) {
						sendFileContents($fullpath,$lastparam);
					} else {
						header("HTTP/1.1 404 Not Found");
						header("Status: 404 Not Found");
						print( "Not Found: Movie information found but no file on file system.");
					}
				}
			} else   {
				header("HTTP/1.1 404 Not Found");
				header("Status: 404 Not Found");
				print( "Not Found: Unknown file extension.");
			}
			$db->close();
			exit();
		}
	}
	
	
	header("Content-Type: text/html; charset=UTF-8");
	print("<HTML>\n<HEAD>\n<TITLE>Movie List</TITLE>\n");
	
	include 'moviestyles.php';
	
	print("</HEAD>\n<BODY>\n");
	if ($ROKSBOX_MODE) print("<h1>Index of " . $_SERVER['PHP_SELF'] . "</h1>\n");
	else {
		print("<div class=\"headercontainer\">");
		if (!empty($cmd)) print("<span class=\"backlink\"><A class=\"backlink\" href=\"..\">BACK</A></span>");
		$subtitle = implode(":", array_slice($params, 1, sizeof($params)-2) );
		print("<div class=\"topheader\">Movie List<BR/>" . $subtitle . "</div></div>\n");
	}
	// check if we have a trailing backslash. If we
	// do, we do not need to prepend anything for our navigation
	// but if not we need to prepend the last parameter
	// in any relative links.
	$relpath = "";
	$addmorelink = false;
	
	
	if (empty($cmd)) {
		print("<table>");
		print("<TR><TD></TD><TD><A HREF=\"Genre/\">Genres</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"Television/\">Television</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"New/\">New</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"Playlists/\">Playlists</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"Search/\">Search</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"Actors/\">Actors</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"Directors/\">Directors</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"XTRAS/\">XTRAS</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"Year/\">Year</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"List/\">List</A></TD></TR>\n");
		print("<TR><TD></TD><TD><A HREF=\"Files/\">Files</A></TD></TR>\n");
		print("</table>");
	}
	else if (strcasecmp($cmd,"LIST")==0) {
		$movies = $db->query('select * from movieview order by ShiftedThe(c00)');
	} else if (strcasecmp($cmd,"GENRE")==0) {
		$genre = $params[2];
		if (empty($genre))
		{
			// output a list of genres
			$result = $db->query('select distinct genre.strGenre from genrelinkmovie left join genre on genrelinkmovie.idGenre=genre.idGenre order by genre.strGenre');
			if ($ROKSBOX_MODE) {
				print("<table>");
				print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n");
			} 
			while ($row = $result->fetchArray()) {
				$genre = $row['strGenre'];
				$haskey = array_key_exists($genre,$GENRE_FILTER);
				if ( ($haskey && $GENRE_FILTER[$genre]) || (!$haskey && $GENRE_FILTER['default']) ) {
					if ($ROKSBOX_MODE) {
						print("<TR><TD><A HREF=\"" . filepathencode($genre) . "/\">" . htmlspecialchars($genre) . "</A></TD></TR>");
						print("<TR><TD><A HREF=\"" . filepathencode($genre) . ".jpg\"> <IMG BORDER=0 SRC=\"" . filepathencode($genre) . ".jpg\"/></A></TD></TR>");
					} else {
						print("<A HREF=\"" . filepathencode($genre) . "/\"><IMG class=\"thumbnail\" BORDER=0 SRC=\"" . filepathencode($genre) . ".jpg\"/></A>");
					}
				}
			}      
			if ($ROKSBOX_MODE) print("</table>");
		}
		else
		{
			$genre = urldecode($genre);
			$genreid = $db->querySingle("select idGenre from genre where strGenre='" . SQLite3::escapeString($genre) . "'");
			
			$movies = $db->query('select * from movieview left join genrelinkmovie on movieview.idMovie = genrelinkmovie.idMovie where genrelinkmovie.idGenre = "' . $genreid . '" order by ShiftedThe(c00)');
		}
	} else if (strcasecmp($cmd,"ACTORS")==0) {
		$actor = $params[2];
		if (empty($actor)) {
			// output a list of actors
			$result = $db->query('select actors.strActor, count(*) as cnt from movie join actorlinkmovie on actorlinkmovie.idMovie=movie.idMovie join actors on actorlinkmovie.idActor=actors.idActor group by actors.strActor order by actors.strActor');
			if ($ROKSBOX_MODE) {
				print("<table>");
				print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n");
			} 
			while ($row = $result->fetchArray()) {
				$actor = $row['strActor'];
				if (array_key_exists($actor,$ACTOR_FILTER))
					$show = $ACTOR_FILTER[$actor];
				else
					$show = $row['cnt'] >= $ACTOR_MOVIE_COUNT;
				if ( $show ) {
					if ($ROKSBOX_MODE) {
						print("<TR><TD><A HREF=\"" . filepathencode($actor) . "/\">" . htmlspecialchars($actor) . " (" . $row['cnt'] . ")</A></TD></TR>");
						print("<TR><TD><A HREF=\"" . filepathencode($actor) . ".jpg\"> <img border=0 src=\"" .  filepathencode($actor) . ".jpg\"/></A></TD></TR>");
					} else {
						print("<div class=\"thumbblock\">");
						print("<A HREF=\"" . filepathencode($actor) . "/\"><img class=\"thumbnail\" border=0 src=\"" .  filepathencode($actor) . ".jpg\"/></A><BR/>");
						print("<A HREF=\"" . filepathencode($actor) . "/\">" . htmlspecialchars($actor) . " (" . $row['cnt'] . ")</A><BR/>");
						print("</div>");
					}
				}
			}      
			if ($ROKSBOX_MODE) print("</table>");
			
		}
		else {
			$actor = urldecode($actor);
			$actorid = $db->querySingle("select idActor from actors where strActor='" . SQLite3::escapeString($actor) . "'");
			
			$movies = $db->query('select * from movieview join actorlinkmovie on movieview.idMovie = actorlinkmovie.idMovie where actorlinkmovie.idActor = "' . $actorid . '" order by ShiftedThe(c00)');
		}
	} else if (strcasecmp($cmd,"DIRECTORS")==0) {
		$director = $params[2];
		if (empty($director)) {
			// output a list of actors
			$result = $db->query('select * from (select c15, count(*) as cnt from movie group by c15) where cnt >= 4  order by cnt desc');
			if ($ROKSBOX_MODE) {
				print("<table>");
				print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n");
			} 
			while ($row = $result->fetchArray()) {
				$director = $row['c15'];
				$directorfile = filepathencode(str_replace('/',';',$director));  // because of the /, don't just use filepathencode
				
				if ($ROKSBOX_MODE) {
					print("<TR><TD><A HREF=\"" . $directorfile . "/\">" . htmlspecialchars($director) . " (" . $row['cnt'] . ")</A></TD></TR>");
					print("<TR><TD><A HREF=\"" . $directorfile . ".jpg\"> <img border=0 src=\"" .  $directorfile . ".jpg\"/></A></TD></TR>");
				} else {
					print("<div class=\"thumbblock\">");
					print("<A HREF=\"" . $directorfile . "/\"><img class=\"thumbnail\" border=0 src=\"" .  $directorfile . ".jpg\"/></A><BR/>");
					print("<A HREF=\"" . $directorfile . "/\">" . htmlspecialchars($director) . " (" . $row['cnt'] . ")</A><BR/>");
					print("</div>");
				}
			}      
			if ($ROKSBOX_MODE) print("</table>");
			
		}
		else {
			$director = urldecode($director);
			$director = str_replace(';','/',$director);
			
			$movies = $db->query('select * from movieview where c15 = "' . SQLite3::escapeString($director) . '" order by ShiftedThe(c00)');
		}
	} else if (strcasecmp($cmd,"YEAR")==0) {
		$year = $params[2];
		if (empty($year) && strcasecmp($year,"0")!=0)
		{
			// output a list of years
			$result = $db->query('select distinct c07 from movie order by c07 desc');
			if ($ROKSBOX_MODE) {
				print("<table>");
				print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n");
			}
			while ($row = $result->fetchArray()) {
				if ($ROKSBOX_MODE) {
					print("<TR><TD><A HREF=\"" . $row['c07'] . "/\">" . htmlspecialchars($row['c07']) . "</A></TD></TR>");
					print("<TR><TD><A HREF=\"" . $row['c07'] . ".jpg\"> <img border=0 src=\"" .  $row['c07'] . ".jpg\"/></A></TD></TR>");
				} else {
					print("<A HREF=\"" . $row['c07'] . "/\"> <img class=\"thumbnail\" border=0 src=\"" .  $row['c07'] . ".jpg\"/></A>");
				}
			}      
			if ($ROKSBOX_MODE) print("</table>");
			$result->finalize();
		}
		else
		{
			$movies = $db->query('select * from movieview where c07="' . $year . '"');
		}
	} else if (strcasecmp($cmd,"NEW")==0) {
		$offset = 0;
		// add 10 for each level down.
		$offset = (sizeof( $params ) - 3) * 10;
		$movies = $db->query('select * from movieview order by idMovie DESC LIMIT ' . $offset . ', 10');
		$addmorelink = true;
	} else if (strcasecmp($cmd,"SEARCH")==0) {
		//$terms  = array_slice($params,2);
		//$search = implode($terms);
		$len    = sizeof($params);
		if ($len > 3)
			$search = $params[$len-2];
		else
			$search = "";
		$movies = $db->query('SELECT * FROM movieview where c00 like "%' . SQLite3::escapeString($search) . '%" order by ShiftedThe(c00) limit 5');
		// count results
		$i = 0;
		while( $row = $movies->fetchArray() ) {
			$i = $i + 1;
		}
		$movies->reset();
		print("<TABLE>\n");
		if ( $i >= 5 ) {
			foreach( range('A','Z') as $letter) {
				$cnt = $db->querySingle('SELECT count(c00) FROM movie WHERE c00 like "%' . SQLite3::escapeString($search . $letter) . '%"');
				if ($cnt > 0)
					print("<TR><TD><A HREF=\"" . $search . $letter . "/\">" . $search . $letter . "</A></TD></TR>\n");
			}
		}
		printMovies($movies);
		print("</TABLE>");
		$movies = NULL;
	} else if (strcasecmp($cmd,"FILES")==0) {
		$terms  = array_slice($params,2);
		$path   = implode('/',$terms);
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: http://" . $_SERVER['SERVER_NAME'] . filepathencode($MOVIE_HTTP_FILES_BASE) . filepathencode($path) );
	} else if (strcasecmp($cmd,"XTRAS")==0) {
		$terms  = array_slice($params,2);
		$path   = implode('/',$terms);
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: http://" . $_SERVER['SERVER_NAME'] . filepathencode($MOVIE_HTTP_FILES_BASE) . "DVD%20Extras/" . filepathencode($path) );
	} else if (strcasecmp($cmd,"PLAYLISTS")==0) {
		$list   = $params[2];
		if (empty($list)) {
			//
			// generate list of playlists!
			//
			$files = scandir(dirname(__FILE__));
			if ($ROKSBOX_MODE) {
				print("<table>\n");
				print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n") ;
			}
			foreach( $files as $file ) {
				if (preg_match('/\.m3u$/',$file)) {
					$name = substr($file,0,strlen($file)-4);
					$namepath = filepathencode($name);
					if ($ROKSBOX_MODE) {
						print("<TR><TD><A href=\"" . $namepath . "/\">" . htmlspecialchars($name) . "</A></TD></TR>\n");
						print("<TR><TD><A href=\"" . $namepath . ".jpg\"> <img border=0 src=\"" . $namepath . ".jpg\"/></A></TD></TR>\n");
					} else {
						print("<div class=\"thumbblock\" >");

						print("<A href=\"" . $namepath . "/\"> <img class=\"thumbnail\" border=0 src=\"" . $namepath . ".jpg\"/></A><BR/>\n");
						print("<A href=\"" . $namepath . "/\">" . htmlspecialchars($name) . "</A></div>\n");
						
					}
				}
			}
			if ($ROKSBOX_MODE) print("</table>\n");
		}
		else {
			// we have a list! open file and parse
			$list .= ".m3u";
			if ($ROKSBOX_MODE) {
				print("<table>\n");
				print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n") ;
			}
			if ($fp = fopen( dirname(__FILE__) . "/" . $list, 'r' )) {
				while( $str = fgets($fp) ) {
					$str = trim($str);
					if (stripos($str,"#EXTINF:") === 0) {
						// get the title
						$idx = strpos($str,',');
						$title = substr($str,$idx+1);
						$filename = fgets($fp);  // filename follows immediately
						$filename = basename($filename);
						$path = filepathencode($title);
						// include a jpg. We MUST put at least a space between the <A></A> tags (another tag won't do it), or Roksbox
						// will not view it.
						if ($ROKSBOX_MODE) {
							print("<TR><TD><A HREF=\"" . $path . ".m4v\">" . htmlspecialchars($title) . "</A></TD></TR>\n");
							print("<TR><TD><A HREF=\"" . $path . ".jpg\"> <img border=0 src=\"" . $path . ".jpg\"/></A></TD></TR>\n");
						} else {
							print("<div style=\"clear:both\"><HR/></div>\n");

							print("<div style=\"float:left\"><A HREF=\"" . $path . ".m4v\"><img class=\"thumbnail\" border=0 src=\"" . $path . ".jpg\"/></A></div>\n");
							print("<div style=\"float:left; padding-left:10px\"><span class=\"movietitle\">" . htmlspecialchars($title) . "</span>\n");
							print("<P></P><A class=\"playlink\" HREF=\"" . $path . ".m4v\">PLAY</A></div>\n");
						}
					}
				}
				fclose($fp);
			}
			if ($ROKSBOX_MODE) {
				print("</table>\n");
			}
		}
	} else if (strcasecmp($cmd,"TELEVISION")==0) {
		$show = $params[2];
		//
		// list the TV shows
		//
		if (empty($show)) {
			$shows = $db->query('select * from tvshow order by ShiftedThe(c00)');
			print("<table>\n");
			if ($ROKSBOX_MODE) print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n") ;
			while( $row = $shows->fetchArray() ) {
				if ($ROKSBOX_MODE) {
					print("<TR><TD><A href=\"" . filepathencode($row['c00']) . "/\">" . htmlspecialchars($row['c00']) . "</A></TD></TR>\n");
					print("<TR><TD><A href=\"" . filepathencode($row['c00']) . ".jpg\"> <IMG BORDER=0 SRC=\"" . filepathencode($row['c00']) . ".jpg\"></A></TD></TR>\n");
				} else {
					print("<TR><TD COLSPAN=2><HR></TD></TR>\n");
					print("<TR><TD valign=\"top\"><A href=\"" . filepathencode($row['c00']) . "/\"> <IMG class=\"thumbnail\" BORDER=0 SRC=\"" . filepathencode($row['c00']) . ".jpg\"></A></TD>\n");
					print("<TD valign=\"top\"><span class=\"movietitle\">" . htmlspecialchars($row['c00']) . "</span><P class=\"description\">" . htmlspecialchars($row['c01']) .  "</P>");
					
					print("<A class=\"selectlink\" href=\"" . filepathencode($row['c00']) . "/\"> SELECT </A></TD></TR>\n");
				}
			}
			print("</TABLE>");
		}
		else {
			$season = $params[3];
			$episodes = NULL;
			$showdata = $db->querySingle("select * from tvshow where c00 = '" . SQLite3::escapeString($show) . "'", true);
			$showid = $showdata['idShow'];
			//
			// list the seasons
			//
			if (empty($season)) {
				$seasons = $db->query("select distinct episode.c12 from tvshowlinkepisode left join episode on tvshowlinkepisode.idEpisode=episode.idEpisode where tvshowlinkepisode.idShow='" . SQLite3::escapeString($showid) . "' order by abs(episode.c12)");
				// count results
				$i = 0;
				while( $row = $seasons->fetchArray() ) {
					$i = $i + 1;
				}
				$seasons->reset();
				if ($i != 1) {
					if ($ROKSBOX_MODE)  {
						print("<table>\n");
						print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n") ;
					}
					while( $row = $seasons->fetchArray() ) {
						if ($ROKSBOX_MODE) {
							print("<TR><TD><A href=\"" . filepathencode("Season " . $row['c12']) . "/\">" . htmlspecialchars("Season " . $row['c12']) . "</A></TD></TR>\n");
							print("<TR><TD><A href=\"" . filepathencode("Season " . $row['c12']) . ".jpg\"> <IMG BORDER=0 SRC=\"" . filepathencode("Season " . $row['c12']) . ".jpg\"/></A></TD></TR>\n");
						} else {
							print("<div class=\"thumbblock\">\n");
							print("<A href=\"" . filepathencode("Season " . $row['c12']) . "/\"> <IMG class=\"thumbnail\" BORDER=0 SRC=\"" . filepathencode("Season " . $row['c12']) . ".jpg\"/></A><BR/>\n");
							print("<A href=\"" . filepathencode("Season " . $row['c12']) . "/\">" . htmlspecialchars("Season " . $row['c12']) . "</A>\n");
							print("</div>");
						}
					}
					if ($ROKSBOX_MODE) print("</table>\n");
				}
				else {
					// if only 1 season then just show all the episodes
					$episodes = $db->query("select * from episodeview left join tvshowlinkepisode on episodeview.idEpisode=tvshowlinkepisode.idEpisode where tvshowlinkepisode.idShow='" . SQLite3::escapeString($showid) . "' order by abs(episodeview.c13)");
				}
				$seasons->finalize();
				
			} else {
				// show all the episodes from the given season
				$season = str_replace('Season ','',rawurldecode($season));
				$episodes = $db->query("select * from episodeview left join tvshowlinkepisode on episodeview.idEpisode=tvshowlinkepisode.idEpisode where tvshowlinkepisode.idShow='" . SQLite3::escapeString($showid) . "' AND episodeview.c12='" . SQLite3::escapeString($season) . "' order by abs(episodeview.c13)");
			}
		}
		
	}
	else {
		error_log("Unknown Command: " . $mypath);
		print("Unknown category: " . $genre);
	}
	
	
	if (!empty($movies)) {
		print("<table>");
		if ($ROKSBOX_MODE) print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n");
		printMovies($movies);
		if ($addmorelink) {
			print("<TR><TD><A class=\"selectlink\" href=\"MORE/\">MORE</A></TD></TR>\n");
		}
		print("</table>");
	}

	if (!empty($episodes)) {
		print("<table>");
		if ($ROKSBOX_MODE) print("<TR><TD><A class=\"selectlink\" href=\"..\">BACK</A></TD></TR>\n");
		printEpisodes($episodes);
		if ($addmorelink) {
			print("<TR><TD><A class=\"selectlink\" href=\"MORE/\">MORE</A></TD></TR>\n");
		}
		print("</table>");
	}
	
	
	$db->close();
	
	
	
	print("</BODY></HTML>\n");
	
	
	?>