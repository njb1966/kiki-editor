<?php
    // a basic one-column layout
    global $site_title, $current_page, $public_wiki, $theme_basename;
    $htmlOutput = '<div id="container">';
    $htmlOutput .= '<div id="navbar">';
    $htmlOutput .= '<div id="navmenu">';
    // add each navmenu item to the navmenu
    foreach ($menu_items as $menu_item)
    {
        $htmlOutput .= '<div class="navmenu-item"><span>' . $menu_item . '</span></div>';
    }
    $htmlOutput .= '</div>'; // close navmenu
    // right side of navmenu
    $menu_icon_img = $theme_basename . "/images/icon-editpage.png";

    $sitelink = build_local_permalink($current_page, "<span>" . $site_title . "</span><img src=$menu_icon_img>");
    $htmlOutput .= '<div id="navmenu-right"><div class="navmenu-item">' . $sitelink . '</div></div>';
    $htmlOutput .= '<div class="separator"></div>'; 
    $htmlOutput .= '</div>'; // close navbar
    $htmlOutput .= '    <div id="desktop">'; // setup the desktop
    $htmlOutput .= '    <div class="window">'; // wrap all content in a window
    $htmlOutput .= '    <div class="window-titlebar"><span>'.$title.'</span></div>'; // titlebar within window
    $htmlOutput .= '    <div class="separator"></div>'; // separate titlebar from content
    // only show the edit link when wiki mode is enabled
    if ($current_page && $public_wiki)
    {
        $htmlOutput .= '    <div id="window-menu">';
        $edit_icon_img = $theme_basename . "/images/icon-editpage.png";
        $edit_link = build_local_permalink($current_page, "<img src=$edit_icon_img><span class=icon_title>Edit Page</span>", "edit");
        $htmlOutput .= '    <span class="icon-small">' . $edit_link . '</span>'; // add edit option at top of page
        $htmlOutput .= '    </div>'; // close window-menu
    }
    $htmlOutput .= '    <div id="content">';
	$htmlOutput .= '	<div class="page_content">';
    $htmlOutput .= $content;
	$htmlOutput .= '</div>'; // close content
	$htmlOutput .= '</div>'; // close page_content
	$htmlOutput .= '</div>'; // close container
	return $htmlOutput;
?>