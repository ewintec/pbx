<?php
//Call controller class for  a PBX machine
//Author :: Danny Simfukwe
//Email :: dannysimfukwe@gmail.com


class call_controller {
  public $server;
  public $username;
  public $secret;
  public $port;
  public $num;
  public $ext;
  var $socket;
  var $error;


  public function dial()
  {
      $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
      if(!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
       }else{
      stream_set_timeout($this->socket, 1); 
           $originate_action = array(implode("\r\n", array(
          'Action: Originate',
          'Callerid: '.$this->ext.'',
          'Channel: SIP/'.$this->ext.'',
          'Exten: '.$this->num.'',
          'Context: CallingRule_pstnout',
          'Priority: 1',
          'Timeout: 30000',
          'Async: yes',
          '',''
        )));
      $wrets = $this->Login();
      $wrets .= $this->Query($originate_action[0]);
      $wrets .= $this->record($channel='SIP/'.$this->ext,$filename='test1'); //Start monitoring this channel
      $response = $this->getActions(); //we return the new SIP in json format
      
      }

	 return $response;

  }


  public function getActions()
  {
    $socket = fsockopen($this->server,$this->port, $errno, $errstr, 1);
    fputs($socket, "Action: Login\r\n");
    fputs($socket, "UserName: $this->username\r\n");
    fputs($socket, "Secret: $this->secret\r\n\r\n");
    fputs($socket, "Action: CoreShowChannels\r\n\r\n");
    fputs($socket, "Action: Logoff\r\n\r\n");
    $count=0;$array;
    while (!feof($socket)) {
    $wrets = fgets($socket, 8192);
    $token = strtok($wrets,':(');
    $j=0;
    while($token!=false & $count>=5)
    {
    $array[$count][$j]=$token;
    $j++; $token = strtok(':(');
    }
    $count++;
  }

    for($i=5;$i<$count-4;$i++){
	 if($array[$i][0] =='Channel'){
		$newSIP =  $array[$i][1];
    break;
	}

}

fclose($socket);

return json_encode(array("sip"=>$newSIP));


}


public function get3way()
{
$socket = fsockopen($this->server,$this->port, $errno, $errstr, 1);
fputs($socket, "Action: Login\r\n");
fputs($socket, "UserName: $this->username\r\n");
fputs($socket, "Secret: $this->secret\r\n\r\n");
fputs($socket, "Action: CoreShowChannels\r\n\r\n");
fputs($socket, "Action: Logoff\r\n\r\n");
$count=0;$array;
while (!feof($socket)) {
$wrets = fgets($socket, 8192);
$token = strtok($wrets,':(');
$j=0;
while($token!=false & $count>=5)
{
$array[$count][$j]=$token;
$j++; $token = strtok(':(');
}
$count++;
}

for($i=5;$i<$count-4;$i++){
    if($array[$i][0] =='Channel'){
    $newSIP =  trim($array[$i][1]);
    break;
  }

}

fclose($socket);

return $newSIP;


}


protected function Login(){
    
$this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
    $this->error =  "Could not connect - $errstr ($errno)";
    return FALSE;
}else{
      stream_set_timeout($this->socket, 1); 
          $login_action = array(implode("\r\n", array(
          'Action: Login',
          'UserName: '.$this->username.'',
          'Secret: '.$this->secret.'',
          'Events: off',
          '',''
        )));
      $wrets = $this->Query($login_action[0]);
      if (strpos($wrets, "Message: Authentication accepted") != FALSE){
        return true;
      }else{
        $this->error = "Could not login - Authentication failed";
        fclose($this->socket); 
        $this->socket = FALSE;
        return FALSE;
      }
    }
  }
  


  protected function Logout(){
    if ($this->socket){
      fputs($this->socket, "Action: Logoff\r\n\r\n"); 
      while (!feof($this->socket)) { 
        $wrets .= fread($this->socket, 8192); 
      } 
      fclose($this->socket); 
      $this->socket = "FALSE";
    }
  	return; 
  }
  
  public function Query($query){
    $wrets = "";
    
    if ($this->socket === FALSE)
      return FALSE;
      
    fputs($this->socket, $query); 
    do
    {
      $line = fgets($this->socket, 8192);
      $wrets .= $line;
      $info = stream_get_meta_data($this->socket);
    }while ($line != "\r\n" && $info['timed_out'] == false );
    return $wrets;
  }
  
  public function GetError(){
    return $this->error;
  }


public function Attended_transfer()
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
      stream_set_timeout($this->socket, 1); 
          $transfer_action = array(implode("\r\n", array(
          'Action: Atxfer',
          'Channel: '.$this->ext.'',
          'Exten: '.$this->num.'',
          'Context: default', //put your calling rule here
          'Priority: 1',
          'Timeout: 30000',
          'Async: yes',
          '',''
        )));
	  $wrets = $this->Login();
    $wrets .= $this->Query($transfer_action[0]);
    $wrets .= $this->record($channel=$this->ext,$filename='test2'); //Start monitoring this channel
	  return $wrets;

}

}


