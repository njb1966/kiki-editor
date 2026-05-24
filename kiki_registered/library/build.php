<?php
// functions used for building html pages

// builds a menu from either an array of menu items sent as a parameter
// or builds them from navmenu.bug by default
function build_menu($override_menu_items = NULL)
{
    // load the navmenu from settings
    global $navmenu, $menu_auto_add_pages, $pages_dir, $rss_enabled, $rssMenuLink, $markup_file_extension, $public_wiki, $current_page;

    $menu_items = [];

    // when menu items are supplied as a parameter, we override the default
    // menu items for this page or post
    if ($override_menu_items)
    {
        if ($override_menu_items !== true)
            $menu_items = $override_menu_items;
        // if a 'true' is supplied to override, skip the menu altogether
        else
            return $menu_items;
    }
    // auto-add pages when this has been set in Settings
    elseif ($menu_auto_add_pages)
    {
        global $menu_auto_add_tags;
		// grabs the permalink for the page using the menutitle if available
        $pages = get_page_permalinks_by_tag($menu_auto_add_tags, false, true, false, true);
        foreach ($pages as $pagelink)
        {
            $menu_items[] = $pagelink;
        }

        // auto-add rss menu item
        if ($rss_enabled)
        {
            $rssmenu =  "[" .$rssMenuLink. "]" . "{RSS}";
            $menu_item = parse_bug_markup($rssmenu);
            $menu_items[] = $menu_item;
        }
        // when public edits are allowed, add an Edit link to the menu
        if ($public_wiki)
        {
            if ($current_page)
                $editlink = build_local_permalink($current_page, "Edit Page", "edit");
            $menu_items[] = $editlink;
        }
    }
    // load the default menu items from the default nav menu
    elseif ($navmenu)
    {
        $navmenu_source = file($navmenu);

        // this is ugly because i'm working around a limitation
        // where header keys MUST be unique when using extract_header_and_content.
        $extracted_header = extract_header_and_content($navmenu_source)["header"];

        // process any keywords found in the header, and if any found, 
        // add their output to the menu
        $keyword_output = interpret_keywords($extracted_header);

        foreach ($keyword_output as $keyword_line)
        {
            $menu_items[] = parse_bug_markup($keyword_line);
        }
    }
    return $menu_items;
}

// builds the html output from the fully parsed kiki page
function build_page($stylesheet, $title, $menu_items, $content)
{
    // build the page
    $page_output = build_page_header($stylesheet);
    $page_output .= build_page_content($title, $menu_items, $content);
    $page_output .= build_page_footer(); // note: the footer is nested within the content div
    $page_output .= build_document_end(); // close all content

    return $page_output;
}

// build the content area of the page
function build_page_content($title, $menu_items, $content)
{
    global $theme_content_layout, $active_theme_dir;
    
    // build the menu using the menu items received
    $menu_items = build_menu($menu_items);
    $content_output = "<body>";
    // load theme's content layout, set in settings.php
    $content_output .= include($theme_content_layout);

    return $content_output;
}

// builds the header
function build_page_header($stylesheet)
{
    // it absolutely sucks to have to use an absolute url here, but this is necessary
    // when using nginx rewrite rules for "easy" permalinks.
    $style_base_url = get_site_basename("easy");
    $stylesheeturl =  $style_base_url . $stylesheet;

    $header_output = '<!DOCTYPE html>';
    $header_output .= '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=0" />';    
    $header_output .= '<html>';
    $header_output .= '<head>';
    $header_output .= '<link rel="stylesheet" type="text/css" href="'.$stylesheeturl.'">';
    $header_output .= '</head>';

    return $header_output;
}

// builds end of document
function build_document_end()
{
    $footer_output = "</div>"; // close content
    $footer_output .= "</body>";
    $footer_output .= '</html>';
    return $footer_output;
}

// build the footer
function build_page_footer()
{
    global $footer_file, $show_footer;
    if ($show_footer)
    {
        $footer_output = '<div class="footer">';
        $footer_source = file($footer_file);
        $footer_output .= parse_bug_markup($footer_source);
        $footer_output .= "</div>"; // close footer
        return $footer_output;
    }
}

// builds a link to pages that match a specified tag
// $tag refers to the name of the tag
function build_tag_permalink($tag, $linktitle)
{
    global $tag_permalink_base;
    $link = '<a href="' . $tag_permalink_base . $tag . '">' . $linktitle . '</a>';
    return $link;
}

// links to pages can be created just from
// the page name
// action (optional): specifies a "command" action to perform
function build_local_permalink($linkname, $linktitle, $action = false)
{
    global $static_generation, $page_permalink_base, $html_file_extension, $public_wiki, $current_page, $bug_tags;
	$linktags = $bug_tags["linktag"];

    $linkaction = "";

    // if a named anchor link is used, strip that off the linkname
    // so we can find the right filename
    $anchor = explode("#", $linkname);
    if (isset($anchor[0]) && isset($anchor[1]))
    {
        if ($anchor[0] != "")
            $linkname = $anchor[0];
        else
            // when the url only contains an anchor, it is an internal page anchor
            $linkname = $current_page;

        $named_anchor = $anchor[1];
    }

    // when no linktitle is supplied, use the linkname as the title instead
    if (!$linktitle)
        $linktitle = $linkname;

    // replace all spaces with underscores
    // note: must happen *after* title has been created from linkname
	if ($linkname)
	    $linkname = str_replace(" ", "_", $linkname);

    // all URIs begin with the permalink base
    $file_path = $page_permalink_base;

    // when an action is specified, add it to the uri
    if ($action)
        $linkaction = "&command=" .$action;

    // tack on .html/.htm to NON-folders in static mode
    // (a trailing slash suggests it is a folder)
    // courtesy of @marlena@kif.rocks
    if ($static_generation && substr($linkname, -1) != "/")
        $linkname .= "." . $html_file_extension;

    // if an anchor was found, tack it back on
    if (isset($named_anchor))
    {
        $linkname .= '#' . $named_anchor;
    }

    // build the complete link
    $link = '<a href="' . $file_path . $linkname . $linkaction . '">' . $linktitle . '</a>';
    return $link;
}
?>