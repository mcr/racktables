<?php
/*
*
*  This file is a library of database access functions for RackTables.
*
*/

function isInnoDBSupported ($dbh = FALSE) {
	global $dbxlink;

	// sometimes db handle isn't available globally, must be passed
	if (!$dbxlink)
		$dbxlink = $dbh;

	// create a temp table
	$dbxlink->query("CREATE TABLE `innodb_test` (`id` int) ENGINE=InnoDB");
	$row = $dbxlink->query("SHOW TABLE STATUS LIKE 'innodb_test'")->fetch(PDO::FETCH_ASSOC);
	$dbxlink->query("DROP TABLE `innodb_test`");
	if ($row['Engine'] == 'InnoDB')
		return TRUE;

	return FALSE;
}

function escapeString ($value, $do_db_escape = TRUE)
{
	$ret = htmlspecialchars ($value, ENT_QUOTES, 'UTF-8');
	if ($do_db_escape)
	{
		global $dbxlink;
		$ret = substr ($dbxlink->quote ($ret), 1, -1);
	}
	return $ret;
}

function getRackspace ($tagfilter = array(), $tfmode = 'any')
{
	$whereclause = getWhereClause ($tagfilter);
	$query =
		"select RackRow.id as row_id, RackRow.name as row_name, count(Rack.id) as count " .
		"from RackRow left join Rack on Rack.row_id = RackRow.id " .
		"left join TagStorage on Rack.id = TagStorage.entity_id and entity_realm = 'rack' " .
		"where 1=1 " .
		$whereclause .
		" group by RackRow.id order by RackRow.name";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	$clist = array ('row_id', 'row_name', 'count');
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		foreach ($clist as $cname)
			$ret[$row['row_id']][$cname] = $row[$cname];
	$result->closeCursor();
	return $ret;
}

// Return detailed information about one rack row.
function getRackRowInfo ($rackrow_id)
{
	$query =
		"select RackRow.id as id, RackRow.name as name, count(Rack.id) as count, " .
		"if(isnull(sum(Rack.height)),0,sum(Rack.height)) as sum " .
		"from RackRow left join Rack on Rack.row_id = RackRow.id " .
		" " .
		"group by RackRow.id";
	$result = useSelectBlade ($query, __FUNCTION__);
	if ($row = $result->fetch (PDO::FETCH_ASSOC))
		return $row;
	else
		return NULL;
}


function getRackRows ()
{
	$query = "select id, name from RackRow ";
	$result = useSelectBlade ($query, __FUNCTION__);
	$rows = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$rows[$row['id']] = parseWikiLink ($row['name'], 'o');
	$result->closeCursor();
	asort ($rows);
	return $rows;
}



function commitAddRow($rackrow_name)
{
	useInsertBlade('RackRow', array('name'=>"'$rackrow_name'"));
	return TRUE;
}

function commitUpdateRow($rackrow_id, $rackrow_name)
{
	global $dbxlink;
	$query = "update RackRow set name = '${rackrow_name}' where id=${rackrow_id}";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ("SQL query '${query}' failed", __FUNCTION__);
		return FALSE;
	}
	$result->closeCursor();
	return TRUE;
}

function commitDeleteRow($rackrow_id)
{
	global $dbxlink;
	$query = "select count(*) from Rack where row_id=${rackrow_id}";
	$result = $dbxlink->query ($query);
	if (($result!=NULL) && ($row = $result->fetch(PDO::FETCH_NUM)) )
	{
		if ($row[0] == 0)
		{
			$query = "delete from RackRow where id=${rackrow_id}";
			$result = $dbxlink->query ($query);
			if ($result == NULL)
			{
				showError ("SQL query '${query}' failed", __FUNCTION__);
				return FALSE;
			}
		}
	}
	else
	{
		showError ("SQL query '${query}' failed", __FUNCTION__);
		return FALSE;
	}
	$result->closeCursor();
	return TRUE;
}




// This function returns id->name map for all object types. The map is used
// to build <select> input for objects.
function getObjectTypeList ()
{
	return readChapter ('RackObjectType');
}

// Return a part of SQL query suitable for embeding into a bigger text.
// The returned result should list all tag IDs shown in the tag filter.
function getWhereClause ($tagfilter = array())
{
	$whereclause = '';
	if (count ($tagfilter))
	{
		$whereclause .= ' and (';
		$conj = '';
		foreach ($tagfilter as $tag_id)
		{
			$whereclause .= $conj . 'tag_id = ' . $tag_id;
			$conj = ' or ';
		}
		$whereclause .= ') ';
	}
	return $whereclause;
}

// Return a simple object list w/o related information, so that the returned value
// can be directly used by printSelect().
function getNarrowObjectList ($typeList = array())
{
	$ret = array();
	global $dbxlink;
	foreach ($typeList as $type_id)
	{
		$type_id = $dbxlink->quote (trim ($type_id));
		$query =
			"select RackObject.id as id, RackObject.name as name, dict_value as objtype_name, " .
			"objtype_id from " .
			"RackObject inner join Dictionary on objtype_id=dict_key join Chapter on Chapter.id = Dictionary.chapter_id " .
			"where RackObject.deleted = 'no' and Chapter.name = 'RackObjectType' " .
			"and objtype_id = ${type_id} " .
			"order by name";
		$result = useSelectBlade ($query, __FUNCTION__);
		while ($row = $result->fetch (PDO::FETCH_ASSOC))
			$ret[$row['id']] = displayedName ($row);
	}
	return $ret;
}

// Return a filtered, detailed object list.
function getObjectList ($type_id = 0, $tagfilter = array(), $tfmode = 'any')
{
	$whereclause = getWhereClause ($tagfilter);
	if ($type_id != 0)
		$whereclause .= " and objtype_id = '${type_id}' ";
	$query =
		"select distinct RackObject.id as id , RackObject.name as name, dict_value as objtype_name, " .
		"RackObject.label as label, RackObject.barcode as barcode, " .
		"dict_key as objtype_id, asset_no, rack_id, Rack.name as Rack_name, Rack.row_id, " .
		"(SELECT name from RackRow where id = Rack.row_id) AS Row_name " .
		"from ((RackObject inner join Dictionary on objtype_id=dict_key join Chapter on Chapter.id = Dictionary.chapter_id) " .
		"left join RackSpace on RackObject.id = object_id) " .
		"left join Rack on rack_id = Rack.id " .
		"left join TagStorage on RackObject.id = TagStorage.entity_id and entity_realm = 'object' " .
		"where RackObject.deleted = 'no' and Chapter.name = 'RackObjectType' " .
		$whereclause .
		"order by name";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		foreach (array (
			'id',
			'name',
			'label',
			'barcode',
			'objtype_name',
			'objtype_id',
			'asset_no',
			'rack_id',
			'Rack_name',
			'row_id',
			'Row_name'
			) as $cname)
			$ret[$row['id']][$cname] = $row[$cname];
		$ret[$row['id']]['dname'] = displayedName ($ret[$row['id']]);
	}
	$result->closeCursor();
	return $ret;
}

function getRacksForRow ($row_id = 0, $tagfilter = array(), $tfmode = 'any')
{
	$query =
		"select Rack.id, Rack.name, height, Rack.comment, row_id, RackRow.name as row_name " .
		"from Rack left join RackRow on Rack.row_id = RackRow.id " .
		"left join TagStorage on Rack.id = TagStorage.entity_id and entity_realm = 'rack' " .
		"where  Rack.deleted = 'no' " .
		(($row_id == 0) ? "" : "and row_id = ${row_id} ") .
		getWhereClause ($tagfilter) .
		" order by row_name, Rack.name";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	$clist = array
	(
		'id',
		'name',
		'height',
		'comment',
		'row_id',
		'row_name'
	);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		foreach ($clist as $cname)
			$ret[$row['id']][$cname] = $row[$cname];
	$result->closeCursor();
	return $ret;
}

// This is a popular helper for getting information about
// a particular rack and its rackspace at once.
function getRackData ($rack_id = 0, $silent = FALSE)
{
	if ($rack_id == 0)
	{
		if ($silent == FALSE)
			showError ('Invalid rack_id', __FUNCTION__);
		return NULL;
	}
	$query =
		"select Rack.id, Rack.name, row_id, height, Rack.comment, RackRow.name as row_name from " .
		"Rack left join RackRow on Rack.row_id = RackRow.id  " .
		"where  Rack.id='${rack_id}' and Rack.deleted = 'no'";
	$result = useSelectBlade ($query, __FUNCTION__);
	if (($row = $result->fetch (PDO::FETCH_ASSOC)) == NULL)
	{
		if ($silent == FALSE)
			showError ('Query #1 succeded, but returned no data', __FUNCTION__);
		return NULL;
	}

	// load metadata
	$clist = array
	(
		'id',
		'name',
		'height',
		'comment',
		'row_id',
		'row_name'
	);
	foreach ($clist as $cname)
		$rack[$cname] = $row[$cname];
	$result->closeCursor();
	unset ($result);

	// start with default rackspace
	for ($i = $rack['height']; $i > 0; $i--)
		for ($locidx = 0; $locidx < 3; $locidx++)
			$rack[$i][$locidx]['state'] = 'F';

	// load difference
	$query =
		"select unit_no, atom, state, object_id " .
		"from RackSpace where rack_id = ${rack_id} and " .
		"unit_no between 1 and " . $rack['height'] . " order by unit_no";
	$result = useSelectBlade ($query, __FUNCTION__);
	global $loclist;
	$mounted_objects = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$rack[$row['unit_no']][$loclist[$row['atom']]]['state'] = $row['state'];
		$rack[$row['unit_no']][$loclist[$row['atom']]]['object_id'] = $row['object_id'];
		if ($row['state'] == 'T' and $row['object_id']!=NULL)
			$mounted_objects[$row['object_id']] = TRUE;
	}
	$rack['mountedObjects'] = array_keys($mounted_objects);
	$result->closeCursor();
	return $rack;
}

// This is a popular helper.
function getObjectInfo ($object_id = 0)
{
	if ($object_id == 0)
	{
		showError ('Invalid object_id', __FUNCTION__);
		return;
	}
	$query =
		"select RackObject.id as id, RackObject.name as name, label, barcode, dict_value as objtype_name, asset_no, dict_key as objtype_id, has_problems, comment from " .
		"RackObject inner join Dictionary on objtype_id = dict_key join Chapter on Chapter.id = Dictionary.chapter_id " .
		"where RackObject.id = '${object_id}' and RackObject.deleted = 'no' and Chapter.name = 'RackObjectType' limit 1";
	$result = useSelectBlade ($query, __FUNCTION__);
	if (($row = $result->fetch (PDO::FETCH_ASSOC)) == NULL)
	{
		showError ('Query succeeded, but returned no data', __FUNCTION__);
		$ret = NULL;
	}
	else
	{
		$ret['id'] = $row['id'];
		$ret['name'] = $row['name'];
		$ret['label'] = $row['label'];
		$ret['barcode'] = $row['barcode'];
		$ret['objtype_name'] = $row['objtype_name'];
		$ret['objtype_id'] = $row['objtype_id'];
		$ret['has_problems'] = $row['has_problems'];
		$ret['asset_no'] = $row['asset_no'];
		$ret['dname'] = displayedName ($ret);
		$ret['comment'] = $row['comment'];
	}
	$result->closeCursor();
	unset ($result);
	return $ret;
}

function getPortTypes ()
{
	return readChapter ('PortType');
}

function getObjectPortsAndLinks ($object_id = 0)
{
	if ($object_id == 0)
	{
		showError ('Invalid object_id', __FUNCTION__);
		return;
	}
	// prepare decoder
	$ptd = readChapter ('PortType');
	$query = "select id, name, label, l2address, type as type_id, reservation_comment from Port where object_id = ${object_id}";
	// list and decode all ports of the current object
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret=array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$row['type'] = $ptd[$row['type_id']];
		$row['l2address'] = l2addressFromDatabase ($row['l2address']);
		$row['remote_id'] = NULL;
		$row['remote_name'] = NULL;
		$row['remote_object_id'] = NULL;
		$row['remote_object_name'] = NULL;
		$ret[] = $row;
	}
	unset ($result);
	// now find and decode remote ends for all locally terminated connections
	foreach (array_keys ($ret) as $tmpkey)
	{
		$portid = $ret[$tmpkey]['id'];
		$remote_id = NULL;
		$query = "select porta, portb from Link where porta = {$portid} or portb = ${portid}";
		$result = useSelectBlade ($query, __FUNCTION__);
		if ($row = $result->fetch (PDO::FETCH_ASSOC))
		{
			if ($portid != $row['porta'])
				$remote_id = $row['porta'];
			elseif ($portid != $row['portb'])
				$remote_id = $row['portb'];
		}
		unset ($result);
		if ($remote_id) // there's a remote end here
		{
			$query = "select Port.name as port_name, Port.type as port_type, object_id, RackObject.name as object_name " .
				"from Port left join RackObject on Port.object_id = RackObject.id " .
				"where Port.id = ${remote_id}";
			$result = useSelectBlade ($query, __FUNCTION__);
			if ($row = $result->fetch (PDO::FETCH_ASSOC))
			{
				$ret[$tmpkey]['remote_name'] = $row['port_name'];
				$ret[$tmpkey]['remote_object_id'] = $row['object_id'];
				$ret[$tmpkey]['remote_object_name'] = $row['object_name'];
			}
			$ret[$tmpkey]['remote_id'] = $remote_id;
			unset ($result);
			// only call displayedName() when necessary
			if (empty ($ret[$tmpkey]['remote_object_name']) and !empty ($ret[$tmpkey]['remote_object_id']))
			{
				$oi = getObjectInfo ($ret[$tmpkey]['remote_object_id']);
				$ret[$tmpkey]['remote_object_name'] = displayedName ($oi);
			}
		}
	}
	return $ret;
}

function commitAddRack ($name, $height = 0, $row_id = 0, $comment, $taglist)
{
	if ($row_id <= 0 or $height <= 0 or empty ($name))
		return FALSE;
	$result = useInsertBlade
	(
		'Rack',
		array
		(
			'row_id' => $row_id,
			'name' => "'${name}'",
			'height' =>  $height,
			'comment' => "'${comment}'"
		)
	);
	if ($result == NULL)
	{
		showError ('useInsertBlade() failed', __FUNCTION__);
		return FALSE;
	}
	$last_insert_id = lastInsertID();
	return (produceTagsForLastRecord ('rack', $taglist, $last_insert_id) == '') and recordHistory ('Rack', "id = ${last_insert_id}");
}

function commitAddObject ($new_name, $new_label, $new_barcode, $new_type_id, $new_asset_no, $taglist = array())
{
	global $dbxlink;
	// Maintain UNIQUE INDEX for common names and asset tags by
	// filtering out empty strings (not NULLs).
	$result1 = useInsertBlade
	(
		'RackObject',
		array
		(
			'name' => empty ($new_name) ? 'NULL' : "'${new_name}'",
			'label' => "'${new_label}'",
			'barcode' => empty ($new_barcode) ? 'NULL' : "'${new_barcode}'",
			'objtype_id' => $new_type_id,
			'asset_no' => empty ($new_asset_no) ? 'NULL' : "'${new_asset_no}'"
		)
	);
	if ($result1 == NULL)
	{
		showError ("SQL query #1 failed", __FUNCTION__);
		return FALSE;
	}
	$last_insert_id = lastInsertID();
	// Do AutoPorts magic
	executeAutoPorts ($last_insert_id, $new_type_id);
	// Now tags...
	$error = produceTagsForLastRecord ('object', $taglist, $last_insert_id);
	if ($error != '')
	{
		showError ("Error adding tags for the object: ${error}");
		return FALSE;
	}
	return recordHistory ('RackObject', "id = ${last_insert_id}");
}

function commitUpdateObject ($object_id = 0, $new_name = '', $new_label = '', $new_barcode = '', $new_type_id = 0, $new_has_problems = 'no', $new_asset_no = '', $new_comment = '')
{
	if ($object_id == 0 || $new_type_id == 0)
	{
		showError ('Not all required args are present.', __FUNCTION__);
		return FALSE;
	}
	global $dbxlink;
	$new_asset_no = empty ($new_asset_no) ? 'NULL' : "'${new_asset_no}'";
	$new_barcode = empty ($new_barcode) ? 'NULL' : "'${new_barcode}'";
	$new_name = empty ($new_name) ? 'NULL' : "'${new_name}'";
	$query = "update RackObject set name=${new_name}, label='${new_label}', barcode=${new_barcode}, objtype_id='${new_type_id}', " .
		"has_problems='${new_has_problems}', asset_no=${new_asset_no}, comment='${new_comment}' " .
		"where id='${object_id}' limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ("SQL query '${query}' failed", __FUNCTION__);
		return FALSE;
	}
	$result->closeCursor();
	return recordHistory ('RackObject', "id = ${object_id}");
}

