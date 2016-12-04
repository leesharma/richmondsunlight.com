<?php

###
# Bill Notes
# 
# PURPOSE
# Creation and editing of public notes for individual bills.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('../includes/functions.inc.php');
include_once('../includes/settings.inc.php');
include_once('../includes/photosynthesis.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

# PAGE METADATA
$page_title = 'Photosynthesis &raquo; Bill Notes';
$site_section = 'photosynthesis';

# ADDITIONAL HTML HEADERS
$html_head = '<link rel="stylesheet" href="/css/photosynthesis.css" type="text/css" />';

# CUSTOM PAGE FUNCTIONS
function notes_form($notes)
{
	$returned_data =
	'<form method="post" action="/photosynthesis/notes/'.$GLOBALS['portfolio']['hash'].'-'.$GLOBALS['id'].'/">
		<textarea rows="10" cols="80" name="notes">'.$notes['notes'].'</textarea><br />
		<small>(Limited HTML is OK: &lt;a&gt;, &lt;em&gt;, &lt;strong&gt;, &lt;blockquote&gt;,
			&lt;embed&gt;, &lt;ol&gt;, &lt;ul&gt;, &lt;li&gt;)</small><br />
		<input type="submit" name="submit" value="Save" />
	</form>';
	
	return $returned_data;
}

# INITIALIZE SESSION
session_start();

# Grab the user data. Bail if none is available.
$user = get_user();
if ($user === FALSE) exit();

if (!isset($_GET['id']))
{
	header('Location: '.$_SERVER['HTTP_REFERER']);
	exit;
}

# Clean up and localize the portfolio and bill data.
$portfolio['hash'] = mysql_real_escape_string($_GET['hash']);
$id = mysql_real_escape_string($_GET['id']);

# If the form is being posted, accept the updated notes and store them.
if (isset($_POST['submit']))
{
	
	# Strip out all tags other than the following.
	$notes = strip_tags($_POST['notes'], '<a><em><strong><i><b><s><blockquote><embed><ol><ul><li>');
	$notes = trim($notes);
	$notes = mysql_real_escape_string($notes);
	
	$sql = 'UPDATE dashboard_bills
			SET notes = '.(empty($notes) ? 'NULL' : '"'.$notes.'"').'
			WHERE id=' . $id . ' AND user_id = '.$user['id'];
	$result = mysql_query($sql);
	if (!$result)
	{
		$message = '<div id="messages" class="errors">Sorry: That note could not be saved.</div>';
	}
	else
	{
			
		/*
		 * Clear the Memcached cache of comments on this bill, since Photosynthesis comments are
		 * among them.
		 */
		$sql = 'SELECT bill_id AS id
				FROM dashboard_bills
				WHERE id=' . $id . ' AND user_id = ' . $user['id'];
		$result = mysql_query($sql);
		$bill = mysql_fetch_array($result);
		$mc = new Memcached();
		$mc->addServer("127.0.0.1", 11211);
		$comments = $mc->delete('comments-' . $bill['id']);
		
		header('Location: https://www.richmondsunlight.com/photosynthesis/#' . $portfolio['hash']);
		exit();
	}
	
}

# Assemble the SQL query.
$sql = 'SELECT dashboard_bills.id, dashboard_bills.notes, bills.number, bills.catch_line
		FROM dashboard_bills
		LEFT JOIN bills
		ON dashboard_bills.bill_id = bills.id
		WHERE dashboard_bills.id='.$id.' AND user_id='.$user['id'];
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0)
{
	header('Location: '.$_SERVER['HTTP_REFERER']);
	exit;
}
$notes = mysql_fetch_array($result);
$notes = array_map('stripslashes', $notes);

# Display any messages generated by operations. If there are none, simply initialize
# the variable.
if (isset($message)) $page_body = $message;
else $page_body = '';

$page_body .= '
	<h2>'.$notes['number'].': '.$notes['catch_line'].'</h2>';

# Display the form.
$page_body .= @notes_form($notes);

$page_body .= '
	<p style="margin-top: 2em; font-family: Georgia, Palatino, \'Times New Roman\', Times; margin-left: 1em; font-size: 1.2em;">
		<a href="/photosynthesis/">&lt;&lt; Back to Your Bills</a>
	</p>
';

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();