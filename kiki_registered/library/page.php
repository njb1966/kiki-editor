<?php
// generates a page consisting of a header, content, and footer using data from a kiki-formatted page file (txt)
require_once("build.php");
require_once("interpreter.php");

if ($public_wiki && file_exists($library_dir . "wiki.php"))
    require_once("wiki.php");

// track the current page globally
$current_page = NULL;

// permalinks use the site's basename with a suffix added
$tag_permalink_classic = get_site_basename("classic") . "?tag=";
$tag_permalink_easy = get_site_basename("easy") . "?tag=";
$page_permalink_classic = get_site_basename("classic") . "?page=";
$page_permalink_easy = get_site_basename("easy");

// page permalink base URL. the page name is added to the end of the url when used in the script
// important: force easy links when in static generation mode
if ($static_generation)
    $page_permalink_base = $page_permalink_easy;
elseif ($permalink_style == "classic")
{
    $tag_permalink_base = $tag_permalink_classic;
    $page_permalink_base = $page_permalink_classic;
}
elseif ($permalink_style == "easy")
{
    $tag_permalink_base = $tag_permalink_easy;
    $page_permalink_base = $page_permalink_easy;
}

// theme URLs are always relative to the base URL of the site
// this variable allows us to grab the theme resources from any
// sub-directory of the site (note: *must* use easy style!)
$theme_basename = get_site_basename("easy") . $theme_dir . $theme;

// returns pagedata consisting of header and content keys
// and interprets all markup and renders pagedata to the desired output format
// $action (optional): pass the user's action to the page
// $dynamic_content: pass any desired dynamic content to the page
// $output_format options: "html" (default) or "gopher"
// $build_page: when true, returns the built page. when false, returns parsed lines
function get_page($page_filename, $action = false, $dynamic_content = false, $output_format = "html", $build_page = true)
{
	global $current_page, $pages_dir;
	// any time a page has been successfully retrieved, it is the (global) current page
	$current_page = strip_filepath($page_filename, $pages_dir);

    $pageData = load_page($page_filename, $action, $dynamic_content);
	if (is_array($pageData))
	{
		global $markup_interpreter;
		if ($output_format == "html")
		{
			// interprets all markup in the page and returns html
			if ($pageData["interpreter"] && $pageData["interpreter"] != $markup_interpreter)
				$pageData["content"] = parse_markup_using($pageData["content"], $markup_interpreter);
			else
				$pageData["content"] = parse_markup($pageData["content"]);

			if ($build_page)
				return build_page($pageData["stylesheet"], $pageData["title"], $pageData["menu"], $pageData["content"]);
			else
				return $pageData["content"];
		}
		// interprets all markup to gopher/gophermap
		elseif ($output_format == "gopher" && function_exists("build_gophermap"))
		{
			return build_gophermap($pageData);
		}
		else
			return false;
	}
    else
	{
		// sorry, page couldn't be loaded!
		$current_page = NULL;
        return false;
	}
}

