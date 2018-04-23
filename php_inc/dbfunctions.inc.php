<?php

function select_db_server($id) {
	global $dbserver, $dbuser, $dbpwd, $dbdb;
	global $SqlLink, $mysql, $dbcurrentid, $debug;
	if(isset($mysql[$id])) {
		if(isset($dbcurrentid)) $mysql[$dbcurrentid]['sqllink']=$SqlLink; //cache our current connection handle
		$dbserver=$mysql[$id]['server'];
		$dbuser=$mysql[$id]['user'];
		$dbpwd=$mysql[$id]['pwd'];
		if(isset($mysql[$id]['db'])) $dbdb=$mysql[$id]['db']; else $dbdb='';
		//now reset the handle so a new connection is made (0) or use an already cached connection
		if(isset($mysql[$id]['sqllink'])) $SqlLink=$mysql[$id]['sqllink']; else $SqlLink=0;
		$dbcurrentid=$id;

		if($SqlLink and !mysql_ping($SqlLink)) {
			mysql_close($SqlLink);
			if($SqlLink =mysql_pconnect($dbserver,$dbuser,$dbpwd)) {
				if($dbdb!='') mysql_select_db($dbdb,$SqlLink);
			}
		}

	} else {
		if($debug) print "WARNING: you selected db: '$id' which is not defined! Current db is still: '$dbcurrentid'!\n";
	}
}

function dbquery($query) {
	global $SqlLink,$debug,$logcmd;
	global $dbserver, $dbuser,$dbpwd,$dbdb;
	if (!$SqlLink) {
		if($debug) print "Reconnecting to $dbserver...\n";
		if($SqlLink =mysql_pconnect($dbserver,$dbuser,$dbpwd)) {
			mysql_select_db($dbdb,$SqlLink);
		} else return false;
	}
	if (DEBUG&2) print "$query\n";
	$result=mysql_query($query, $SqlLink);
	if ($result==0) {
		print "MYSQL says: ".mysql_errno($SqlLink).": ";
		print mysql_error($SqlLink)."\n";
	}
	if(isset($logcmd)) {
		if($logcmd!='') {
			if($fp=fopen($logcmd,'ab')) {
				@fwrite($fp,str_replace(array("\t","\n","  "),' ',$query).";\n-----------------------------------------\n");
				@fclose($fp);
			}
		}
	}
	return $result;
}

function dbfetchrow($R) {
	return mysql_fetch_row($R);
}

function dbfetchhash($R) {
	return mysql_fetch_assoc($R);
}

function dbrowcount() {
	global $SqlLink;
	return mysql_affected_rows($SqlLink);
}

function dbinsertid() {
	global $SqlLink;
	return mysql_insert_id($SqlLink);
}


function dbencode($value) {
	if (get_magic_quotes_gpc()) return $value;
	$result=addslashes($value);
	return $result;
}

function dbr2v($D,$R) { //pass a row as an array and a result handle
	for ($i=0; $i<count($D); $i++ ){
		$f=mysql_field_name($R,$i);
		global $$f;
		$$f=$D[$i];																	//turn all fields into variables of the same name
	}
}

function dbsql2row($query) {
	$R=dbquery($query);
	if ($D=mysql_fetch_row($R)) return $D; else return 0;
}

function dbsql2hash($query) {
	$R=dbquery($query);
	if ($D=mysql_fetch_assoc($R)) return $D; else return 0;
}

function &dbsql2hashes($query) {
	$R=dbquery($query);
	if ($rc=mysql_num_rows($R)) {
		while ($D=mysql_fetch_assoc($R)) $data[]=$D;
		return array("record_count"=>$rc, "record_set"=>$data);
	} else return array("record_count"=>0,"record_set"=>array());
}

function dbq2v($table, $k) {	//called with table name and index number
	$R=dbquery("select * from $table where L0=$k");
	$D=mysql_fetch_row($R);							//get a row as array
	for ($i=0; $i<count($D); $i++ ){
		$f=mysql_field_name($R,$i);
		global $$f;
		$$f=$D[$i];																	//turn all fields into variables of the same name
	}
}

function dbsql2v($query) {	//called with query
	$R=dbquery($query);
	$D=mysql_fetch_row($R);							//get a row as array
	for ($i=0; $i<count($D); $i++ ){
		$f=mysql_field_name($R,$i);
		global $$f;
		$$f=$D[$i];																	//turn all fields into variables of the same name
	}
}

function dbsql2v1($query) {	//called with query that will only return one value
	$R=dbquery($query);
	if ($D=mysql_fetch_row($R)) {							//get a row as array
		return $D[0];												//and return first value
	} else return 0;
}

