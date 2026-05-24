<?php
// a basic one-column layout
    global $current_page, $public_wiki, $theme_basename;
    $htmlOutput = '<div id="container">';
    $htmlOutput .= '<div id="navmenu">';
    // add each navmenu item to the navmenu
    foreach ($menu_items as $menu_item)
    {
        $htmlOutput .= '<span class="decoration">[</span><div class="navmenu-item"><span>' . $menu_item . '</span></div><span class="decoration">]</span>';
    }
    $htmlOutput .= '</div>'; // close navmenu
    // only show the edit link when wiki mode is enabled
    if ($current_page && $public_wiki)
    {
        $htmlOutput .= '    <div id="page_menu">';
        $edit_link = build_local_permalink($current_page, "Edit Page", "edit");
        $htmlOutput .= '<span class="decoration">[</span><span class="edit_button">' . $edit_link . '</span><span class="decoration">]</span>'; // add edit option at top of page
        $htmlOutput .= '    </div>'; // close page_menu
    }
    if ($title)
        $htmlOutput .= '	<div class="title"><span>' . $title . '</span></div>';
    $htmlOutput .= '    <div id="content">';
	$htmlOutput .= '	<div class="page_content">';
    $htmlOutput .= $content;
	$htmlOutput .= '</div>'; // close content
	$htmlOutput .= '</div>'; // close page_content
	$htmlOutput .= '</div>'; // close container
    return $htmlOutput;
?>