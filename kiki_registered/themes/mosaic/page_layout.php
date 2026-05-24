<?php
    global $current_page, $theme_basename, $public_wiki, $site_contact;
    // a basic one-column layout
    $htmlOutput = '<div id="container">';
    $htmlOutput .= '<div id="navbar">';
    $title = "https://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
    if ($title)
        $htmlOutput .= '	<div id="location"><span>Location:</span><div class="title"><span>' . $title . '</span></div></div>';
    $htmlOutput .= '<div id="navmenu">';
    // add each navmenu item to the navmenu
    foreach ($menu_items as $menu_item)
    {
        $htmlOutput .= '<div class="navmenu-item"><span>' . $menu_item . '</span></div>';
    }
    $htmlOutput .= '</div>'; // close navmenu
    $htmlOutput .= '<div class="separator"></div>'; 
    $htmlOutput .= '</div>'; // close navbar
    // only show the edit link when wiki mode is enabled
    if ($current_page && $public_wiki)
    {
        $htmlOutput .= '    <div id="page_menu">';
        $edit_link = build_local_permalink($current_page, "Edit Page", "edit");
        $htmlOutput .= '<div class="navmenu-item"><span class="edit_button">' . $edit_link . '</span></div>'; // add edit option at top of page
        $htmlOutput .= '    </div>'; // close page_menu
    }
    $htmlOutput .= '    <div id="content">';
	$htmlOutput .= '	<div class="page_content">';
    $htmlOutput .= $content;
    $htmlOutput .= '<div class="contact_icon"><a href=' . $site_contact . '><img src="' . $theme_basename . '/mail.png"></a></div>';
	$htmlOutput .= '</div>'; // close content
	$htmlOutput .= '</div>'; // close page_content
	$htmlOutput .= '</div>'; // close container
    return $htmlOutput;
?>