// There are times when you want to delete all traces of an object
function commitDeleteObject ($object_id = 0)
{
	if ($object_id <= 0)
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$dbxlink->query("DELETE FROM AttributeValue WHERE object_id = ${object_id}");
	$dbxlink->query("DELETE FROM File WHERE id IN (SELECT file_id FROM FileLink WHERE entity_id = 'object' AND entity_id = ${object_id})");
	$dbxlink->query("DELETE FROM IPv4LB WHERE object_id = ${object_id}");
	$dbxlink->query("DELETE FROM IPv4Allocation WHERE object_id = ${object_id}");
	$dbxlink->query("DELETE FROM Port WHERE object_id = ${object_id}");
	$dbxlink->query("DELETE FROM IPv4NAT WHERE object_id = ${object_id}");
	$dbxlink->query("DELETE FROM RackSpace WHERE object_id = ${object_id}");
	$dbxlink->query("DELETE FROM TagStorage WHERE entity_realm = 'object' and entity_id = ${object_id}");
	$dbxlink->query("DELETE FROM Atom WHERE molecule_id IN (SELECT new_molecule_id FROM MountOperation WHERE object_id = ${object_id})");
	$dbxlink->query("DELETE FROM Molecule WHERE id IN (SELECT new_molecule_id FROM MountOperation WHERE object_id = ${object_id})");
	$dbxlink->query("DELETE FROM MountOperation WHERE object_id = ${object_id}");
	$dbxlink->query("DELETE FROM RackObjectHistory WHERE id = ${object_id}");
	$dbxlink->query("DELETE FROM RackObject WHERE id = ${object_id}");

	return '';
}

function commitDeleteRack($rack_id)
{
	global $dbxlink;
	$query = "delete from RackSpace where rack_id = '${rack_id}'";
	$dbxlink->query ($query);
	$query = "delete from TagStorage where entity_realm='rack' and entity_id='${rack_id}'";
	$dbxlink->query ($query);
	$query = "delete from RackHistory where id = '${rack_id}'";
	$dbxlink->query ($query);
	$query = "delete from Rack where id = '${rack_id}'";
	$dbxlink->query ($query);
	return TRUE;
}

function commitUpdateRack ($rack_id, $new_name, $new_height, $new_row_id, $new_comment)
{
	if (empty ($rack_id) || empty ($new_name) || empty ($new_height))
	{
		showError ('Not all required args are present.', __FUNCTION__);
		return FALSE;
	}
	global $dbxlink;
	$query = "update Rack set name='${new_name}', height='${new_height}', comment='${new_comment}', row_id=${new_row_id} " .
		"where id='${rack_id}' limit 1";
	$result1 = $dbxlink->query ($query);
	if ($result1->rowCount() != 1)
	{
		showError ('Error updating rack information', __FUNCTION__);
		return FALSE;
	}
	return recordHistory ('Rack', "id = ${rack_id}");
}

// This function accepts rack data returned by getRackData(), validates and applies changes
// supplied in $_REQUEST and returns resulting array. Only those changes are examined, which
// correspond to current rack ID.
// 1st arg is rackdata, 2nd arg is unchecked state, 3rd arg is checked state.
// If 4th arg is present, object_id fields will be updated accordingly to the new state.
// The function returns the modified rack upon success.
function processGridForm (&$rackData, $unchecked_state, $checked_state, $object_id = 0)
{
	global $loclist, $dbxlink;
	$rack_id = $rackData['id'];
	$rack_name = $rackData['name'];
	$rackchanged = FALSE;
	for ($unit_no = $rackData['height']; $unit_no > 0; $unit_no--)
	{
		for ($locidx = 0; $locidx < 3; $locidx++)
		{
			if ($rackData[$unit_no][$locidx]['enabled'] != TRUE)
				continue;
			// detect a change
			$state = $rackData[$unit_no][$locidx]['state'];
			if (isset ($_REQUEST["atom_${rack_id}_${unit_no}_${locidx}"]) and $_REQUEST["atom_${rack_id}_${unit_no}_${locidx}"] == 'on')
				$newstate = $checked_state;
			else
				$newstate = $unchecked_state;
			if ($state == $newstate)
				continue;
			$rackchanged = TRUE;
			// and validate
			$atom = $loclist[$locidx];
			// The only changes allowed are those introduced by checkbox grid.
			if
			(
				!($state == $checked_state && $newstate == $unchecked_state) &&
				!($state == $unchecked_state && $newstate == $checked_state)
			)
				return array ('code' => 500, 'message' => "${rack_name}: Rack ID ${rack_id}, unit ${unit_no}, 'atom ${atom}', cannot change state from '${state}' to '${newstate}'");
			// Here we avoid using ON DUPLICATE KEY UPDATE by first performing DELETE
			// anyway and then looking for probable need of INSERT.
			$query =
				"delete from RackSpace where rack_id = ${rack_id} and " .
				"unit_no = ${unit_no} and atom = '${atom}' limit 1";
			$r = $dbxlink->query ($query);
			if ($r == NULL)
				return array ('code' => 500, 'message' => __FUNCTION__ . ": ${rack_name}: SQL DELETE query failed");
			if ($newstate != 'F')
			{
				$query =
					"insert into RackSpace(rack_id, unit_no, atom, state) " .
					"values(${rack_id}, ${unit_no}, '${atom}', '${newstate}') ";
				$r = $dbxlink->query ($query);
				if ($r == NULL)
					return array ('code' => 500, 'message' => __FUNCTION__ . ": ${rack_name}: SQL INSERT query failed");
			}
			if ($newstate == 'T' and $object_id != 0)
			{
				// At this point we already have a record in RackSpace.
				$query =
					"update RackSpace set object_id=${object_id} " .
					"where rack_id=${rack_id} and unit_no=${unit_no} and atom='${atom}' limit 1";
				$r = $dbxlink->query ($query);
				if ($r->rowCount() == 1)
					$rackData[$unit_no][$locidx]['object_id'] = $object_id;
				else
					return array ('code' => 500, 'message' => "${rack_name}: Rack ID ${rack_id}, unit ${unit_no}, atom '${atom}' failed to set object_id to '${object_id}'");
			}
		}
	}
	if ($rackchanged)
	{
		resetThumbCache ($rack_id);
		return array ('code' => 200, 'message' => "${rack_name}: All changes were successfully saved.");
	}
	else
		return array ('code' => 300, 'message' => "${rack_name}: No changes.");
}

// This function builds a list of rack-unit-atom records, which are assigned to
// the requested object.
function getMoleculeForObject ($object_id = 0)
{
	if ($object_id == 0)
	{
		showError ("object_id == 0", __FUNCTION__);
		return NULL;
	}
	$query =
		"select rack_id, unit_no, atom from RackSpace " .
		"where state = 'T' and object_id = ${object_id} order by rack_id, unit_no, atom";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = $result->fetchAll (PDO::FETCH_ASSOC);
	$result->closeCursor();
	return $ret;
}

// This function builds a list of rack-unit-atom records for requested molecule.
function getMolecule ($mid = 0)
{
	if ($mid == 0)
	{
		showError ("mid == 0", __FUNCTION__);
		return NULL;
	}
	$query =
		"select rack_id, unit_no, atom from Atom " .
		"where molecule_id=${mid}";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = $result->fetchAll (PDO::FETCH_ASSOC);
	$result->closeCursor();
	return $ret;
}

// returns exactly what is's named after
function lastInsertID ()
{
	if (NULL == ($result = useSelectBlade ('select last_insert_id()', __FUNCTION__)))
	{
		showError ('SQL query failed!', __FUNCTION__);
		die;
	}
	$row = $result->fetch (PDO::FETCH_NUM);
	return $row[0];
}

// This function creates a new record in Molecule and number of linked
// R-U-A records in Atom.
function createMolecule ($molData)
{
	global $dbxlink;
	$query = "insert into Molecule values()";
	$result1 = $dbxlink->query ($query);
	if ($result1->rowCount() != 1)
	{
		showError ('Error inserting into Molecule', __FUNCTION__);
		return NULL;
	}
	$molecule_id = lastInsertID();
	foreach ($molData as $rua)
	{
		$rack_id = $rua['rack_id'];
		$unit_no = $rua['unit_no'];
		$atom = $rua['atom'];
		$query =
			"insert into Atom(molecule_id, rack_id, unit_no, atom) " .
			"values (${molecule_id}, ${rack_id}, ${unit_no}, '${atom}')";
		$result3 = $dbxlink->query ($query);
		if ($result3 == NULL or $result3->rowCount() != 1)
		{
			showError ('Error inserting into Atom', __FUNCTION__);
			return NULL;
		}
	}
	return $molecule_id;
}

// History logger. This function assumes certain table naming convention and
// column design:
// 1. History table name equals to dictionary table name plus 'History'.
// 2. History table must have the same row set (w/o keys) plus one row named
// 'ctime' of type 'timestamp'.
function recordHistory ($tableName, $whereClause)
{
	global $dbxlink, $remote_username;
	$query = "insert into ${tableName}History select *, current_timestamp(), '${remote_username}' from ${tableName} where ${whereClause}";
	$result = $dbxlink->query ($query);
	if ($result == NULL or $result->rowCount() != 1)
	{
		showError ("SQL query '${query}' failed for table ${tableName}", __FUNCTION__);
		return FALSE;
	}
	return TRUE;
}

function getRackspaceHistory ()
{
	$query =
		"select mo.id as mo_id, ro.id as ro_id, ro.name, mo.ctime, mo.comment, dict_value as objtype_name, user_name from " .
		"MountOperation as mo inner join RackObject as ro on mo.object_id = ro.id " .
		"inner join Dictionary on objtype_id = dict_key join Chapter on Chapter.id = Dictionary.chapter_id " .
		"where Chapter.name = 'RackObjectType' order by ctime desc";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = $result->fetchAll(PDO::FETCH_ASSOC);
	$result->closeCursor();
	return $ret;
}

// This function is used in renderRackspaceHistory()
function getOperationMolecules ($op_id = 0)
{
	if ($op_id <= 0)
	{
		showError ("Missing argument", __FUNCTION__);
		return;
	}
	$query = "select old_molecule_id, new_molecule_id from MountOperation where id = ${op_id}";
	$result = useSelectBlade ($query, __FUNCTION__);
	// We expect one row.
	$row = $result->fetch (PDO::FETCH_ASSOC);
	if ($row == NULL)
	{
		showError ("SQL query succeded, but returned no results.", __FUNCTION__);
		return;
	}
	$omid = $row['old_molecule_id'];
	$nmid = $row['new_molecule_id'];
	$result->closeCursor();
	return array ($omid, $nmid);
}

function getResidentRacksData ($object_id = 0, $fetch_rackdata = TRUE)
{
	if ($object_id <= 0)
	{
		showError ('Invalid object_id', __FUNCTION__);
		return;
	}
	$query = "select distinct rack_id from RackSpace where object_id = ${object_id} order by rack_id";
	$result = useSelectBlade ($query, __FUNCTION__);
	$rows = $result->fetchAll (PDO::FETCH_NUM);
	$result->closeCursor();
	$ret = array();
	foreach ($rows as $row)
	{
		if (!$fetch_rackdata)
		{
			$ret[$row[0]] = $row[0];
			continue;
		}
		$rackData = getRackData ($row[0]);
		if ($rackData == NULL)
		{
			showError ('getRackData() failed', __FUNCTION__);
			return NULL;
		}
		$ret[$row[0]] = $rackData;
	}
	$result->closeCursor();
	return $ret;
}

function getObjectGroupInfo ()
{
	$query =
		'select dict_key as id, dict_value as name, count(dict_key) as count from ' .
		'Dictionary join Chapter on Chapter.id = Dictionary.chapter_id join RackObject on dict_key = objtype_id ' .
		'where Chapter.name = "RackObjectType" ' .
		'group by dict_key order by dict_value';
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	$ret[0] = array ('id' => 0, 'name' => 'ALL types');
	$clist = array ('id', 'name', 'count');
	$total = 0;
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		if ($row['count'] > 0)
		{
			$total += $row['count'];
			foreach ($clist as $cname)
				$ret[$row['id']][$cname] = $row[$cname];
		}
	$result->closeCursor();
	$ret[0]['count'] = $total;
	return $ret;
}

// This function returns objects, which have no rackspace assigned to them.
// Additionally it keeps rack_id parameter, so we can silently pre-select
// the rack required.
function getUnmountedObjects ()
{
	$query =
		'select dict_value as objtype_name, dict_key as objtype_id, name, label, barcode, id, asset_no from ' .
		'RackObject inner join Dictionary on objtype_id = dict_key join Chapter on Chapter.id = Dictionary.chapter_id ' .
		'left join RackSpace on id = object_id '.
		'where rack_id is null and Chapter.name = "RackObjectType" order by dict_value, name, label, asset_no, barcode';
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	$clist = array ('id', 'name', 'label', 'barcode', 'objtype_name', 'objtype_id', 'asset_no');
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		foreach ($clist as $cname)
			$ret[$row['id']][$cname] = $row[$cname];
		$ret[$row['id']]['dname'] = displayedName ($ret[$row['id']]);
	}
	$result->closeCursor();
	return $ret;
}

function getProblematicObjects ()
{
	$query =
		'select dict_value as objtype_name, dict_key as objtype_id, name, id, asset_no from ' .
		'RackObject inner join Dictionary on objtype_id = dict_key join Chapter on Chapter.id = Dictionary.chapter_id '.
		'where has_problems = "yes" and Chapter.name = "RackObjectType" order by objtype_name, name';
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	$clist = array ('id', 'name', 'objtype_name', 'objtype_id', 'asset_no');
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		foreach ($clist as $cname)
			$ret[$row['id']][$cname] = $row[$cname];
		$ret[$row['id']]['dname'] = displayedName ($ret[$row['id']]);
	}
	$result->closeCursor();
	return $ret;
}

function commitAddPort ($object_id = 0, $port_name, $port_type_id, $port_label, $port_l2address)
{
	if ($object_id <= 0)
	{
		showError ('Invalid object_id', __FUNCTION__);
		return;
	}
	if (NULL === ($db_l2address = l2addressForDatabase ($port_l2address)))
		return "Invalid L2 address ${port_l2address}";
	$result = useInsertBlade
	(
		'Port',
		array
		(
			'name' => "'${port_name}'",
			'object_id' => "'${object_id}'",
			'label' => "'${port_label}'",
			'type' => "'${port_type_id}'",
			'l2address' => ($db_l2address === '') ? 'NULL' : "'${db_l2address}'"
		)
	);
	if ($result)
		return '';
	else
		return 'SQL query failed';
}

// The fifth argument may be either explicit 'NULL' or some (already quoted by the upper layer)
// string value. In case it is omitted, we just assign it its current value.
// It would be nice to simplify this semantics later.
function commitUpdatePort ($port_id, $port_name, $port_type_id, $port_label, $port_l2address, $port_reservation_comment = 'reservation_comment')
{
	global $dbxlink;
	if (NULL === ($db_l2address = l2addressForDatabase ($port_l2address)))
		return "Invalid L2 address ${port_l2address}";
	$query =
		"update Port set name='$port_name', type=$port_type_id, label='$port_label', " .
		"reservation_comment = ${port_reservation_comment}, l2address=" .
		(($db_l2address === '') ? 'NULL' : "'${db_l2address}'") .
		" where id='$port_id'";
	$result = $dbxlink->exec ($query);
	if ($result == 1)
		return '';
	$errorInfo = $dbxlink->errorInfo();
	// We could update nothing.
	if ($errorInfo[0] == '00000')
		return '';
	return $errorInfo[2];
}

function delObjectPort ($port_id)
{
	if (unlinkPort ($port_id) != '')
		return __FUNCTION__ . ': unlinkPort() failed';
	if (useDeleteBlade ('Port', 'id', $port_id) != TRUE)
		return __FUNCTION__ . ': useDeleteBlade() failed';
	return '';
}

