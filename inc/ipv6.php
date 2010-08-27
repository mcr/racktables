<?

function renderIPv6SpaceRecords ($tree, $baseurl, $target = 0, $level = 1)
{
	$self = __FUNCTION__;
	static $vdomlist = NULL;
	if ($vdomlist == NULL and getConfigVar ('IPV6_TREE_SHOW_VLAN') == 'yes')
		$vdomlist = getVLANDomainOptions();
	foreach ($tree as $item)
	{
		if (getConfigVar ('IPV6_TREE_SHOW_USAGE') == 'yes')
			loadIpv6AddrList ($item); // necessary to compute router list and address counter
		else
		{
			$item['addrlist'] = array();
			$item['addrc'] = 0;
		}
		$used = $item['addrc'];
		$maxdirect = $item['addrt'];
		$maxtotal = binInvMaskFromDec ($item['mask']) + 1;
		if (isset ($item['id']))
		{
			if ($item['symbol'] == 'node-collapsed')
				$expandurl = "${baseurl}&eid=" . $item['id'] . "#netid" . $item['id'];
			elseif ($item['symbol'] == 'node-expanded')
				$expandurl = $baseurl . ($item['parent_id'] ? "&eid=${item['parent_id']}#netid${item['parent_id']}" : '');
			else
				$expandurl = '';
			echo "<tr valign=top>";
			printIpv6NetInfoTDs ($item, 'tdleft', $level, $item['symbol'], $expandurl);
			echo "<td class=tdcenter>";
			if ($target == $item['id'])
				echo "<a name=netid${target}></a>";
			if (getConfigVar ('IPV6_TREE_SHOW_USAGE') == 'yes')
			{
				renderProgressBar ($maxdirect ? $used/$maxdirect : 0);
				echo "<br><small>${used}/${maxdirect}" . ($maxdirect == $maxtotal ? '' : "/${maxtotal}") . '</small>';
			}
			else
				echo "<small>${maxdirect}</small>";
			echo "</td>";
			if (getConfigVar ('IPV6_TREE_SHOW_VLAN') == 'yes')
			{
				echo '<td class=tdleft>';
				if (count ($item['8021q']))
				{
					echo '<ul>';
					foreach ($item['8021q'] as $binding)
					{
						echo '<li><a href="' . makeHref (array ('page' => 'vlan', 'vlan_ck' => $binding['domain_id'] . '-' . $binding['vlan_id'])) . '">';
						// FIXME: would formatVLANName() do this?
						echo $binding['vlan_id'] . '@' . niftyString ($vdomlist[$binding['domain_id']], 15) . '</a></li>';
					}
					echo '</ul>';
				}
				echo '</td>';
			}
			if (getConfigVar ('EXT_IPV6_VIEW') == 'yes')
				printRoutersTD (findRouters ($item['addrlist']), getConfigVar ('IPV6_TREE_RTR_AS_CELL'));
			echo "</tr>";
			if ($item['symbol'] == 'node-expanded' or $item['symbol'] == 'node-expanded-static')
				$self ($item['kids'], $baseurl, $target, $level + 1);
		}
		else
		{
			echo "<tr valign=top>";
			printIPv6NetInfoTDs ($item, 'tdleft sparenetwork', $level, $item['symbol']);
			echo "<td class=tdcenter>";
			if (getConfigVar ('IPV6_TREE_SHOW_USAGE') == 'yes')
			{
				renderProgressBar ($used/$maxtotal, 'sparenetwork');
				echo "<br><small>${used}/${maxtotal}</small>";
			}
			else
				echo "<small>${maxtotal}</small>";
			if (getConfigVar ('IPV6_TREE_SHOW_VLAN') == 'yes')
				echo '</td><td>&nbsp;</td>';
			echo "</td><td>&nbsp;</td></tr>";
		}
	}
}

