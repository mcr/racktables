<?php
/*
*
*  This file implements generic navigation for RackTables.
*
*/

$page = array();
$tab = array();
$helptab = array();
$trigger = array();
$ophandler = array();
$tabhandler = array();

$page['index']['title'] = 'static_title';
$page['index']['handler'] = 'renderIndex';

$page['rackspace']['title'] = 'static_title';
$page['rackspace']['parent'] = 'index';
$tab['rackspace']['default'] = 'Browse';
$tab['rackspace']['history'] = 'History';
$tab['rackspace']['firstrow'] = 'Click me!';
$trigger['rackspace']['firstrow'] = 'trigger_emptyRackspace';
$tabhandler['rackspace']['default'] = 'renderRackspace';
$tabhandler['rackspace']['history'] = 'renderRackspaceHistory';
$tabhandler['rackspace']['firstrow'] = 'renderFirstRowForm';

$page['objects']['title'] = 'static_title';
$page['objects']['parent'] = 'index';
$tab['objects']['default'] = 'View';
$tab['objects']['newobj'] = 'Add an object';
$tab['objects']['newmulti'] = 'Add multiple objects';
$tabhandler['objects']['default'] = 'renderObjectGroupSummary';
$tabhandler['objects']['newobj'] = 'renderNewObjectForm';
$tabhandler['objects']['newmulti'] = 'renderAddMultipleObjectsForm';

$page['row']['title'] = 'dynamic_title_row';
$page['row']['bypass'] = 'row_id';
$page['row']['bypass_type'] = 'uint';
$page['row']['parent'] = 'rackspace';
$tab['row']['default'] = 'View';
$tab['row']['newrack'] = 'Add new rack';
$tabhandler['row']['default'] = 'renderRow';
$tabhandler['row']['newrack'] = 'renderNewRackForm';

$page['rack']['title'] = 'dynamic_title_rack';
$page['rack']['bypass'] = 'rack_id';
$page['rack']['bypass_type'] = 'uint';
$page['rack']['parent'] = 'row';
$tab['rack']['default'] = 'View';
$tab['rack']['edit'] = 'Properties';
$tab['rack']['design'] = 'Design';
$tab['rack']['problems'] = 'Problems';
$tabhandler['rack']['default'] = 'renderRackPage';
$tabhandler['rack']['edit'] = 'renderEditRackForm';
$tabhandler['rack']['design'] = 'renderRackDesign';
$tabhandler['rack']['problems'] = 'renderRackProblems';
$helptab['rack']['design'] = 'rackspace';
$helptab['rack']['problems'] = 'rackspace';

$page['objgroup']['title'] = 'dynamic_title_objgroup';
$page['objgroup']['handler'] = 'handler_objgroup';
$page['objgroup']['bypass'] = 'group_id';
$page['objgroup']['parent'] = 'objects';

$page['object']['title'] = 'dynamic_title_object';
$page['object']['bypass'] = 'object_id';
$page['object']['bypass_type'] = 'uint';
$page['object']['parent'] = 'objgroup';
$tab['object']['default'] = 'View';
$tab['object']['edit'] = 'Properties';
$tab['object']['rackspace'] = 'Rackspace';
$tab['object']['ports'] = 'Ports';
$tab['object']['network'] = 'IPv4';
$tab['object']['portfwrd'] = 'NATv4';
$tab['object']['switchvlans'] = 'Live VLANs';
$tab['object']['snmpportfinder'] = 'SNMP port finder';
$tab['object']['lvsconfig'] = 'LVS configuration';
$tabhandler['object']['default'] = 'renderRackObject';
$tabhandler['object']['edit'] = 'renderEditObjectForm';
$tabhandler['object']['rackspace'] = 'renderRackSpaceForObject';
$tabhandler['object']['ports'] = 'renderPortsForObject';
$tabhandler['object']['network'] = 'renderNetworkForObject';
$tabhandler['object']['portfwrd'] = 'renderIPAddressPortForwarding';
$tabhandler['object']['switchvlans'] = 'renderVLANMembership';
$tabhandler['object']['snmpportfinder'] = 'renderSNMPPortFinder';
$tabhandler['object']['lvsconfig'] = 'renderLVSConfig';
$helptab['object']['network'] = 'nets';
$helptab['object']['ports'] = 'ports';
$helptab['object']['portfwrd'] = 'nets';
$helptab['object']['rackspace'] = 'rackspace';
$trigger['object']['switchvlans'] = 'trigger_switchvlans';
$trigger['object']['snmpportfinder'] = 'trigger_snmpportfinder';
$trigger['object']['lvsconfig'] = 'trigger_lvsconfig';
$ophandler['object']['ports']['addPort'] = 'addPortForObject';
$ophandler['object']['ports']['delPort'] = 'delPortFromObject';
$ophandler['object']['ports']['editPort'] = 'editPortForObject';
$ophandler['object']['ports']['linkPort'] = 'linkPortForObject';
$ophandler['object']['ports']['unlinkPort'] = 'unlinkPortForObject';
$ophandler['object']['ports']['addMultiPorts'] = 'addMultiPorts';
$ophandler['object']['ports']['useup'] = 'useupPort';
$ophandler['object']['network']['editAddressFromObject'] = 'editAddressFromObject';
$ophandler['object']['network']['addAddrFObj'] = 'addAddressToObject';
$ophandler['object']['network']['delAddrFObj'] = 'delAddressFromObject';
$ophandler['object']['edit']['del'] = 'resetAttrValue';
$ophandler['object']['edit']['upd'] = 'updateAttrValues';
$ophandler['object']['portfwrd']['forwardPorts'] = 'addPortForwarding';
$ophandler['object']['portfwrd']['delPortForwarding'] = 'delPortForwarding';
$ophandler['object']['portfwrd']['updPortForwarding'] = 'updPortForwarding';
$ophandler['object']['switchvlans']['submit'] = 'updateVLANMembership';