function getAllIPv4Allocations ()
{
	$query =
		"select object_id as object_id, ".
		"RackObject.name as object_name, ".
		"IPv4Allocation.name as name, ".
		"INET_NTOA(ip) as ip ".
		"from IPv4Allocation join RackObject on id=object_id ";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	$count=0;
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$ret[$count]['object_id']=$row['object_id'];
		$ret[$count]['object_name']=$row['object_name'];
		$ret[$count]['name']=$row['name'];
		$ret[$count]['ip']=$row['ip'];
		$count++;
	}
	$result->closeCursor();
	return $ret;
}

function getEmptyPortsOfType ($type_id)
{
	$query =
		"select distinct Port.id as Port_id, ".
		"Port.object_id as Port_object_id, ".
		"RackObject.name as Object_name, ".
		"Port.name as Port_name, ".
		"Port.type as Port_type_id, ".
		"dict_value as Port_type_name ".
		"from ( ".
		"	( ".
		"		Port inner join Dictionary on Port.type = dict_key join Chapter on Chapter.id = Dictionary.chapter_id ".
		"	) ".
		" 	join RackObject on Port.object_id = RackObject.id ".
		") ".
		"left join Link on Port.id=Link.porta or Port.id=Link.portb ".
		"inner join PortCompat on Port.type = PortCompat.type2 ".
		"where Chapter.name = 'PortType' and PortCompat.type1 = '$type_id' and Link.porta is NULL ".
		"and Port.reservation_comment is null order by Object_name, Port_name";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	$count=0;
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$ret[$count]['Port_id']=$row['Port_id'];
		$ret[$count]['Port_object_id']=$row['Port_object_id'];
		$ret[$count]['Object_name']=$row['Object_name'];
		$ret[$count]['Port_name']=$row['Port_name'];
		$ret[$count]['Port_type_id']=$row['Port_type_id'];
		$ret[$count]['Port_type_name']=$row['Port_type_name'];
		$count++;
	}
	$result->closeCursor();
	return $ret;
}

function linkPorts ($porta, $portb)
{
	if ($porta == $portb)
		return "Ports can't be the same";
	if ($porta > $portb)
	{
		$tmp = $porta;
		$porta = $portb;
		$portb = $tmp;
	}
	global $dbxlink;
	$query1 = "insert into Link set porta='${porta}', portb='{$portb}'";
	$query2 = "update Port set reservation_comment = NULL where id = ${porta} or id = ${portb} limit 2";
	// FIXME: who cares about the return value?
	$result = $dbxlink->exec ($query1);
	$result = $dbxlink->exec ($query2);
	return '';
}

function unlinkPort ($port)
{
	global $dbxlink;
	$query =
		"delete from Link where porta='$port' or portb='$port'";
	$result = $dbxlink->exec ($query);
	return '';
}

// Return all IPv4 addresses allocated to the objects. Attach detailed
// info about address to each alocation records. Index result by dotted-quad
// address.
function getObjectIPv4Allocations ($object_id = 0)
{
	$ret = array();
	$query = 'select name as osif, type, inet_ntoa(ip) as dottedquad from IPv4Allocation ' .
		"where object_id = ${object_id} " .
		'order by ip';
	$result = useSelectBlade ($query, __FUNCTION__);
	// don't spawn a sub-query with unfetched buffer, it may fail
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$ret[$row['dottedquad']] = array ('osif' => $row['osif'], 'type' => $row['type']);
	unset ($result);
	foreach (array_keys ($ret) as $dottedquad)
		$ret[$dottedquad]['addrinfo'] = getIPv4Address ($dottedquad);
	return $ret;
}

// Return minimal IPv4 address, optionally with "ip" key set, if requested.
function constructIPv4Address ($dottedquad = NULL)
{
	$ret = array
	(
		'name' => '',
		'reserved' => 'no',
		'outpf' => array(),
		'inpf' => array(),
		'rslist' => array(),
		'allocs' => array(),
		'lblist' => array()
	);
	if ($dottedquad != NULL)
		$ret['ip'] = $dottedquad;
	return $ret;
}

// Check the range requested for meaningful IPv4 records, build them
// into a list and return. Return an empty list if nothing matched.
// Both arguments are expected in signed int32 form. The resulting list
// is keyed by uint32 form of each IP address, items aren't sorted.
// LATER: accept a list of pairs and build WHERE sub-expression accordingly
function scanIPv4Space ($pairlist)
{
	$ret = array();
	if (!count ($pairlist)) // this is normal for a network completely divided into smaller parts
		return $ret;;
	$dnamechache = array();
	// FIXME: this is a copy-and-paste prototype
	$or = '';
	$whereexpr1 = '(';
	$whereexpr2 = '(';
	$whereexpr3 = '(';
	$whereexpr4 = '(';
	$whereexpr5a = '(';
	$whereexpr5b = '(';
	foreach ($pairlist as $tmp)
	{
		$db_first = sprintf ('%u', 0x00000000 + $tmp['i32_first']);
		$db_last = sprintf ('%u', 0x00000000 + $tmp['i32_last']);
		$whereexpr1 .= $or . "ip between ${db_first} and ${db_last}";
		$whereexpr2 .= $or . "ip between ${db_first} and ${db_last}";
		$whereexpr3 .= $or . "vip between ${db_first} and ${db_last}";
		$whereexpr4 .= $or . "rsip between ${db_first} and ${db_last}";
		$whereexpr5a .= $or . "remoteip between ${db_first} and ${db_last}";
		$whereexpr5b .= $or . "localip between ${db_first} and ${db_last}";
		$or = ' or ';
	}
	$whereexpr1 .= ')';
	$whereexpr2 .= ')';
	$whereexpr3 .= ')';
	$whereexpr4 .= ')';
	$whereexpr5a .= ')';
	$whereexpr5b .= ')';

	// 1. collect labels and reservations
	$query = "select INET_NTOA(ip) as ip, name, reserved from IPv4Address ".
		"where ${whereexpr1} and (reserved = 'yes' or name != '')";
	$result = useSelectBlade ($query, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$ip_bin = ip2long ($row['ip']);
		if (!isset ($ret[$ip_bin]))
			$ret[$ip_bin] = constructIPv4Address ($row['ip']);
		$ret[$ip_bin]['name'] = $row['name'];
		$ret[$ip_bin]['reserved'] = $row['reserved'];
	}
	unset ($result);

	// 2. check for allocations
	$query =
		"select INET_NTOA(ipb.ip) as ip, ro.id as object_id, " .
		"ro.name as object_name, ipb.name, ipb.type, objtype_id, " .
		"dict_value as objtype_name from " .
		"IPv4Allocation as ipb inner join RackObject as ro on ipb.object_id = ro.id " .
		"left join Dictionary on objtype_id=dict_key join Chapter on Chapter.id = Dictionary.chapter_id " .
		"where ${whereexpr2} " .
		"and Chapter.name = 'RackObjectType'" .
		"order by ipb.type, object_name";
	$result = useSelectBlade ($query, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$ip_bin = ip2long ($row['ip']);
		if (!isset ($ret[$ip_bin]))
			$ret[$ip_bin] = constructIPv4Address ($row['ip']);
		if (!isset ($dnamecache[$row['object_id']]))
		{
			$quasiobject['name'] = $row['object_name'];
			$quasiobject['objtype_id'] = $row['objtype_id'];
			$quasiobject['objtype_name'] = $row['objtype_name'];
			$dnamecache[$row['object_id']] = displayedName ($quasiobject);
		}
		$tmp = array();
		foreach (array ('object_id', 'type', 'name') as $cname)
			$tmp[$cname] = $row[$cname];
		$tmp['object_name'] = $dnamecache[$row['object_id']];
		$ret[$ip_bin]['allocs'][] = $tmp;
	}
	unset ($result);

	// 3. look for virtual services and related LB 
	$query = "select vs_id, inet_ntoa(vip) as ip, vport, proto, vs.name, " .
		"object_id, objtype_id, ro.name as object_name, dict_value as objtype_name from " .
		"IPv4VS as vs inner join IPv4LB as lb on vs.id = lb.vs_id " .
		"inner join RackObject as ro on lb.object_id = ro.id " .
		"left join Dictionary on objtype_id=dict_key " .
		"join Chapter on Chapter.id = Dictionary.chapter_id " .
		"where ${whereexpr3} " .
		"and Chapter.name = 'RackObjectType'" .
		"order by vport, proto, ro.name, object_id";
	$result = useSelectBlade ($query, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$ip_bin = ip2long ($row['ip']);
		if (!isset ($ret[$ip_bin]))
			$ret[$ip_bin] = constructIPv4Address ($row['ip']);
		if (!isset ($dnamecache[$row['object_id']]))
		{
			$quasiobject['name'] = $row['object_name'];
			$quasiobject['objtype_id'] = $row['objtype_id'];
			$quasiobject['objtype_name'] = $row['objtype_name'];
			$dnamecache[$row['object_id']] = displayedName ($quasiobject);
		}
		$tmp = array();
		foreach (array ('object_id', 'vport', 'proto', 'vs_id', 'name') as $cname)
			$tmp[$cname] = $row[$cname];
		$tmp['object_name'] = $dnamecache[$row['object_id']];
		$tmp['vip'] = $row['ip'];
		$ret[$ip_bin]['lblist'][] = $tmp;
	}
	unset ($result);

	// 4. don't forget about real servers along with pools
	$query = "select inet_ntoa(rsip) as ip, inservice, rsport, rspool_id, rsp.name as rspool_name from " .
		"IPv4RS as rs inner join IPv4RSPool as rsp on rs.rspool_id = rsp.id " .
		"where ${whereexpr4} " .
		"order by ip, rsport, rspool_id";
	$result = useSelectBlade ($query, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$ip_bin = ip2long ($row['ip']);
		if (!isset ($ret[$ip_bin]))
			$ret[$ip_bin] = constructIPv4Address ($row['ip']);
		$tmp = array();
		foreach (array ('rspool_id', 'rsport', 'rspool_name', 'inservice') as $cname)
			$tmp[$cname] = $row[$cname];
		$ret[$ip_bin]['rslist'][] = $tmp;
	}
	unset ($result);

	// 5. add NAT rules, part 1
	$query =
		"select " .
		"proto, " .
		"INET_NTOA(localip) as localip, " .
		"localport, " .
		"INET_NTOA(remoteip) as remoteip, " .
		"remoteport, " .
		"description " .
		"from IPv4NAT " .
		"where ${whereexpr5a} " .
		"order by localip, localport, remoteip, remoteport, proto";
	$result = useSelectBlade ($query, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$remoteip_bin = ip2long ($row['remoteip']);
		if (!isset ($ret[$remoteip_bin]))
			$ret[$remoteip_bin] = constructIPv4Address ($row['remoteip']);
		$ret[$remoteip_bin]['inpf'][] = $row;
	}
	unset ($result);
	// 5. add NAT rules, part 2
	$query =
		"select " .
		"proto, " .
		"INET_NTOA(localip) as localip, " .
		"localport, " .
		"INET_NTOA(remoteip) as remoteip, " .
		"remoteport, " .
		"description " .
		"from IPv4NAT " .
		"where ${whereexpr5b} " .
		"order by localip, localport, remoteip, remoteport, proto";
	$result = useSelectBlade ($query, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$localip_bin = ip2long ($row['localip']);
		if (!isset ($ret[$localip_bin]))
			$ret[$localip_bin] = constructIPv4Address ($row['localip']);
		$ret[$localip_bin]['outpf'][] = $row;
	}
	unset ($result);
	return $ret;
}

// Return summary data about an IPv4 prefix, if it exists, or NULL otherwise.
function getIPv4NetworkInfo ($id = 0)
{
	if ($id <= 0)
	{
		showError ('Invalid arg', __FUNCTION__);
		return NULL;
	}
	$query = "select INET_NTOA(ip) as ip, mask, name ".
		"from IPv4Network where id = $id";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = $result->fetch (PDO::FETCH_ASSOC);
	if ($ret == NULL)
		return NULL;
	unset ($result);
	$ret['id'] = $id;
	$ret['ip_bin'] = ip2long ($ret['ip']);
	$ret['mask_bin'] = binMaskFromDec ($ret['mask']);
	$ret['mask_bin_inv'] = binInvMaskFromDec ($ret['mask']);
	$ret['db_first'] = sprintf ('%u', 0x00000000 + $ret['ip_bin'] & $ret['mask_bin']);
	$ret['db_last'] = sprintf ('%u', 0x00000000 + $ret['ip_bin'] | ($ret['mask_bin_inv']));
	return $ret;
}

function getIPv4Address ($dottedquad = '')
{
	if ($dottedquad == '')
	{
		showError ('Invalid arg', __FUNCTION__);
		return NULL;
	}
	$i32 = ip2long ($dottedquad); // signed 32 bit
	$scanres = scanIPv4Space (array (array ('i32_first' => $i32, 'i32_last' => $i32)));
	if (!isset ($scanres[$i32]))
		//$scanres[$i32] = constructIPv4Address ($dottedquad); // XXX: this should be verified to not break things
		return constructIPv4Address ($dottedquad);
	markupIPv4AddrList ($scanres);
	return $scanres[$i32];
}

function bindIpToObject ($ip = '', $object_id = 0, $name = '', $type = '')
{
	$result = useInsertBlade
	(
		'IPv4Allocation',
		array
		(
			'ip' => "INET_ATON('$ip')",
			'object_id' => "'${object_id}'",
			'name' => "'${name}'",
			'type' => "'${type}'"
		)
	);
	return $result ? '' : (__FUNCTION__ . '(): useInsertBlade() failed');
}

// Collect data necessary to build a tree. Calling functions should care about
// setting the rest of data.
function getIPv4NetworkList ($tagfilter = array(), $tfmode = 'any')
{
	$whereclause = getWhereClause ($tagfilter);
	$query =
		"select distinct id, INET_NTOA(ip) as ip, mask, name " .
		"from IPv4Network left join TagStorage on id = entity_id and entity_realm = 'ipv4net' " .
		"where true ${whereclause} order by IPv4Network.ip, IPv4Network.mask";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		// ip_bin and mask are used by iptree_fill()
		$row['ip_bin'] = ip2long ($row['ip']);
		$ret[$row['id']] = $row;
	}
	// After all the keys are known we can update parent_id appropriately. Also we don't
	// run two queries in parallel this way.
	$keys = array_keys ($ret);
	foreach ($keys as $netid)
	{
		// parent_id is for treeFromList()
		$ret[$netid]['parent_id'] = getIPv4AddressNetworkId ($ret[$netid]['ip'], $ret[$netid]['mask']);
		if ($ret[$netid]['parent_id'] and !in_array ($ret[$netid]['parent_id'], $keys))
		{
			$ret[$netid]['real_parent_id'] = $ret[$netid]['parent_id'];
			$ret[$netid]['parent_id'] = NULL;
		}
	}
	return $ret;
}

// Return the id of the smallest IPv4 network containing the given IPv4 address
// or NULL, if nothing was found. When finding the covering network for
// another network, it is important to filter out matched records with longer
// masks (they aren't going to be the right pick).
function getIPv4AddressNetworkId ($dottedquad, $masklen = 32)
{
// N.B. To perform the same for IPv6 address and networks, some pre-requisites
// are necessary and a different query. IPv6 addresses are 128 bit long, which
// is too much for both PHP and MySQL data types. These values must be split
// into 4 32-byte long parts (b_u32_0, b_u32_1, b_u32_2, b_u32_3).
// Then each network must have its 128-bit netmask split same way and either
// stored right in its record or JOINed from decoder and accessible as m_u32_0,
// m_u32_1, m_u32_2, m_u32_3. After that the query to pick the smallest network
// covering the given address would look as follows:
// $query = 'select id from IPv6Network as n where ' .
// "(${b_u32_0} & n.m_u32_0 = n.b_u32_0) and " .
// "(${b_u32_1} & n.m_u32_1 = n.b_u32_1) and " .
// "(${b_u32_2} & n.m_u32_2 = n.b_u32_2) and " .
// "(${b_u32_3} & n.m_u32_3 = n.b_u32_3) and " .
// "mask < ${masklen} " .
// 'order by mask desc limit 1';

	$query = 'select id from IPv4Network where ' .
		"inet_aton('${dottedquad}') & (4294967295 >> (32 - mask)) << (32 - mask) = ip " .
		"and mask < ${masklen} " .
		'order by mask desc limit 1';
	$result = useSelectBlade ($query, __FUNCTION__);
	if ($row = $result->fetch (PDO::FETCH_ASSOC))
		return $row['id'];
	return NULL;
}

