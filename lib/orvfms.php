<?php

/*************************************************************************
*  Copyright (C) 2015 by Fernando M. Silva   fcr at netcabo dot pt      *
*                                                                       *
*  This program is free software; you can redistribute it and/or modify *
*  it under the terms of the GNU General Public License as published by *
*  the Free Software Foundation; either version 3 of the License, or    *
*  (at your option) any later version.                                  *
*                                                                       *
*  This program is distributed in the hope that it will be useful,      *
*  but WITHOUT ANY WARRANTY; without even the implied warranty of       *
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        *
*  GNU General Public License for more details.                         *
*                                                                       *
*  You should have received a copy of the GNU General Public License    *
*  along with this program.  If not, see <http://www.gnu.org/licenses/>.*
*************************************************************************/
/*
      Main  functions to control the Orvibo (C) S20 swicth.

      This program was developed independently and it is not
      supported or endorsed in any way by Orvibo (C).
   
*/

include_once("globals.php"); // constants
include_once("utils.php");   // utitlity functions

function actionToTxt($st){
    return $st ? "ON" : "OFF";
}

function setTimer($mac,$h,$m,$s,$act,$s20Table){

    $ip=$s20Table[$mac]['ip'];
    $timeHex = hourToSecHex($h,$m,$s);
    $action  = ($act ? ONT : OFFT);
    $setTimer="00".$action.substr($timeHex,0,2).substr($timeHex,2,2);
 
    $cmdCode = "6364";
    $setTimerHexMsg=MAGIC_KEY."001A".$cmdCode.$mac.
                   TWENTIES.FOUR_ZEROS.$setTimer; 
   
    $stay = 1; $loop_count=0;
    while($stay && ($loop_count++ < MAX_RETRIES)){
        $s = createSocketAndBind($ip);
        $recHex = sendHexMsgWaitReply($s,$setTimerHexMsg,$ip);
        socket_close($s);
        $hexMsg = strtoupper($setTimerHexMsg);
        $recHex = strtoupper($recHex);
        $recCode = substr($recHex,2*18,2);
        $recHexAux = $recHex; 
        $recHexAux[36]="0";$recHexAux[37]="0";
        if(DEBUG){
            print("Send\n");
            printHex($hexMsg);
            print("Rec\n");
            printHex($recHex);
            print("RecAux\n");
            printHex($recHexAux);
        }        
        if(($recCode != "00") && ($recHexAux == $hexMsg))
            return 0;
        else
            error_log("Retrying in setTimer\n");
    }
    return 1;
}

function checkTimer($mac,$s20Table,&$h,&$m,&$s,&$action){
    
    $ip=$s20Table[$mac]['ip'];
    $cmdCode = "6364";
    $checkTimer="01000000";
    $checkTimerHexMsg = MAGIC_KEY."001A".$cmdCode.$mac.
                        TWENTIES.FOUR_ZEROS.$checkTimer; 
   
    $s = createSocketAndBind($ip);
    $recHex = sendHexMsgWaitReply($s,$checkTimerHexMsg,$ip);
    if(DEBUG){
        echo "Check timer\n";
        echo "Sent\n"; 
        printHex($checkTimerHexMsg);
        echo "Rec\n"; 
        printHex($recHex);
    }
    socket_close($s);

    $relevant = substr($recHex,-6);
    $status = substr($relevant,0,2);
    $isSet = 0;
    if($status!="FF"){
        $isSet = 1;
        $timeHex = substr($relevant,4,2).substr($relevant,2,2);
        $sec = hexdec($timeHex);
        secToHour($sec,$h,$m,$s); 
        if($status == "00")
            $action = 0; // Set to turn off
        else
            $action = 1; // Set to turn on
    }
    return $isSet;
}



