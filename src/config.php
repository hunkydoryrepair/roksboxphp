<?php

	// for file paths in the DB that use the file system location, we replace
	// the MOVIE_FS_FILES_BASE with MOVIE_HTTP_FILES_BASE
	$MOVIE_HTTP_FILES_BASE = "/movies/";
	$MOVIE_FS_FILES_BASE = "/media/usb/webroot/movies/";
	// specify where the database location is. If it is relative to the RoksDB.php file,
	// set $MOVIE_DB_LOCATION_IS_RELATIVE to true
	$MOVIE_DB_LOCATION = "../../db/Movies.db";
	$MOVIE_DB_LOCATION_IS_RELATIVE = true;
	
	// set to a font available on the system with GD Library to be used
	// for rendering text thumbnails.
	$THUMBNAIL_FONT = "arial.ttf";
	
	// if $USE_XSENDFILE is true, mod_xsendfile must be configured on
	// the web server. If false, redirects will be used instead.
	$USE_XSENDFILE = false;
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
	
	//
	// $ACTOR_MOVIE_COUNT is the minimum number of films and actor
	// appears in to show them in the list. 
	$ACTOR_MOVIE_COUNT = 7;  
	//
	// $ACTOR_FILTER is an override to explicitly add actors who may
	// have fewer than the required films, or remove actors who have
	// a lot of films but still should not be displayed.
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