// loads data from a kiki-formatted page
// returns a page with header and content (as an array)
// with markup intact
// optional parameter: $dynamic_content is an array of named
// keys (with values) that can be used to inject dynamic content
// into the page as it is parsed. 
// action is an array with key 'command' and key 'content': when command "edit" is passed, enables editing mode
// when command "publish" is passed, "publish_content" contains the new content for the page
function load_page($page_filename, $action = false, $dynamic_content = false)
{
    global $timezone, $public_wiki, $pages_dir, $delete_document, $dynamic_variable_file;
    // load the default stylesheet, which can be overridden for specific pages below
    global $theme_stylesheet;
    $stylesheet = $theme_stylesheet;

    // start building a new page
	$pageOutput = [];

    // publish/create/delete actions need to be processed BEFORE the page can be loaded
    if ($public_wiki && $action)
    {
		$dynamic_content = [];

        if ($action["command"] == "publish" && function_exists("publish"))
        {
            // we have to use an array to capture the outcome of publish
            // attempts because they also handle file uploads too
            $publish_outcome = publish($page_filename, $action);

            // publish failed
            if (!$publish_outcome["publish"]["success"])
            {
                // the publish failed. so go back to editing mode with the content that was submitted
                $action["command"] = "edit";
                $page_source[0] = $action["publish_content"];
            }
            // handle publish error/success output
            $pageOutput[] = $publish_outcome["publish"]["html_output"];

            if (key_exists("html_output", $publish_outcome["upload"]))
                // handle upload error/success output
                $pageOutput[] = $publish_outcome["upload"]["html_output"];
        }
        // creating a new page returns a template page as the page source
        elseif ($action["command"] == "new" && function_exists("create"))
            $page_source = create();
        // editing a page creates a document with the existing page data as source
        elseif ($action["command"] == "edit" && function_exists("create"))
        {
            if (file_exists($page_filename))
                $page_source = create($page_filename);
            else
                $page_source = create();
        }
        // when a user tries to delete a document
        elseif ($action["command"] == "delete" && function_exists("delete"))
        {
            // if the deletion was successful, present a deletion success page
            if (delete($page_filename, $action["request"], $action["password"]))
            {
                $page_filename = $delete_document;
                // inject the name of the current page into the successful deletion document
                $dynamic_content["deleted_page"] = $action["request"];
            }
            else
            {
                $pageOutput[] = '<div class="error"><span>Could not delete the page. Do you have the correct password?</span></div>';
            }
        }
		// shareware version does not have the wiki feature, so return an error
		elseif ($action["command"] && (!function_exists("delete") || !function_exists("publish") || !function_exists("create") || !function_exists("delete")))
		{
			$page_filename = $error_general;
			$dynamic_content["error_output"] = '<div class="error"><span>Wiki functions are only available in the [tomo-dashi.itch.io/kiki]{registered version of kiki}.</span></div>';
		}
    }

    // try retrieving the page from the filename
    if (!isset($page_source))
        $page_source = file($page_filename);

    if ($page_source)
    {
        //////// Setup Page Variables and Text ///////////

        // setup empty variables
        $page_header = []; // store only the header area of the page in an array
        $page_content = []; // stores only the content area of the page as an array

        if (!$dynamic_content)
            $dynamic_content = [];

        // this will store each menu item
        $title = "";

        $extracted_page_data = extract_header_and_content($page_source);
        if (key_exists("header", $extracted_page_data))
            $page_header = $extracted_page_data["header"];
        if (key_exists("content", $extracted_page_data))
            $page_content = $extracted_page_data["content"];

        // make all header keys available as dynamic content to the page
        // this allows you to use keywords like $$title$$ in your content
        // to show the title of the page
        if ($page_header)
        {
            foreach ($page_header as $header_key=>$header_value)
            {
                $dynamic_content[$header_key] = parse_key($page_header, $header_key);
            }
        }

		// dynamic content is also extracted from $dynamic_variable_file (see settings.php)
		if (file_exists($dynamic_variable_file))
		{
			$dynamic_source = file($dynamic_variable_file);
			if ($dynamic_source)
			{
				$dynamic_source_data = extract_header_and_content($dynamic_source);
				if (key_exists("header", $dynamic_source_data))
				{
					$dynamic_header = $dynamic_source_data["header"];
					foreach ($dynamic_header as $header_key=>$header_value)
						$dynamic_content[$header_key] = parse_key($dynamic_header, $header_key);
				}
			}
		}

        // build a menu from the menuitems in the header
        $menu_items = [];
        $menuItems = parse_key($page_header, "menuitem");
        if (is_array($menuItems))
        {
            foreach($menuItems as $menu_item)
            {
                $menu_items[] = $menu_item;
            }
        }
		elseif ($menuItems)
			$menu_items[] = $menuItems;

        // store the title, used for the browser bar title and page title
        $title = parse_key($page_header, "title");

        // no title found in the header, so we use the page name instead
        if (!$title)
            $title = strip_filepath($page_filename, $pages_dir);

        $dynamic_content["title"] = $title;

		$dynamic_content["version"] = get_kiki_version();

        date_default_timezone_set($timezone);
   
        // get page time in H:i:s format (HH:mm:ss)
        $page_time_text = parse_key($page_header, "time");

        // when the page time hasn't been manually written in the header,
        // try to automatically grab it from the file modified time
        if ($page_time_text)
            $dynamic_content["time"] = $page_time_text;
        else
            $dynamic_content["time"] = get_file_modified_time($page_filename);

        // parse page date in Y-m-d format
        $page_date_text = parse_key($page_header, "date");
        if ($page_date_text)
        {
            // only fall back to the default page time
            // when it isn't found in the page header
            global $default_page_publication_time;
            if ($page_time_text)
                $time = $page_time_text;
            else
                $time = $default_page_publication_time;

            $page_datetime = create_timestamp($page_date_text, $time, "l F dS, Y");
            if ($page_datetime)
                $dynamic_content["date"] = $page_datetime;
        }

        // extract tags from header and store them as dynamic content
        $page_tags = get_tags($page_header);
        if ($page_tags)
        {
            $taglist = "";
            $current_tag_count = 0;
            $tagcount = count($page_tags);
            foreach ($page_tags as $tag)
            {
                $current_tag_count += 1;
                // build a comma-separated list of links to each tag
                $taglist .= build_tag_permalink($tag, $tag);
                // don't add a comma to the last item
                if ($current_tag_count < $tagcount)
                    $taglist .= ', ';
            }
            $dynamic_content["tags"] = $taglist;
        }

        // use a custom stylesheet when one is present in the header
        $custom_stylesheet = parse_key($page_header, "css");

        if ($custom_stylesheet)
            $stylesheet = $custom_stylesheet;

        // check for a custom markup format
        $markup_language = parse_key($page_header, "markup");

        ////////// Build the Page //////////
        // in editing mode, wraps all content in a textarea with a submit button
        if ($public_wiki && $action && function_exists("edit") && function_exists("create") && $action["command"] && ($action["command"] == "edit" || $action["command"] == "new"))
        {
            // dump the page source into a big string
            $page_text = implode($page_source);

            // injects an edit form into the page for both edit and new commands
            $pageOutput[] = edit($page_filename, strip_filepath($page_filename, $pages_dir), $page_text);
        }
        // in viewing mode, just render the page content to html
        else
        {
			// parse any dynamic content on the page
			if ($dynamic_content)
				$page_content = interpret_dynamic_content($page_content, $dynamic_content);

			// interpret all keywords used on the page
			$keyword_output = interpret_keywords($page_content);

            // when keyword output has been generated, replace the page content with that output
            if ($keyword_output)
                $page_content = $keyword_output;

            // there's no page content, generate an error
            if (!$page_content)
                $pageOutput[] = '<div class="error"><span>Page has no content yet. Did you forget to include a ((content)) kikitag in the page?</span></div>';

			// add the content to the page output
			$pageOutput = array_merge($pageOutput, $page_content);
        }

        // re-pack the content and header into an array for return
        $pageData = array("content" => $pageOutput, "header" => $page_header, "stylesheet" => $stylesheet, "title" => $title, "menu" => $menu_items, "interpreter" => $markup_language, "dynamic_content" => $dynamic_content);
        return $pageData;
    }
    else
        return false;
}

