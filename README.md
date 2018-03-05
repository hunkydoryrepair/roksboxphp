# roksboxphp
Server for Roksbox Roku app. Serves directory listings and thumbnails based on database, to organize movies by title, genre, actors, directors, etc.

Not quite ready for public consumption. This project has dependencies that are not well documented.

# Dependencies
TVDB - https://github.com/ryandoherty/phptvdb.git  
Copy the TVDB project into the movielist-tools folder
<br>
getid3 - https://github.com/JamesHeinrich/getID3.git
Copy getid3 folder into the movielist-tools folder
<br>
Jquery - linked to remotely<br>
sqlite3 - requires php-sqlite3 module be installed<br>
php-xml - requires xml module for php<br>
php7.0-gd - needs GD library for PHP<br>
mod_xsendfile - optional. (although might be required as redirects don't always work with roksbox ) <br>