$page['ipv4space']['title'] = 'static_title';
$page['ipv4space']['parent'] = 'index';
$tab['ipv4space']['default'] = 'Browse';
$tab['ipv4space']['newrange'] = 'Subnets';
$tab['ipv4space']['editrstab'] = '[SLB RSs]';
$tab['ipv4space']['editlbconf'] = '[SLB LBs]';
$tab['ipv4space']['editvstab'] = '[SLB VSs]';
$helptab['ipv4space']['default'] = 'nets';
$helptab['ipv4space']['newrange'] = 'nets';
$tabhandler['ipv4space']['default'] = 'renderAddressspace';
$tabhandler['ipv4space']['newrange'] = 'renderAddNewRange';
$ophandler['ipv4space']['newrange']['addRange'] = 'addNewrange';
$ophandler['ipv4space']['newrange']['delRange'] = 'delRange';

$page['iprange']['title'] = 'dynamic_title_iprange';
$page['iprange']['parent'] = 'ipv4space';
$page['iprange']['bypass'] = 'id';
$tab['iprange']['default'] = 'Browse';
$tab['iprange']['properties'] = 'Properties';
$helptab['iprange']['default'] = 'nets';
$helptab['iprange']['properties'] = 'nets';
$tabhandler['iprange']['default'] = 'renderIPRange';
$tabhandler['iprange']['properties'] = 'renderIPRangeProperties';
$ophandler['iprange']['properties']['editRange'] = 'editRange';

$page['ipaddress']['title'] = 'dynamic_title_ipaddress';
$page['ipaddress']['parent'] = 'iprange';
$page['ipaddress']['bypass'] = 'ip';
$tab['ipaddress']['default'] = 'Browse';
$tab['ipaddress']['properties'] = 'Properties';
$tab['ipaddress']['assignment'] = 'Allocation';
$helptab['ipaddress']['default'] = 'nets';
$helptab['ipaddress']['properties'] = 'nets';
$helptab['ipaddress']['assignment'] = 'nets';
$tabhandler['ipaddress']['default'] = 'renderIPAddress';
$tabhandler['ipaddress']['properties'] = 'renderIPAddressProperties';
$tabhandler['ipaddress']['assignment'] = 'renderIPAddressAssignment';
$ophandler['ipaddress']['properties']['editAddress'] = 'editAddress';
$ophandler['ipaddress']['assignment']['delIpAssignment'] = 'delIpAssignment';
$ophandler['ipaddress']['assignment']['editBondForAddress'] = 'editIpAssignment';
$ophandler['ipaddress']['assignment']['bindObjectToIp'] = 'addIpAssignment';

$page['search']['title'] = 'dynamic_title_search';
$page['search']['handler'] = 'handler_search';
$page['search']['parent'] = 'index';
$page['search']['bypass'] = 'q';