// loads an array of page filenames
// parses them for markup,
// and returns the html output of all the pages
// optional: build_entire_page: when true, builds a page with header and footer
// when false, builds only the content of the page
function get_pages($page_filepaths, $build_entire_page = true, $output_format = "html")
{
    // these variables are all found in the Settings file
    global $theme_stylesheet, $site_title;
    $stylesheet = $theme_stylesheet;
    
    if ($page_filepaths)
    {
        // store all html output as text
        $htmlOutput = "";
        // store all of the parsed pages glued together
        $pages_content = "";
		$pages_content .= '</div>'; // close outer page_content used in themes/page_layout.php
        foreach ($page_filepaths as $page_file)
        {
            // insert a title for each page shown
            $pages_content .= '<div class="page_title"><span>' . get_page_permalink($page_file, true) . '</span></div>';
			$pages_content .= '<div class="page_content">';
            $pages_content .= get_page($page_file, false, false, $output_format, false); // add the page to the html output
			$pages_content .= '</div>'; // close inner page_content
        }
        if ($build_entire_page)
            $htmlOutput = build_page($stylesheet, $site_title, NULL, $pages_content);
        else
            $htmlOutput = $pages_content;
    }
    return $htmlOutput;
}

// returns a single page permalink from its filename
// $withTitle: include the title of the page in the link description
// $withDate: include the page date (if available) in the link description
// $menuTitle: use the menutitle (if available) as the title
function get_page_permalink($page_filename, $withTitle = true, $withDate = false, $menuTitle = false, $outputFormat = "html")
{
    global $pages_dir;
    // grab the name of the page from the filename
    $pagename = strip_filepath($page_filename, $pages_dir);
    $pageheader = load_header($page_filename);
    if ($pageheader)
    {
        $separator = "";
        $date = "";
        $title = "";

        // handle options passed as parameters
        if ($withTitle)
        {
			// when requested, use the menutitle (if available) for the link description
            if ($menuTitle)
                $title = get_header_key($pageheader, "menutitle");

            // no menutitle available or requested, so just use the page title
			if ($title == "")
	            $title = get_header_key($pageheader, "title");

            // okay, still no title, let's just use the filename!
            if ($title == "")
                $title = $pagename;
        }
        if ($withDate)
            $date = get_header_key($pageheader, "date");
        
        if ($withTitle && $withDate && $date)
            $separator = " - ";
        elseif (!$withTitle && !$withDate)
            $title = $pagename;

		if ($outputFormat == "html")
	        $link = build_local_permalink($pagename, $date . $separator . $title);
		elseif ($outputFormat == "gopher" && function_exists("build_local_gopherlink"))
			$link = build_local_gopherlink($pagename, $date . $separator . $title);
        return $link;
    }
    // if document has no header, just return the filename
    elseif ($pagename)
    {
		if ($outputFormat == "html")
	        $link = build_local_permalink($pagename, $pagename);
		elseif ($outputFormat == "gopher" && function_exists("build_local_gopherlink"))
			$link = build_local_gopherlink($pagename, $pagename);
        return $link;
    }
    else
        return false;
}

