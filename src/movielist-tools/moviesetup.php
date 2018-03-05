<?php 

	// for file paths in the DB that use the file system location, we replace
	// the MOVIE_FS_FILES_BASE with MOVIE_HTTP_FILES_BASE
	$MOVIE_HTTP_FILES_BASE = "/media/movies/";
	$MOVIE_FS_FILES_BASE = "/Volumes/Bolivia/movies/";
	// specify where the database location is. If it is relative to the .php file,
	// set $MOVIE_DB_LOCATION_IS_RELATIVE to true
	$MOVIE_DB_LOCATION = "/../../../db/Movies.db";
	$MOVIE_DB_LOCATION_IS_RELATIVE = true;
	$API_KEY = '835727baa2a8325eab45362f7fed6f98';
	
	
	
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
				
			$this->open($dbFile, SQLITE3_OPEN_READWRITE);
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
	
	function filepathencode($path) {
		return str_replace('#','%23',str_replace(" ","%20",str_replace("'","%27",str_replace("?","%3f",str_replace(":","%3a",$path)))));
	}	



	function getAPI3Result($request,$params = NULL)
	{
		global $API_KEY;
	
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

		curl_close($ch);	
	
		$result = json_decode($content, true);
		return $result;		
	}
	
	
	//
	// cache thumbnail locally
	//
	function generateCacheThumbnail($thumb, $targetpath) {

		$image = imagecreatefromjpeg($thumb);
		if (!$image) {
			$image = imagecreatefrompng($thumb);
			if (!$image) {
				error_log("Unable to read as JPEG: " . $thumb);
				return NULL;
			}
		}
		$w = imagesx($image);
		$h = imagesy($image);
		if ($h > $w) {
			$neww = ($w * 306)/$h;
			$newh = 306;
		} else {
			$newh = ($h * 256)/$w;
			$neww = 256;
		}
		$image_p = imagecreatetruecolor($neww, $newh);
		imagecopyresampled($image_p, $image, 0, 0, 0, 0, $neww, $newh, $w, $h);
		// write the resampled jpeg to $filepath
		$success = imagejpeg($image_p, $targetpath, 80);
		imagedestroy($image);
		imagedestroy($image_p);
		
		if (!$success) {
			error_log("Unable to save JPEG: " . $filepath);
			return NULL;
		}
		return $targetpath;
	}
	    



?>	