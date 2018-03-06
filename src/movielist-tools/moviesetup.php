<?php 

	require("../config.php");
	require("common.php");
	require("RoksDB.php");

	$API_KEY = '835727baa2a8325eab45362f7fed6f98';
	


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