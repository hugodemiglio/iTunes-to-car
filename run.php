<?php

echo "\n\nAguarde, iniciando...\n\n";

$to_path = '/Volumes/CARRO/';

include 'ConsoleInput.php';
include 'xml_parse.php';
include 'pr.php';

$library = simplexml_load_file($file);

$iTunes = new Itunes($songs, $library->dict->array->dict, $to_path);

class Itunes {
  var $songs;
  var $playlists;
  var $playlists_data;
  var $index;
  var $commands;
  var $stdin;
  
  function __construct($songs = array(), $playlists_data = array(), $path = ''){
    /* Load data */
    $this->stdin = new ConsoleInput('php://stdin');
    $this->welcome();
    $this->songs = $songs;
    $this->playlists = $this->process_playlist($playlists_data);
    $this->playlists_data = $this->process_playlists_data($playlists_data);
    $this->index = $this->process_index($songs);
    $this->path = $path;
    
    /* Process to user */
    $this->playlist_menu();
    
    //$this->send_musics(19);
    //pr($this->index['1782']['location']);
  }
  
  function playlist_menu(){
    $this->write(";Selecione a playlist que deseja importar:;;");
    foreach($this->playlists as $key => $playlist){
      $this->write("[".$key."] - ".$playlist.";");
    }
    $this->write(";[0-".(count($this->playlists)-1)." | Q - quit]: ");
    return $this->stdin->read();
  }
  
  function send_musics($playlist_id = 0){
    if(isset($this->playlists[$playlist_id])){
      $this->write(";Preparando para enviar playlist '".$this->playlists[$playlist_id]."'... <ok> [ OK ]</c>;");
      if($this->check_folder($this->playlists[$playlist_id])){
        $this->write(";Enviando músicas... (".count($this->playlists_data[$playlist_id])." músicas)");
        $path = $this->path.'iTunes/'.$this->playlists[$playlist_id].'/';
        pr($this->playlists_data[$playlist_id]);
        $i = 0; foreach($this->playlists_data[$playlist_id] as $track){
          $music_path = urldecode($this->index[$track]['location']);
          if(file_exists($music_path)){
            $this->system("cp ".$this->command_name($music_path)." ".$this->command_name($path.$this->index[$track]['name'].".mp3"));
          } else $i++;
        }
        $this->write(" <ok>[ OK ]</c>;");
        if($i > 0) $this->write("Algumas músicas não foram encontradas. (".$i." músicas) <fail>[ FAIL ]</c>;");
      } else {
        $this->write("Ocorreu um erro ao verificar os diretórios. <fail>[ FAIL ]</c>;");
      }
    } else {
      $this->write("A playlist informada não foi encontrada. <fail>[ FAIL ]</c>;");
    }
  }
  
  function check_folder($playlist_name = ''){
    $this->write(";Verificando diretórios...;");
    if($this->mkdir('iTunes')){
      $path = $path.'iTunes/'.$playlist_name;
      if($this->mkdir($path)){
        return true;
      }
    }
    return false;
  }
  
  function mkdir($path = null){
    $this->write("Criando diretório '".$path."'...");
    $path = $this->path.'/'.($path);
    if(!is_dir($path)){
      $this->system("mkdir ".$this->command_name($path));
      if(!is_dir($path)){
        $this->write(" <fail>[ FAIL ]</c>;");
        return false;
      }
    }
    $this->write(" <ok>[ OK ]</c>;");
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
    $this->write("Processando índice de músicas...");
    $return = array();
    foreach($songs as $song){
      if(isset($song['Location'])){
        $return[$song['Track ID']] = array(
          'name' => $song['Name'],
          'location' => str_replace('file://localhost', '', $song['Location']),
        );
      }
    }
    $this->write(" <ok>[ OK ]</c>;");
    return $return;
  }
  
  function process_playlists_data($playlist_data){
    $this->write("Processando músicas das playlists...");
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
    $this->write("Processando lista de playlists...");
    $return = array();
    $i = 0; foreach($playlist_data as $key => $playlist){
      $return[$i] = (string) $playlist->string[0];
      $i++;
    }
    $this->write(" <ok>[ OK ]</c>;");
    return $return;
  }
  
  function system($command = null){
    $this->commands[] = $command;
    system($command);
  }
  
  function write($text){
    $text = str_replace(';', "\n", $text);
    $text = str_replace('</c>', "\033[0m", $text); //Normal Color
    $text = str_replace('<ok>', "\033[92m", $text); //OK Color
    $text = str_replace('<info>', "\033[94m", $text); //INFO Color
    $text = str_replace('<fail>', "\033[91m", $text); //FAIL Color
    echo $text;
  }
  
  function welcome(){
    $this->system("clear");
    $this->write("╔═══════════════════════════════╗;");
    $this->write("║  iTunes to car    1.0.0 beta  ║;");
    $this->write("╚═══════════════════════════════╝;");
    $this->write(";Buscando playlists do iTunes...;;");
  }
  
}

//print_r($library->dict->array->dict[0]);

/*$i = 0; foreach($library->dict->array->dict as $key => $playlist){
  echo $i.' - '.$playlist->string[0]."\n";
  $i++;
}

print_r($library->dict->array->dict[14]);*/

?>