// Same as for routers, but produce two TD cells to lay the content out better.
function printIPNetInfoTDs ($netinfo, $addrname, $addrlen, $knight, $ipnet,
                            $newspace, $tdclass = 'tdleft', $indent = 0,
                            $symbol = 'spacer', $symbolurl = '')
{
	if ($symbol == 'spacer')
	{
		$indent++;
		$symbol = '';
	}
	echo "<td class='${tdclass}' style='padding-left: " . ($indent * 16) . "px;'>";
	if (strlen ($symbol))
	{
		if (strlen ($symbolurl))
			echo "<a href='${symbolurl}'>";
		printImageHREF ($symbol, $symbolurl);
		if (strlen ($symbolurl))
			echo '</a>';
	}
	if (isset ($netinfo['id']))
		echo "<a href='index.php?page=${ipnet}&id=${netinfo['id']}'>";
	echo "${netinfo[$addrname]}/${netinfo[$addrlen]}";
	if (isset ($netinfo['id']))
		echo '</a>';
	echo "</td><td class='${tdclass}'>";
	if (!isset ($netinfo['id']))
	{
		printImageHREF ('dragons', 'Here be dragons.');
		if (getConfigVar ($knight) == 'yes')
		{
			echo '<a href="' . makeHref (array
			(
				'page' => ${newspace},
				'tab' => 'newrange',
				'set-prefix' => $netinfo[${addrname}] . '/' . $netinfo[${addrlen}],
			)) . '">';
			printImageHREF ('knight', 'create network here', TRUE);
			echo '</a>';
		}
	}
	else
	{
		echo niftyString ($netinfo['name']);
		if (count ($netinfo['etags']))
			echo '<br><small>' . serializeTags ($netinfo['etags'], "index.php?page=${newspace}&tab=default&") . '</small>';
	}
	echo "</td>";
}

// Same as for routers, but produce two TD cells to lay the content out better.
function printIPv6NetInfoTDs ($netinfo, $tdclass = 'tdleft', $indent = 0, $symbol = 'spacer', $symbolurl = '')
{
        return printIPNetInfoTDs ($netinfo, 'ipv6', 'prefixlen', 'IPV6_ENABLE_KNIGHT',
                                  'ipv6net', 'ipv6space',
                                  $tdclass, $indent, $symbol, $symbolurl);
}



// Check the range requested for meaningful IPv4 records, build them
// into a list and return. Return an empty list if nothing matched.
// Both arguments are expected in signed int32 form. The resulting list
// is keyed by uint32 form of each IP address, items aren't sorted.
// LATER: accept a list of pairs and build WHERE sub-expression accordingly
function scanIPv6Space ($pairlist)
{
	$ret = array();
	if (!count ($pairlist)) // this is normal for a network completely divided into smaller parts
		return $ret;

	// FIXME: this is a copy-and-paste prototype
	$or = '';
	$whereexpr1 = '(';
	$whereexpr2 = '(';
	$whereexpr3 = '(';
	$whereexpr4 = '(';
	$whereexpr5a = '(';
	$whereexpr5b = '(';
	$qparams = array();
	foreach ($pairlist as $tmp)
	{
		$network = $tmp['ipv6'];
		$charlen = $tmp['prefixlen']+3 / 4;     # round up on nibbles.
                $charlen = $charlen + ($charlen / 4);   # add : 
                $charlen = $charlen - 1;                # left is zero based
		$whereexpr1 .= $or . "left(ipv6,?)=?";
		$whereexpr2 .= $or . "left(ipv6,?)=?";
		$whereexpr3 .= $or . "left(vipv6,?)=?";
		$whereexpr4 .= $or . "left(rsipv6,?)=?";
		//$whereexpr5a .= $or . "remoteip between ? and ?";
		//$whereexpr5b .= $or . "localip between ? and ?";
		$or = ' or ';
		$qparams[] = $network;
		$qparams[] = $charlen;
	}
	$whereexpr1 .= ')';
	$whereexpr2 .= ')';
	$whereexpr3 .= ')';
	$whereexpr4 .= ')';
	$whereexpr5a .= ')';
	$whereexpr5b .= ')';

	// 1. collect labels and reservations
	$query = "select ipv6, name, reserved from IPv6Address ".
		"where ${whereexpr1} and (reserved = 'yes' or name != '')";
	$result = usePreparedSelectBlade ($query, $qparams);
	while ($row = $result->fetch (PDO::FETCH_ASSOC))
	{
		$ip_bin = $row['ipv6'];
                $ret[$ip_bin]=$ip_bin;
		$ret[$ip_bin]['name'] = $row['name'];
		$ret[$ip_bin]['reserved'] = $row['reserved'];
	}
	unset ($result);

	// 2. check for allocations
	$query =
		"select ipv6, object_id, name, type " .
		"from IPv6Allocation where ${whereexpr2} order by type";
	$result = usePreparedSelectBlade ($query, $qparams);
	// release DBX early to avoid issues with nested spotEntity() calls
	$allRows = $result->fetchAll (PDO::FETCH_ASSOC);
	unset ($result);
	foreach ($allRows as $row)
	{
		$ip_bin = $row['ipv6'];
                $ret[$ip_bin]=$ip_bin;
		$oinfo = spotEntity ('object', $row['object_id']);
		$ret[$ip_bin]['allocs'][] = array
		(
			'type' => $row['type'],
			'name' => $row['name'],
			'object_id' => $row['object_id'],
			'object_name' => $oinfo['dname'],
		);
	}

	return $ret;
}