$page['config']['title'] = 'static_title';
$page['config']['handler'] = 'handler_config';
$page['config']['parent'] = 'index';

$page['accounts']['title'] = 'static_title';
$page['accounts']['parent'] = 'config';
$tab['accounts']['default'] = 'View';
$tab['accounts']['edit'] = 'Change';
$tabhandler['accounts']['default'] = 'renderAccounts';
$tabhandler['accounts']['edit'] = 'renderAccountsEditForm';
$ophandler['accounts']['edit']['updateAccount'] = 'updateUserAccount';
$ophandler['accounts']['edit']['createAccount'] = 'createUserAccount';
$ophandler['accounts']['edit']['disableAccount'] = 'disableUserAccount';
$ophandler['accounts']['edit']['enableAccount'] = 'enableUserAccount';

$page['perms']['title'] = 'static_title';
$page['perms']['parent'] = 'config';
$tab['perms']['default'] = 'View';
$tab['perms']['edit'] = 'Change';
$tabhandler['perms']['default'] = 'renderPermissions';
$tabhandler['perms']['edit'] = 'renderPermissionsEditForm';
$ophandler['perms']['edit']['revoke'] = 'revokePermission';
$ophandler['perms']['edit']['grant'] = 'grantPermission';

$page['portmap']['title'] = 'static_title';
$page['portmap']['handler'] = 'handler_portmap';
$page['portmap']['parent'] = 'config';
$tab['portmap']['default'] = 'View';
$tab['portmap']['edit'] = 'Change';
$ophandler['portmap']['edit']['save'] = 'savePortMap';

$page['attrs']['title'] = 'static_title';
$page['attrs']['parent'] = 'config';
$tab['attrs']['default'] = 'View';
$tab['attrs']['editattrs'] = 'Edit attributes';
$tab['attrs']['editmap'] = 'Edit map';
$tabhandler['attrs']['default'] = 'renderAttributes';
$tabhandler['attrs']['editattrs'] = 'renderEditAttributesForm';
$tabhandler['attrs']['editmap'] = 'renderEditAttrMapForm';
$ophandler['attrs']['editattrs']['add'] = 'createAttribute';
$ophandler['attrs']['editattrs']['upd'] = 'changeAttribute';
$ophandler['attrs']['editattrs']['del'] = 'deleteAttribute';
$ophandler['attrs']['editmap']['add'] = 'supplementAttrMap';
$ophandler['attrs']['editmap']['del'] = 'reduceAttrMap';

$page['dict']['title'] = 'static_title';
$page['dict']['parent'] = 'config';
$tab['dict']['default'] = 'View';
$tab['dict']['edit'] = 'Edit words';
$tab['dict']['chapters'] = 'Manage chapters';
$tabhandler['dict']['default'] = 'renderDictionary';
$tabhandler['dict']['edit'] = 'renderDictionaryEditor';
$tabhandler['dict']['chapters'] = 'renderChaptersEditor';
$ophandler['dict']['edit']['del'] = 'reduceDictionary';
$ophandler['dict']['edit']['upd'] = 'updateDictionary';
$ophandler['dict']['edit']['add'] = 'supplementDictionary';
$ophandler['dict']['chapters']['del'] = 'delChapter';
$ophandler['dict']['chapters']['upd'] = 'updateChapter';
$ophandler['dict']['chapters']['add'] = 'addChapter';

$page['ui']['title'] = 'static_title';
$page['ui']['parent'] = 'config';
$tab['ui']['default'] = 'View';
$tab['ui']['edit'] = 'Change';
$tab['ui']['reset'] = 'Reset';
$tabhandler['ui']['default'] = 'renderUIConfig';
$tabhandler['ui']['edit'] = 'renderUIConfigEditForm';
$tabhandler['ui']['reset'] = 'renderUIResetForm';
$ophandler['ui']['edit']['upd'] = 'updateUI';
$ophandler['ui']['reset']['go'] = 'resetUIConfig';

$page['reports']['title'] = 'static_title';
$page['reports']['parent'] = 'index';
$tab['reports']['default'] = 'View';
$tabhandler['reports']['default'] = 'renderReportSummary';

