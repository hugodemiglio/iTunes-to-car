<?php

include 'config.php';
include 'locales.php';

echo "\n\nAguarde, iniciando...\n\n";

include 'ConsoleInput.php';
include 'pr.php';

$iTunes = new Itunes($iTunesConfiguration, $locales);

class Itunes {
  var $playlists;
  var $playlists_data;
  var $index;
  var $commands;
  var $stdin;
  var $path;
  var $file;
  var $config;
  var $founded = array();
  
  function __construct($iTunesConfiguration, $locales){
    
    /* Load basic data */
    $this->stdin = new ConsoleInput('php://stdin');
    $this->path = $iTunesConfiguration['volume-path'];
    $this->file = str_replace('~', $_SERVER['HOME'], $iTunesConfiguration['iTunes-xml']);
    $this->config = $iTunesConfiguration;
    $this->locales = $locales;
    
    /* Init system */
    $this->welcome();
    $this->check_dependence();
    
    /* Process data */
    $playlists_data = @simplexml_load_file($this->file) or die($this->write("#invalid-library&"));
    $this->playlists = $this->process_playlist($playlists_data->dict->array->dict);
    $this->playlists_data = $this->process_playlists_data($playlists_data->dict->array->dict);
    $this->index = $this->process_index($playlists_data->dict->dict->dict);
    
    /* Process to user */
    $this->path = $this->select_menu();
    
    /* Update data */
    $this->update();
    
    $process = true;
    while($process){
      $choise = trim($this->playlist_menu());
      
      $get = true;
      while($get){
        if(strtolower($choise) == 'q' OR strtolower($choise) == 's'){
          $process = false;
          $get = false;
        } else if(!$this->is_int($choise) OR ($choise < 0 OR $choise > count($this->playlists)-1)){
          $this->write("#invalid-option&");
          $choise = trim($this->get_playlist_menu());
        } else {
          $get = false;
          $this->send_musics($choise);
          $process = $this->exit_menu();
        }
      }
      
    }
    
    $this->end();
  }
  
  function select_menu(){
    $this->write("#select-drive&");
    $contents = $this->get_dir_contents($this->path);
    foreach($contents as $key => $name){
      $this->write("[".$key."] - ".$name['name'].";");
    }
    
    $get = true;
    while($get){
      $this->write(";[0-".(count($contents)-1)." | Q - quit]: ");
      $choise = trim($this->stdin->read());
      
      if(strtolower($choise) == 'q' OR strtolower($choise) == 's'){
        $this->end();
        die();
      } elseif(!$this->is_int($choise) OR ($choise < 0 OR $choise > count($contents)-1)){
        $this->write("#invalid-option&");
      } else {
        $get = false;
        return $contents[$choise]['location'];
      }
    }
  }
  
  function get_dir_contents($path = null){
    if(is_dir($path)){
      $dh = opendir($path);
      $dirs = array();
      while (($name = readdir($dh)) !== false){
        
        if(isset($this->config['volume-ignore']) AND is_array($this->config['volume-ignore'])){
          $continue = false;
          foreach($this->config['volume-ignore'] as $volume_ignore){
            if(trim($name) == trim($volume_ignore)) $continue = true;;
          }
          
        }
        
        if($name == '.' OR $name == '..' OR $continue) continue;
        
        if(is_dir($path.'/'.$name)){
          $dirs[] = array(
            'name' => $name,
            'location' => $path.$name.'/',
          );
        }
      }
      closedir($dh);
    }
    return $dirs;
  }
  
  function playlist_menu(){
    $this->system("clear");
    $this->write("#select-playlist&");
    foreach($this->playlists as $key => $playlist){
      $this->write("[".$key."] - ".$playlist."");
      if(isset($this->founded[$key])) $this->write(" <info>[ ALREADY ]</c>;");
      else $this->write(";");
    }
    return $this->get_playlist_menu();
  }
  
  function get_playlist_menu(){
    $this->write(";[0-".(count($this->playlists)-1)." | Q - quit]: ");
    return $this->stdin->read();
  }
  