// returns an array of all pages as links by their titles
function get_page_permalinks($page_filenames, $withTitles = true, $withDates = false, $menuTitle = false, $outputFormat = "html")
{
    $links = [];
    $page_headers = [];
    foreach ($page_filenames as $page_file)
    {
        $links[] = get_page_permalink($page_file, $withTitles, $withDates, $menuTitle, $outputFormat);
    }
    if ($links)
        return $links;
    else
        return false;
}

// searches through the pages folder (and all sub-directories)
// and returns permalinks to each page that has the passed tag
// in an array
// by default, includes the page's title
// optional: sort method, and include the dates and titles of each page
function get_page_permalinks_by_tag($tag = false, $sort = false, $withTitles = true, $withDates = false, $menuTitle = false, $outputFormat = "html")
{
    $pagesFound = get_pages_by_tag($tag, $sort);

    if ($pagesFound)
    {
        $permalinks = get_page_permalinks($pagesFound, $withTitles, $withDates, $menuTitle, $outputFormat);
        if ($permalinks)
            return $permalinks;
    }
    return false;
}

// extracts the tags from a page header
// and returns them as html output
// returns false if there are no tags found
// or when no header is supplied
function get_tags($pageheader)
{
    if ($pageheader)
    {
        $pagetags = get_header_key($pageheader, "tags");
        // extract comma-separated tags out and trim off any whitespaces
        $split_tags = array_map('trim', explode(",", $pagetags));
        if ($split_tags)
            return $split_tags;
    }

    return false;
}

