<?php 

	require 'TVDB.php';

	include 'moviesetup.php';
	

	//
	// cache thumbnail locally
	//
	function cacheImage($url, $targetpath) {

		$image = imagecreatefromjpeg($url);
		if (!$image) {
			$image = imagecreatefrompng($url);
			if (!$image) {
				error_log("Unable to read as JPEG: " . $url);
				return NULL;
			}
		}

		// make sure folder exists
		if(!is_dir($targetpath))
			mkdir( dirname($targetpath), 0775, true );
		// write jpeg to $filepath
		$success = imagejpeg($image, $targetpath, 80);
		imagedestroy($image);
		
		if (!$success) {
			error_log("Unable to save JPEG: " . $targetpath);
			return NULL;
		}
		return $targetpath;
	}
	    

	$pathtoload = $_REQUEST['BANNERPATH'];
	$tvdbpath = TVDB::baseUrl;

	$prependpath = __DIR__ . "/TVDB/tvdbcache/";

	$destfile = $prependpath . $pathtoload;

	if (file_exists( $destfile )) {
		sendFileContents($destfile);
	}
	else {
		// cache the file
		$cached = cacheImage($tvdbpath . "banners/" . $pathtoload, $destfile);
		sendFileContents($cached);
	}

?>

