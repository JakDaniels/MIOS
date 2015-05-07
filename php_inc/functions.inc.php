<?php
function status_ok(&$status,&$m) {
	$status=_("OK");
	$m=E_MSG_CHAT;
}

function status_failed(&$status,&$m,&$ret) {
	$status=_("FAILED");
	$m=E_MSG_WARNING;
	$ret=0;
}

function print_messages(&$messages) {
	//print debug_print_array($messages,'messages');
	global $debug;
	for ($i=0;$i<count($messages['message']);$i++) {
		if($messages['severity'][$i]<=$debug) print $messages['message'][$i]."\n";
	}
	$messages=array();
}

function addmessage(&$messages,$severity,$message) {
	global $print_mode;
	$messages["severity"][]=$severity;
	$messages["message"][]=$message;
	if($print_mode==1) print "$message\n";
}

function crypt_md5($password,$salt='') {
	$s="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./";
	if ($salt=="") {
		$salt="$1$";
		for ($i=0;$i<9;$i++) $salt.=$s[rand(0,strlen($s)-1)];
	}
	return crypt($password,$salt);
}

function check_pwd($pwd,$minlength,$andtests) { //check a password for certain strength
	$m=0;
	if(strlen($pwd)>=$minlength) $m++;
	for($i=0;$i<count($andtests);$i++) {
		if(preg_match("/".$andtests[$i]."/",$pwd)) $m++;
	}
	if($m==($i+1)) return 1;
	return 0;
}

function date_swap1($dt) {
	return substr($dt,6).'-'.substr($dt,3,2).'-'.substr($dt,0,2);
}

function parse_args(&$argc,&$argv) {
	$argv[]="";
	$argv[]="";
	$args=array();
	//build a hashed array of all the arguments
	$i=1; $ov=0;
	while ($i<$argc) {
		if (substr($argv[$i],0,2)=="--") $a=substr($argv[$i++],2);
		elseif (substr($argv[$i],0,1)=="-") $a=substr($argv[$i++],1);
		else $a=$ov++;
		if (strpos($a,"=") >0) {
			$tmp=explode("=",$a);
			$args[$tmp[0]]=$tmp[1];
		} else {
			if (substr($argv[$i],0,1)=="-" or $i==$argc) $v=1;
			else $v=$argv[$i++];
			$args[$a]=$v;
		}
	}
	return $args;
}

function debug_print_array($array,$title='') {
	$o="\n---Debug Info for array \$$title------------------------\n";
	$o.=debug_print_array1($array);
	$o.="\n--------------------------------------------------------\n";
	return $o;
}

function debug_print_array1($array,$p=0) {
	$o="";
	if(gettype($array)=="array") {
		$o.=str_repeat (" ",$p);
		while (list($index, $subarray) = each($array) ) {
		  $tmp="* $index = ";
			$o.=$tmp;
			$o.=debug_print_array1($subarray,($p+strlen($tmp)));
		}
		$o.="\n".str_repeat(" ",$p);
	} else $o.=$array."\n";
	return $o;
}

function dprint($message) {
	if (DEBUG) {
		$messages=explode(chr(10),$message);
		for ($i=0;$i<count($messages);$i++) syslog(LOG_DEBUG,"$messages[$i]");
	}
}

function exec_cmd($cmd,$r_regexp) {
	global $debug;
	if($debug&SHOWCMDS) print "DEBUG: Executing $cmd\n";
	$r=`$cmd 2>&1`;
	if(preg_match("/$r_regexp/i",$r))	return 1;
	if($debug&LOGCMDERRORS or $debug&SHOWCMDERRORS) err_report($cmd,$r);
	return 0;
}

function err_report($cmd,$r) {
	global $debug,$logfile;
	if($debug&SHOWCMDERRORS) print "DEBUG: Command Failed with error:\n$r\n";
	if($debug&LOGCMDERRORS) {
		if($fp=@fopen($logfile,"ab")) {
			$log=date("Y-m-d H:i:s")." ********************************************************************\r\n"
						."Command: $cmd\r\nError: $r\r\n";
			fwrite($fp,$log);
			@fclose($fp);
		}
	}
}

function error($error) {
	print "$error";
	exit(1);
}

function signal_handler($signal) {
	global $must_exit;
	switch($signal) {
		case SIGTERM:
			$must_exit='SIGTERM';
			break;
		case SIGKILL:
			$must_exit='SIGKILL';
			break;
		case SIGINT:
			$must_exit='SIGINT';
			break;
	}
}

function makecurrency($value) { //pass a number or string to return a price
	return "&pound;".number_format(striptonum($value),2,".",",");
}

function clip_string($string,$length,$default="&nbsp;") {
	if (strlen($string)>$length) {
		$string=substr($string,0,$length-4);
		$s=strrpos($string," ");
		if ($s>0) $string=substr($string,0,$s);
		return $string." ...";
	}
	if (strlen($string)==0) return $default;
	return $string;
}