  function send_musics($playlist_id = 0, $update = false){
    if(isset($this->playlists[$playlist_id])){
      if(!$update) $this->write("#preparing-playlist&", array($this->playlists[$playlist_id]));
      if($this->check_folder($this->playlists[$playlist_id])){
        if(!$update) $this->write("#sending-musics&", array(count($this->playlists_data[$playlist_id])));
        $path = $this->path.'iTunes/'.$this->playlists[$playlist_id].'/';
        $musics_in_path = $this->read_folder($path);
        $this->delete_musics($musics_in_path, $this->playlists_data[$playlist_id], $path);
        $i = 0; foreach($this->playlists_data[$playlist_id] as $track){
          $music_path = urldecode($this->index[$track]['location']);
          if(file_exists($music_path)){
            //$this->write($path." <info>[ DEBUG ]</c>;");
            $this->write("#copying&", array($this->index[$track]['name']));
            $this->index[$track]['name'] = str_replace('/', ' ', $this->index[$track]['name']);
            if(file_exists($path.$this->index[$track]['name'].$this->index[$track]['extencion'])) $this->write(" <info>[ ALREADY ]</c>;");
            else {
              $this->system("cp ".$this->command_name($music_path)." ".$this->command_name($path.$this->index[$track]['name'].$this->index[$track]['extencion']));
              $this->write(" <ok>[ OK ]</c>;");
            }
          } else $i++;
        }
        //$this->write(" <ok>[ OK ]</c>;");
        if($i > 0) $this->write("#not-found-musics&", array($i));
      } else {
        $this->write("#directory-verification-error&");
      }
    } else {
      $this->write("#not-found-playlist&");
    }
  }
  
  function delete_musics($in_folder, $in_list, $path){
    $in_list = $this->resolve($in_list, 'name');
    $to_delete = array();
    foreach($in_folder as $music){
      $music_name = str_replace($this->get_extencion($music), '', $music);
      if(!isset($in_list[$music_name]['name'])) {
        $this->write("#deleting-music&", array($music_name));
        $this->system("rm ".$this->command_name($path.$music));
        $this->write(" <fail>[ DELETED ]</c>;");
      }
    }
  }
  
  function resolve($list = array(), $mode = 'original_id'){
    $return = array();
    foreach($list as $id){
      switch($mode){
        case 'original_id':
        $return[] = $this->index[$id];
        break;
        
        case 'music_id':
        $return[$id] = $this->index[$id];
        break;
        
        case 'name':
        $return[$this->index[$id]['name']] = $this->index[$id];
        break;
      }
    }
    return $return;
  }
  
  function check_folder($playlist_name = ''){
    $this->write("#checking-directories&");
    if($this->mkdir('iTunes')){
      $path = $path.'iTunes/'.$playlist_name;
      if($this->mkdir($path)){
        return true;
      }
    }
    return false;
  }
  
  function update(){
    $playlists = $this->read_folder($this->path.'iTunes/', false);
    $this->write("#checking-updates&");
    
    foreach($playlists as $playlist){
      $name = explode('/', $playlist);
      $name = $name[count($name)-1];
      
      $playlist = array_search($name, $this->playlists);
      
      if($this->playlists[$playlist] == $name){
        $this->founded[$playlist] = true;
        $this->send_musics($playlist, true);

        $this->write("#playlist-updated&", array($name));
      }
      
    }
  }
  
  function read_folder($path, $only_files = true){
    if(is_dir($path)){
      $dh = opendir($path);
      $files = array();
      $dirs = array();
      while (($file = readdir($dh)) !== false){
        if(substr($file, 0, 1) == '.') continue;
        if(is_dir($path.'/'.$file)){
          array_push($dirs, $path.'/'.$file);
        } else {
          array_push($files, $file);
        }
      }
      closedir($dh);
    }
    if($only_files) return $files;
    else return $dirs;
  }
  
  function mkdir($path = null){
    $this->write("#creating-directory&", array($path));
    $path = $this->path.'/'.($path);
    if(!is_dir($path)){
      $this->system("mkdir ".$this->command_name($path));
      if(!is_dir($path)){
        $this->write(" <fail>[ FAIL ]</c>;");
        return false;
      }
      $this->write(" <ok>[ OK ]</c>;");
    } else {
      $this->write(" <info>[ ALREADY ]</c>;");
    }
    return true;
  }
  
  function command_name($name){
    $replaces = array(
      ' ' => '\ ',
      "'" => "\'",
      '"' => '\"',
      '(' => '\(',
      ')' => '\)',
      '&' => '\&',
    );
    foreach($replaces as $key => $replace){
      $name = str_replace($key, $replace, $name);
    }
    return $name;
  }
  
  function process_index($songs = array()){
    $this->write("#processing-musics&");
    $return = array();
    
    foreach($songs as $music){
      $location = str_replace('file://localhost', '', (string) $music->string[count($music->string) -1]);
      
      $return[(int)$music->integer[0]] = array(
        'name' => (string) $this->adapt_string($music->string[0]),
        'location' => $location,
        'extencion' => $this->get_extencion($location),
      );
    }
    
    $this->write(" <ok>[ OK ]</c>;");
    return $return;
  }
  