function updateRange ($id=0, $name='')
{
	global $dbxlink;
	$query =
		"update IPv4Network set name='$name' where id='$id'";
	$result = $dbxlink->exec ($query);
	return '';
}

// This function is actually used not only to update, but also to create records,
// that's why ON DUPLICATE KEY UPDATE was replaced by DELETE-INSERT pair
// (MySQL 4.0 workaround).
function updateAddress ($ip = 0, $name = '', $reserved = 'no')
{
	// DELETE may safely fail.
	$r = useDeleteBlade ('IPv4Address', 'ip', "INET_ATON('${ip}')");
	// INSERT may appear not necessary.
	if ($name == '' and $reserved == 'no')
		return '';
	if (useInsertBlade ('IPv4Address', array ('name' => "'${name}'", 'reserved' => "'${reserved}'", 'ip' => "INET_ATON('${ip}')")))
		return '';
	else
		return __FUNCTION__ . '(): useInsertBlade() failed';
}

function updateBond ($ip='', $object_id=0, $name='', $type='')
{
	global $dbxlink;

	$query =
		"update IPv4Allocation set name='$name', type='$type' where ip=INET_ATON('$ip') and object_id='$object_id'";
	$result = $dbxlink->exec ($query);
	return '';
}

function unbindIpFromObject ($ip='', $object_id=0)
{
	global $dbxlink;

	$query =
		"delete from IPv4Allocation where ip=INET_ATON('$ip') and object_id='$object_id'";
	$result = $dbxlink->exec ($query);
	return '';
}

// This function returns either all or one user account. Array key is user name.
function getUserAccounts ($tagfilter = array(), $tfmode = 'any')
{
	$whereclause = getWhereClause ($tagfilter);
	$query =
		'select user_id, user_name, user_password_hash, user_realname ' .
		'from UserAccount left join TagStorage ' .
		"on UserAccount.user_id = TagStorage.entity_id and entity_realm = 'user' " .
		"where true ${whereclause} " .
		'order by user_name';
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	$clist = array ('user_id', 'user_name', 'user_realname', 'user_password_hash');
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		foreach ($clist as $cname)
			$ret[$row['user_name']][$cname] = $row[$cname];
	$result->closeCursor();
	return $ret;
}

function searchByl2address ($port_l2address)
{
	if (NULL === ($db_l2address = l2addressForDatabase ($port_l2address)))
		return NULL; // Don't complain, other searches may return own data.
	$query = "select object_id, Port.id as port_id from RackObject as ro inner join Port on ro.id = Port.object_id " .
		"where l2address = '${db_l2address}'";
	$result = useSelectBlade ($query, __FUNCTION__);
	$rows = $result->fetchAll (PDO::FETCH_ASSOC);
	$result->closeCursor();
	if (count ($rows) == 0) // No results.
		return NULL;
	if (count ($rows) == 1) // Target found.
		return $rows[0];
	showError ('More than one results was found. This is probably a broken unique key.', __FUNCTION__);
	return NULL;
}

function getIPv4PrefixSearchResult ($terms)
{
	$query = "select id, inet_ntoa(ip) as ip, mask, name from IPv4Network where ";
	$or = '';
	foreach (explode (' ', $terms) as $term)
	{
		$query .= $or . "name like '%${term}%'";
		$or = ' or ';
	}
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$ret[] = $row;
	return $ret;
}

function getIPv4AddressSearchResult ($terms)
{
	$query = "select inet_ntoa(ip) as ip, name from IPv4Address where ";
	$or = '';
	foreach (explode (' ', $terms) as $term)
	{
		$query .= $or . "name like '%${term}%'";
		$or = ' or ';
	}
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$ret[] = $row;
	return $ret;
}

function getIPv4RSPoolSearchResult ($terms)
{
	$query = "select id as pool_id, name from IPv4RSPool where ";
	$or = '';
	foreach (explode (' ', $terms) as $term)
	{
		$query .= $or . "name like '%${term}%'";
		$or = ' or ';
	}
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$ret[] = $row;
	return $ret;
}

function getIPv4VServiceSearchResult ($terms)
{
	$query = "select id, inet_ntoa(vip) as vip, vport, proto, name from IPv4VS where ";
	$or = '';
	foreach (explode (' ', $terms) as $term)
	{
		$query .= $or . "name like '%${term}%'";
		$or = ' or ';
	}
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$ret[] = $row;
	return $ret;
}

function getAccountSearchResult ($terms)
{
	$byUsername = getSearchResultByField
	(
		'UserAccount',
		array ('user_id', 'user_name', 'user_realname'),
		'user_name',
		$terms,
		'user_name'
	);
	$byRealname = getSearchResultByField
	(
		'UserAccount',
		array ('user_id', 'user_name', 'user_realname'),
		'user_realname',
		$terms,
		'user_name'
	);
	// Filter out dupes.
	foreach ($byUsername as $res1)
		foreach (array_keys ($byRealname) as $key2)
			if ($res1['user_id'] == $byRealname[$key2]['user_id'])
			{
				unset ($byRealname[$key2]);
				continue 2;
			}
	return array_merge ($byUsername, $byRealname);
}

function getFileSearchResult ($terms)
{
	$byFilename = getSearchResultByField
	(
		'File',
		array ('id', 'name', 'comment', 'type', 'size'),
		'name',
		$terms,
		'name'
	);
	$byComment = getSearchResultByField
	(
		'File',
		array ('id', 'name', 'comment', 'type', 'size'),
		'comment',
		$terms,
		'name'
	);
	// Filter out dupes.
	foreach ($byFilename as $res1)
		foreach (array_keys ($byComment) as $key2)
			if ($res1['id'] == $byComment[$key2]['id'])
			{
				unset ($byComment[$key2]);
				continue 2;
			}
	return array_merge ($byFilename, $byComment);
}

function getSearchResultByField ($tname, $rcolumns, $scolumn, $terms, $ocolumn = '')
{
	$pfx = '';
	$query = 'select ';
	foreach ($rcolumns as $col)
	{
		$query .= $pfx . $col;
		$pfx = ', ';
	}
	$pfx = '';
	$query .= " from ${tname} where ";
	foreach (explode (' ', $terms) as $term)
	{
		$query .= $pfx . "${scolumn} like '%${term}%'";
		$pfx = ' or ';
	}
	if ($ocolumn != '')
		$query .= " order by ${ocolumn}";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$ret[] = $row;
	return $ret;
}

// This function returns either port ID or NULL for specified arguments.
function getPortID ($object_id, $port_name)
{
	$query = "select id from Port where object_id=${object_id} and name='${port_name}' limit 2";
	$result = useSelectBlade ($query, __FUNCTION__);
	$rows = $result->fetchAll (PDO::FETCH_NUM);
	if (count ($rows) != 1)
		return NULL;
	$ret = $rows[0][0];
	$result->closeCursor();
	return $ret;
}

function commitCreateUserAccount ($username, $realname, $password)
{
	return useInsertBlade
	(
		'UserAccount',
		array
		(
			'user_name' => "'${username}'",
			'user_realname' => "'${realname}'",
			'user_password_hash' => "'${password}'"
		)
	);
}

function commitUpdateUserAccount ($id, $new_username, $new_realname, $new_password)
{
	global $dbxlink;
	$query =
		"update UserAccount set user_name = '${new_username}', user_realname = '${new_realname}', " .
		"user_password_hash = '${new_password}' where user_id = ${id} limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ('SQL query failed', __FUNCTION__);
		die;
	}
	return TRUE;
}

// This function returns an array of all port type pairs from PortCompat table.
function getPortCompat ()
{
	$query =
		"select type1, type2, d1.dict_value as type1name, d2.dict_value as type2name from " .
		"PortCompat as pc inner join Dictionary as d1 on pc.type1 = d1.dict_key " .
		"inner join Dictionary as d2 on pc.type2 = d2.dict_key " .
		"inner join Chapter as c1 on d1.chapter_id = c1.id " .
		"inner join Chapter as c2 on d2.chapter_id = c2.id " .
		"where c1.name = 'PortType' and c2.name = 'PortType'";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = $result->fetchAll (PDO::FETCH_ASSOC);
	$result->closeCursor();
	return $ret;
}

function removePortCompat ($type1 = 0, $type2 = 0)
{
	global $dbxlink;
	if ($type1 == 0 or $type2 == 0)
	{
		showError ('Invalid arguments', __FUNCTION__);
		die;
	}
	$query = "delete from PortCompat where type1 = ${type1} and type2 = ${type2} limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ('SQL query failed', __FUNCTION__);
		die;
	}
	return TRUE;
}

function addPortCompat ($type1 = 0, $type2 = 0)
{
	if ($type1 <= 0 or $type2 <= 0)
	{
		showError ('Invalid arguments', __FUNCTION__);
		die;
	}
	return useInsertBlade
	(
		'PortCompat',
		array ('type1' => $type1, 'type2' => $type2)
	);
}

// This function returns the dictionary as an array of trees, each tree
// representing a single chapter. Each element has 'id', 'name', 'sticky'
// and 'word' keys with the latter holding all the words within the chapter.
function getDict ($parse_links = FALSE)
{
	$query1 =
		"select Chapter.name as chapter_name, Chapter.id as chapter_no, dict_key, dict_value, sticky from " .
		"Chapter left join Dictionary on Chapter.id = Dictionary.chapter_id order by Chapter.name, dict_value";
	$result = useSelectBlade ($query1, __FUNCTION__);
	$dict = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$chapter_no = $row['chapter_no'];
		if (!isset ($dict[$chapter_no]))
		{
			$dict[$chapter_no]['no'] = $chapter_no;
			$dict[$chapter_no]['name'] = $row['chapter_name'];
			$dict[$chapter_no]['sticky'] = $row['sticky'] == 'yes' ? TRUE : FALSE;
			$dict[$chapter_no]['word'] = array();
		}
		if ($row['dict_key'] != NULL)
		{
			$dict[$chapter_no]['word'][$row['dict_key']] = ($parse_links or $row['dict_key'] <= MAX_DICT_KEY) ?
				parseWikiLink ($row['dict_value'], 'a') : $row['dict_value'];
			$dict[$chapter_no]['refcnt'][$row['dict_key']] = 0;
		}
	}
	$result->closeCursor();
	unset ($result);
// Find the list of all assigned values of dictionary-addressed attributes, each with
// chapter/word keyed reference counters. Use the structure to adjust reference counters
// of the returned disctionary words.
	$query2 = "select a.id as attr_id, am.chapter_id as chapter_no, uint_value, count(object_id) as refcnt " .
		"from Attribute as a inner join AttributeMap as am on a.id = am.attr_id " .
		"inner join AttributeValue as av on a.id = av.attr_id " .
		"inner join Dictionary as d on am.chapter_id = d.chapter_id and av.uint_value = d.dict_key " .
		"where a.type = 'dict' group by a.id, am.chapter_id, uint_value " .
		"order by a.id, am.chapter_id, uint_value";
	$result = useSelectBlade ($query2, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$dict[$row['chapter_no']]['refcnt'][$row['uint_value']] = $row['refcnt'];
	$result->closeCursor();
	return $dict;
}

function getDictStats ()
{
	$stock_chapters = array (1, 2, 11, 12, 13, 14, 16, 17, 18, 19, 20, 21, 22, 23);
	$query =
		"select Chapter.id as chapter_no, Chapter.name as chapter_name, count(dict_key) as wc from " .
		"Chapter left join Dictionary on Chapter.id = Dictionary.chapter_id group by Chapter.id";
	$result = useSelectBlade ($query, __FUNCTION__);
	$tc = $tw = $uc = $uw = 0;
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$tc++;
		$tw += $row['wc'];;
		if (in_array ($row['chapter_no'], $stock_chapters))
			continue;
		$uc++;
		$uw += $row['wc'];;
	}
	$result->closeCursor();
	unset ($result);
	$query = "select count(id) as attrc from RackObject as ro left join " .
		"AttributeValue as av on ro.id = av.object_id group by ro.id";
	$result = useSelectBlade ($query, __FUNCTION__);
	$to = $ta = $so = 0;
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$to++;
		if ($row['attrc'] != 0)
		{
			$so++;
			$ta += $row['attrc'];
		}
	}
	$result->closeCursor();
	$ret = array();
	$ret['Total chapters in dictionary'] = $tc;
	$ret['Total words in dictionary'] = $tw;
	$ret['User chapters'] = $uc;
	$ret['Words in user chapters'] = $uw;
	$ret['Total objects'] = $to;
	$ret['Objects with stickers'] = $so;
	$ret['Total stickers attached']  = $ta;
	return $ret;
}

function getIPv4Stats ()
{
	$ret = array();
	$subject = array();
	$subject[] = array ('q' => 'select count(id) from IPv4Network', 'txt' => 'Networks');
	$subject[] = array ('q' => 'select count(ip) from IPv4Address', 'txt' => 'Addresses commented/reserved');
	$subject[] = array ('q' => 'select count(ip) from IPv4Allocation', 'txt' => 'Addresses allocated');
	$subject[] = array ('q' => 'select count(*) from IPv4NAT', 'txt' => 'NAT rules');
	$subject[] = array ('q' => 'select count(id) from IPv4VS', 'txt' => 'Virtual services');
	$subject[] = array ('q' => 'select count(id) from IPv4RSPool', 'txt' => 'Real server pools');
	$subject[] = array ('q' => 'select count(id) from IPv4RS', 'txt' => 'Real servers');
	$subject[] = array ('q' => 'select count(distinct object_id) from IPv4LB', 'txt' => 'Load balancers');

	foreach ($subject as $item)
	{
		$result = useSelectBlade ($item['q'], __FUNCTION__);
		$row = $result->fetch (PDO::FETCH_NUM);
		$ret[$item['txt']] = $row[0];
		$result->closeCursor();
		unset ($result);
	}
	return $ret;
}

function getRackspaceStats ()
{
	$ret = array();
	$subject = array();
	$subject[] = array ('q' => 'select count(*) from RackRow', 'txt' => 'Rack rows');
	$subject[] = array ('q' => 'select count(*) from Rack', 'txt' => 'Racks');
	$subject[] = array ('q' => 'select avg(height) from Rack', 'txt' => 'Average rack height');
	$subject[] = array ('q' => 'select sum(height) from Rack', 'txt' => 'Total rack units in field');

	foreach ($subject as $item)
	{
		$result = useSelectBlade ($item['q'], __FUNCTION__);
		$row = $result->fetch (PDO::FETCH_NUM);
		$ret[$item['txt']] = empty ($row[0]) ? 0 : $row[0];
		$result->closeCursor();
		unset ($result);
	}
	return $ret;
}

function renderTagStats ()
{
	global $taglist, $root;
	$query = "select id, tag, count(tag_id) as refcnt from " .
		"TagTree inner join TagStorage on TagTree.id = TagStorage.tag_id " .
		"group by tag_id order by refcnt desc limit 50";
	// The same data is already present in pre-loaded tag list, but not in
	// the form we need. So let's ask the DB server for cooked top list and
	// use the cooked tag list to break it down.
	$result = useSelectBlade ($query, __FUNCTION__);
	$refc = $result->fetchAll (PDO::FETCH_ASSOC);
	echo '<table border=1><tr><th>tag</th><th>total</th><th>objects</th><th>IPv4 nets</th><th>racks</th>';
	echo '<th>IPv4 VS</th><th>IPv4 RS pools</th><th>users</th><th>files</th></tr>';
	$pagebyrealm = array
	(
		'file' => 'filesbylink&entity_type=all',
		'ipv4net' => 'ipv4space&tab=default',
		'ipv4vs' => 'ipv4vslist&tab=default',
		'ipv4rspool' => 'ipv4rsplist&tab=default',
		'object' => 'objgroup&group_id=0',
		'rack' => 'rackspace&tab=default',
		'user' => 'userlist&tab=default'
	);
	foreach ($refc as $ref)
	{
		echo "<tr><td>${ref['tag']}</td><td>${ref['refcnt']}</td>";
		foreach (array ('object', 'ipv4net', 'rack', 'ipv4vs', 'ipv4rspool', 'user', 'file') as $realm)
		{
			echo '<td>';
			if (!isset ($taglist[$ref['id']]['refcnt'][$realm]))
				echo '&nbsp;';
			else
			{
				echo "<a href='${root}?page=" . $pagebyrealm[$realm] . "&tagfilter[]=${ref['id']}'>";
				echo $taglist[$ref['id']]['refcnt'][$realm] . '</a>';
			}
			echo '</td>';
		}
		echo '</tr>';
	}
	echo '</table>';
}

