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
			printIpv6NetInfoTDs ($item, 'tdleft sparenetwork', $level, $item['symbol']);
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

function renderIPv6Space ()
{
	global $pageno, $tabno;
	$cellfilter = getCellFilter();
	$netlist = filterCellList (listCells ('ipv6net'), $cellfilter['expression']);
	array_walk ($netlist, 'amplifyCell');

	$netcount = count ($netlist);
	// expand request can take either natural values or "ALL". Zero means no expanding.
	$eid = isset ($_REQUEST['eid']) ? $_REQUEST['eid'] : 0;
	$tree = prepareIpv6Tree ($netlist, $eid);

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