function pad_clip_string($string,$length,$default="&nbsp;",$pad=" ") {
	$a=strlen($string);
	if ($a>$length) return substr($string,0,$length-$a-4)." ...";
	if ($a==0) return $default.str_repeat($pad,$length);
	return $string.str_repeat($pad,$length-$a);
}


function strip_most_tags($string) {
	$string=preg_replace("/<([\/]*)(p|i|b|ul|li|blockquote)>/i",'{{$1$2}}',$string);
	$string=preg_replace("/<([\/]*)([^>]*)>/","",$string);
	$string=preg_replace("/{{([\/]*)(p|i|b|ul|li|blockquote)}}/i",'<$1$2>',$string);
	return $string;
}

function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}


function array_to_xml($array) {
	if(gettype($array)=="array") {
		$xml.="\n";
		while (list($index, $subarray) = each($array) ) {
			$xml.="<$index>";
			$xml.=array_to_xml($subarray);
			$xml.="<$index>\n";
		}
	} else $xml.=$array;
	return $xml;
}

/**
* takes a string including html unicode entities and converts it to a unicode binary encoded string
*/

function print_unicode($source) {
	print chr(255).chr(254);
	for ($i=0;$i<strlen($source);$i++) {
		if (substr($source,$i,2)=="&#") {
			$length=strpos($source,";",$i+2)-$i-2;
			$entity=substr($source,$i+2,$length);
			if (substr($entity,0,1)=="0") $entity=hexdec($entity);
			$i=$i+$length+2;
		}	else $entity=ord(substr($source,$i,1));
		print chr($entity%256).chr(floor($entity/256));
	}
}

/**
* takes a string of html unicode entities and converts it to a utf-8 encoded string
* each unicode entitiy has the form &#nnn(nn); n={0..9} and can be displayed by utf-8 supporting
* browsers.  Ascii will not be modified.
* @param $source string of unicode entities [STRING]
* @return a utf-8 encoded string [STRING]
* @access public
*/
function utf8Encode ($source) {
		$utf8Str = '';
		$entityArray = explode ("&#", $source);
		$size = count ($entityArray);
		for ($i = 0; $i < $size; $i++) {
				$subStr = $entityArray[$i];
				$nonEntity = strstr ($subStr, ';');
				if ($i>0 and $nonEntity !== false) {
						$unicode = intval (substr ($subStr, 0, (strpos ($subStr, ';') + 1)));
						// determine how many chars are needed to represent this unicode char
						if ($unicode < 128) {
								$utf8Substring = chr ($unicode);
						}	else if ($unicode >= 128 && $unicode < 2048) {
								$binVal = str_pad (decbin ($unicode), 11, "0", STR_PAD_LEFT);
								$binPart1 = substr ($binVal, 0, 5);
								$binPart2 = substr ($binVal, 5);

								$char1 = chr (192 + bindec ($binPart1));
								$char2 = chr (128 + bindec ($binPart2));
								$utf8Substring = $char1 . $char2;
						}	else if ($unicode >= 2048 && $unicode < 65536) {
								$binVal = str_pad (decbin ($unicode), 16, "0", STR_PAD_LEFT);
								$binPart1 = substr ($binVal, 0, 4);
								$binPart2 = substr ($binVal, 4, 6);
								$binPart3 = substr ($binVal, 10);

								$char1 = chr (224 + bindec ($binPart1));
								$char2 = chr (128 + bindec ($binPart2));
								$char3 = chr (128 + bindec ($binPart3));
								$utf8Substring = $char1 . $char2 . $char3;
						}	else {
								$binVal = str_pad (decbin ($unicode), 21, "0", STR_PAD_LEFT);
								$binPart1 = substr ($binVal, 0, 3);
								$binPart2 = substr ($binVal, 3, 6);
								$binPart3 = substr ($binVal, 9, 6);
								$binPart4 = substr ($binVal, 15);

								$char1 = chr (240 + bindec ($binPart1));
								$char2 = chr (128 + bindec ($binPart2));
								$char3 = chr (128 + bindec ($binPart3));
								$char4 = chr (128 + bindec ($binPart4));
								$utf8Substring = $char1 . $char2 . $char3 . $char4;
						}
						if (strlen ($nonEntity) > 1) $nonEntity = substr ($nonEntity, 1); // chop the first char (';')
						else $nonEntity = '';
						$utf8Str .= $utf8Substring . $nonEntity;
				}	else {
						$utf8Str .= $subStr;
				}
		}
		return $utf8Str;
}
function utf8_to_unicode($str) { //convert utf-8 string to unicode
	$unicode=array();
	$values=array();
	$lookingFor=1;
	for($i=0;$i<strlen($str);$i++) {
		$thisValue = ord( $str[ $i ] );
		if($thisValue<128) $unicode[] = $thisValue;
		else {
			if(count($values)==0) $lookingFor=($thisValue<224)?2:3;
			$values[]=$thisValue;
			if(count($values)==$lookingFor) {
				$number=($lookingFor==3)?(($values[0]%16)*4096)+(($values[1]%64)*64)+($values[2]%64):(($values[0]%32)*64)+($values[1]%64);
				$unicode[]=$number;
				$values=array();
				$lookingFor=1;
			}
		}
	}
	return $unicode;
}