/*

The following allows figuring out records in TagStorage, which refer to non-existing entities:

mysql> select entity_id from TagStorage left join Files on entity_id = id where entity_realm = 'file' and id is null;
mysql> select entity_id from TagStorage left join IPv4Network on entity_id = id where entity_realm = 'ipv4net' and id is null;
mysql> select entity_id from TagStorage left join RackObject on entity_id = id where entity_realm = 'object' and id is null;
mysql> select entity_id from TagStorage left join Rack on entity_id = id where entity_realm = 'rack' and id is null;
mysql> select entity_id from TagStorage left join IPv4VS on entity_id = id where entity_realm = 'ipv4vs' and id is null;
mysql> select entity_id from TagStorage left join IPv4RSPool on entity_id = id where entity_realm = 'ipv4rspool' and id is null;
mysql> select entity_id from TagStorage left join UserAccount on entity_id = user_id where entity_realm = 'user' and user_id is null;

Accordingly, these are the records, which refer to non-existent tags:

mysql> select tag_id from TagStorage left join TagTree on tag_id = id where id is null;

*/

function commitUpdateDictionary ($chapter_no = 0, $dict_key = 0, $dict_value = '')
{
	if ($chapter_no <= 0 or $dict_key <= 0 or empty ($dict_value))
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query =
		"update Dictionary set dict_value = '${dict_value}' where chapter_id=${chapter_no} " .
		"and dict_key=${dict_key} limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ('SQL query failed', __FUNCTION__);
		die;
	}
	return TRUE;
}

function commitSupplementDictionary ($chapter_no = 0, $dict_value = '')
{
	if ($chapter_no <= 0 or empty ($dict_value))
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	return useInsertBlade
	(
		'Dictionary',
		array ('chapter_id' => $chapter_no, 'dict_value' => "'${dict_value}'")
	);
}

function commitReduceDictionary ($chapter_no = 0, $dict_key = 0)
{
	if ($chapter_no <= 0 or $dict_key <= 0)
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query =
		"delete from Dictionary where chapter_id=${chapter_no} " .
		"and dict_key=${dict_key} limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ('SQL query failed', __FUNCTION__);
		die;
	}
	return TRUE;
}

function commitAddChapter ($chapter_name = '')
{
	if (empty ($chapter_name))
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	return useInsertBlade
	(
		'Chapter',
		array ('name' => "'${chapter_name}'")
	);
}

function commitUpdateChapter ($chapter_no = 0, $chapter_name = '')
{
	if ($chapter_no <= 0 or empty ($chapter_name))
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query =
		"update Chapter set name = '${chapter_name}' where id = ${chapter_no} " .
		"and sticky = 'no' limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ('SQL query failed', __FUNCTION__);
		die;
	}
	return TRUE;
}

function commitDeleteChapter ($chapter_no = 0)
{
	if ($chapter_no <= 0)
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query =
		"delete from Chapter where id = ${chapter_no} and sticky = 'no' limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ('SQL query failed', __FUNCTION__);
		die;
	}
	return TRUE;
}

// This is a dictionary accessor. We perform link rendering, so the user sees
// nice <select> drop-downs.
function readChapter ($chapter_name = '')
{
	if (empty ($chapter_name))
	{
		showError ('invalid argument', __FUNCTION__);
		return NULL;
	}
	$query =
		"select dict_key, dict_value from Dictionary join Chapter on Chapter.id = Dictionary.chapter_id " .
		"where Chapter.name = '${chapter_name}'";
	$result = useSelectBlade ($query, __FUNCTION__);
	$chapter = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$chapter[$row['dict_key']] = parseWikiLink ($row['dict_value'], 'o');
	$result->closeCursor();
	// SQL ORDER BY had no sense, because we need to sort after link rendering, not before.
	asort ($chapter);
	return $chapter;
}

function getAttrMap ()
{
	$query =
		"select a.id as attr_id, a.type as attr_type, a.name as attr_name, am.objtype_id, " .
		"d.dict_value as objtype_name, am.chapter_id, c2.name as chapter_name from " .
		"Attribute as a left join AttributeMap as am on a.id = am.attr_id " .
		"left join Dictionary as d on am.objtype_id = d.dict_key " .
		"left join Chapter as c1 on d.chapter_id = c1.id " .
		"left join Chapter as c2 on am.chapter_id = c2.id " .
		"where c1.name = 'RackObjectType' or c1.name is null " .
		"order by a.name";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$attr_id = $row['attr_id'];
		if (!isset ($ret[$attr_id]))
		{
			$ret[$attr_id]['id'] = $attr_id;
			$ret[$attr_id]['type'] = $row['attr_type'];
			$ret[$attr_id]['name'] = $row['attr_name'];
			$ret[$attr_id]['application'] = array();
		}
		if ($row['objtype_id'] == '')
			continue;
		$application['objtype_id'] = $row['objtype_id'];
		$application['objtype_name'] = $row['objtype_name'];
		if ($row['attr_type'] == 'dict')
		{
			$application['chapter_no'] = $row['chapter_id'];
			$application['chapter_name'] = $row['chapter_name'];
		}
		$ret[$attr_id]['application'][] = $application;
	}
	$result->closeCursor();
	return $ret;
}

function commitUpdateAttribute ($attr_id = 0, $attr_name = '')
{
	if ($attr_id <= 0 or empty ($attr_name))
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query =
		"update Attribute set name = '${attr_name}' " .
		"where id = ${attr_id} limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ("SQL query '${query}' failed", __FUNCTION__);
		die;
	}
	return TRUE;
}

function commitAddAttribute ($attr_name = '', $attr_type = '')
{
	if (empty ($attr_name))
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	switch ($attr_type)
	{
		case 'uint':
		case 'float':
		case 'string':
		case 'dict':
			break;
		default:
			showError ('Invalid args', __FUNCTION__);
			die;
	}
	return useInsertBlade
	(
		'Attribute',
		array ('name' => "'${attr_name}'", 'type' => "'${attr_type}'")
	);
}

function commitDeleteAttribute ($attr_id = 0)
{
	if ($attr_id <= 0)
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	return useDeleteBlade ('Attribute', 'id', $attr_id);
}

// FIXME: don't store garbage in chapter_no for non-dictionary types.
function commitSupplementAttrMap ($attr_id = 0, $objtype_id = 0, $chapter_no = 0)
{
	if ($attr_id <= 0 or $objtype_id <= 0 or $chapter_no <= 0)
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	return useInsertBlade
	(
		'AttributeMap',
		array
		(
			'attr_id' => $attr_id,
			'objtype_id' => $objtype_id,
			'chapter_id' => $chapter_no
		)
	);
}

function commitReduceAttrMap ($attr_id = 0, $objtype_id)
{
	if ($attr_id <= 0 or $objtype_id <= 0)
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query =
		"delete from AttributeMap where attr_id=${attr_id} " .
		"and objtype_id=${objtype_id} limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ('SQL query failed', __FUNCTION__);
		die;
	}
	return TRUE;
}

// This function returns all optional attributes for requested object
// as an array of records. NULL is returned on error and empty array
// is returned, if there are no attributes found.
function getAttrValues ($object_id, $strip_optgroup = FALSE)
{
	if ($object_id <= 0)
	{
		showError ('Invalid argument', __FUNCTION__);
		return NULL;
	}
	$ret = array();
	$query =
		"select A.id as attr_id, A.name as attr_name, A.type as attr_type, C.name as chapter_name, " .
		"AV.uint_value, AV.float_value, AV.string_value, D.dict_value from " .
		"RackObject as RO inner join AttributeMap as AM on RO.objtype_id = AM.objtype_id " .
		"inner join Attribute as A on AM.attr_id = A.id " .
		"left join AttributeValue as AV on AV.attr_id = AM.attr_id and AV.object_id = RO.id " .
		"left join Dictionary as D on D.dict_key = AV.uint_value and AM.chapter_id = D.chapter_id " .
		"left join Chapter as C on AM.chapter_id = C.id " .
		"where RO.id = ${object_id} order by A.type, A.name";
	$result = useSelectBlade ($query, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$record = array();
		$record['id'] = $row['attr_id'];
		$record['name'] = $row['attr_name'];
		$record['type'] = $row['attr_type'];
		switch ($row['attr_type'])
		{
			case 'uint':
			case 'float':
			case 'string':
				$record['value'] = $row[$row['attr_type'] . '_value'];
				$record['a_value'] = parseWikiLink ($record['value'], 'a');
				break;
			case 'dict':
				$record['value'] = parseWikiLink ($row[$row['attr_type'] . '_value'], 'o', $strip_optgroup);
				$record['a_value'] = parseWikiLink ($row[$row['attr_type'] . '_value'], 'a', $strip_optgroup);
				$record['chapter_name'] = $row['chapter_name'];
				$record['key'] = $row['uint_value'];
				break;
			default:
				$record['value'] = NULL;
				break;
		}
		$ret[$row['attr_id']] = $record;
	}
	$result->closeCursor();
	return $ret;
}

function commitResetAttrValue ($object_id = 0, $attr_id = 0)
{
	if ($object_id <= 0 or $attr_id <= 0)
	{
		showError ('Invalid arguments', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query = "delete from AttributeValue where object_id = ${object_id} and attr_id = ${attr_id} limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ('SQL query failed', __FUNCTION__);
		die;
	}
	return TRUE;
}

// FIXME: don't share common code with use commitResetAttrValue()
function commitUpdateAttrValue ($object_id = 0, $attr_id = 0, $value = '')
{
	if ($object_id <= 0 or $attr_id <= 0)
	{
		showError ('Invalid arguments', __FUNCTION__);
		die;
	}
	if (empty ($value))
		return commitResetAttrValue ($object_id, $attr_id);
	global $dbxlink;
	$query1 = "select type as attr_type from Attribute where id = ${attr_id}";
	$result = $dbxlink->query ($query1);
	if ($result == NULL)
	{
		showError ('SQL query #1 failed', __FUNCTION__);
		die;
	}
	$row = $result->fetch (PDO::FETCH_NUM);
	if ($row == NULL)
	{
		showError ('SQL query #1 returned no results', __FUNCTION__);
		die;
	}
	$attr_type = $row[0];
	$result->closeCursor();
	switch ($attr_type)
	{
		case 'uint':
		case 'float':
		case 'string':
			$column = $attr_type . '_value';
			break;
		case 'dict':
			$column = 'uint_value';
			break;
		default:
			showError ("Unknown attribute type '${attr_type}' met", __FUNCTION__);
			die;
	}
	$query2 =
		"delete from AttributeValue where " .
		"object_id = ${object_id} and attr_id = ${attr_id} limit 1";
	$result = $dbxlink->query ($query2);
	if ($result == NULL)
	{
		showError ('SQL query #2 failed', __FUNCTION__);
		die;
	}
	// We know $value isn't empty here.
	$query3 =
		"insert into AttributeValue set ${column} = '${value}', " .
		"object_id = ${object_id}, attr_id = ${attr_id} ";
	$result = $dbxlink->query ($query3);
	if ($result == NULL)
	{
		showError ('SQL query #3 failed', __FUNCTION__);
		die;
	}
	return TRUE;
}

function commitUseupPort ($port_id = 0)
{
	if ($port_id <= 0)
	{
		showError ("Invalid argument", __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query = "update Port set reservation_comment = NULL where id = ${port_id} limit 1";
	$result = $dbxlink->exec ($query);
	if ($result == NULL)
	{
		showError ("SQL query failed", __FUNCTION__);
		die;
	}
	return TRUE;
	
}

// This is a swiss-knife blade to insert a record into a table.
// The first argument is table name.
// The second argument is an array of "name" => "value" pairs.
// The function returns either TRUE or FALSE (we expect one row
// to be inserted).
function useInsertBlade ($tablename, $values)
{
	global $dbxlink;
	$namelist = $valuelist = '';
	foreach ($values as $name => $value)
	{
		$namelist = $namelist . ($namelist == '' ? "(${name}" : ", ${name}");
		$valuelist = $valuelist . ($valuelist == '' ? "(${value}" : ", ${value}");
	}
	$query = "insert into ${tablename} ${namelist}) values ${valuelist})";
	$result = $dbxlink->exec ($query);
	if ($result != 1)
		return FALSE;
	return TRUE;
}

// This swiss-knife blade deletes one record from the specified table
// using the specified key name and value.
function useDeleteBlade ($tablename, $keyname, $keyvalue)
{
	global $dbxlink;
	return 1 === $dbxlink->exec ("delete from ${tablename} where ${keyname}=${keyvalue} limit 1");
}

function useSelectBlade ($query, $caller = 'N/A')
{
	global $dbxlink;
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		$ei = $dbxlink->errorInfo();
		showError ("SQL query '${query}'\n failed in useSelectBlade with error ${ei[1]} (${ei[2]})", $caller);
		return NULL;
	}
	return $result;
}

function loadConfigCache ()
{
	$query = 'SELECT varname, varvalue, vartype, is_hidden, emptyok, description FROM Config ORDER BY varname';
	$result = useSelectBlade ($query, __FUNCTION__);
	$cache = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$cache[$row['varname']] = $row;
	$result->closeCursor();
	return $cache;
}

// setConfigVar() is expected to perform all necessary filtering
function storeConfigVar ($varname = NULL, $varvalue = NULL)
{
	global $dbxlink;
	if (empty ($varname) || $varvalue === NULL)
	{
		showError ('Invalid arguments', __FUNCTION__);
		return FALSE;
	}
	$query = "update Config set varvalue='${varvalue}' where varname='${varname}' limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ("SQL query '${query}' failed", __FUNCTION__);
		return FALSE;
	}
	$rc = $result->rowCount();
	$result->closeCursor();
	if ($rc == 0 or $rc == 1)
		return TRUE;
	showError ("Something went wrong for args '${varname}', '${varvalue}'", __FUNCTION__);
	return FALSE;
}

// Database version detector. Should behave corretly on any
// working dataset a user might have.
function getDatabaseVersion ()
{
	global $dbxlink;
	$query = "select varvalue from Config where varname = 'DB_VERSION' and vartype = 'string'";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		$errorInfo = $dbxlink->errorInfo();
		if ($errorInfo[0] == '42S02') // ER_NO_SUCH_TABLE
			return '0.14.4';
		die (__FUNCTION__ . ': SQL query #1 failed with error ' . $errorInfo[2]);
	}
	$rows = $result->fetchAll (PDO::FETCH_NUM);
	if (count ($rows) != 1 || empty ($rows[0][0]))
	{
		$result->closeCursor();
		die (__FUNCTION__ . ': Cannot guess database version. Config table is present, but DB_VERSION is missing or invalid. Giving up.');
	}
	$ret = $rows[0][0];
	$result->closeCursor();
	return $ret;
}

// Return an array of virtual services. For each of them list real server pools
// with their load balancers and other stats.
function getSLBSummary ()
{
	$query = 'select vs.id as vsid, inet_ntoa(vip) as vip, vport, proto, vs.name, object_id, ' .
		'lb.rspool_id, pool.name as pool_name, count(rs.id) as rscount ' .
		'from IPv4VS as vs inner join IPv4LB as lb on vs.id = lb.vs_id ' .
		'inner join IPv4RSPool as pool on lb.rspool_id = pool.id ' .
		'left join IPv4RS as rs on rs.rspool_id = lb.rspool_id ' .
		'group by vs.id, object_id order by vs.vip, object_id';
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$vsid = $row['vsid'];
		$object_id = $row['object_id'];
		if (!isset ($ret[$vsid]))
		{
			$ret[$vsid] = array();
			foreach (array ('vip', 'vport', 'proto', 'name') as $cname)
				$ret[$vsid][$cname] = $row[$cname];
			$ret[$vsid]['lblist'] = array();
		}
		// There's only one assigned RS pool possible for each LB-VS combination.
		$ret[$vsid]['lblist'][$row['object_id']] = array
		(
			'id' => $row['rspool_id'],
			'size' => $row['rscount'],
			'name' => $row['pool_name']
		);
	}
	$result->closeCursor();
	return $ret;
}

