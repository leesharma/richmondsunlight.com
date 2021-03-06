<?php

	###
	# Comment Activity RSS
	# 
	# PURPOSE
	# Lists the last 20 comments posted.
	#
	# NOTES
	# None.
	#
	# TODO
	# * Support If-Modified-Since and If-None-Match headers to reduce bandwidth.
	# * The session year in the RSS URL is hard-coded due to soem kind of a weird
	#   MySQL join error.
	#
	###
	
	# INCLUDES
	# Include any files or libraries that are necessary for this specific
	# page to function.
	include_once($_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php');
	include_once($_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php');
	
	# LOCALIZE VARIABLES
	if (isset($_REQUEST['year'])) $year = $_REQUEST['year'];
	if (isset($_REQUEST['bill'])) $bill = $_REQUEST['bill'];
	
	# Make sure that the year and bill number are valid-looking.
	if ((!ereg('([0-9]{4})', $year)) || (!ereg('([b-s]{2})([0-9]+)', $year)))
	{
		unset($bill);
		unset($year);
	}
	
	# PAGE CONTENT
	# Open a database connection.
	@connect_to_db();
	
	# Query the database for the last 20 comments.
	$sql = 'SELECT comments.id, comments.bill_id, comments.date_created AS date,
			comments.name, comments.email, comments.url, comments.comment,
			comments.type, bills.number AS bill_number, sessions.year,
				(
				SELECT COUNT(*)
				FROM comments
				WHERE bill_id=bills.id AND status="published"
				AND date_created <= date
				) AS number
			FROM comments
			LEFT JOIN bills
			ON bills.id=comments.bill_id
			LEFT JOIN sessions
			ON bills.session_id=sessions.id
			WHERE comments.status="published"
			ORDER BY comments.date_created DESC
			LIMIT 20';
	$result = @mysql_query($sql);
	
	$rss_content = '';
	
	# Generate the RSS.
	while ($comment = @mysql_fetch_array($result))
	{
		
		# Aggregate the variables into their RSS components.
		$title = '<![CDATA['.($comment['type'] == 'pingback' ? 'Pingback from ' : '').$comment['name'].' '.$comment['bill_number'].']]>';
		$link = 'http://www.richmondsunlight.com/bill/'.$comment['year'].'/'.$comment['bill_number'].'/#comment-'.$comment['number'];
		$description = '<![CDATA[
			'.nl2p($comment['comment']).'
			]]>';
		
		# Now assemble those RSS components into an XML fragment.
		$rss_content .= '
		<item>
			<title>'.$title.'</title>
			<link>'.$link.'</link>
			<description>'.$description.'</description>
		</item>';
	
		# Unset those variables for reuse.
		unset($item_completed);
		unset($title);
		unset($link);
		unset($description);
		
	}
	

	
	$rss = '<?xml version="1.0" encoding=\'utf-8\'?>
<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://www.rssboard.org/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<title>Richmond Sunlight Comments</title>
		<link>http://www.richmondsunlight.com/</link>
		<description>The most recent comments posted to bills on Richmond Sunlight.</description>
		<language>en-us</language>
		'.$rss_content.'
	</channel>
</rss>';

	header('Content-Type: application/xml');
	echo $rss;
	
?>
