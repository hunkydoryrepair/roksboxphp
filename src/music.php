<?php
	$ituneslibrary = "/Volumes/Argentina/iTunes/iTunes Library.xml";
	$itunesroot = "/Volumes/Argentina/iTunes/iTunes%20Music/";
	$httproot = "/media/Music/";

	mb_internal_encoding('UTF-8');
	mb_http_output('UTF-8');
	mb_http_input('UTF-8');
	mb_language('uni');
	mb_regex_encoding('UTF-8');

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

	if ($lastparam == "playlists.txt") {
		header("Content-Type: text/plain; charset=UTF-8");
	    // only playlists
	    $itunes = new Parse($ituneslibrary, "playlists");
	} elseif ($lastparam == "music.xml") {
	
		header("Content-Type: text/xml; charset=UTF-8");
		print "<?xml version='1.0' encoding='utf-8'?>
	<MusicDatabase>
	  <Songs>
	";
	
	    // only tracks now, but you could choose "playlists" or "all"
	    $itunes = new Parse($ituneslibrary, "tracks");
	
		print "</Songs></MusicDatabase>\n";    
	}
 
 
//
// the class used to parse the itunes xml
// 
class Parse {
    private $library;                   // Array, where data is stored
    private $current_key;               // key for each element in second dimension of array
    private $current_element;           // stores xml element name
    private $current_data;              // value for second dimension array elements
    private $current_type = "";   // stores what type we are handling, starts with traks, later playlists
    
    private $track_id;                  // counter for num tracks
    private $playlist_id;               // counter for playlist_id
    private $skip_playlist=false;         // boolean, will skip the master playlist (everything)
    
    private $listing_playlist_tracks;   // boolean, if inside playlist tracks array
    private $stop_parser;               // boolean used to help us out of library
    
