<?php


	//
	// ignore THE and various punctuation when
	// sorting titles.
	//
	function ShiftedThe($string) {
		$res = strtoupper($string);
		if (strcasecmp(substr($res,0,4),"THE ")==0) {
			$res = substr($res,4);
		}
		$res = str_replace(',','',str_replace('\'','',str_replace(':','',str_replace('.','',str_replace('-','',$res)))));
		
		return $res;
	}		
	
	class RoksDB extends SQLite3
	{
		function __construct($readwrite = false)
		{
			global $MOVIE_DB_LOCATION_IS_RELATIVE;
			global $MOVIE_DB_LOCATION;
			if ($MOVIE_DB_LOCATION_IS_RELATIVE) {
				if ( ord($MOVIE_DB_LOCATION) != ord('/') )
					$dbFile = __DIR__ . '/' . $MOVIE_DB_LOCATION;
				else
					$dbFile = __DIR__ . $MOVIE_DB_LOCATION;
			} else {
				$dbFile = $MOVIE_DB_LOCATION;
			}
				
			$this->open($dbFile, $readwrite ? SQLITE3_OPEN_READWRITE : SQLITE3_OPEN_READONLY);
			$this->createFunction('ShiftedThe','ShiftedThe');
		}
	}
	
	
	