function unicode_to_HTMLentities_preserving_ascii($unicode) { //$unicode is a double byte array
	$entities = '';
	foreach( $unicode as $value ) {
		$entities .= ( $value > 127 ) ? '&#' . $value . ';' : chr( $value );
  }
	return $entities;
}

function parse_ini($filepath) {
	$ini = @file($filepath);
	if($ini===false or count($ini) == 0 ) { return array(); }
	$sections = array();
	$values = array();
	$globals = array();
	$i = 0;
	foreach($ini as $line){
		$line = trim($line);
		// Comments
		if($line == '' || $line{0} == ';')  continue;
		if($line{0}=='[') {
			$sections[]=substr($line, 1, -1);
			$values[]=array();
			$i++;
			continue;
		}
		// Key-value pair
		list($key, $value) = explode('=', $line, 2);
		$key = trim($key);
		$value = trim($value);
		if($value{0}=='"') { //value in double quotes must end on second double quote
			$t=explode('"',$value);
			$value='"'.$t[1].'"';
		} else {	//otherwise value must end if not quoted and we reach a comment character (;)
			$t=explode(';',$value);
			$value=trim($t[0]);
		}
		if($i == 0) {
			// Array values
			if (substr($line, -1, 2) == '[]') {
				$globals[$key][] = $value;
			} else {
				$globals[$key] = $value;
			}
		} else {
			// Array values
			if(substr($line, -1, 2) == '[]' ) {
				$values[$i-1][$key][] = $value;
			} else {
				$values[$i-1][$key] = $value;
			}
		}
	}
	for($j=0; $j<$i; $j++) {
		$result[$sections[ $j ]] = $values[$j];
	}
	return $result + $globals;
}

function ini_merge($inis) {
	$ini=array();
	foreach($inis as $i) { //values in ini2 take precidence over ini1 etc.
		if(count($i)>0) {
			foreach($i as $section=>$data) {
				ksort($data);
				foreach($data as $k=>$v) {
					$ini[$section][$k]=$v;
				}
			}
		}
	}
	$sorted=array();
	ksort($ini);
	foreach($ini as $section=>$data) {
		ksort($data);
		$sorted[$section]=$data;
	}
	return $sorted;
}

function write_ini($filepath,$ini) {
	if($fp=@fopen($filepath,'wb')) {
		foreach($ini as $section=>$data) {
			fwrite($fp,"[".$section."]\n");
			foreach($data as $k=>$v) {
				if(preg_match("/[\ ]/",$v) and substr($v,0,1)!='"') fwrite($fp,"\t$k = \"$v\"\n");
				else fwrite($fp,"\t$k = $v\n");
			}
			fwrite($fp,"\n");
		}
		fclose($fp);
		return 1;
	}
	return 0;
}

function write_text_file($filepath,$text) {
	if($fp=@fopen($filepath,'wb')) {
		fwrite($fp,$text);
		fclose($fp);
		return 1;
	}
	return 0;
}

function write_text_file_from_array($filepath,$tarray) {
	if($fp=@fopen($filepath,'wb')) {
		foreach($tarray as $text) {
			fwrite($fp,$text."\n");
		}
		fclose($fp);
		return 1;
	}
	return 0;
}

function read_text_file_to_array($filepath) {
	if(file_exists($filepath)) return @file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	return array();
}

function write_text_file_from_array_with_lock($filepath,$tarray,$lock=LOCK_EX) {
	if($fp=@fopen($filepath,'wb')) {
		if(flock($fp, $lock)) {
			foreach($tarray as $text) {
				fwrite($fp,$text."\n");
			}
		}
		fflush($fp);            // flush output before releasing the lock
    flock($fp, LOCK_UN);    // release the lock
		fclose($fp);
		return 1;
	}
	return 0;
}

function read_text_file_to_array_with_lock($filepath,$lock=LOCK_EX) {
	$r=array();
	if(file_exists($filepath)) {
		if($fp=@fopen($filepath,'rb')) {
			if(flock($fp, $lock)) {
				while(!feof($fp)) {
					$l=trim(fgets($fp));
					if($l!='') $r[]=$l;
				}
			}
		}
    flock($fp, LOCK_UN);    // release the lock
		fclose($fp);
	}
	return $r;
}

function date_log($text) {
	print date('Y-m-d H:i:s')." ".$text;
}

?>