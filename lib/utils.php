<?php

/*************************************************************************
*  Copyright (C) 2015 by Fernando M. Silva   fcr@netcabo.pt             *
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
   General purspose utility functions 
*/

function hex_str2byte($string){
    //
    // Converts a string with pairs of hexadecimal numbers in a binary
    // buffer array. Extracted from an example published in  http://pastebin.com/Jizwptqc
    //
    $bytes = "";
    for($i = 0; $i < strlen($string); $i+=2) {
        $bytes .= chr(hexdec(substr($string, $i, 2)));
    }
    return $bytes;
}

function hex_byte2str($bytes,$n){
    //
    // Convert a binary (non human readable) array of size $n
    // on a sequence of readable 2 hexadecimal numbers.
    //
    $str="";
    for($i = 0; $i < $n ; $i++){
        $hex = dechex(ord($bytes[$i]));
        if(strlen($hex) == 1){
            $hex="0".$hex;
        }             
        $str .= $hex;
    }     
    return strtoupper($str);
}

function invMac($mac){
    //
    // Invert a $mac address from big endian to little endian (and conversely)
    // Both mac and reverse should be represented as a sequence of hexadecimals 
    // without the usual separators; for example, ac:cf:23:34:e2:b8 should
    // be supplied as "accf2334e2b8" and it returns as "b8e23423cfac"
    //
    if(strlen($mac) != 12){
        echo "<h1>Wrong arg to invMac</h1>\n";
        exit(0);
    }
    $imac="";
         for($i = 0; $i < 12; $i+=2)
             $imac = substr($mac,$i,2).$imac;
    return $imac;
}

function  printHex($hexMsg){
    $n = strlen($hexMsg)/2;
    echo"\n\n";
    $col = 0;
    $lin = 0;
    for($k=0; $k <$n; $k++){
        if($col++ == 0){
            echo sprintf(" %02d    ",$lin++);
        }
        $pair = substr($hexMsg,2*$k,2);
        echo sprintf(" %2s",$pair);
        if($col == 16){
            $col = 0;
            echo "\n";
        }
    }
    echo "\n";
}


function secToHour($seconds,&$hour,&$min,&$sec){
    $min = (int) ($seconds / 60);
    $sec = $seconds % 60;
    $hour = (int) ($min / 60);
    $min = $min % 60;
}
    
function hourToSec($h,$min,$seconds){
    $sec = $h * 3600 + $min * 60 + $seconds;
    return $sec;
}

function hourToSecHex($h,$min,$seconds){
    //    converts hour, min and sec to a little endian hexadecimal 
    //    representation of the number of second;
    //
    $sec = hourToSec($h,$min,$seconds);
    $hexSec = dechex($sec);
    $n = 4 - strlen($hexSec);
    for($i = 0; $i < $n ; $i++){
        $hexSec = "0".$hexSec;
    }
    $hexSecLE = substr($hexSec,2,2).substr($hexSec,0,2); // return Little Endian
    return $hexSecLE;
}
    
?>