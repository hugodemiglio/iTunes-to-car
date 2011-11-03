<?php

include 'pr.php';

$file = 'iTunes Music Library.xml';

$library = simplexml_load_file($file);

//pr($library);

$songs = array();
$i = 0; foreach($library->dict->dict->dict as $music){
  //if($i > 10) continue;
  
  $songs[(int)$music->integer[0]] = array(
    'name' => (string) $music->string[0],
    'location' => (string) $music->string[count($music->string) -1],
  );
  
  $i++;
}

pr($songs);

?>