// returns an array of filepaths to all pages that have 
// a specific tag in the header
// optional: supply a subdirectory to search under
function get_pages_by_tag($searchTags = false, $sort = false, $maximum = false)
{
    $pagesFound = get_all_page_filepaths($sort);

    // "all" is a special tag that returns the entire site's worth of pages
	// forgetting to include a search tag also returns all
    if ($searchTags == "all" || !$searchTags)
        return $pagesFound;
    else
    {
        // build a list of all pages found with the search tag
        $foundPages = [];
        // if the user has passed more than one tag, separate them here
        $searchForTags = array_map('trim', explode(",", $searchTags));
        foreach ($pagesFound as $page_filename)
        {
            $pageheader = load_header($page_filename);
            if ($pageheader)
            {
                $pagetags = get_tags($pageheader);
                if ($pagetags)
                {
                    foreach ($pagetags as $pagetag)
                    {
                        // when a page contains the tag, add it to the list of found pages
                        foreach ($searchForTags as $searchTag)
                        {
                            if ($pagetag == $searchTag)
                                $foundPages[] = $page_filename;
                        }
                    }
                }
            }
        }
		// when a maximum number of pages has been requested, 
		// return only up to that number of pages
		if ($maximum)
			$foundPages = array_slice($foundPages, 0, $maximum, true);

        return $foundPages;
    }
    return false;
}

// utility function to compare two pages, and return the newer one
function compare_pages_by_date($pageA, $pageB)
{
    // page A
    $pageA_source = file($pageA);
    $page_dataA = extract_header_and_content($pageA_source);
    $page_dateA = get_header_key($page_dataA['header'], "date");
    // page B
    $pageB_source = file($pageB);
    $page_dataB = extract_header_and_content($pageB_source);
    $page_dateB = get_header_key($page_dataB['header'], "date");
    // return comparison
    // this is really ugly because usort no longer supports returning bools
    if ($page_dateA == $page_dateB)
        return 0;
    elseif ($page_dateA < $page_dateB)
        return -1;
    else
        return 1;
}

// sorts pages by date
function sort_pages($pages, $sortStyle)
{
	if ($pages)
	{
		// sort in-place by date
		if ($sortStyle == "newest" || $sortStyle == "oldest")
		{
            usort($pages, "compare_pages_by_date");
			if ($sortStyle == "newest")
                $pages = array_reverse($pages);
		}
	}
	return $pages;
}

// returns all filepaths of the pages, if any, in the pages folder
// optional: $sort style, and $subdirectory of pages folder
// returns false on no pages found
function get_all_page_filepaths($sort = false, $subdirectory = false)
{
    global $pages_dir, $markup_file_extension, $bug_file_extension;
    if ($subdirectory)
        $directory = $pages_dir . $subdirectory . "/";
    else
        $directory = $pages_dir;

    // build a list of pages
	// important: always include the bug extension so help files are included
	// when a non-bug parser is in use

	// build a list of extensions to search for
	if ($markup_file_extension != $bug_file_extension)
		$file_extensions = "{" . $markup_file_extension . "," . $bug_file_extension . "}";
	else
		$file_extensions = "{" . $markup_file_extension . "}";

    $pages = recursive_glob($directory . "*." . $file_extensions, GLOB_BRACE);
    if ($pages)
    {
        // sort returned pages, when the user has requested it
        if ($sort)
            $pages = sort_pages($pages, $sort);

        return $pages;
    }
    else
        return false;
}

// searches for a page by its name, and returns
// the filepath of the page if found,
// returns false if no match
// important option: when $checkForBug = true,
// first tries to find the page
// using the existing markup interpreter
// file extension, and then falls back to
// using the bug extension
function search_page_filepath_by_name($pagename, $checkForBug = false)
{
	global $pages_dir, $markup_file_extension, $bug_file_extension;

	$foundFile = glob($pages_dir . $pagename . "." . $markup_file_extension);
	// nothing found, so fall back to searching for bug pages
	if (!$foundFile && $checkForBug && $markup_file_extension != $bug_file_extension)
		$foundFile = glob($pages_dir . $pagename . "." . $bug_file_extension);

	if ($foundFile)
		return $foundFile;
	else
		return false;
}

?>