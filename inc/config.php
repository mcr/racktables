<?php
/*
 *
 * This file used to hold a collection of constants, variables and arrays,
 * which drived the way misc RackTables functions performed. Now most of
 * then have gone into the database, and there is perhaps a user interface
 * for changing them. This file now provides a couple of functions to
 * access the new config storage.
 *
 */


// Current code version is subject to change with each new release.
define ('CODE_VERSION', '0.14.6');

// The name of hash used to store account password hashes
// in the database. I think, we are happy with this one forever.
define ('PASSWORD_HASH', 'sha1');

function getConfigVar ($varname = '')
{
	global $configCache;
	// We assume the only point of cache init, and it is init.php. If it
	// has failed, we don't retry loading.
	if (!isset ($configCache))
	{
		showError ("Configuration cache is unavailable in getConfigVar()");
		die;
	}
	if ($varname == '')
	{
		showError ("Missing argument to getConfigVar()");
		die;
	}
	if (isset ($configCache[$varname]))
	{
		// Try casting to int, if possible.
		if ($configCache[$varname]['vartype'] == 'unit')
			return 0 + $configCache[$varname]['varvalue'];
		else
			return $configCache[$varname]['varvalue'];
	}
	return NULL;
}

function setConfigVar ($varname = '', $varvalue = '')
{
	global $configCache;
	if (!isset ($configCache))
	{
		showError ('Configuration cache is unavailable in setConfigVar()');
		die;
	}
	if (empty ($varname))
	{
		showError ("Empty argument to setConfigVar()");
		die;
	}
	// We don't operate on unknown data.
	if (!isset ($configCache[$varname]))
	{
		showError ("setConfigVar() doesn't know how to handle '${varname}'");
		die;
	}
	if (empty ($varvalue) && $configCache[$varname]['emptyok'] != 'yes')
	{
		showError ("'${varname}' is configured to take non-empty value. Perhaps there was a reason to do so.");
		die;
	}
	// Update cache only if the changes went into DB.
	if (storeConfigVar ($varname, $varvalue))
		$configCache[$varname]['varvalue'] = $varvalue;
}

?>
