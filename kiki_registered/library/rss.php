<?php
// kiki rss builder is the tiniest, simplest RSS feed builder I can design
// it doesn't support rebuilding relative links/images into absolute ones, 
// because that's something the rss reader should do. not my job.

// if you leave this alone, the rss link will be the URL of your site's entire blog roll
// this is NOT the rss link that appears on the menu. it is the rss link that
// goes into rss.xml
// page permalink base URL. the page name is added to the end of the url when used in the script

$rss_permalink_classic = "";
$rss_permalink_easy = "";

// in static generation mode we rely upon the site_base_url which MUST be set in settings
$rss_permalink_classic = get_site_basename("classic") . "?tag=" . $rss_tags;
$rss_permalink_easy = get_site_basename("easy");
$rssMenuLink = get_site_basename("easy") . "rss.xml";

// rssLink is a global
if ($static_generation)
    $site_rssLink = $page_permalink_easy;
elseif ($permalink_style == "classic")
    $site_rssLink = $rss_permalink_classic;
elseif ($permalink_style == "easy")
    $site_rssLink = $rss_permalink_easy . $pages_dir;

// builds the entire rss feed based on the
// tag selected in settings.php
function build_rss_feed()
{
	global $rss_title, $site_rssLink, $rss_description, $rss_tags, $page_permalink_base, $rss_item_limit, $rss_sort_style;
	if ($rss_tags)
	{
		$page_filepaths = get_pages_by_tag($rss_tags, $rss_sort_style);
		$rssFeed = build_rss_feed_header($rss_title, $site_rssLink, $rss_description);    

		// store all html output as text
		$htmlOutput = "";

		$rss_item_count = 0;
		foreach ($page_filepaths as $page_file)
		{
			$pageSource = load_page($page_file);
			$content_html = get_page($page_file, false, false, "html", false);

			$rssFeed .= extract_rss_item($page_file, $pageSource["header"], $content_html, $page_permalink_base);
			$rss_item_count++;
			// stop adding rss items when we've hit max rss item limit, found in settings.php
			if ($rss_item_count == $rss_item_limit)
				break;
		}
		$rssFeed .= build_rss_feed_footer();
		save_rss($rssFeed);
	}
}

// builds the rss pubdate from date and time strings
// returns a string, formatted in unix-style timestamp
function build_rss_pubdate($date, $time)
{
    global $default_page_publication_time;
    if ($time)
        $rsstime = $time;
    else
        $rsstime = $default_page_publication_time;

    // translate the date and time to D, d M Y H:i:s T format, in GMT timezone format
	// in order to satisfy 1970s RFC-822 requirements
    $dateTime = create_timestamp($date, $rsstime, "D, d M Y H:i:s T", true);
	if ($dateTime) 
		return $dateTime;
	else	
		return false;
}

// builds an rss feed header using the title, link and description as parameters
function build_rss_feed_header($title, $link, $description)
{
	$rssHeader = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>';
	$rssHeader .= '<title>' . $title . '</title><link>' . $link . '</link>';
	$rssHeader .= '<description>' . $description . '</description>';
	$rssHeader .= '<language>en-us</language>';
	return $rssHeader;
}

// builds an rss feed item using the title, link, content and pubdate of the item
function build_rss_feed_item($title, $link, $content, $pubDate)
{
	$rssItem = '<item><title>' . $title . '</title>';
	$rssItem .= '<link>' . $link . '</link>';
	$rssItem .= '<description><![CDATA[' . $content . ']]></description>';
	$rssItem .= '<pubDate>' . $pubDate . '</pubDate>';
    $rssItem .= '<guid>' . $link . '</guid>';
	$rssItem .= '</item>';
	return $rssItem;
}

// builds an rss feed item from a page's filename and header data
// returns rss-formatted text
function extract_rss_item($page_filename, $page_header, $content, $permalink_base_url)
{
	global $markup_file_extension, $pages_dir, $static_generation, $html_file_extension;

	// get page date from the header
    $page_date = parse_key($page_header, "date");
    // get page time in H:i:s format (HH:mm:ss)
	$page_time = parse_key($page_header, "time");
    $page_date_RSS = build_rss_pubdate($page_date, $page_time);
    $page_title = parse_key($page_header, "title");
	// snip off the directory and file extension so we can use only the page's filename for its permalink
	$linkfile = strip_filepath($page_filename, $pages_dir);

    // don't forget to add the .html in static generation mode!
    if ($static_generation)
        $linkfile = $linkfile . "." . $html_file_extension;
    $rssPageLink = $permalink_base_url . $linkfile;
    $rssItem = build_rss_feed_item($page_title, $rssPageLink, $content, $page_date_RSS);

    return $rssItem;
}

function build_rss_feed_footer()
{
	$rssFooter = '</channel></rss>';
	return $rssFooter;
}

// saves an rss-formatted feed to rss.xml
function save_rss($rssFeed)
{
	global $rss_document; // set in settings.php
	// write the rss feed to a file
	$rssFile = fopen($rss_document, "w");
	if ($rssFile)
	{
		fwrite($rssFile, $rssFeed);
		fclose($rssFile);
	}
}

?>