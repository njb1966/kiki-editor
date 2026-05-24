<?php
    // a basic two-column layout
    global $current_page, $public_wiki, $theme_basename;
    $htmlOutput = '<div id="container">';
    $htmlOutput .= '<div id="navmenu">';
    // add each navmenu item to the navmenu
    foreach ($menu_items as $menu_item)
    {
        $htmlOutput .= '<div class="navmenu-item"><span>' . $menu_item . '</span></div>';
    }
    $htmlOutput .= '</div>'; // close navmenu
    $htmlOutput .= '    <div id="content">';
	$htmlOutput .= '	<div class="page_content">';
    if ($title)
        $htmlOutput .= '	<div class="title"><span>' . $title . '</span></div>';
    // only show the edit link when wiki mode is enabled
    if ($current_page && $public_wiki)
    {
        $htmlOutput .= '    <div id="page_menu">';
        $edit_link = build_local_permalink($current_page, "<span class=edit_button>Edit Page</span>", "edit");
        $htmlOutput .= $edit_link; // add edit option at top of page
        $htmlOutput .= '    </div>'; // close page_menu
    }
    $htmlOutput .= $content;
	$htmlOutput .= '</div>'; // close content
	$htmlOutput .= '</div>'; // close page_content
	$htmlOutput .= '</div>'; // close container
    return $htmlOutput;
?>