    /**
     * Starts this shit :P
     *
     **/
    public function __construct($file, $type = "all")
    {
        $this->find_type = $type;
        $this->listing_playlist_tracks = false;
        $this->stop_parser = false;
        $this->skip_playlist = false;
        
        $xml_parser = xml_parser_create();
        xml_set_object($xml_parser, &$this);
    	xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, true);
    	xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, true);
        xml_set_element_handler($xml_parser, "_start", "_end");
        xml_set_character_data_handler($xml_parser, "_char");
        
        if (!($fp = @fopen($file, "r"))) {
            return false;
        }
        
        while(($data = fread($fp, 4096)) && $this->stop_parser != true) {
            if(!xml_parse($xml_parser, $data, feof($fp))) {
                die(sprintf("XML error: %s at line %d\n",
                    xml_error_string(xml_get_error_code($xml_parser)),
                        xml_get_current_line_number($xml_parser)));
            }
        }
        
		xml_parser_free($xml_parser);
    }
    
    /**
     * Function is called when start tag is found
     *
     **/
    private function _start($parser, $name, $attr)
    {
        if($name == "DICT" 
                && $this->current_type == "playlists" 
                && $this->listing_playlist_tracks === false ) {
            $this->playlist_id++;
        }
        
    	$this->current_element = $name;
    }
    
    /**
     * Function is called when end tag is found.
     *
     **/
    private function _end($parser, $name)
    {
		global $itunesroot;
		global $httproot;
        
        if($this->find_type == "tracks" && $this->current_type == "playlists") {
            $this->stop_parser = true;
            return;
        } 
        
        if($this->find_type == "playlists" && $this->current_type != "playlists") {
            //$this->stop_parser = true; should not stop parser because playlists come after tracks in xml file
            return;
        }
                
        if($this->current_type == "playlists"  && $this->skip_playlist === false
            && (($this->current_key == "Master" && $this->current_element == "TRUE") ||
				($this->current_key == "Visible" && $this->current_element == "FALSE") || 
				($this->current_key == "Music" && $this-> current_element == "TRUE") ||
				($this->current_key == "Movies" && $this-> current_element == "TRUE") ||
				($this->current_key == "TV Shows" && $this-> current_element == "TRUE"))
		) {
            $this->skip_playlist = true;
            unset($this->library[$this->current_type]);
            $this->playlist_id--;
        }
 
    	if(!empty($this->current_element) && $this->skip_playlist === false) {
    		if($this->current_element=="KEY"){
    			$this->current_key = trim($this->current_data);
    		} elseif(!empty($this->current_data)) {
    		    if($this->current_type == "tracks") {
    		        $this->library[$this->current_type][$this->current_key] = $this->current_data;
    		    } elseif($this->listing_playlist_tracks === false) {
    		        $this->library[$this->current_type][$this->current_key] = $this->current_data;
    		    } else {
    		        $this->library[$this->current_type]['Tracks'][][$this->current_key] = $this->current_data;
    		    }
    		}
			else {
   		        $this->library[$this->current_type][$this->current_key] = $this->current_element;
			}
    	}

		if ($name == "DICT" && $this->current_type == "playlists" && 
						$this->listing_playlist_tracks === false  &&
						$this->skip_playlist === false &&
						$this->playlist_id != NULL &&
						!empty($this->library[$this->current_type]))
		{
			print "<playlist>" . $this->library[$this->current_type]["Name"] . "." . $this->playlist_id . ".xml</playlist>\n";
					
			$this->library[$this->current_type] = NULL;
		}

		if ($name == "DICT" && $this->current_type == "tracks" && $this->track_id != NULL)
		{

			$loc = $this->library[$this->current_type]["Location"];
			$loc = str_ireplace("file://localhost" . $itunesroot,
							"http://" . $_SERVER['SERVER_NAME'] . $httproot, $loc );

			$songtime = intval($this->library[$this->current_type]["Total Time"]) / 1000;
			$songlength = sprintf("%02d:%02d:%02d", $songtime/(60*60),
							($songtime % (60*60))/60,
							($songtime % (60)));
	
			print "<Song>\n";
			print "<Title>" . $this->library[$this->current_type]["Name"] . "</Title>\n";
			if ( array_key_exists("Artist",$this->library[$this->current_type]))
				print "<PerformingArtist>" . $this->library[$this->current_type]["Artist"] . "</PerformingArtist>\n";
			if ( array_key_exists("Album", $this->library[$this->current_type]))
				print "<ContainedInAlbum>" . $this->library[$this->current_type]["Album"] . "</ContainedInAlbum>\n";
			print "<Location><path>" . $loc . "</path></Location>\n";
			print "<SongLength>" . $songlength . "</SongLength>\n";
			if ( array_key_exists("Year",$this->library[$this->current_type]))
				print "<Year value=\"" . $this->library[$this->current_type]["Year"] . "\"/>\n";
			if ( array_key_exists("Genre",$this->library[$this->current_type]))
				print "<Genre>" . $this->library[$this->current_type]["Genre"] . "</Genre>\n";

			print "</Song>\n";

			$this->library[$this->current_type] = NULL;
			$this->track_id = NULL;
		}
    	        	
    	if($name == "ARRAY" && $this->listing_playlist_tracks) {
    	    $this->listing_playlist_tracks = false;
    	    $this->skip_playlist = false;
    	}

		$this->current_element = "";
		$this->current_data = "";
    }
    
    
    /**
     * Function for handling data inside tags
     *
     **/
    private function _char($parser, $data)
    {       
		if ($this->current_element == "KEY")
		{             
	        if($data=="Playlists") {
	    		$this->current_type = "playlists";
	    	}
	
			if ( $this->current_type=="tracks" && $this->track_id == NULL) {
				$this->track_id = trim($data);
			}

			if ($data == "Tracks") {
	    		$this->current_type = "tracks";
			}
		}
    	
    	if($data == "Playlist Items") {
    	    $this->listing_playlist_tracks = true;
    	}
    	
		if (!empty($this->current_element))
	    	$this->current_data .= htmlspecialchars($data);
    }
    
    
    /**
     * Returns the library
     *
     * @return array
     **/
    public function getLibrary()
    {   
        return $this->library;
    }
}  
 
?>