function sendHexMsgWaitReply($s,$hexMsg,$ip){
    //
    // Sends msg specified by $hexMsg, in hexadecimal, to $ip and
    // waits for reply.
    // Returns the reply in hex format after checking major conditions
    //
    
    $magicKey = substr($hexMsg,0,4);               
    if($magicKey != MAGIC_KEY)
        error_log("Warning: wrong msg key in send msg (sendhexMsgWaitReply)!!");
    
    $msgCodeSend = substr($hexMsg,8,4);               
    //
    // double check msg length
    //
    $msgLenHex = substr($hexMsg,4,4);               
    $msgLen    = hexdec($msgLenHex);
    if($msgLen != strlen($hexMsg)/2){
        echo $hexMsg."\n";
        printHex($hexMsg);
        echo $msgLenHex." -> ".$msgLen."\n";
        error_log("Wrong msg length in sendHexWaitReply: Msg has ".strlen($hexMsg).
                 " bytes, code states ".$msgLen." bytes\n");        
    }
    $codeSend = substr($hexMsg,8,4);
    sendHexMsg($s,$hexMsg,$ip);
    $loop_count=0;
    for(;;){
        if(++$loop_count > MAX_RETRIES){
            echo "<h1> Error: too many retries without successfull replies</h1>\n";
            exit(0);
        }
        $n=@socket_recvfrom($s,$binRecMsg,BUFFER_SIZE,0,$recIP,$recPort);
        if($n == 0){
            // This is probably due to timeout; retry...
            sendHexMsg($s,$hexMsg,$ip);
            if(DEBUG)
                error_log( "retrying on update\n");
        }
        else{
            if($n >= 12){
                $recHexMsg     = hex_byte2str($binRecMsg,$n);                        
                $magicKey      = substr($recHexMsg,0,4);
                $recLenHex     = substr($recHexMsg,4,4);                
                $recLen        = hexdec($recLenHex);
                $msgCodeRec    = substr($recHexMsg,8,4);                
                if(DEBUG){
                    echo "Received: \n";
                    printHex($recHexMsg);
                    echo "Magic = ".$magicKey."\n";
                    echo "recLenHex  = ".$recLenHex." n = ".$n."\n";
                    echo "msgCodeRec = ".$msgCodeRec." (was ".$msgCodeSend.")\n";
                    echo "IP=".$recIP. " ".$ip."\n";
                }
                if(($magicKey == MAGIC_KEY) &&
                   ($n == $recLen) &&
                   ($recIP==$ip) &&
                   ($msgCodeRec == $msgCodeSend)) { 
                    // Everything seems OK
                    if(DEBUG) 
                        error_log("Number of retries subscribe = ".$loop_count."\n");
                    return $recHexMsg;
                }
            }
        }
    } /* Never reaches */;
    echo "<h1>Fatal Error in sendHexMsgWaitReply: reached end of function</h1>";
    exit(0);
    return "";
}

function sendHexMsg($s,$hexMsg,$ip){
    //
    // Send the datagram $hexMsg to address ($ip,PORT), using 
    // opened socket $s
    // $hexMsg is an hexadecimal coded sequence/string, therefore must be converted to
    // binary.
    // 
    if(strlen($hexMsg) % 2){
        error_log("Warning: odd hex msg in sendHexMsg");
    }
    if(strlen($hexMsg) == 0){
        echo "<h1>Fatal: attempting to send null msg len in sendHexMsg</h1>\n";
        exit(0);
    }
    $binMsg = hex_str2byte($hexMsg);
    $lenBinMsg=strlen($hexMsg)/2;
    if(!socket_sendto($s,$binMsg,$lenBinMsg,0,$ip,PORT)){
        echo "<h1>Error sending  message to socket in sendHexMsg, addr= ".$ip."</h1>\n";
        exit(0);
    }
}

function createSocketAndBind($ip){
    //
    // Create socket, bind it to local address (0.0.0.0,PORT),
    // for listening, sets timeout to receiving operations, 
    // and sends msge $msg to address ($ip,PORT)
    // 
    $s = socket_create(AF_INET,SOCK_DGRAM,0);
    if(!$s){
        echo "<h1>Error opening socket</h1>";
        exit(0);
    }
    
    $loop_count = 0;
    $stay = 1;
    while($stay){
        if(!socket_bind($s,"0.0.0.0",PORT)){
            if(++$loop_count > MAX_RETRIES){
                error_log("Fatal error binding to socket\n");
                echo "<h1>Error binding socket</h1>";
                exit(0);
            }
            usleep(TIMEOUT);
    	        }
        else{
            $stay = 0;
        }
    }
    if(DEBUG)
        error_log("Bind loop count = ".$loop_count);
    if(!socket_set_option($s,SOL_SOCKET,SO_BROADCAST,1)){
        echo "<h1>Error setting socket options</h1>";
        exit(0);
    }
    //
    // Set the timeout. Default set in globals.php
    // to 300ms; seems enough. 
    //
    $sec = (int)  TIMEOUT;
    $usec = (int) ((TIMEOUT - $sec) * 1000000.0);
    $timeout = array('sec' => $sec,'usec'=> $usec); 
    socket_set_option($s,SOL_SOCKET,SO_RCVTIMEO,$timeout);
    return $s;
}

function createSocketAndSendMsg($msg,$ip){
    //
    // Create socket, bind it to local address (0.0.0.0,PORT),
    // for listening, sets timeout to receiving operations, 
    // and sends msge $msg to address ($ip,PORT)
    // 
    $s = createSocketAndBind($ip);		
    sendHexMsg($s,$msg,$ip);			
    return $s;
}