// turn a query into an array of field variables, each array index=1 row
function dbq2arrays($table,$fields,$order) { //called with table name and comma separated list of fields and an order by field (or 0)
	$Q="select $fields from $table";
	if ($order) $Q .=" order by $order";
	$R=dbquery($Q);
	$cr=0;
	while ($D=mysql_fetch_row($R)) {	//get each row as array
		for ($i=0; $i<count($D); $i++ ){
			$f=mysql_field_name($R,$i);
			if ($cr==0)	global $$f;
			${$f}[$cr++]=$D[$i];																	//turn all fields into arrays of the same name
		}
	}
	return ($cr-1);	//also return the number of rows
}

// turn a query into an array of field variables, each array index=1 row
function dbsql2arrays($query) { //called with table name and comma separated list of fields and an order by field (or 0)
	$R=dbquery($query);
	$cr=0; $rc=0;
	while ($D=mysql_fetch_row($R)) {	//get each row as array
		for ($i=0; $i<count($D); $i++ ){
			$f=mysql_field_name($R,$i);
			if ($cr==0)	global $$f;
			${$f}[$rc]=$D[$i];																	//turn all fields into arrays of the same name
		}
		$rc++;
	}
	return ($rc);	//also return the number of rows
}

/* find number of rows in query result */
function number_rows ($result) {
    $number_rows = @mysql_num_rows($result);
    return($number_rows);
}

/* get 1 row result in numeric array */
function dbsql2array($query) {
	$R=dbquery($query);
	$row = @mysql_fetch_row($R);
	return($row);
}


/* get all results in numeric array */
function dbsql2array2($query) {
	$R=dbquery($query);
	$nr=number_rows($R);
	if($nr==0) return 0;
	for ($i=0;$i<$nr;$i++) {
		$row[$i] = @mysql_fetch_row($R);
	}
	return($row);

}

/* get all results in associative array */
function dbsql2hash2($query) {
	$R=dbquery($query);
	$nr=number_rows($R);
	if($nr==0) return 0;
	for ($i=0;$i<$nr;$i++) {
		$rows[$i] = @mysql_fetch_assoc($R);
	}
	return($rows);
}

/* get single field multi row results in an array */
function dbsql2array3($query) {
	$R=dbquery($query);
	$nr=number_rows($R);
	if($nr==0) return 0;
	for ($i=0;$i<$nr;$i++) {
		$r=@mysql_fetch_row($R);
		$row[$i] = $r[0];
	}
	return($row);
}

/* get two field multi row results in an array as key,value */
function dbsql2hash3($query) {
	$R=dbquery($query);
	$nr=number_rows($R);
	if($nr==0) return 0;
	for ($i=0;$i<$nr;$i++) {
		$r=@mysql_fetch_row($R);
		$row[$r[0]] = $r[1];
	}
	return($row);
}

function dbupdate_from_hash($table,$data,$k,$kname='id') { //called with table name, hashed array and key
	$Q="";
	while (list($key,$val)=each($data)) $Q.=" $key='".addslashes($val)."',";
	if (dbquery("update $table set ".substr($Q,0,-1). " where $kname='$k'")) return 1; else return 0;
}

function dbinsert_from_hash($table,$data) { //called with table name, hashed array
	$Q1=""; $Q2="";
	while (list($key,$val)=each($data)) {
		$Q1.="$key,";
		$Q2.="'".addslashes($val)."',";
	}
	if (dbquery("insert into $table (".substr($Q1,0,-1).") values (".substr($Q2,0,-1).")"))
		return dbinsertid(); else return 0;
}

function dbreplace_from_hash($table,$data) { //called with table name, hashed array
	$Q1=""; $Q2="";
	while (list($key,$val)=each($data)) {
		$Q1.="$key,";
		$Q2.="'".addslashes($val)."',";
	}
	if (dbquery("replace into $table (".substr($Q1,0,-1).") values (".substr($Q2,0,-1).")"))
		return 1; else return 0;
}

function dbupdate_from_hash_safe($table,$data,$k,$kname='id') { //called with table name, hashed array and key
	$Q=''; $fields=dbget_fields($table);
	while (list($key,$val)=each($data)) if(in_array($key,$fields)) $Q.=" $key='".addslashes($val)."',";
	if (dbquery("update $table set ".substr($Q,0,-1). " where $kname='$k'")) return 1; else return 0;
}

function dbinsert_from_hash_safe($table,$data) { //called with table name, hashed array
	$Q1=''; $Q2=''; $fields=dbget_fields($table);
	while (list($key,$val)=each($data)) {
		if(in_array($key,$fields)) {
			$Q1.="$key,";
			$Q2.="'".addslashes($val)."',";
		}
	}
	if (dbquery("insert into $table (".substr($Q1,0,-1).") values (".substr($Q2,0,-1).")"))
		return dbinsertid(); else return 0;
}

