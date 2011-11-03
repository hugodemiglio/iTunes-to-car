<?php

echo "\n\nAguarde, iniciando...\n\n";

$to_path = '/Users/hugodemiglio/Desktop/CARRO/';
$file = 'iTunes Music Library.xml';

//if(file_exists(realpath('./').$file)) die("Biblioteca do iTunes não encontrada. \033[91m[ FAIL ]\033[0m\n\n");

include 'ConsoleInput.php';
include 'pr.php';

$library = simplexml_load_file($file);

$iTunes = new Itunes($library, $to_path);

class Itunes {
  var $playlists;
  var $playlists_data;
  var $index;
  var $commands;
  var $stdin;
  var $path;
  
  function __construct($playlists_data = array(), $path = ''){
    /* Load data */
    $this->stdin = new ConsoleInput('php://stdin');
    $this->welcome();
    $this->playlists = $this->process_playlist($playlists_data->dict->array->dict);
    $this->playlists_data = $this->process_playlists_data($playlists_data->dict->array->dict);
    $this->index = $this->process_index($playlists_data->dict->dict->dict);
    $this->path = $path;
    $this->check_dependence();
    
    /* Process to user */
    $process = true;
    while($process){
      $choise = trim($this->playlist_menu());
      
      $get = true;
      while($get){
        if(strtolower($choise) == 'q' OR strtolower($choise) == 's'){
          $process = false;
          $get = false;
        } else if(!$this->is_int($choise) OR ($choise < 0 OR $choise > count($this->playlists)-1)){
          $this->write(";Opção inválida, entre novamente com a opção. <fail>[ FAIL ]</c>;");
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
  
  function playlist_menu(){
    $this->write(";Selecione a playlist que deseja importar:;;");
    foreach($this->playlists as $key => $playlist){
      $this->write("[".$key."] - ".$playlist.";");
    }
    return $this->get_playlist_menu();
  }
  
  function get_playlist_menu(){
    $this->write(";[0-".(count($this->playlists)-1)." | Q - quit]: ");
    return $this->stdin->read();
  }
  
  function send_musics($playlist_id = 0){
    if(isset($this->playlists[$playlist_id])){
      $this->write(";Preparando para enviar playlist '".$this->playlists[$playlist_id]."'... <ok> [ OK ]</c>;");
      if($this->check_folder($this->playlists[$playlist_id])){
        $this->write(";Enviando músicas... (".count($this->playlists_data[$playlist_id])." músicas)");
        $path = $this->path.'iTunes/'.$this->playlists[$playlist_id].'/';
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
    
    foreach($songs as $music){
      $return[(int)$music->integer[0]] = array(
        'name' => (string) $music->string[0],
        'location' => str_replace('file://localhost', '', (string) $music->string[count($music->string) -1]),
      );
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
  
  function write($text){
    $text = str_replace(';', "\n", $text);
    $text = str_replace('</c>', "\033[0m", $text); //Normal Color
    $text = str_replace('<ok>', "\033[92m", $text); //OK Color
    $text = str_replace('<info>', "\033[94m", $text); //INFO Color
    $text = str_replace('<fail>', "\033[91m", $text); //FAIL Color
    echo $text;
  }
  
  function check_dependence(){
    $this->write(";Verificando depêndencias...;");
    
    if(!is_dir($this->path)){
      $this->write("Não foi encontrado o local de destino. <fail>[ FAIL ]</c>;");
      $this->abort();
    }
  }
  
  function exit_menu(){
    $this->write(";Processar uma nova playlist?;[Y/n]: ");
    $choise = trim($this->stdin->read());
    if(strtolower($choise) == 'n') return false;
    return true;
  }
  
  function abort(){
    $this->write(";Processo abortado.;;");
    die();
  }
  
  function end(){
    $this->write(";Processo concluido.;;");
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