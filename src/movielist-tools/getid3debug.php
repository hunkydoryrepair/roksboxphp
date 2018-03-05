<?php 

	include 'moviesetup.php';
	include 'getid3/getid3.php';
	
    if ( array_key_exists('filename', $_REQUEST) ) {
        $moviefile = urldecode($_REQUEST['filename']);
		$meta = new getID3();
		$file = $meta->analyze($moviefile);
		$duration = $file['playtime_seconds'];
		print "GOT DURATION " . $duration . "<BR>";
    }
        
    $count = 0;
    $dir = $MOVIE_FS_FILES_BASE;

    $path = realpath($dir);

    $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), 
        RecursiveIteratorIterator::SELF_FIRST);

	foreach($objects as $name => $object){
		if (preg_match('/\/DVD Extras\//', $name)) continue;

		if (preg_match('/\.m4v$/', $name) != 0 ) {
			print "<A HREF='getid3debug.php?filename=" . urlencode($name) . "'>";
			print htmlentities( $name );
			print "</A><BR/>";
		}
	}
	
	print "</BODY></HTML>\n";




	?>