function searchS20(){
    //
    // This function searchs for all S20 in a local network
    // through a broadcast call 
    // and returns an associative array $s20Table indexed
    // by each S20 mac adress. Each array position is itself 
    // an associative array which contains 
    //
    // $s20Table[$mac)['ip'] - IP adresss
    // $s20Table[$mac)['st'] - current S20 status (ON=1,OFF=0)
    // $s20Table[$mac)['imac'] - Inverted mac,not strictly required, 
    //                             computed just once for sake of efficiency.
    //
    // Note that $mac and is represented as a sequence of hexadecimals 
    // without the usual separators; for example, ac:cf:23:34:e2:b8 is represented
    // as "accf2334e2b8".
    //
    // An additional field $s20Table[$mac]['name'] is later added 
    // to each entry with the name assigned to each device. 
    // This is done in a specific function since it requires a separate
    // request to each S20 (see function getName() and fillNames below).
    //
    // Returns the $s20Table array
    //
    $s = createSocketAndSendMsg(DISCOVERY_MSG,IP_BROADCAST);
    $recIP="";
    $recPort=0;
    $s20Table=array();
    $loop_count = 0;
    while ( 1 ){
        $n=@socket_recvfrom($s,$binRecMsg,BUFFER_SIZE,0,$recIP,$recPort);
        if($n == 0){
            if(++$loop_count > 3){
                if(count($s20Table) == 0){
                    error_log("Giving up searching for sockets");
                    echo "<h1>Internal server error (see logs)</h1>";
                    exit(1);
                }
                else{
                    break;
                }
            }
            sendHexMsg($s,DISCOVERY_MSG,IP_BROADCAST);
            continue;
        }
        if($n >= 42){
            $recMsg = hex_byte2str($binRecMsg,$n);
            if((substr($recMsg,0,4) == MAGIC_KEY) && (substr($recMsg,8,4) == "7161")){
                $mac = substr($recMsg,14,12);
                $status = (int) substr($recMsg,-1);
                $s20Table[$mac]=array();
                $s20Table[$mac]['ip']=$recIP;
                $s20Table[$mac]['st']=$status;
                $s20Table[$mac]['imac']=invMac($mac);
            }
        }
    }
    socket_close($s);
    return $s20Table;
}

function subscribe($mac,$s20Table){
    //
    // Sends a subscribe message to S20 specified by mac address 
    // $mac, using global device information im $s20Table.
    // 
    // Returns the socket status 
    //
    if(!isset($s20Table)){
        echo "<h1>Internal server error</h1>";
        error_log("Found null s20Table in subscribe\n");
    }
    $imac = $s20Table[$mac]['imac'];
    $ip   = $s20Table[$mac]['ip'];
    $hexMsg = SUBSCRIBE.$mac.TWENTIES.$imac.TWENTIES;
    $s = createSocketAndBind($ip);
    $hexRecMsg = sendHexMsgWaitReply($s,$hexMsg,$ip);
    $status = (int) hexdec(substr($hexRecMsg,-2,2));
    socket_close($s);
    return $status;
}

function getTable($mac,$table,$vflag,$s20Table){
    $tableHex = dechex($table);
    if(strlen($tableHex) == 1)
        $tableHex = "0".$tableHex;
    $hexMsg = "6864001D7274".$mac.TWENTIES."00000000".$tableHex."00".$vflag."00000000";    
    $ip = $s20Table[$mac]['ip'];
    $s = createSocketAndBind($ip);
    $hexRec = sendHexMsgWaitReply($s,$hexMsg,$ip);
    socket_close($s);
    return $hexRec;
}

function getName($mac,$s20Table){
    //
    // Returns the registered name in S20 specified by the mac $mac.
    // Uses previous device information available in $s20Table.
    //
    subscribe($mac,$s20Table);
    $table = 4; $vflag = "17";
    $recTable = getTable($mac,$table,$vflag,$s20Table);
    $binTable = hex_str2byte($recTable);
    $name = substr($binTable,70,16);
    return trim($name);
}

function getNameOld($mac,$s20Table){
    //
    // Returns the registered name in S20 specified by the mac $mac.
    // Uses previous device information available in $s20Table.
    //
    $ip = $s20Table[$mac]['ip'];
    subscribe($mac,$s20Table);
    $getSocketData = "6864001D7274".$mac.TWENTIES."0000000004001700000000";    
    $s = createSocketAndSendMsg($getSocketData,$ip);
    $recIp = ""; $recPort=0;
    $stay = 1;
    $loop_count = 0;
    while($stay){
        if(++$loop_count > MAX_RETRIES){
            echo "<h1> Error: too many retries without successfull reply in getName()</h1>\n";
            exit(0);
        }
        $n=@socket_recvfrom($s,$binRecMsg,168,0,$recIP,$recPort);        
        if($n == 0){
            // This is probably due to timeout; retry...
            if(DEBUG) 
                error_log("retrying in getName()".$loop_count);
            sendHexMsg($s,$getSocketData,$ip);
        }
        else{
            $recMsg = hex_byte2str($binRecMsg,$n);            
            //            print_r( "getName (".$n.")=".$recMsg."\n");
            if($n==168){
                if(substr($recMsg,0,4)==MAGIC_KEY){
                    $rmac = substr($recMsg,12,12);
                    if($rmac == $mac){
                        $name = substr($binRecMsg,70,16);
                        $stay=0;
                    }
                }
            }
        }
        //        ob_flush();
    }
    if(DEBUG) 
        error_log("Number of retries getName = ".$loop_count."\n");
    socket_close($s);
    return trim($name);
}

