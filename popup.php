<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" style="height: 100%;">
<head>
 <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
	require 'inc/init.php';
	// This is our context.
	$pageno = 'objects';
	$tabno = 'default';
	echo '<title>' . getTitle ($pageno, $tabno) . "</title>\n";
	echo "<link rel=stylesheet type='text/css' href=pi.css />\n";
	echo "<link rel=icon href='" . getFaviconURL() . "' type='image/x-icon' />";
	echo '</head><body style="height: 100%;">';
	fixContext();
	if (!permitted())
	{
		renderAccessDenied();
		die;
	}
	assertStringArg ('helper', __FILE__);
	switch ($_REQUEST['helper'])
	{
		case 'portlist':
			assertUIntArg ('type', __FILE__);
			assertUIntArg ('port', __FILE__);
			assertUIntArg ('object_id', __FILE__);
			assertStringArg ('port_name', __FILE__);
			echo '<div style="background-color: #f0f0f0; border: 1px solid #3c78b5; padding: 10px; height: 100%; text-align: center; margin: 5px;">';
			echo '<h2>Choose a port:</h2><br><br>';
			echo '<form action="javascript:;">';
			echo '<input type=hidden id=remote_port_name>';
			echo '<input type=hidden id=remote_object_name>';
			echo '<select size=' . getConfigVar ('MAXSELSIZE') . ' id=ports>';
			$type_id = $_REQUEST['type'];
			$port_id = $_REQUEST['port'];
			$object_id = $_REQUEST['object_id'];
			$port_name = $_REQUEST['port_name'];
			renderEmptyPortsSelect ($port_id, $type_id);
			echo '</select><br><br>';
			echo "<input type='submit' value='Proceed' onclick='".
			"if (getElementById(\"ports\").value != \"\") {".
			"	opener.location=\"${root}process.php?page=object&tab=ports&op=linkPort&object_id=$object_id&port_id=$port_id&port_name=$port_name&remote_port_name=\"+getElementById(\"remote_port_name\").value+\"&remote_object_name=\"+getElementById(\"remote_object_name\").value+\"&remote_port_id=\"+getElementById(\"ports\").value; ".
			"	window.close();}'>";
			echo '</form></div>';
			break;
		case 'inet4list':
			echo '<div style="background-color: #f0f0f0; border: 1px solid #3c78b5; padding: 10px; height: 100%; text-align: center; margin: 5px;">';
			echo '<h2>Choose a port:</h2><br><br>';
			echo '<form action="javascript:;">';
			echo '<input type=hidden id=ip>';
			echo '<select size=' . getConfigVar ('MAXSELSIZE') . ' id=addresses>';
			renderAllIPv4Allocations();
			echo '</select><br><br>';
			echo "<input type=submit value='Proceed' onclick='".
			"if (getElementById(\"ip\")!=\"\") {".
			" opener.document.getElementById(\"remoteip\").value=getElementById(\"ip\").value;".
			" window.close();}'>";
			echo '</form></div>';
			break;
		default:
			showError ('Invalid parameter or internal error', __FILE__);
			break;
	}
?>
</body>
</html>