public function Redirect_transfer()
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
    stream_set_timeout($this->socket, 1); 
    $wrets = $this->Login();
    $trunk = $this->get3way();
    $redirect_action = array(implode("\r\n", array(
          'Action: Redirect',
          'Channel: '.$trunk.'',
          'ExtraChannel: '.$this->ext.'',
          'Exten: 103', //replace 103 with your conference extension
          'Context: conferences',
          'Priority: 1',
          'ExtraPriority: 1',
          'ExtraContext: conferences',
          'ExtraExten:103', //replace 103 with your conference extension **
          'Timeout: 30000',
          'Async: yes',
          '',''
        )));
    $originate_action = array(implode("\r\n", array(
          'Action: Originate',
          'Callerid: '.$this->ext.'',
          'Channel: local/103@conferences',
          'Exten: '.$this->num.'',
          'Context: default', // put your calling rule here
          'Priority: 1',
          'Timeout: 30000',
          'Async: yes',
          '',''
        )));
    $wrets .= $this->Query($redirect_action[0]); //redirect to a conference call
    $wrets .= $this->Query($originate_action[0]); // add a third person to the conference call
    $wrets .= $this->record($channel=$trunk,$filename='testing'); //Start monitoring this channel
    return $wrets;

}
}

public function mute()
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
          stream_set_timeout($this->socket, 1); 
          $mute_action = array(implode("\r\n", array(
          'Action: MeetmeMute',
          'Meetme: 103', // put your conference extension here
          'Usernum: 1',
          '',''
        )));
    $wrets = $this->Login();
    $wrets .= $this->Query($mute_action[0]);
    return $wrets;

}
}

public function unmute()
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
          stream_set_timeout($this->socket, 1); 
          $unmute_action = array(implode("\r\n", array(
          'Action: MeetmeUnMute',
          'Meetme: 103', //replace 103 with your conference extension
          'Usernum: 1',
          '',''
        )));
    $wrets = $this->Login();
    $wrets .= $this->Query($unmute_action[0]);
    return $wrets;

}
}

public function record($channel,$filename)
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
          stream_set_timeout($this->socket, 1); 
          $record_action = array(implode("\r\n", array(
          'Action: MixMonitor',
          'Channel: '.$channel.'',
          'File: /media/somepath/to/storage/'.$filename.time().'.wav',
          'Format: wav',
          'Mix: True',
          'ActionID: '.$this->num.'',
          '',''
        )));
    $wrets = $this->Login();
    $wrets .= $this->Query($record_action[0]);
    return $wrets;

}
}

public function record_pause()
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
          $trunk = $this->get3way();
          stream_set_timeout($this->socket, 1); 
          $pause_action = array(implode("\r\n", array(
          'Action: PauseMonitor',
          'Channel: '.$trunk.'',
          'ActionID: '.$this->num.'',
          '',''
        )));
    $wrets = $this->Login();
    $wrets .= $this->Query($pause_action[0]);
    return $wrets;

}
}

public function record_unpause()
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
          $trunk = $this->get3way();
          stream_set_timeout($this->socket, 1); 
          $unpause_action = array(implode("\r\n", array(
          'Action: UnPauseMonitor',
          'ActionID: '.$this->num.'',
          '',''
        )));
    $wrets = $this->Login();
    $wrets .= $this->Query($unpause_action[0]);
    return $wrets;

}
}

public function record_stop()
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
          $trunk = $this->get3way();
          stream_set_timeout($this->socket, 1); 
          $stop_action = array(implode("\r\n", array(
          'Action: StopMonitor',
          'Channel: '.$trunk.'',
          '',''
        )));
    $wrets = $this->Login();
    $wrets .= $this->Query($stop_action[0]);
    return $wrets;

}
}


public function extension_status()
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
          $trunk = $this->get3way();
          stream_set_timeout($this->socket, 1); 
          $status_action = array(implode("\r\n", array(
          'Action: ExtensionState',
          'Context: default',
          'Exten: 103', //replace 103 with your conference extension
          'ActionID: 1234',
          '',''
        )));
    $wrets = $this->Login();
    $wrets .= $this->Query($status_action[0]);
    return $wrets;

}
}

public function hangup()
{
    $this->socket = @fsockopen($this->server,$this->port, $errno, $errstr, 1); 
    if (!$this->socket) {
      $this->error =  "Could not connect - $errstr ($errno)";
      return FALSE;
    }else{
          $trunk = $this->get3way();
          stream_set_timeout($this->socket, 1); 
          $hangup_action = array(implode("\r\n", array(
          'Action: Hangup',
          'Channel: '.$trunk.'',
          '',''
        )));
    $wrets = $this->Login();
    $wrets .= $this->Query($hangup_action[0]);
    return $wrets;

}
}


}


// Calling usage example 

  $caller = new call_controller();
  $caller->server = "127.0.0.1";
  $caller->username = "username";
  $caller->secret = "password";
  $caller->port = "5038";
  $caller->num = "071954897";
  $caller->ext = "100"; //extension to use
  print  $caller->dial();




?>