// Get the detailed composition of a particular virtual service, namely the list
// of all pools, each shown with the list of objects servicing it. VS/RS configs
// will be returned as well.
function getVServiceInfo ($vsid = 0)
{
	$query1 = "select id, inet_ntoa(vip) as vip, vport, proto, name, vsconfig, rsconfig " .
		"from IPv4VS where id = ${vsid}";
	$result = useSelectBlade ($query1, __FUNCTION__);
	$vsinfo = array ();
	$row = $result->fetch (PDO::FETCH_ASSOC);
	if (!$row)
		return NULL;
	foreach (array ('id', 'vip', 'vport', 'proto', 'name', 'vsconfig', 'rsconfig') as $cname)
		$vsinfo[$cname] = $row[$cname];
	$vsinfo['rspool'] = array();
	$result->closeCursor();
	unset ($result);
	$query2 = "select pool.id, name, pool.vsconfig, pool.rsconfig, object_id, " .
		"lb.vsconfig as lb_vsconfig, lb.rsconfig as lb_rsconfig from " .
		"IPv4RSPool as pool left join IPv4LB as lb on pool.id = lb.rspool_id " .
		"where vs_id = ${vsid} order by pool.name, object_id";
	$result = useSelectBlade ($query2, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		if (!isset ($vsinfo['rspool'][$row['id']]))
		{
			$vsinfo['rspool'][$row['id']]['name'] = $row['name'];
			$vsinfo['rspool'][$row['id']]['vsconfig'] = $row['vsconfig'];
			$vsinfo['rspool'][$row['id']]['rsconfig'] = $row['rsconfig'];
			$vsinfo['rspool'][$row['id']]['lblist'] = array();
		}
		if ($row['object_id'] == NULL)
			continue;
		$vsinfo['rspool'][$row['id']]['lblist'][$row['object_id']] = array
		(
			'vsconfig' => $row['lb_vsconfig'],
			'rsconfig' => $row['lb_rsconfig']
		);
	}
	$result->closeCursor();
	return $vsinfo;
}

// Collect and return the following info about the given real server pool:
// basic information
// parent virtual service information
// load balancers list (each with a list of VSes)
// real servers list