function countOwnIPv6Addresses (&$node)
{
	$toscan = array();
	$node['addrt'] = 0;

        $toscan[] = array('network' => $node['ipv6'],
                          'length'  => $node['prefixlen']);

	// Don't do anything more, because the displaying function will load the addresses anyway.
	return;
	$node['addrc'] = count (scanIPv6Space ($toscan));
}


function prepareIPTree ($netlist, $compfunc, $countfunc, $expanded_id = 0)
{

	// treeFromList() requires parent_id to be correct for an item to get onto the tree,
	// so perform necessary pre-processing to make orphans belong to root. This trick
	// was earlier performed by getIPv4NetworkList().
	$netids = array_keys ($netlist);
	foreach ($netids as $cid)
		if (!in_array ($netlist[$cid]['parent_id'], $netids))
			$netlist[$cid]['parent_id'] = NULL;
	$tree = treeFromList ($netlist); // medium call
	sortTree ($tree, $compfunc);
	// complement the tree before markup to make the spare networks have "symbol" set
	treeApplyFunc ($tree, 'iptree_fill');
	iptree_markup_collapsion ($tree, getConfigVar ('TREE_THRESHOLD'), $expanded_id);
	// count addresses after the markup to skip computation for hidden tree nodes
	treeApplyFunc ($tree, $countfunc, 'nodeIsCollapsed');
	return $tree;
}

function prepareIPv6Tree ($netlist, $expanded_id = 0)
{
        return prepareIPTree($netlist, 'IPv6NetworkCmp', 'countOwnIPv6Addresses');
}


function renderIPv6Space ()
{
	global $pageno, $tabno;
	$cellfilter = getCellFilter();
	$netlist = filterCellList (listCells ('ipv6net'), $cellfilter['expression']);
	array_walk ($netlist, 'amplifyCell');

	$netcount = count ($netlist);
	// expand request can take either natural values or "ALL". Zero means no expanding.
	$eid = isset ($_REQUEST['eid']) ? $_REQUEST['eid'] : 0;
	$tree = prepareIPv6Tree ($netlist, $eid);

	echo "<table border=0 class=objectview>\n";
	echo "<tr><td class=pcleft>";
	startPortlet ("networks (${netcount})");
	echo '<h4>';
	if ($eid === 0)
		echo 'auto-collapsing at threshold ' . getConfigVar ('TREE_THRESHOLD') .
			" (<a href='".makeHref(array('page'=>$pageno, 'tab'=>$tabno, 'eid'=>'ALL')) .
			$cellfilter['urlextra'] . "'>expand all</a>)";
	elseif ($eid === 'ALL')
		echo "expanding all (<a href='".makeHref(array('page'=>$pageno, 'tab'=>$tabno)) .
			$cellfilter['urlextra'] . "'>auto-collapse</a>)";
	else
	{
		$netinfo = spotEntity ('ipv6net', $eid);
		echo "expanding ${netinfo['ip']}/${netinfo['mask']} (<a href='" .
			makeHref (array ('page' => $pageno, 'tab' => $tabno)) .
			$cellfilter['urlextra'] . "'>auto-collapse</a> / <a href='" .
			makeHref (array ('page' => $pageno, 'tab' => $tabno, 'eid' => 'ALL')) .
			$cellfilter['urlextra'] . "'>expand&nbsp;all</a>)";
	}
	echo "</h4><table class='widetable' border=0 cellpadding=5 cellspacing=0 align='center'>\n";
	echo "<tr><th>prefix</th><th>name/tags</th><th>capacity</th>";
	if (getConfigVar ('IPV6_TREE_SHOW_VLAN') == 'yes')
		echo '<th>VLAN</th>';
	if (getConfigVar ('EXT_IPV6_VIEW') == 'yes')
		echo "<th>routed by</th>";
	echo "</tr>\n";
	$baseurl = makeHref(array('page'=>$pageno, 'tab'=>$tabno)) . $cellfilter['urlextra'];
	renderIpv6SpaceRecords ($tree, $baseurl, $eid);
	echo "</table>\n";
	finishPortlet();
	echo '</td><td class=pcright>';
	renderCellFilterPortlet ($cellfilter, 'ipv6net');
	echo "</td></tr></table>\n";
}
 
?>
