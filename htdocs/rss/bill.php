<?php

	###
	# Bills' Activity by Individual Bill
	# 
	# PURPOSE
	# Lists the last 20 bill actions for a specific bill.
	#
	# NOTES
	# None.
	#
	# TODO
	# * Have die() provide an error that will appear in an RSS reader.
	# * Support If-Modified-Since and If-None-Match headers to reduce bandwidth.
	# * Bill numbers are reused here -- the cache file consists solely of the bill number, which
	#   isn't unique. That'll reset with every new session, so that's not terrible, but it's not
	#   good. The solution is to improve the URL structure (which currently contains only bill
	#   numbers) as well as the cache filename structure.
	#
	###
	
	# INCLUDES
	# Include any files or libraries that are necessary for this specific
	# page to function.
	include_once($_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php');
	include_once($_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php');
	
	# LOCALIZE VARIABLES
	$bill['number'] = strtolower($_REQUEST['number']);
	
	# PAGE CONTENT
	
	# Check to see if there's any need to regenerate this RSS feed -- only do so
	# if it's more than a half hour old.
	if ((file_exists('cache/bill-'.$bill['number'].'.xml')) && ((filemtime('cache/bill-'.$bill['number'].'.xml') + 1800) > time()))
	{
		header('Content-Type: application/xml');
		header('Last-Modified: '.date('r', filemtime('cache/bill-'.$bill['number'].'.xml')));
		header('ETag: '.md5_file('cache/bill-'.$bill['number'].'.xml'));
		readfile('cache/bill-'.$bill['number'].'.xml');
		exit();
	}

	# Open a database connection.
	@connect_to_db();

	# Query the database for all bills by that bill number.
	$sql = 'SELECT bills_status.status, bills.catch_line
			FROM bills_status
			LEFT JOIN bills
			ON bills_status.bill_id=bills.id
			WHERE bills.id=bills_status.bill_id AND bills.session_id = '.SESSION_ID.'
			AND bills.number="'.mysql_real_escape_string($bill['number']).'"
			ORDER BY bills_status.date DESC, bills_status.id DESC';

	$result = @mysql_query($sql);

	if (@mysql_num_rows($result) > 0)
	{
		
		$rss_content = '';

		# Generate the RSS.
		while ($status = @mysql_fetch_array($result))
		{
			
			$status = array_map('stripslashes', $status);
			
			# Aggregate the variables into their RSS components.
			$title = '<![CDATA['.$status['status'].']]>';
			$link = 'http://www.richmondsunlight.com/bill/'.SESSION_YEAR.'/'.$bill['number'].'/';
			$description = '<![CDATA['.$status['status'].']]>';
			
			# Now assemble those RSS components into an XML fragment.
			$rss_content .= '
			<item>
				<title>'.$title.'</title>
				<link>'.$link.'</link>
				<description>'.$description.'</description>
			</item>';
		}
	}

	
	$rss = '<?xml version="1.0" encoding=\'utf-8\'?>
<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://www.rssboard.org/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<title>'.strtoupper($bill['number']).' Status</title>
		<link>http://www.richmondsunlight.com/bill/'.SESSION_YEAR.'/'.$bill['number'].'/</link>
		<description>The activity of bill '.strtoupper($bill['number']).' in the '
			.SESSION_YEAR.' Virginia General Assembly session.</description>
		<language>en-us</language>
		'.$rss_content.'
	</channel>
</rss>';
	
	
	# Cache the RSS file.
	$fp = @file_put_contents('cache/bill-'.$bill['number'].'.xml', $rss);
	
	header('Content-Type: application/rss+xml');
	header('Last-Modified: '.date('r', filemtime('cache/bill-'.$bill['number'].'.xml')));
	header('ETag: '.md5_file('cache/bill-'.$bill['number'].'.xml'));
	echo $rss;	
?>
