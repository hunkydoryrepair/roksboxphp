<?php

	function sendFileContents($filepath, $originalname) {
		global $USE_XSENDFILE;
		if ($USE_XSENDFILE) {
			header("X-Sendfile: " . $filepath);
			header("Content-type: application/octet-stream");
			header('Content-Disposition: attachment; filename="' . basename($originalname) . '"');		
		} else {
			$path = fs2httppath($filepath);
			header("Location: http://" . $_SERVER['SERVER_NAME'] . filepathencode($path) );
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


	function removeExtension($path) {
		$slash = strrpos($path, '/');
		$dot = strrpos($path, '.');
		// even if one or both of these is false, the comparison works.
		if ($dot > $slash) {
			return substr($path,0,$dot);
		}
		return $path;
	}