<?php

$iTunesConfiguration = array(
  /* Place the drivers you want to ignore list, like your system HD */
  'volume-ignore' => array(
    'Macintosh',
    'Macintosh HD',
  ),
  
  /* UNIX path to volumes */
  'volume-path' => '/Volumes/',
  
  /* Location for iTunes xml */
  'iTunes-xml' => '~/Music/iTunes/iTunes Music Library.xml',
  
  /* Use the module to remove hidden files Mac (https://github.com/hugodemiglio/noHiddens) */
  'noHiddens-config' => array(
    'status' => true,
    'location' => 'php ~/Documents/noHiddens/run $ARGS',
  ),
  
  /* Set language (pt-BR "Brazillian Portuguese" ou en-US "American English") */
  'default-locate' => 'pt-BR',
);

?>