function fillNames($s20Table){
    //
    // Loos through all S20 regiestered in $s20Table and
    // fills the name in each entry
    // 
    // 
    foreach($s20Table as $mac => $devData){
        $name = getName($mac,$s20Table);
        $s20Table[$mac]['name'] = $name;
    }    
    return $s20Table;
}

function initS20Data(){
    //
    // Search all sockets in the network, and returns 
    // an associative array with all collected data,
    // including names
    //
    $s20Table = searchS20();
    $s20Table = fillNames($s20Table);
    return $s20Table;
}

function checkStatus($mac,$s20Table){
    //
    // Checks the power status of the S20 speciifed by
    // mac adresss $mac using available information in 
    // $s20Table. This is basically done with a subscribe 
    // function (see above)
    // 
    return subscribe($mac,$s20Table);
}

function updateAllStatus($s20Table){
    //
    // This function updates the power status of all S20 in $allAllS20Data.
    //
    // InitS20Data also fills the power status when it is called.
    // However, this function is more efficient when $s20Table
    // was already initialized and relevant available 
    // and one just wants to update the power status of all S20s
    //
    foreach($s20Table as $mac => $devData){
        $s20Table[$mac]['st'] = checkStatus($mac,$s20Table);
    }
    return $s20Table;
}

function sendAction($mac,$action,$s20Table){
    //
    // Sends an $action (ON=1, OFF = 0) to S20 specified by $mac
    // It retries until a proper reply is received with the desired 
    // power status
    // However, we have detected that the reported power status just
    // after an action fails sometimes, and therefore you should not
    // use this function alone. Prefer switchAndCheck() below, which 
    // performs a double check of the final state.
    //
    subscribe($mac,$s20Table);
    $msg = ACTION.$mac.TWENTIES; 
    if($action)
        $msg .= ON;
    else
        $msg .= OFF;
    $ip = $s20Table[$mac]['ip'];
    $s = createSocketAndBind($ip);    
    $hexRecMsg = sendHexMsgWaitReply($s,$msg,$ip);    
    $status = (int) hexdec(substr($hexRecMsg,-2,2));    
    socket_close($s);
}

function actionAndCheck($mac,$action,$s20Table){
    /*
      This function implements a switch and check satus.
      The check is in fact a double check and should not 
      be required, since the sendAction function checks the 
      power status itself in the reply command and only gives up
      when the correct reply is received. 
      Nevertheless, we have seen the S20 fail report the 
      wrong status sometimes on power on/power off actions 
      Checking the status through a separate subscribe command
      seems ro be able to always get the right status.
    */
    $stay = 1;
    $loop_count = 0;
    while($stay){
        if(++$loop_count > MAX_RETRIES){
            echo "<h1> Error: too many retries without successfull action in actionAndCheck ()</h1>\n";
            exit(0);
        }
        sendAction($mac,$action,$s20Table);
        $st = checkStatus($mac,$s20Table);
        if($st == $action){
            $stay = 0;
        }
        else{
            $logmsg = "switch action FAILED, repeating:\n".
                    " (ordered=".$action." checked=".$st.")\n";
            error_log($logmsg);
        }
    } 
    if(DEBUG) 
        error_log("Number of retries actionAndCheck() = ".$loop_count."\n");
    return $st;
}

function getMacFromName($name,$s20Table){
//
// Returns the $mac address of the S20 with name $name
//
    $count = 0;
    foreach($s20Table as $imac => $devData){
        if($devData['name'] == $name){
            $mac = $imac;
            $count++;
        } 
    }
    if($count == 0){
        echo "<h1>Not found S20 with name ".$name." </h1>\n";
        exit(0);
    }
    if($count > 1){
        echo "<h1>Ambiguous: more than one S20 found with same name  ".$name." result may be incorrect</h1>\n";
    }

    return $mac;
}

function sendActionByDeviceName($name,$action,$s20Table){
    //
    // Sends an action to device designates with $name
    //    
    $mac = getMacFromName($name,$s20Table);
    return actionAndCheck($mac,$action,$s20Table);
}
?>