$page['help']['title'] = 'static_title';
$page['help']['handler'] = 'renderHelpTab';
$page['help']['parent'] = 'index';
$tab['help']['default'] = 'Welcome';
$tab['help']['quickstart'] = 'Quick start';
$tab['help']['workflow'] = 'Workflow';
$tab['help']['rackspace'] = 'Rackspace';
$tab['help']['objects'] = 'Objects';
$tab['help']['nets'] = 'Networking';
$tab['help']['auth'] = '[ User accounts & permissions ]';
$tab['help']['dict'] = '[ Dictionary ]';
$tab['help']['ports'] = 'Ports and links';
$tab['help']['hacking'] = '[ Hacker\'s guide ]';

// This function returns array if page numbers leading to the target page
// plus page number of target page itself. The first element is the target
// page number and the last element is the index page number.
function getPath ($targetno)
{
	global $page;
	$path = array();
	// Recursion breaks at first parentless page.
	if (!isset ($page[$targetno]['parent']))
		$path = array ($targetno);
	else
	{
		$path = getPath ($page[$targetno]['parent']);
		$path[] = $targetno;
	}
	return $path;
}

function showPathAndSearch ($pageno)
{
	global $root, $page;
	// Path.
	echo "<td class=activemenuitem width='99%'>" . getConfigVar ('enterprise');
	if (isset ($page[$pageno]['title']))
	{
		$path = getPath ($pageno);
		foreach ($path as $dummy => $no)
		{
			$title = $page[$no]['title']($no);
			echo ": <a href='${root}?page=${no}";
			foreach ($title['params'] as $param_name => $param_value)
				echo "&${param_name}=${param_value}";
			echo "'>" .$title['name'] . "</a>";
		}
	}
	echo "</td>";
	// Search form.
	echo "<td><table border=0 cellpadding=0 cellspacing=0><tr><td>Search:</td>";
	echo "<form method=get action='${root}'><td>";
	echo '<input type=hidden name=page value=search>';
	// This input will be the first, if we don't add ports or addresses.
	echo "<input type=text name=q size=20 tabindex=1000></td></form></tr></table></td>";
}

function getTitle ($pageno, $tabno)
{
	global $page;
	if (!isset ($page[$pageno]['title']))
		return getConfigVar ('enterprise');
	$tmp = $page[$pageno]['title']($pageno);
	return $tmp['name'];
}

function showTabs ($pageno, $tabno)
{
	global $tab, $root, $page, $remote_username, $trigger;
	if (!isset ($tab[$pageno]['default']))
		return;
	echo "<td><div class=greynavbar><ul id=foldertab style='margin-bottom: 0px; padding-top: 10px;'>";
	foreach ($tab[$pageno] as $tabidx => $tabtitle)
	{
		// Hide forbidden tabs.
		if (authorized ($remote_username, $pageno, $tabidx) == FALSE)
			continue;
		// Dynamic tabs should only be shown in certain cases (trigger exists and returns true).
		if
		(
			isset ($trigger[$pageno][$tabidx]) &&
			$trigger[$pageno][$tabidx] () != TRUE
		)
			continue;
		echo '<li><a' . (($tabidx == $tabno) ? ' class=current' : '');
		echo " href='${root}?page=${pageno}&tab=${tabidx}";
		if (isset ($page[$pageno]['bypass']) and isset ($_REQUEST[$page[$pageno]['bypass']]))
		{
			$bpname = $page[$pageno]['bypass'];
			$bpval = $_REQUEST[$bpname];
			echo "&${bpname}=${bpval}";
		}
		echo "'>${tabtitle}</a></li>\n";
	}
	lookupHelpTopic ($pageno, $tabno);
	echo "</ul></div></td>\n";
}

// This function returns pages, which are direct children of the requested
// page and are accessible by the current user.
function getDirectChildPages ($pageno)
{
	global $page, $remote_username;
	$children = array();
	foreach ($page as $cpageno => $cpage)
		if
		(
			isset ($cpage['parent']) and
			$cpage['parent'] == $pageno and
			authorized ($remote_username, $cpageno, 'default') == TRUE
		)
			$children[$cpageno] = $cpage;
	return $children;
}

function getAllChildPages ($parent)
{
	global $page;
	// Array pointer is global, so if we don't create local copies of
	// the global array, we can't advance any more after nested call
	// of getAllChildPages returns.
	$mypage = $page;
	$mykids = array();
	foreach ($mypage as $ctitle => $cpage)
		if (isset ($cpage['parent']) and $cpage['parent'] == $parent)
			$mykids[] = array ('title' => $ctitle, 'kids' => getAllChildPages ($ctitle));
	return $mykids;
}

?>