  function adapt_string($string, $limit = 60, $include_last = false){
    $replaces = array(
      'a' => array('á', 'ã', 'à', 'â'),
      'A' => array('Á', 'Ã', 'À', 'Â'),
      'e' => array('é', 'è', 'ê'),
      'E' => array('É', 'È', 'Ê'),
      'i' => array('í', 'ì', 'î'),
      'I' => array('Í', 'Ì', 'Î'),
      'o' => array('ó', 'õ', 'ò', 'ô'),
      'O' => array('Ó', 'Õ', 'Ò', 'Ô'),
      'u' => array('ú', 'ù', 'û'),
      'U' => array('Ú', 'Ù', 'Û'),
      'c' => array('ç'),
      'C' => array('Ç'),
      ' ' => array('/'), 
      '' => array('!', '?', '@', '$', '%', '&', '*', '=', ',', "'", '"')
    );
    foreach($replaces as $to => $replace){
      foreach($replace as $find){
        $string = str_replace($find, $to, $string);
      }
    }
    $len = strlen($string);
    if($len > $limit) $string = substr($string, 0, (!$include_last ? $limit : $limit - strlen($include_last))) . ($include_last ? $include_last : '');
    return $string;
  }
  
  function get_extencion($name = null){
    $name = explode('.', $name);
    return '.'.$name[count($name)-1];
  }
  
  function process_playlists_data($playlist_data){
    $this->write("#processing-music-playlists&");
    $return = array();
    $i = 0; foreach($playlist_data as $key => $playlist){
      $return[$i] = array();
      $j = 0; if(isset($playlist->array->dict)) foreach($playlist->array->dict as $track){
        $return[$i][$j] = (int) $track->integer;
        $j++;
      }
      $i++;
    }
    $this->write(" <ok>[ OK ]</c>;");
    return $return;
  }
  
  function process_playlist($playlist_data){
    $this->write("#processing-playlists&");
    $return = array();
    $i = 0; foreach($playlist_data as $key => $playlist){
      $return[$i] = (string) $playlist->string[0];
      $i++;
    }
    $this->write(" <ok>[ OK ]</c>;");
    return $return;
  }
  
  function is_int($var = null){
    if($var == '0') return true;
    $var = (int) $var;
    if($var == 0) return false;
    return true;
  }
  
  function system($command = null){
    $this->commands[] = $command;
    system($command);
  }
  
  function write($text, $args = array()){
    if(substr($text, 0, 1) == '#' AND substr($text, strlen($text)-1, strlen($text)) == '&'){
      $text = $this->locales[$this->config['default-locate']][substr($text, 1, strlen($text)-2)];
      foreach($args as $i => $arg){
        $text = str_replace('ARG'.$i, $arg, $text);
      }
    }
    
    $text = str_replace(';', "\n", $text);
    $text = str_replace('</c>', "\033[0m", $text); //Normal Color
    $text = str_replace('<ok>', "\033[92m", $text); //OK Color
    $text = str_replace('<info>', "\033[94m", $text); //INFO Color
    $text = str_replace('<fail>', "\033[91m", $text); //FAIL Color
    echo $text;
  }
  
  function check_dependence(){
    $this->write("#checking-dependencies&");
    $abort = false;
    
    if(!is_dir($this->path)){
      $this->write("#not-found-volumes&");
      $abort = true;
    }
    
    if(empty($this->file)){
      $this->write("#not-found-library-config&");
      $abort = true;
    }
    
    if(!file_exists($this->file)){
      $this->write("#not-found-library&");
      $abort = true;
    }
    
    if(count($this->get_dir_contents($this->path)) == 0){
      $this->write("#not-found-device&");
      $abort = true;
    }
    
    if(!(isset($this->config['volume-ignore']) AND is_array($this->config['volume-ignore']))){
      $this->write("#volume-config-error&");
    }
    
    if($abort) $this->abort();
  }
  
  function exit_menu(){
    $this->write("#process-new-playlist&");
    $choise = trim($this->stdin->read());
    if(strtolower($choise) == 'n') return false;
    return true;
  }
  
  function abort(){
    $this->write("#aborted-process&");
    die();
  }
  
  function end(){
    $this->write("#process-done&");
    
    if($this->config['noHiddens-config']['status']){
      if($this->path != $this->config['volume-path']){
        $command = str_replace('$ARGS', $this->path, $this->config['noHiddens-config']['location']);
        system(substr($command, 0, strlen($command)-1));
      }
    }
  }
  
  function welcome(){
    $this->system("clear");
    $this->write("╔═══════════════════════════════╗;");
    $this->write("║  iTunes to car    1.0.0 beta  ║;");
    $this->write("╚═══════════════════════════════╝;");
    $this->write("#searching-playlists&");
  }
  
}

//print_r($library->dict->array->dict[0]);

/*$i = 0; foreach($library->dict->array->dict as $key => $playlist){
  echo $i.' - '.$playlist->string[0]."\n";
  $i++;
}

print_r($library->dict->array->dict[14]);*/

?>