function getRSPoolInfo ($id = 0)
{
	$query1 = "select id, name, vsconfig, rsconfig from " .
		"IPv4RSPool where id = ${id}";
	$result = useSelectBlade ($query1, __FUNCTION__);
	$ret = array();
	$row = $result->fetch (PDO::FETCH_ASSOC);
	if (!$row)
		return NULL;
	foreach (array ('id', 'name', 'vsconfig', 'rsconfig') as $c)
		$ret[$c] = $row[$c];
	$result->closeCursor();
	unset ($result);
	$ret['lblist'] = array();
	$ret['rslist'] = array();
	$query2 = "select object_id, vs_id, lb.vsconfig, lb.rsconfig from " .
		"IPv4LB as lb inner join IPv4VS as vs on lb.vs_id = vs.id " .
		"where rspool_id = ${id} order by object_id, vip, vport";
	$result = useSelectBlade ($query2, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		foreach (array ('vsconfig', 'rsconfig') as $c)
			$ret['lblist'][$row['object_id']][$row['vs_id']][$c] = $row[$c];
	$result->closeCursor();
	unset ($result);
	$query3 = "select id, inservice, inet_ntoa(rsip) as rsip, rsport, rsconfig from " .
		"IPv4RS where rspool_id = ${id} order by IPv4RS.rsip, rsport";
	$result = useSelectBlade ($query3, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		foreach (array ('inservice', 'rsip', 'rsport', 'rsconfig') as $c)
			$ret['rslist'][$row['id']][$c] = $row[$c];
	$result->closeCursor();
	return $ret;
}

function addRStoRSPool ($pool_id = 0, $rsip = '', $rsport = 0, $inservice = 'no', $rsconfig = '')
{
	if ($pool_id <= 0)
	{
		showError ('Invalid arguments', __FUNCTION__);
		die;
	}
	if (empty ($rsport) or $rsport == 0)
		$rsport = 'NULL';
	return useInsertBlade
	(
		'IPv4RS',
		array
		(
			'rsip' => "inet_aton('${rsip}')",
			'rsport' => $rsport,
			'rspool_id' => $pool_id,
			'inservice' => ($inservice == 'yes' ? "'yes'" : "'no'"),
			'rsconfig' => (empty ($rsconfig) ? 'NULL' : "'${rsconfig}'")
		)
	);
}

function commitCreateVS ($vip = '', $vport = 0, $proto = '', $name = '', $vsconfig, $rsconfig, $taglist = array())
{
	if (empty ($vip) or $vport <= 0 or empty ($proto))
		return __FUNCTION__ . ': invalid arguments';
	if (!useInsertBlade
	(
		'IPv4VS',
		array
		(
			'vip' => "inet_aton('${vip}')",
			'vport' => $vport,
			'proto' => "'${proto}'",
			'name' => (empty ($name) ? 'NULL' : "'${name}'"),
			'vsconfig' => (empty ($vsconfig) ? 'NULL' : "'${vsconfig}'"),
			'rsconfig' => (empty ($rsconfig) ? 'NULL' : "'${rsconfig}'")
		)
	))
		return __FUNCTION__ . ': SQL insertion failed';
	return produceTagsForLastRecord ('ipv4vs', $taglist);
}

function addLBtoRSPool ($pool_id = 0, $object_id = 0, $vs_id = 0, $vsconfig = '', $rsconfig = '')
{
	if ($pool_id <= 0 or $object_id <= 0 or $vs_id <= 0)
	{
		showError ('Invalid arguments', __FUNCTION__);
		die;
	}
	return useInsertBlade
	(
		'IPv4LB',
		array
		(
			'object_id' => $object_id,
			'rspool_id' => $pool_id,
			'vs_id' => $vs_id,
			'vsconfig' => (empty ($vsconfig) ? 'NULL' : "'${vsconfig}'"),
			'rsconfig' => (empty ($rsconfig) ? 'NULL' : "'${rsconfig}'")
		)
	);
}

function commitDeleteRS ($id = 0)
{
	if ($id <= 0)
		return FALSE;
	return useDeleteBlade ('IPv4RS', 'id', $id);
}

function commitDeleteVS ($id = 0)
{
	if ($id <= 0)
		return FALSE;
	return useDeleteBlade ('IPv4VS', 'id', $id) && destroyTagsForEntity ('ipv4vs', $id);
}

function commitDeleteLB ($object_id = 0, $pool_id = 0, $vs_id = 0)
{
	global $dbxlink;
	if ($object_id <= 0 or $pool_id <= 0 or $vs_id <= 0)
		return FALSE;
	$query = "delete from IPv4LB where object_id = ${object_id} and " .
		"rspool_id = ${pool_id} and vs_id = ${vs_id} limit 1";
	$result = $dbxlink->exec ($query);
	if ($result === NULL)
		return FALSE;
	elseif ($result != 1)
		return FALSE;
	else
		return TRUE;
}

function commitUpdateRS ($rsid = 0, $rsip = '', $rsport = 0, $rsconfig = '')
{
	if ($rsid <= 0)
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	if (long2ip (ip2long ($rsip)) !== $rsip)
	{
		showError ("Invalid IP address '${rsip}'", __FUNCTION__);
		die;
	}
	if (empty ($rsport) or $rsport == 0)
		$rsport = 'NULL';
	global $dbxlink;
	$query =
		"update IPv4RS set rsip = inet_aton('${rsip}'), rsport = ${rsport}, rsconfig = " .
		(empty ($rsconfig) ? 'NULL' : "'${rsconfig}'") .
		" where id = ${rsid} limit 1";
	$result = $dbxlink->query ($query);
	if ($result == NULL)
	{
		showError ("SQL query '${query}' failed", __FUNCTION__);
		die;
	}
	return TRUE;
}

function commitUpdateLB ($object_id = 0, $pool_id = 0, $vs_id = 0, $vsconfig = '', $rsconfig = '')
{
	if ($object_id <= 0 or $pool_id <= 0 or $vs_id <= 0)
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query =
		"update IPv4LB set vsconfig = " .
		(empty ($vsconfig) ? 'NULL' : "'${vsconfig}'") .
		', rsconfig = ' .
		(empty ($rsconfig) ? 'NULL' : "'${rsconfig}'") .
		" where object_id = ${object_id} and rspool_id = ${pool_id} " .
		"and vs_id = ${vs_id} limit 1";
	$result = $dbxlink->exec ($query);
	if ($result === NULL)
		return FALSE;
	else
		return TRUE;
}

function commitUpdateVS ($vsid = 0, $vip = '', $vport = 0, $proto = '', $name = '', $vsconfig = '', $rsconfig = '')
{
	if ($vsid <= 0 or empty ($vip) or $vport <= 0 or empty ($proto))
	{
		showError ('Invalid args', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query = "update IPv4VS set " .
		"vip = inet_aton('${vip}'), " .
		"vport = ${vport}, " .
		"proto = '${proto}', " .
		'name = ' . (empty ($name) ? 'NULL,' : "'${name}', ") .
		'vsconfig = ' . (empty ($vsconfig) ? 'NULL,' : "'${vsconfig}', ") .
		'rsconfig = ' . (empty ($rsconfig) ? 'NULL' : "'${rsconfig}'") .
		" where id = ${vsid} limit 1";
	$result = $dbxlink->exec ($query);
	if ($result === NULL)
		return FALSE;
	else
		return TRUE;
}

// Return the list of virtual services, indexed by vs_id.
// Each record will be shown with its basic info plus RS pools counter.
function getVSList ($tagfilter = array(), $tfmode = 'any')
{
	$whereclause = getWhereClause ($tagfilter);
	$query = "select vs.id, inet_ntoa(vip) as vip, vport, proto, vs.name, vs.vsconfig, vs.rsconfig, count(rspool_id) as poolcount " .
		"from IPv4VS as vs left join IPv4LB as lb on vs.id = lb.vs_id " .
		"left join TagStorage on vs.id = TagStorage.entity_id and entity_realm = 'ipv4vs' " . 
		"where true ${whereclause} group by vs.id order by vs.vip, proto, vport";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array ();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		foreach (array ('vip', 'vport', 'proto', 'name', 'vsconfig', 'rsconfig', 'poolcount') as $cname)
			$ret[$row['id']][$cname] = $row[$cname];
	$result->closeCursor();
	return $ret;
}

// Return the list of RS pool, indexed by pool id.
function getRSPoolList ($tagfilter = array(), $tfmode = 'any')
{
	$whereclause = getWhereClause ($tagfilter);
	$query = "select pool.id, pool.name, count(rspool_id) as refcnt, pool.vsconfig, pool.rsconfig " .
		"from IPv4RSPool as pool left join IPv4LB as lb on pool.id = lb.rspool_id " .
		"left join TagStorage on pool.id = TagStorage.entity_id and entity_realm = 'ipv4rspool' " .
		"where true ${whereclause} group by pool.id order by pool.name, pool.id";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array ();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		foreach (array ('name', 'refcnt', 'vsconfig', 'rsconfig') as $cname)
			$ret[$row['id']][$cname] = $row[$cname];
	$result->closeCursor();
	return $ret;
}

function loadThumbCache ($rack_id = 0)
{
	$ret = NULL;
	$query = "select thumb_data from Rack where id = ${rack_id} and thumb_data is not null limit 1";
	$result = useSelectBlade ($query, __FUNCTION__);
	$row = $result->fetch (PDO::FETCH_ASSOC);
	if ($row)
		$ret = base64_decode ($row['thumb_data']);
	$result->closeCursor();
	return $ret;
}

function saveThumbCache ($rack_id = 0, $cache = NULL)
{
	global $dbxlink;
	if ($rack_id == 0 or $cache == NULL)
	{
		showError ('Invalid arguments', __FUNCTION__);
		return;
	}
	$data = base64_encode ($cache);
	$query = "update Rack set thumb_data = '${data}' where id = ${rack_id} limit 1";
	$result = $dbxlink->exec ($query);
}

function resetThumbCache ($rack_id = 0)
{
	global $dbxlink;
	if ($rack_id == 0)
	{
		showError ('Invalid argument', __FUNCTION__);
		return;
	}
	$query = "update Rack set thumb_data = NULL where id = ${rack_id} limit 1";
	$result = $dbxlink->exec ($query);
}

// Return the list of attached RS pools for the given object. As long as we have
// the LB-VS UNIQUE in IPv4LB table, it is Ok to key returned records
// by vs_id, because there will be only one RS pool listed for each VS of the
// current object.
function getRSPoolsForObject ($object_id = 0)
{
	if ($object_id <= 0)
	{
		showError ('Invalid object_id', __FUNCTION__);
		return NULL;
	}
	$query = 'select vs_id, inet_ntoa(vip) as vip, vport, proto, vs.name, pool.id as pool_id, ' .
		'pool.name as pool_name, count(rsip) as rscount, lb.vsconfig, lb.rsconfig from ' .
		'IPv4LB as lb inner join IPv4RSPool as pool on lb.rspool_id = pool.id ' .
		'inner join IPv4VS as vs on lb.vs_id = vs.id ' .
		'left join IPv4RS as rs on lb.rspool_id = rs.rspool_id ' .
		"where lb.object_id = ${object_id} " .
		'group by lb.rspool_id, lb.vs_id order by vs.vip, vport, proto, pool.name';
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array ();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		foreach (array ('vip', 'vport', 'proto', 'name', 'pool_id', 'pool_name', 'rscount', 'vsconfig', 'rsconfig') as $cname)
			$ret[$row['vs_id']][$cname] = $row[$cname];
	$result->closeCursor();
	return $ret;
}

function commitCreateRSPool ($name = '', $vsconfig = '', $rsconfig = '', $taglist = array())
{
	if (empty ($name))
		return __FUNCTION__ . ': invalid arguments';
	if (!useInsertBlade
	(
		'IPv4RSPool',
		array
		(
			'name' => (empty ($name) ? 'NULL' : "'${name}'"),
			'vsconfig' => (empty ($vsconfig) ? 'NULL' : "'${vsconfig}'"),
			'rsconfig' => (empty ($rsconfig) ? 'NULL' : "'${rsconfig}'")
		)
	))
		return __FUNCTION__ . ': SQL insertion failed';
	return produceTagsForLastRecord ('ipv4rspool', $taglist);
}

function commitDeleteRSPool ($pool_id = 0)
{
	global $dbxlink;
	if ($pool_id <= 0)
		return FALSE;
	return useDeleteBlade ('IPv4RSPool', 'id', $pool_id) && destroyTagsForEntity ('ipv4rspool', $pool_id);
}

function commitUpdateRSPool ($pool_id = 0, $name = '', $vsconfig = '', $rsconfig = '')
{
	if ($pool_id <= 0)
	{
		showError ('Invalid arg', __FUNCTION__);
		die;
	}
	global $dbxlink;
	$query = "update IPv4RSPool set " .
		'name = ' . (empty ($name) ? 'NULL,' : "'${name}', ") .
		'vsconfig = ' . (empty ($vsconfig) ? 'NULL,' : "'${vsconfig}', ") .
		'rsconfig = ' . (empty ($rsconfig) ? 'NULL' : "'${rsconfig}'") .
		" where id = ${pool_id} limit 1";
	$result = $dbxlink->exec ($query);
	if ($result === NULL)
		return FALSE;
	elseif ($result != 1)
		return FALSE;
	else
		return TRUE;
}

function getRSList ()
{
	$query = "select id, inservice, inet_ntoa(rsip) as rsip, rsport, rspool_id, rsconfig " .
		"from IPv4RS order by rspool_id, IPv4RS.rsip, rsport";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array ();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		foreach (array ('inservice', 'rsip', 'rsport', 'rspool_id', 'rsconfig') as $cname)
			$ret[$row['id']][$cname] = $row[$cname];
	$result->closeCursor();
	return $ret;
}

// Return the list of all currently configured load balancers with their pool count.
function getLBList ()
{
	$query = "select object_id, count(rspool_id) as poolcount " .
		"from IPv4LB group by object_id order by object_id";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array ();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$ret[$row['object_id']] = $row['poolcount'];
	$result->closeCursor();
	return $ret;
}

// For the given object return: it vsconfig/rsconfig; the list of RS pools
// attached (each with vsconfig/rsconfig in turn), each with the list of
// virtual services terminating the pool. Each pool also lists all real
// servers with rsconfig.
function getSLBConfig ($object_id)
{
	if ($object_id <= 0)
	{
		showError ('Invalid arg', __FUNCTION__);
		return NULL;
	}
	$ret = array();
	$query = 'select vs_id, inet_ntoa(vip) as vip, vport, proto, vs.name as vs_name, ' .
		'vs.vsconfig as vs_vsconfig, vs.rsconfig as vs_rsconfig, ' .
		'lb.vsconfig as lb_vsconfig, lb.rsconfig as lb_rsconfig, pool.id as pool_id, pool.name as pool_name, ' .
		'pool.vsconfig as pool_vsconfig, pool.rsconfig as pool_rsconfig, ' .
		'rs.id as rs_id, inet_ntoa(rsip) as rsip, rsport, rs.rsconfig as rs_rsconfig from ' .
		'IPv4LB as lb inner join IPv4RSPool as pool on lb.rspool_id = pool.id ' .
		'inner join IPv4VS as vs on lb.vs_id = vs.id ' .
		'inner join IPv4RS as rs on lb.rspool_id = rs.rspool_id ' .
		"where lb.object_id = ${object_id} and rs.inservice = 'yes' " .
		"order by vs.vip, vport, proto, pool.name, rs.rsip, rs.rsport";
	$result = useSelectBlade ($query, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$vs_id = $row['vs_id'];
		if (!isset ($ret[$vs_id]))
		{
			foreach (array ('vip', 'vport', 'proto', 'vs_name', 'vs_vsconfig', 'vs_rsconfig', 'lb_vsconfig', 'lb_rsconfig', 'pool_vsconfig', 'pool_rsconfig', 'pool_id', 'pool_name') as $c)
				$ret[$vs_id][$c] = $row[$c];
			$ret[$vs_id]['rslist'] = array();
		}
		foreach (array ('rsip', 'rsport', 'rs_rsconfig') as $c)
			$ret[$vs_id]['rslist'][$row['rs_id']][$c] = $row[$c];
	}
	$result->closeCursor();
	return $ret;
}

function commitSetInService ($rs_id = 0, $inservice = '')
{
	if ($rs_id <= 0 or empty ($inservice))
	{
		showError ('Invalid args', __FUNCTION__);
		return NULL;
	}
	global $dbxlink;
	$query = "update IPv4RS set inservice = '${inservice}' where id = ${rs_id} limit 1";
	$result = $dbxlink->exec ($query);
	if ($result === NULL)
		return FALSE;
	elseif ($result != 1)
		return FALSE;
	else
		return TRUE;
}

function executeAutoPorts ($object_id = 0, $type_id = 0)
{
	if ($object_id == 0 or $type_id == 0)
	{
		showError ('Invalid arguments', __FUNCTION__);
		die;
	}
	$ret = TRUE;
	foreach (getAutoPorts ($type_id) as $autoport)
		$ret = $ret and '' == commitAddPort ($object_id, $autoport['name'], $autoport['type'], '', '');
	return $ret;
}

// Return only implicitly listed tags, the rest of the chain will be
// generated/deducted later at higher levels.
// Result is a chain: randomly indexed taginfo list.
function loadEntityTags ($entity_realm = '', $entity_id = 0)
{
	if ($entity_realm == '' or $entity_id <= 0)
	{
		showError ('Invalid or missing arguments', __FUNCTION__);
		return NULL;
	}
	$ret = array();
	$query = "select tt.id, tag from " .
		"TagStorage as ts inner join TagTree as tt on ts.tag_id = tt.id " .
		"where entity_realm = '${entity_realm}' and entity_id = ${entity_id} " .
		"order by tt.tag";
	$result = useSelectBlade ($query, __FUNCTION__);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$ret[$row['id']] = $row;
	$result->closeCursor();
	return getExplicitTagsOnly ($ret);
}

function loadFileTags ($id)
{
	return loadEntityTags ('file', $id);
}

function loadRackObjectTags ($id)
{
	return loadEntityTags ('object', $id);
}

function loadIPv4PrefixTags ($id)
{
	return loadEntityTags ('ipv4net', $id);
}

function loadRackTags ($id)
{
	return loadEntityTags ('rack', $id);
}

function loadIPv4VSTags ($id)
{
	return loadEntityTags ('ipv4vs', $id);
}

function loadIPv4RSPoolTags ($id)
{
	return loadEntityTags ('ipv4rspool', $id);
}

function loadUserTags ($user_id)
{
	return loadEntityTags ('user', $user_id);
}

// Return a tag chain with all DB tags on it.
function getTagList ()
{
	$ret = array();
	$query = "select id, parent_id, tag, entity_realm as realm, count(entity_id) as refcnt " .
		"from TagTree left join TagStorage on id = tag_id " .
		"group by id, entity_realm order by tag";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ci = 0; // Collation index. The resulting rows are ordered according to default collation,
	// which is utf8_general_ci for UTF-8.
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		if (!isset ($ret[$row['id']]))
			$ret[$row['id']] = array
			(
				'id' => $row['id'],
				'tag' => $row['tag'],
				'ci' => $ci++,
				'parent_id' => $row['parent_id'],
				'refcnt' => array()
			);
		if ($row['realm'])
			$ret[$row['id']]['refcnt'][$row['realm']] = $row['refcnt'];
	}
	$result->closeCursor();
	return $ret;
}

function commitCreateTag ($tagname = '', $parent_id = 0)
{
	if ($tagname == '' or $parent_id === 0)
		return "Invalid args to " . __FUNCTION__;
	$result = useInsertBlade
	(
		'TagTree',
		array
		(
			'tag' => "'${tagname}'",
			'parent_id' => $parent_id
		)
	);
	if ($result)
		return '';
	else
		return "SQL query failed in " . __FUNCTION__;
}

function commitDestroyTag ($tagid = 0)
{
	if ($tagid == 0)
		return 'Invalid arg to ' . __FUNCTION__;
	if (useDeleteBlade ('TagTree', 'id', $tagid))
		return '';
	else
		return 'useDeleteBlade() failed in ' . __FUNCTION__;
}

function commitUpdateTag ($tag_id, $tag_name, $parent_id)
{
	if ($parent_id == 0)
		$parent_id = 'NULL';
	global $dbxlink;
	$query = "update TagTree set tag = '${tag_name}', parent_id = ${parent_id} " .
		"where id = ${tag_id} limit 1";
	$result = $dbxlink->exec ($query);
	if ($result === NULL)
		return 'SQL query failed in ' . __FUNCTION__;
	return '';
}

// Drop the whole chain stored.
function destroyTagsForEntity ($entity_realm, $entity_id)
{
	global $dbxlink;
	$query = "delete from TagStorage where entity_realm = '${entity_realm}' and entity_id = ${entity_id}";
	$result = $dbxlink->exec ($query);
	if ($result === NULL)
		return FALSE;
	else
		return TRUE;
}

// Drop only one record. This operation doesn't involve retossing other tags, unlike when adding.
function deleteTagForEntity ($entity_realm, $entity_id, $tag_id)
{
	global $dbxlink;
	$query = "delete from TagStorage where entity_realm = '${entity_realm}' and entity_id = ${entity_id} and tag_id = ${tag_id}";
	$result = $dbxlink->exec ($query);
	if ($result === NULL)
		return FALSE;
	else
		return TRUE;
}

// Push a record into TagStorage unconditionally.
function addTagForEntity ($realm = '', $entity_id, $tag_id)
{
	if (empty ($realm))
		return FALSE;
	return useInsertBlade
	(
		'TagStorage',
		array
		(
			'entity_realm' => "'${realm}'",
			'entity_id' => $entity_id,
			'tag_id' => $tag_id,
		)
	);
}

// Add records into TagStorage, if this makes sense (IOW, they don't appear
// on the implicit list already). Then remove any other records, which
// appear on the "implicit" side of the chain. This will make sure,
// that both the tag base is still minimal and all requested tags appear on
// the resulting tag chain.
// Return TRUE, if any changes were committed.
function rebuildTagChainForEntity ($realm, $entity_id, $extrachain = array())
{
	// Put the current explicit sub-chain into a buffer and merge all tags from
	// the extra chain, which aren't there yet.
	$newchain = $oldchain = loadEntityTags ($realm, $entity_id);
	foreach ($extrachain as $extratag)
		if (!tagOnChain ($extratag, $newchain))
			$newchain[] = $extratag;
	// Then minimize the working buffer and check if it differs from the original
	// chain we started with. If it is so, save the work and signal the upper layer.
	$newchain = getExplicitTagsOnly ($newchain);
	if (tagChainCmp ($oldchain, $newchain))
	{
		destroyTagsForEntity ($realm, $entity_id);
		foreach ($newchain as $taginfo)
			addTagForEntity ($realm, $entity_id, $taginfo['id']);
		return TRUE;
	}
	return FALSE;
}

// Presume, that the target record has no tags attached.
function produceTagsForLastRecord ($realm, $tagidlist, $last_insert_id = 0)
{
	if (!count ($tagidlist))
		return '';
	if (!$last_insert_id)
		$last_insert_id = lastInsertID();
	$errcount = 0;
	foreach (getExplicitTagsOnly (buildTagChainFromIds ($tagidlist)) as $taginfo)
		if (addTagForEntity ($realm, $last_insert_id, $taginfo['id']) == FALSE)
			$errcount++;	
	if (!$errcount)
		return '';
	else
		return "Experienced ${errcount} errors adding tags in realm '${realm}' for entity ID == ${last_insert_id}";
}

function createIPv4Prefix ($range = '', $name = '', $is_bcast = FALSE, $taglist = array())
{
	// $range is in x.x.x.x/x format, split into ip/mask vars
	$rangeArray = explode('/', $range);
	if (count ($rangeArray) != 2)
		return "Invalid IPv4 prefix '${range}'";
	$ip = $rangeArray[0];
	$mask = $rangeArray[1];

	if (empty ($ip) or empty ($mask))
		return "Invalid IPv4 prefix '${range}'";
	$ipL = ip2long($ip);
	$maskL = ip2long($mask);
	if ($ipL == -1 || $ipL === FALSE)
		return 'Bad IPv4 address';
	if ($mask < 32 && $mask > 0)
		$maskL = $mask;
	else
	{
		$maskB = decbin($maskL);
		if (strlen($maskB)!=32)
			return 'Invalid netmask';
		$ones=0;
		$zeroes=FALSE;
		foreach( str_split ($maskB) as $digit)
		{
			if ($digit == '0')
				$zeroes = TRUE;
			if ($digit == '1')
			{
				$ones++;
				if ($zeroes == TRUE)
					return 'Invalid netmask';
			}
		}
		$maskL = $ones;
	}
	$binmask = binMaskFromDec($maskL);
	$ipL = $ipL & $binmask;
	$result = useInsertBlade
	(
		'IPv4Network',
		array
		(
			'ip' => sprintf ('%u', $ipL),
			'mask' => "'${maskL}'",
			'name' => "'${name}'"
		)
	);
	if ($result != TRUE)
		return "Could not add ${range} (already exists?).";

	if ($is_bcast and $maskL < 31)
	{
		$network_addr = long2ip ($ipL);
		$broadcast_addr = long2ip ($ipL | binInvMaskFromDec ($maskL));
		updateAddress ($network_addr, 'network', 'yes');
		updateAddress ($broadcast_addr, 'broadcast', 'yes');
	}
	return produceTagsForLastRecord ('ipv4net', $taglist);
}

// FIXME: This function doesn't wipe relevant records from IPv4Address table.
function destroyIPv4Prefix ($id = 0)
{
	if ($id <= 0)
		return __FUNCTION__ . ': Invalid IPv4 prefix ID';
	if (!useDeleteBlade ('IPv4Network', 'id', $id))
		return __FUNCTION__ . ': SQL query #1 failed';
	if (!destroyTagsForEntity ('ipv4net', $id))
		return __FUNCTION__ . ': SQL query #2 failed';
	return '';
}

function loadScript ($name)
{
	$result = useSelectBlade ("select script_text from Script where script_name = '${name}'");
	$row = $result->fetch (PDO::FETCH_NUM);
	if ($row !== FALSE)
		return $row[0];
	else
		return NULL;
}

function saveScript ($name, $text)
{
	if (empty ($name))
	{
		showError ('Invalid argument');
		return FALSE;
	}
	// delete regardless of existence
	useDeleteBlade ('Script', 'script_name', "'${name}'");
	return useInsertBlade
	(
		'Script',
		array
		(
			'script_name' => "'${name}'",
			'script_text' => "'${text}'"
		)
	);
}

function saveUserPassword ($user_id, $newp)
{
	$newhash = hash (PASSWORD_HASH, $newp);
	$query = "update UserAccount set user_password_hash = ${newhash} where user_id = ${user_id} limit 1";
}

function objectIsPortless ($id = 0)
{
	if ($id <= 0)
	{
		showError ('Invalid argument', __FUNCTION__);
		return;
	}
	if (($result = useSelectBlade ("select count(id) from Port where object_id = ${id}", __FUNCTION__)) == NULL) 
	{
		showError ('SQL query failed', __FUNCTION__);
		return;
	}
	$row = $result->fetch (PDO::FETCH_NUM);
	$count = $row[0];
	$result->closeCursor();
	unset ($result);
	return $count === '0';
}

function recordExists ($id = 0, $realm = 'object')
{
	if ($id <= 0)
		return FALSE;
	$table = array
	(
		'object' => 'RackObject',
		'ipv4net' => 'IPv4Network',
		'user' => 'UserAccount',
	);
	$idcol = array
	(
		'object' => 'id',
		'ipv4net' => 'id',
		'user' => 'user_id',
	);
	$query = 'select count(*) from ' . $table[$realm] . ' where ' . $idcol[$realm] . ' = ' . $id;
	if (($result = useSelectBlade ($query, __FUNCTION__)) == NULL) 
	{
		showError ('SQL query failed', __FUNCTION__);
		return FALSE;
	}
	$row = $result->fetch (PDO::FETCH_NUM);
	$count = $row[0];
	$result->closeCursor();
	unset ($result);
	return $count === '1';
}

function tagExistsInDatabase ($tname)
{
	$result = useSelectBlade ("select count(*) from TagTree where lower(tag) = lower('${tname}')");
	$row = $result->fetch (PDO::FETCH_NUM);
	$count = $row[0];
	$result->closeCursor();
	unset ($result);
	return $count !== '0';
}

function newPortForwarding ($object_id, $localip, $localport, $remoteip, $remoteport, $proto, $description)
{
	if (NULL === getIPv4AddressNetworkId ($localip))
		return "$localip: Non existant ip";
	if (NULL === getIPv4AddressNetworkId ($localip))
		return "$remoteip: Non existant ip";
	if ( ($localport <= 0) or ($localport >= 65536) )
		return "$localport: invaild port";
	if ( ($remoteport <= 0) or ($remoteport >= 65536) )
		return "$remoteport: invaild port";

	$result = useInsertBlade
	(
		'IPv4NAT',
		array
		(
			'object_id' => $object_id,
			'localip' => "INET_ATON('${localip}')",
			'remoteip' => "INET_ATON('$remoteip')",
			'localport' => $localport,
			'remoteport' => $remoteport,
			'proto' => "'${proto}'",
			'description' => "'${description}'",
		)
	);
	if ($result)
		return '';
	else
		return __FUNCTION__ . ': Failed to insert the rule.';
}

function deletePortForwarding ($object_id, $localip, $localport, $remoteip, $remoteport, $proto)
{
	global $dbxlink;

	$query =
		"delete from IPv4NAT where object_id='$object_id' and localip=INET_ATON('$localip') and remoteip=INET_ATON('$remoteip') and localport='$localport' and remoteport='$remoteport' and proto='$proto'";
	$result = $dbxlink->exec ($query);
	return '';
}

function updatePortForwarding ($object_id, $localip, $localport, $remoteip, $remoteport, $proto, $description)
{
	global $dbxlink;

	$query =
		"update IPv4NAT set description='$description' where object_id='$object_id' and localip=INET_ATON('$localip') and remoteip=INET_ATON('$remoteip') and localport='$localport' and remoteport='$remoteport' and proto='$proto'";
	$result = $dbxlink->exec ($query);
	return '';
}

function getNATv4ForObject ($object_id)
{
	$ret = array();
	$ret['out'] = array();
	$ret['in'] = array();
	$query =
		"select ".
		"proto, ".
		"INET_NTOA(localip) as localip, ".
		"localport, ".
		"INET_NTOA(remoteip) as remoteip, ".
		"remoteport, ".
		"ipa1.name as local_addr_name, " .
		"ipa2.name as remote_addr_name, " .
		"description ".
		"from IPv4NAT ".
		"left join IPv4Address as ipa1 on IPv4NAT.localip = ipa1.ip " .
		"left join IPv4Address as ipa2 on IPv4NAT.remoteip = ipa2.ip " .
		"where object_id='$object_id' ".
		"order by localip, localport, proto, remoteip, remoteport";
	$result = useSelectBlade ($query, __FUNCTION__);
	$count=0;
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		foreach (array ('proto', 'localport', 'localip', 'remoteport', 'remoteip', 'description', 'local_addr_name', 'remote_addr_name') as $cname)
			$ret['out'][$count][$cname] = $row[$cname];
		$count++;
	}
	$result->closeCursor();
	unset ($result);

	$query =
		"select ".
		"proto, ".
		"INET_NTOA(localip) as localip, ".
		"localport, ".
		"INET_NTOA(remoteip) as remoteip, ".
		"remoteport, ".
		"IPv4NAT.object_id as object_id, ".
		"RackObject.name as object_name, ".
		"description ".
		"from ((IPv4NAT join IPv4Allocation on remoteip=IPv4Allocation.ip) join RackObject on IPv4NAT.object_id=RackObject.id) ".
		"where IPv4Allocation.object_id='$object_id' ".
		"order by remoteip, remoteport, proto, localip, localport";
	$result = useSelectBlade ($query, __FUNCTION__);
	$count=0;
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		foreach (array ('proto', 'localport', 'localip', 'remoteport', 'remoteip', 'object_id', 'object_name', 'description') as $cname)
			$ret['in'][$count][$cname] = $row[$cname];
		$count++;
	}
	$result->closeCursor();

	return $ret;
}

// This function performs search and then calculates score for each result.
// Given previous search results in $objects argument, it adds new results
// to the array and updates score for existing results, if it is greater than
// existing score.
function mergeSearchResults (&$objects, $terms, $fieldname)
{
	global $dbxlink;
	$query =
		"select ro.name, label, asset_no, barcode, ro.id, dict_key as objtype_id, " .
		"dict_value as objtype_name, asset_no from RackObject as ro inner join Dictionary " .
		"on objtype_id = dict_key join Chapter on Chapter.id = Dictionary.chapter_id where Chapter.name = 'RackObjectType' and ";
	$count = 0;
	foreach (explode (' ', $terms) as $term)
	{
		if ($count) $query .= ' or ';
		$query .= "ro.${fieldname} like '%$term%'";
		$count++;
	}
	$query .= " order by ${fieldname}";
	$result = useSelectBlade ($query, __FUNCTION__);
// FIXME: this dead call was executed 4 times per 1 object search!
//	$typeList = getObjectTypeList();
	$clist = array ('id', 'name', 'label', 'asset_no', 'barcode', 'objtype_id', 'objtype_name');
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		foreach ($clist as $cname)
			$object[$cname] = $row[$cname];
		$object['score'] = 0;
		$object['dname'] = displayedName ($object);
		unset ($object['objtype_id']);
		foreach (explode (' ', $terms) as $term)
			if (strstr ($object['name'], $term))
				$object['score'] += 1;
		unset ($object['name']);
		if (!isset ($objects[$row['id']]))
			$objects[$row['id']] = $object;
		elseif ($objects[$row['id']]['score'] < $object['score'])
			$objects[$row['id']]['score'] = $object['score'];
	}
	return $objects;
}

