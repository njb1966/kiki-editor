<?php
// this file loads a page that has been formatted with the kiki markup language
// it should be located in your www server public directory

///// GET & POST requests /////
// determine which page the browser is asking for
if (!isset($_POST['page']))
{
	if (!isset($_GET['page']))
		$pageRequested = false;
	else
		$pageRequested = $_GET['page'];
}
else
	$pageRequested = $_POST['page'];

// automagically determine if connection is http or https
if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1))
    $url_protocol = "https://";
else
    $url_protocol = "http://";

// handle file uploads - file must be larger than zero
if (isset($_FILES['file_upload']) && ($_FILES['file_upload']['size'] > 0))
    $file_upload = $_FILES['file_upload'];
else
    $file_upload = false;

// when the user requests pages based on a tag
if (!isset($_GET['tag']))
    $tagRequested = false;
else
    $tagRequested = $_GET['tag'];

// sort style when requesting more than one page
if (!isset($_GET['sort']))
    $sort = false;
else
    $sort = $_GET['sort'];

// return a maximum number of page results
if (!isset($_GET['maximum']))
	$maxPages = false;
else
	$maxPages = $_GET['maximum'];

// allow actions like edit
if (!isset($_POST['command']))
{
    if (!isset($_GET['command']))
        $command = false;
    else
        $command = $_GET['command'];
}
else
    $command = $_POST['command'];

// allow publish action
if (!isset($_POST['publish_content']))
    $publish_content = false;
else
    $publish_content = $_POST['publish_content'];

// allow password for wiki mode
if (!isset($_POST['password']))
    $password = false;
else
    $password = $_POST['password'];

if ($pageRequested)
{
    // trailing slashes on urls not allowed
    $pageRequested = rtrim($pageRequested, '/');
    // all page requests must use underscores instead of spaces
    $pageRequested = str_replace(' ', '_', $pageRequested);
}

////// Include Internal libraries //////
require_once("settings.php");
require_once($library_dir . "utils.php");
require_once($library_dir . "page.php");
if ($gopher_enabled && file_exists($library_dir . "gopher.php"))
	require_once($library_dir . "gopher.php");
require_once($library_dir . "static.php");
if ($rss_enabled)
    require_once($library_dir . "rss.php");


////// WIKI ACTIONS //////

// an action is a command and its related parameters that
// involve a user's request
$action = ["command" => $command, "request" => $pageRequested, "publish_content" => $publish_content, "file_upload" => $file_upload, "password" => $password];

////// PAGE & POST LOADING/SERVING //////

// when gopher is enabled, generate a gopher hole
if ($gopher_enabled && function_exists("dig_gopher_hole"))
	dig_gopher_hole();

// static generation mode
if ($static_generation)
	echo generate_static_site();

// site loads in dynamic/live mode
else
{
    // if the user hasn't asked for a specific page, 
    // or tag load the default page (set in settings.php)
    if (!$pageRequested && !$tagRequested)
        $pageRequested = $default_page;

	// sometimes the requested page uses tags instead
	$requestTags = check_request_for_tags($pageRequested);
	if ($requestTags)
	{
		$tagRequested = $requestTags["tags"];
		if ($requestTags["sortstyle"])
			$sort = $requestTags["sortstyle"];

		$pageRequested = false;
	}

    // track whether the page was successfully loaded
    $pageLoaded = false;

    // handle 503/maintenance mode
    if ($site_offline)
    {
        // load the 503 page
        echo get_page($error_503);
        $pageLoaded = true;
    }
    // retrieve the requested page
    elseif ($pageRequested)
    {
        // do a directory search for the page
        $page_found = search_page_filepath_by_name($pageRequested, true);

        // snip off the directory and file extension so we can compare it to the request
        if ($page_found && isset($page_found[0]))
        {
            // use the first result found
            $page_found = $page_found[0];
            $page_found_name = strip_filepath($page_found, $pages_dir);
        }
        else
            $page_found_name = false;

        if ($page_found_name == $pageRequested)
        {
            echo get_page($page_found, $action);
            $pageLoaded = true;
        }
        // in wiki mode, we allow users to create new pages
        elseif (!$page_found_name && $public_wiki)
        {
            // no action specified, so send a "create new page" action 
            // to the requested wiki page
            if (!$action["command"])
                $action["command"] = "new";
            
			echo get_page($pages_dir . $pageRequested . "." . $markup_file_extension, $action);
            $pageLoaded = true;
        }
    }
    elseif ($tagRequested)
    {
        $tagPagesFound = get_pages_by_tag($tagRequested, $sort, $maxPages);
        if ($tagPagesFound)
        {
            echo get_pages($tagPagesFound);
            $pageLoaded = true;
        }
    }

    // no pages found: yield a 404
    if (!$pageLoaded)
		echo get_page($error_404);
}

////// RSS Feed //////
if ($rss_enabled)
    build_rss_feed();

?>