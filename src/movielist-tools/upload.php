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

$finaltarget = $dir."/".$filename;

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

$input = fopen("php://input", "r");
// copy input file to chunk file.
file_put_contents($target, $input);

// now, verify the upload
$input = file_get_contents($target);
$hash_file = md5($input);
if($hash===$hash_file)
{
	// when complete, assemble the final file.
	$pieces = 0;
	for( $i=0; $i < $total; $i++) {
		$filename = $dir."/".$filename."-".$i."-".$total;
		if (file_exists($filename)) $pieces++;
	}
	
	$result = array
	(
		'filename' => $filename,
		'start' => $index,
		'end' => $total,
		'percent' => intval((pieces) * 100 / $total),
		'hash' => $hash_file
	);
	
	if ($pieces == $total ) {
		//
		// time to assemble the pieces
		//
		for( $i=0; $i < $total; $i++) {
			$filename = $dir."/".$filename."-".$i."-".$total;
			$input = file_get_contents($filename);
			if ($i === 0)
				file_put_contents($finaltarget, $input);
			else
				file_put_contents($finaltarget, $input, FILE_APPEND);
			unlink($filename);
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

