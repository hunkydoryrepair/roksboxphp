<?php

$DESTFOLDER = "/media/usb/webroot/movies";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 1000");
header("Access-Control-Allow-Headers: x-requested-with, x-file-name, x-index, x-total, x-hash, Content-Type, origin, authorization, accept, client-security-token");

$dir = $DESTFOLDER;

if (!isset($_SERVER['HTTP_X_FILE_NAME']))
    throw new Exception('Name required');
if (!isset($_SERVER['HTTP_X_INDEX']))
    throw new Exception('Index required');
if (!isset($_SERVER['HTTP_X_TOTAL']))
    throw new Exception('Total chunks required');

if(!preg_match('/^[0-9]+$/', $_SERVER['HTTP_X_INDEX']))
    throw new Exception('Index error');
if(!preg_match('/^[0-9]+$/', $_SERVER['HTTP_X_TOTAL']))
    throw new Exception('Total error');
 
$filename   = $_SERVER['HTTP_X_FILE_NAME'];
$index      = intval($_SERVER['HTTP_X_INDEX']);
$total      = intval($_SERVER['HTTP_X_TOTAL']);
$hash      = $_SERVER['HTTP_X_HASH'];


$info = pathinfo($filename);
//
// force to an extension we support.
//
if ($info['extension'] !== ".m4v" &&
	$info['extension'] !== ".mp4" &&
	$info['extension'] !== ".mpeg" &&
	$info['extension'] !== ".wmv" &&
	$info['extension'] !== ".mov" &&
	$info['extension'] !== ".avi" ) {
		$filename = basename($filename,'.'.$info['extension']).".m4v";
	}
	
$finaltarget =  $dir."/".$filename;

if (strpos($filename,"/") !== false ||
	strpos($filename,"?") !== false ||
	strpos($filename,"*") !== false ||
	strpos($filename,":") !== false) 
	throw new Exception('Invalid filename.');

if (file_exists($finaltarget)) {
    throw new Exception('Cannot overwrite existing files.');
}

// save each chunk in a separate file, to be combined later.
// This allows them to come out of order.
$target = $dir."/".$filename."-".$index."-".$total;

$input = file_get_contents("php://input");
$hash_file = md5($input);
if($hash===$hash_file)
{
	if ($index === 0)
		file_put_contents($finaltarget+'.tmp', $input);
	else
		file_put_contents($finaltarget+'.tmp', $input, FILE_APPEND);	
	
	$result = array
	(
		'filename' => $filename,
		'start' => $index,
		'end' => $total,
		'percent' => intval(($index+1) * 100 / $total),
		'hash' => $hash_file
	);
	
	if ($index+1 == $total ) {
		rename($finaltarget+'.tmp',$finaltarget);
	}
}
else
{
	$result = array
	(
		'error' => 'E_HASH'
	);
}

echo json_encode($result);