function dbreplace_from_hash_safe($table,$data) { //called with table name, hashed array
	$Q1=''; $Q2=''; $fields=dbget_fields($table);
	while (list($key,$val)=each($data)) {
		if(in_array($key,$fields)) {
			$Q1.="$key,";
			$Q2.="'".addslashes($val)."',";
		}
	}
	if (dbquery("replace into $table (".substr($Q1,0,-1).") values (".substr($Q2,0,-1).")"))
		return dbinsertid(); else return 0;
}

function dbget_fields($table) {
	$f=dbsql2hash2("show fields from $table");
	$fl=array();
	for($i=0;$i<count($f);$i++) $fl[$i]=$f[$i]['Field'];
	return $fl;
}

function dbmakeselect($table, $sname, $size, $sel, $order) {	//called with a table name a variable name and a height and a selected index no and an order by field name
	$Q="select * from $table"; if ($order !="" or $order) $Q .=" order by $order";
	$R=dbquery($Q);
	$H="<select name=\"$sname\" size=$size>\n";
	$H .="<option value=\"0\">Please Select One\n";
	while ($D = mysql_fetch_row($R)) {
		$H .="	<option value=\"".$D[0]."\" ";
		if ($sel==$D[0]) $H .="selected";
		$H .=">".$D[1]."\n";
	}
	$H .= "</select>";
	return $H;
}

function dbmakeselectbyquery($Q, $sname, $size, $sel, $oc) {	//called with a table name a variable name and a height and a selected index no and an onclick/change
	$R=dbquery($Q);
	$H="<select name=\"$sname\" size=$size $oc>\n";
	$H .="<option value=\"0\">Select / Dewis\n";
	while ($D = mysql_fetch_row($R)) {
		$H .="	<option value=\"".$D[0]."\" ";
		if ($sel==$D[0]) $H .="selected";
		$H .=">".$D[1]."\n";
	}
	$H .= "</select>";
	return $H;
}

function dbmakemulti($table, $sname, $size, $sel) {	//called with a table name a variable name and a height and a selected binary map
	$R=dbquery("select * from $table order by _".$table."_id");
	$H="<select name=\"".$sname."[]\" multiple size=$size>\n";
	$i=0;
	while ($D = mysql_fetch_row($R)) {
		$H .="	<option value=\"".$D[0]."\" ";
		if (substr($sel,$i++,1)==1) $H .="selected";
		$H .=">".$D[1]."\n";
	}
	$H .= "</select>";
	return $H;
}

function dbmakechecks($title, $table, $cols, $sel, $si) {	//called with a heading/title, table name, no of columns, a selected binary map and a starting index
	$R=dbquery("select * from $table where L0>$si order by L0");
	$i=0; $H="<table width=100% border=0 cellpadding=5>\n<tr><td bgcolor=#c0c0f0 align=center><b>$title</b><br>\n<table width=100% border=1 bordercolor=#000000 cellspacing=0 cellpadding=0>\n<tr>";
	while ($D = mysql_fetch_row($R)) {
		if ((($i % $cols) ==0) and $i>0) $H .="</tr>\n<tr>";
		$H .="<td valign=top align=right width=3% bgcolor=#ffc0c0><input type=\"checkbox\" value=\"".$D[0]."\" name=\"".$table."[]\" ";
		if (substr($sel,$si+$i++,1)==1) $H .="checked";
		$H .="></td><td valign=top bgcolor=#c0f0c0>".$D[1]."</td>\n";
	}
	if (($i % $cols)>0) $H .="<td bgcolor=#c0c0f0 colspan=".(($cols-($i % $cols))*2).">&nbsp;</td>";
	$H .="</tr><tr><td bgcolor=#c0c0f0 align=center valign=bottom colspan=".($cols*2).">\n";
	$H .="<input type=\"button\" value=\"Check All Tick Boxes\" onClick=\"checkAll(this, '".$table."[]')\">\n";
	$H .="<input type=\"button\" value=\"Clear All Tick Boxes\" onClick=\"uncheckAll(this, '".$table."[]')\">\n";
	$H .="<input type=\"button\" value=\"Reverse Tick Box Values\" onClick=\"toggleAll(this, '".$table."[]')\">\n";
	$H .="</td></tr></table>\n</td></tr>\n</table>";
	return $H;
}

//must be called with a sql query which has select count(x) in it....
function get_recordcount($sql) {
	$R=dbsql2array($sql);
	return $R[0];
}
?>