function getLostIPv4Addresses ()
{
	dragon();
}

// File-related functions
function getAllFiles ()
{
	$query = "SELECT id, name, type, size, ctime, mtime, atime, comment FROM File ORDER BY name";
	$result = useSelectBlade ($query, __FUNCTION__);
	$ret=array();
	$count=0;
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$ret[$count]['id'] = $row['id'];
		$ret[$count]['name'] = $row['name'];
		$ret[$count]['type'] = $row['type'];
		$ret[$count]['size'] = $row['size'];
		$ret[$count]['ctime'] = $row['ctime'];
		$ret[$count]['mtime'] = $row['mtime'];
		$ret[$count]['atime'] = $row['atime'];
		$ret[$count]['comment'] = $row['comment'];
		$count++;
	}
	$result->closeCursor();
	return $ret;
}

// Return a list of files which are not linked to the specified record. This list
// will be used by printSelect().
function getAllUnlinkedFiles ($entity_type = NULL, $entity_id = 0)
{
	if ($entity_type == NULL || $entity_id == 0)
	{
		showError ('Invalid parameters', __FUNCTION__);
		return NULL;
	}
	global $dbxlink;
	$sql =
		'SELECT id, name FROM File ' .
		'WHERE id NOT IN (SELECT file_id FROM FileLink WHERE entity_type = ? AND entity_id = ?) ' .
		'ORDER BY name, id';
	$query = $dbxlink->prepare($sql);
	$query->bindParam(1, $entity_type);
	$query->bindParam(2, $entity_id);
	$query->execute();
	$ret=array();
	while ($row = $query->fetch (PDO::FETCH_ASSOC))
		$ret[$row['id']] = $row['name'];
	return $ret;
}

// Return a filtered, detailed file list.  Used on the main Files listing page.
function getFileList ($entity_type = NULL, $tagfilter = array(), $tfmode = 'any')
{
	$whereclause = getWhereClause ($tagfilter);

	if ($entity_type == 'no_links')
		$whereclause .= 'AND File.id NOT IN (SELECT file_id FROM FileLink) ';
	elseif ($entity_type != 'all')
		$whereclause .= "AND entity_type = '${entity_type}' ";

	$query =
		'SELECT File.id, name, type, size, ctime, mtime, atime, comment ' .
		'FROM File ' .
		'LEFT JOIN FileLink ' .
		'ON File.id = FileLink.file_id ' .
		'LEFT JOIN TagStorage ' .
		"ON File.id = TagStorage.entity_id AND entity_realm = 'file' " .
		'WHERE size >= 0 ' .
		$whereclause .
		'ORDER BY name';

	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		foreach (array (
			'id',
			'name',
			'type',
			'size',
			'ctime',
			'mtime',
			'atime',
			'comment'
			) as $cname)
			$ret[$row['id']][$cname] = $row[$cname];
	}
	$result->closeCursor();
	return $ret;
}

function getFilesOfEntity ($entity_type = NULL, $entity_id = 0)
{
	if ($entity_type == NULL || $entity_id == 0)
	{
		showError ('Invalid parameters', __FUNCTION__);
		return NULL;
	}
	global $dbxlink;
	$sql =
		'SELECT FileLink.file_id, FileLink.id AS link_id, name, type, size, ctime, mtime, atime, comment ' .
		'FROM FileLink LEFT JOIN File ON FileLink.file_id = File.id ' .
		'WHERE FileLink.entity_type = ? AND FileLink.entity_id = ? ORDER BY name';
	$query  = $dbxlink->prepare($sql);
	$query->bindParam(1, $entity_type);
	$query->bindParam(2, $entity_id);
	$query->execute();
	$ret = array();
	while ($row = $query->fetch (PDO::FETCH_ASSOC))
		$ret[$row['file_id']] = array (
			'id' => $row['file_id'],
			'link_id' => $row['link_id'],
			'name' => $row['name'],
			'type' => $row['type'],
			'size' => $row['size'],
			'ctime' => $row['ctime'],
			'mtime' => $row['mtime'],
			'atime' => $row['atime'],
			'comment' => $row['comment'],
		);
	return $ret;
}

function getFile ($file_id = 0)
{
	if ($file_id == 0)
	{
		showError ('Invalid file_id', __FUNCTION__);
		return NULL;
	}
	global $dbxlink;
	$query = $dbxlink->prepare('SELECT * FROM File WHERE id = ?');
	$query->bindParam(1, $file_id);
	$query->execute();
	if (($row = $query->fetch (PDO::FETCH_ASSOC)) == NULL)
	{
		showError ('Query succeeded, but returned no data', __FUNCTION__);
		$ret = NULL;
	}
	else
	{
		$ret = array();
		$ret['id'] = $row['id'];
		$ret['name'] = $row['name'];
		$ret['type'] = $row['type'];
		$ret['size'] = $row['size'];
		$ret['ctime'] = $row['ctime'];
		$ret['mtime'] = $row['mtime'];
		$ret['atime'] = $row['atime'];
		$ret['contents'] = $row['contents'];
		$ret['comment'] = $row['comment'];
		$query->closeCursor();

		// Someone accessed this file, update atime
		$q_atime = $dbxlink->prepare('UPDATE File SET atime = ? WHERE id = ?');
		$q_atime->bindParam(1, date('YmdHis'));
		$q_atime->bindParam(2, $file_id);
		$q_atime->execute();
	}
	return $ret;
}

function getFileInfo ($file_id = 0)
{
	if ($file_id == 0)
	{
		showError ('Invalid file_id', __FUNCTION__);
		return NULL;
	}
	global $dbxlink;
	$query = $dbxlink->prepare('SELECT id, name, type, size, ctime, mtime, atime, comment FROM File WHERE id = ?');
	$query->bindParam(1, $file_id);
	$query->execute();
	if (($row = $query->fetch (PDO::FETCH_ASSOC)) == NULL)
	{
		showError ('Query succeeded, but returned no data', __FUNCTION__);
		$ret = NULL;
	}
	else
	{
		$ret = array();
		$ret['id'] = $row['id'];
		$ret['name'] = $row['name'];
		$ret['type'] = $row['type'];
		$ret['size'] = $row['size'];
		$ret['ctime'] = $row['ctime'];
		$ret['mtime'] = $row['mtime'];
		$ret['atime'] = $row['atime'];
		$ret['comment'] = $row['comment'];
		$query->closeCursor();
	}
	return $ret;
}

function getFileLinks ($file_id = 0)
{
	if ($file_id <= 0)
	{
		showError ('Invalid file_id', __FUNCTION__);
		return NULL;
	}

	global $dbxlink;
	$query = $dbxlink->prepare('SELECT * FROM FileLink WHERE file_id = ? ORDER BY entity_id');
	$query->bindParam(1, $file_id);
	$query->execute();
	$rows = $query->fetchAll (PDO::FETCH_ASSOC);
	$ret = array();
	foreach ($rows as $row)
	{
		// get info of the parent
		switch ($row['entity_type'])
		{
			case 'ipv4net':
				$page = 'ipv4net';
				$id_name = 'id';
				$parent = getIPv4NetworkInfo($row['entity_id']);
				$name = sprintf("%s (%s/%s)", $parent['name'], $parent['ip'], $parent['mask']);
				break;
			case 'ipv4rspool':
				$page = 'ipv4rspool';
				$id_name = 'pool_id';
				$parent = getRSPoolInfo($row['entity_id']);
				$name = $parent['name'];
				break;
			case 'ipv4vs':
				$page = 'ipv4vs';
				$id_name = 'vs_id';
				$parent = getVServiceInfo($row['entity_id']);
				$name = $parent['name'];
				break;
			case 'object':
				$page = 'object';
				$id_name = 'object_id';
				$parent = getObjectInfo($row['entity_id']);
				$name = $parent['name'];
				break;
			case 'rack':
				$page = 'rack';
				$id_name = 'rack_id';
				$parent = getRackData($row['entity_id']);
				$name = $parent['name'];
				break;
			case 'user':
				$page = 'user';
				$id_name = 'user_id';
				global $accounts;
				foreach ($accounts as $account)
					if ($account['user_id'] == $row['entity_id'])
						$name = $account['user_name'];
				break;
		}

		// name needs to have some value for hrefs to work
        if (empty($name))
			$name = sprintf("[Unnamed %s]", formatEntityName($row['entity_type']));

		$ret[$row['id']] = array(
				'page' => $page,
				'id_name' => $id_name,
				'entity_type' => $row['entity_type'],
				'entity_id' => $row['entity_id'],
				'name' => $name
		);
	}
	return $ret;
}

// Return list of possible file parents along with the number of children.
// Used on main Files listing page.
function getFileLinkInfo ()
{
	global $dbxlink;
	$query = 'SELECT entity_type, COUNT(*) AS count FROM FileLink GROUP BY entity_type';

	$result = useSelectBlade ($query, __FUNCTION__);
	$ret = array();
	$ret[0] = array ('entity_type' => 'all', 'name' => 'ALL files');
	$clist = array ('entity_type', 'name', 'count');
	$total = 0;
	$i=2;
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		if ($row['count'] > 0)
		{
			$total += $row['count'];
			$row['name'] = formatEntityName ($row['entity_type']);
			foreach ($clist as $cname)
				$ret[$i][$cname] = $row[$cname];
				$i++;
		}
	$result->closeCursor();

	// Find number of files without any linkage
	$linkless_sql =
		'SELECT COUNT(*) ' .
		'FROM File ' .
		'WHERE id NOT IN (SELECT file_id FROM FileLink)';
	$q_linkless = useSelectBlade ($linkless_sql, __FUNCTION__);
	$ret[1] = array ('entity_type' => 'no_links', 'name' => 'Files w/no links', 'count' => $q_linkless->fetchColumn ());
	$q_linkless->closeCursor();

	// Find total number of files
	$total_sql = 'SELECT COUNT(*) FROM File';
	$q_total = useSelectBlade ($total_sql, __FUNCTION__);
	$ret[0]['count'] = $q_total->fetchColumn ();
	$q_total->closeCursor();

	ksort($ret);
	return $ret;
}

function commitAddFile ($name, $type, $size, $contents, $comment)
{
	$now = date('YmdHis');

	global $dbxlink;
	$query  = $dbxlink->prepare('INSERT INTO File (name, type, size, ctime, mtime, atime, contents, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
	$query->bindParam(1, $name);
	$query->bindParam(2, $type);
	$query->bindParam(3, $size);
	$query->bindParam(4, $now);
	$query->bindParam(5, $now);
	$query->bindParam(6, $now);
	$query->bindParam(7, $contents, PDO::PARAM_LOB);
	$query->bindParam(8, $comment);

	$result = $query->execute();


	if ($result)
		return '';
	else
		return 'commitAddFile: SQL query failed';
}

function commitLinkFile ($file_id, $entity_type, $entity_id)
{
	global $dbxlink;
	$query  = $dbxlink->prepare('INSERT INTO FileLink (file_id, entity_type, entity_id) VALUES (?, ?, ?)');
	$query->bindParam(1, $file_id);
	$query->bindParam(2, $entity_type);
	$query->bindParam(3, $entity_id);

	$result = $query->execute();

	if ($result)
		return '';
	else
		return 'commitLinkFile: SQL query failed';
}

function commitReplaceFile ($file_id = 0, $contents)
{
	if ($file_id == 0)
	{
		showError ('Not all required args are present.', __FUNCTION__);
		return FALSE;
	}

	global $dbxlink;
	$query = $dbxlink->prepare('UPDATE File SET mtime = NOW(), contents = ?, size = LENGTH(contents) WHERE id = ?');
	$query->bindParam(1, $contents, PDO::PARAM_LOB);
	$query->bindParam(2, $file_id);

	$result = $query->execute();
	if (!$result)
	{
		showError ('commitReplaceFile: SQL query failed', __FUNCTION__);
		return FALSE;
	}
	return '';
}

function commitUpdateFile ($file_id = 0, $new_name = '', $new_type = '', $new_comment = '')
{
	if ($file_id <= 0 or empty ($new_name) or empty ($new_type))
	{
		showError ('Not all required args are present.', __FUNCTION__);
		return FALSE;
	}
	global $dbxlink;
	$query = $dbxlink->prepare('UPDATE File SET name = ?, type = ?, comment = ? WHERE id = ?');
	$query->bindParam(1, $new_name);
	$query->bindParam(2, $new_type);
	$query->bindParam(3, $new_comment);
	$query->bindParam(4, $file_id);

	$result = $query->execute();
	if (!$result)
		return 'SQL query failed in ' . __FUNCTION__;
	return '';
}

function commitUnlinkFile ($link_id)
{
	if (useDeleteBlade ('FileLink', 'id', $link_id) != TRUE)
		return __FUNCTION__ . ': useDeleteBlade() failed';
	return '';
}

function commitDeleteFile ($file_id)
{
	if (useDeleteBlade ('File', 'id', $file_id) != TRUE)
		return __FUNCTION__ . ': useDeleteBlade() failed';
	return '';
}

function getChapterList ()
{
	$ret = array();
	$result = useSelectBlade ('select id as chapter_no, name as chapter_name from Chapter order by Chapter.name');
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
		$ret[$row['chapter_no']] = $row['chapter_name'];
	return $ret;
}

// Return file id by file name. There may be more, than one record in the database,
// so return only the first one, until it is fixed by appropriate UNIQUE key.
function findFileByName ($filename)
{
	$result = useSelectBlade ("select id from File where name = '${filename}' limit 1"); 
	if ($row = $result->fetch (PDO::FETCH_ASSOC))
		return $row['id'];
	else
		return NULL;
}

?>
