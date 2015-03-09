<?php
/**
* Orvibo 
*
* Orvibo
*
* @package project
* @author Serge J. <jey@tut.by>
* @copyright http://www.atmatic.eu/ (c)
* @version 0.1 (wizard, 11:03:11 [Mar 09, 2015])
*/
//
//
class orvibo extends module {
/**
* orvibo
*
* Module class constructor
*
* @access private
*/
function orvibo() {
  $this->name="orvibo";
  $this->title="Orvibo";
  $this->module_category="<#LANG_SECTION_DEVICES#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['TAB']=$this->tab;
  if ($this->single_rec) {
   $out['SINGLE_REC']=1;
  }
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }

 $this->getConfig();
 $out['API_URL']=$this->config['API_URL'];
 if (!$out['API_URL']) {
  $out['API_URL']='localhost';
 }
 $out['API_ENABLE']=(int)$this->config['API_ENABLE'];

 if ($this->view_mode=='update_settings') {
   global $api_url;
   global $api_enable;
   $this->config['API_URL']=$api_url;
   $old_status=$this->config['API_ENABLE'];
   $this->config['API_ENABLE']=(int)$api_enable;
   if ($this->config['API_ENABLE']!=$old_status) {
    SaveFile(ROOT.'reboot');
   }
   $this->saveConfig();
   $this->redirect("?");
 }


 if ($this->data_source=='orvibodevices' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_orvibodevices') {
   $this->search_orvibodevices($out);
  }
  if ($this->view_mode=='edit_orvibodevices') {
   $this->edit_orvibodevices($out, $this->id);
  }
  if ($this->view_mode=='delete_orvibodevices') {
   $this->delete_orvibodevices($this->id);
   $this->redirect("?");
  }
 }
}
/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
 $this->admin($out);
}
/**
* orvibodevices search
*
* @access public
*/
 function search_orvibodevices(&$out) {
  require(DIR_MODULES.$this->name.'/orvibodevices_search.inc.php');
 }
/**
* orvibodevices edit/add
*
* @access public
*/
 function edit_orvibodevices(&$out, $id) {
  require(DIR_MODULES.$this->name.'/orvibodevices_edit.inc.php');
 }
/**
* orvibodevices delete record
*
* @access public
*/
 function delete_orvibodevices($id) {
  $rec=SQLSelectOne("SELECT * FROM orvibodevices WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM orvibodevices WHERE ID='".$rec['ID']."'");
 }
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  parent::install();
 }

function propertySetHandle($object, $property, $value) {
   $devices=SQLSelect("SELECT ID FROM orvibodevices WHERE LINKED_OBJECT LIKE '".DBSafe($object)."' AND LINKED_PROPERTY LIKE '".DBSafe($property)."'");
   $total=count($devices);
   if ($total) {
    for($i=0;$i<$total;$i++) {
     $this->setStatus($devices[$i]['ID'], $value);
    }
   }
 }


function setStatus($id, $status) {

 $this->getConfig();
 $this->port=10000;

 $twenties=array(0x20, 0x20, 0x20, 0x20, 0x20, 0x20);

 $this->port=10000;   
 $rec=SQLSelectOne("SELECT * FROM orvibodevices WHERE ID='".$id."'");
 if ($rec['ID']) {
  if(!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
  {
     $errorcode = socket_last_error();
     $errormsg = socket_strerror($errorcode);
     die("Couldn't create socket: [$errorcode] $errormsg \n");
  }

  $payload = $this->makePayload(array(0x68, 0x64, 0x00, 0x17, 0x64, 0x63)).$this->makePayload($this->HexStringToArray($rec['MAC'])).$this->makePayload($twenties);
  if((int)$status) {
   $payload.=$this->makePayload(array(0x00, 0x00, 0x00, 0x00, 0x01)); // ON
  } else {
   $payload.=$this->makePayload(array(0x00, 0x00, 0x00, 0x00, 0x00)); // OFF
  }

  //echo "Sending status change (".$rec['IP'].":".$this->port."): ".$this->binaryToString($payload)."\n";

  socket_sendto($sock, $payload, strlen($payload), 0, $rec['IP'], $this->port); 
  socket_close($sock);
  $rec['STATUS']=$status;
  $rec['UPDATED']=date('Y-m-d H:i:s');
  SQLUpdate('orvibodevices', $rec);
 }
}

/**
* Title
*
* Description
*
* @access public
*/
 function statusChanged($id, $status) {
  $rec=SQLSelectOne("SELECT * FROM orvibodevices WHERE ID='".$id."'");  
  $old_value=$rec['STATUS'];
  $rec['STATUS']=$status;
  $rec['UPDATED']=date('Y-m-d H:i:s');
  SQLUpdate('orvibodevices', $rec);

  if ($rec['LINKED_OBJECT'] && $rec['LINKED_PROPERTY']) {
   setGlobal($rec['LINKED_OBJECT'].'.'.$rec['LINKED_PROPERTY'], $rec['STATUS'], array($this->name=>'0'));
  }

  if ($rec['LINKED_OBJECT'] && $rec['LINKED_METHOD'] && ($rec['STATUS']!=$old_value)) {
    $params=array();
    $params['VALUE']=$rec['STATUS'];
    callMethod($rec['LINKED_OBJECT'].'.'.$rec['LINKED_METHOD'], $params);
  }

 }

/**
* Title
*
* Description
*
* @access public
*/
 function discover($sock) {
     $payload = $this->makePayload(array(0x68, 0x64, 0x00, 0x06, 0x71, 0x61));
     echo date('H:i:s')." Sending multicast: ".$this->binaryToString($payload)."\n";
     socket_sendto($sock, $payload, strlen($payload), 0, '255.255.255.255', $this->port); 
 }

/**
* Title
*
* Description
*
* @access public
*/
 function processMessage($buf, $remote_ip, $sock) {

     echo date('H:i:s')." $remote_ip : " . $this->binaryToString($buf)."\n";
     $twenties=array(0x20, 0x20, 0x20, 0x20, 0x20, 0x20);

     $message=$this->binaryToString($buf);
     $macAddress = '';
     if (is_integer(strpos($message, 'accf'))) {
      $macAddress = substr($message, strpos($message, 'accf'),12);
     }
     if (!$macAddress) {
      return;
     }

     echo date('H:i:s')." MAC: ".$macAddress."\n";
     $command=substr($message, 8,4);
     $rec=SQLSelectOne("SELECT * FROM orvibodevices WHERE MAC LIKE '".DBSafe($macAddress)."'");
     if (!$rec['ID'] && $command!='7161') {
      echo date('H:i:s')." Unknown device.";
      return;
     }
     if ($command=='7161') { // We've asked for all sockets on the network, and smoeone has replied!
      echo date('H:i:s')." Discover reply from $macAddress\n";
      if (is_integer(strpos($message, '4952443030'))) { //from a known AllOne (because IR00 appears in the packet)
       $rec['TYPE']=1;
       if (!$rec['TITLE']) {
        $rec['TITLE']='AllOne - '.$macAddress;
       }
      } elseif (is_integer(strpos($message, '534f433030'))) { //socket
       $rec['TYPE']=0;
       $rec['TITLE']='Socket - '.$macAddress;
      }
      $rec['MAC']=$macAddress;
      $rec['IP']=$remote_ip;
      $rec['UPDATED']=date('Y-m-d H:i:s');
      if ($rec['ID']) {
       SQLUpdate('orvibodevices', $rec);
      } else {
       SQLInsert('orvibodevices', $rec);
      }

      //subscribe to it
      if (!$this->subscribed[$rec['MAC']]) {
       $macReversed=array_reverse($this->HexStringToArray($rec['MAC']));
       $payload = $this->makePayload(array(0x68, 0x64, 0x00, 0x1e, 0x63, 0x6c)).$this->makePayload($this->HexStringToArray($rec['MAC'])).$this->makePayload($twenties).$this->makePayload($macReversed).$this->makePayload($twenties);
       echo date('H:i:s')." Sending subscribe request: ".$this->binaryToString($payload)."\n";
       socket_sendto($sock, $payload, strlen($payload), 0, $rec['IP'], $this->port); 
       $this->subscribed[$rec['MAC']]=1;

       //query for name (optional)
       /*
       $payload = $this->makePayload(array(0x68, 0x64, 0x00, 0x1d, 0x72, 0x74));
       $payload.=$this->makePayload($this->HexStringToArray($rec['MAC']));
       $payload.=$this->makePayload($twenties);
       $payload.=$this->makePayload(array(0x00, 0x00, 0x00, 0x00, 0x04, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00));
       echo date('H:i:s')." Sending name request: ".$this->binaryToString($payload)."\n";
       socket_sendto($sock, $payload, strlen($payload), 0, $rec['IP'], $this->port); 
       */
      }


     } elseif ($command=='7274') { // We've queried the socket for the name, and we've got data coming back
       echo date('H:i:s')." Name reply from $macAddress\n";
       $tmp=explode('202020202020', $message);
       $strName=$tmp[4];
       if(strName == "ffffffffffffffffffffffffffffffff") {
        $strName='Orvibo '.$rec['MAC'];
        if ($rec['TYPE']==0) {
         $strName.=" (socket)";
        }
       } else {
        $strName=trim($this->HexStringToString($strName));
       }
       if (!$rec['TITLE']) {
        $rec['TITLE']=$strName;
        SQLUpdate('orvibodevices', $rec);
       }

     } elseif ($command=='636c') { // We've asked to subscribe to an AllOne, and this is confirmation.
       echo date('H:i:s')." Subscription reply from $macAddress\n";
     } elseif ($command=='6463') { // We've asked to change our state, and it's happened
       echo date('H:i:s')." State change reply from $macAddress\n";
     } elseif ($command=='6469') { // Possible button press, or just a ping thing?
       echo date('H:i:s')." Button pressed from $macAddress\n";
     } elseif ($command=='7366') { // Something has changed our socket state externally
       echo date('H:i:s')." Socket state changed from $macAddress\n";
       if ($rec['TYPE']==0) {
        $state=substr($message, strlen($message)-1, 1);
        $this->statusChanged($rec['ID'], (int)$state);
       }
     } elseif ($command=='6963') { // We've asked to emit some IR and it's been done.
       echo date('H:i:s')." Emit IR done from $macAddress\n";
     } elseif ($command=='6c73') { // We're in learning mode, and we've got some IR back!
       echo date('H:i:s')." IR learning mode done from $macAddress\n";
     } else {
      echo date('H:i:s')." Unknown command: $command \n";
     }

  
 }

/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
  SQLExec('DROP TABLE IF EXISTS orvibodevices');
  parent::uninstall();
 }

 function makePayload($data) {
  $res='';
  foreach($data as $v) {
   $res.=chr($v);
  }
  return $res;
 }

  function HexStringToArray($buf) {
   $res=array();
   for($i=0;$i<strlen($buf)-1;$i+=2) {
    $res[]=(hexdec($buf[$i].$buf[$i+1]));
   }
   return $res;   
  }

  function HexStringToString($buf) {
   $res='';
   for($i=0;$i<strlen($buf)-1;$i+=2) {
    $res.=chr(hexdec($buf[$i].$buf[$i+1]));
   }
   return $res;   
  }


  function binaryToString($buf) {
   $res='';
   for($i=0;$i<strlen($buf);$i++) {
    $num=dechex(ord($buf[$i]));
    if (strlen($num)==1) {
     $num='0'.$num;
    }
    $res.=$num;
   }
   return $res;
  }

/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
 function dbInstall() {
/*
orvibodevices - Devices
*/
  $data = <<<EOD
 orvibodevices: ID int(10) unsigned NOT NULL auto_increment
 orvibodevices: TITLE varchar(255) NOT NULL DEFAULT ''
 orvibodevices: TYPE int(3) NOT NULL DEFAULT '0'
 orvibodevices: MAC char(50) NOT NULL DEFAULT ''
 orvibodevices: IP char(50) NOT NULL DEFAULT ''
 orvibodevices: STATUS int(3) NOT NULL DEFAULT '0'
 orvibodevices: LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
 orvibodevices: UPDATED datetime
 orvibodevices: LINKED_PROPERTY varchar(255) NOT NULL DEFAULT ''
 orvibodevices: LINKED_METHOD varchar(255) NOT NULL DEFAULT ''
EOD;
  parent::dbInstall($data);
 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgTWFyIDA5LCAyMDE1IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/