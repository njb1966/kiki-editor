<?php
/////////// Basic Settings ///////////
// set this to your local timezone, used to calculate post date/time
// refer to the timezones here for a list of acceptable location words:
// https://www.php.net/manual/en/timezones.php
$timezone = "America/Inuvik";

// Important: rewrite these to personalize your site.
$site_title = "My Homepage";
$site_description = "All About Me";
// link to contact the site owner, can be mailto or url
$site_contact = "mailto:myname@mysite.com";

// default page to load when none has been specified
// use the name of a single page (e.g. "home")
// or to use comma-separated tags: $default_page = "tags=post,dogs,cats"
// and to sort with tags: $default_page = "tags=post&sort=newest"
$default_page = "home";

// offline/maintenance mode
// when you want to work on the site and not have visitors
// viewing pages, set this to true.
$site_offline = false;

/////////// Themes //////////
// Themes let you design the layout of the site
// however you want, using a combination of two files:
// content.php which contains the content layout of pages
// and style.css which contains the CSS styling for pages
// to change the theme to single column, set $theme = "onecolumn"
$theme = "onecolumn";

// when true, the footer layout file is shown at the bottom of each page
$show_footer = false;

/////////// Advanced Settings ////////////

/////////// Wiki Support //////////
// public wiki editing mode
// make your kiki into a wiki!
// when enabled, an Edit link appears on every
// page, and allows people to create new pages
$public_wiki = false;

// when public wiki mode is enabled, you can
// set a password for all edits
// this password must be entered whenever a page is edited
// in the web interface
// when set to false, no password is required for edits
$wiki_password = "wiki";

// when true, users can delete files from the wiki using the edit form
// note: this is potentially dangerous if you make your wiki public!
$wiki_delete_allowed = false;

// when file upload support is enabled,
// a file upload field is available on the wiki edit form
$wiki_upload_allowed = false;

/////////// Markup Interpreters //////////
// you can use whatever php-based markup interpreter you'd like
// default: "bug", and it comes built-in to kiki
// other options can be installed in /plugins/

// options:
// michel fortin's php-markdown: "php-markdown";
// michel fortin's php-markdown Extra: "php-markdown-extra";
// parsedown.org's markdown: "parsedown";
$markup_interpreter = "bug";

/////////// Menus //////////////

// when set to true, kiki uses $menu_auto_add_tags to decide which
// pages to add to the menu.
// when set to false, you must manually add pages to menus/navmenu.bug
$menu_auto_add_pages = true;

// when menu_auto_add_pages is enabled, you can set which pages to
// automatically add to the menu here. by default, it is set to "all"
// which **includes** the help pages. you can change this behaviour
// by selecting specific pages using a tag here
// to use multiple tags, separate them with a comma
$menu_auto_add_tags = "page, post";

//////////// Permalinks ///////////
// there are a couple of different options for displaying permalink urls
// on your pages. the most easy-to-read is using the name of
// the page as the url, e.g. https://mydomain.com/homepage/
// this option is called "easy": $permalink_style = "easy";
// the other options is to show the entire http request in the url
// in the classic 1990s style, 
// e.g. https://mydomain.com/index.php?page=homepage
// this option is called 'classic': $permalink_style = "classic";
$permalink_style = "classic";

/////////// RSS Settings //////////
// when enabled (true): generates an rss feed from pages in the pages 
// folder, as rss.xml. false: does not generate an rss feed
$rss_enabled = false;

// Limit the number of items added to the rss feed.
// By default, this is set to 15.
$rss_item_limit = 15;

// Sort the rss feed by date.
// default is "newest" for newest to oldest
// also available: "oldest" for oldest to newest
// and false (no quotes): do not sort
$rss_sort_style = "newest";

// This should describe your RSS feed for your pages
// by default, they are set to your site title/description, but you can
// rewrite these to whatever you want.
$rss_title = $site_title;
$rss_description = $site_description;

// the rss feed is populated by posts that include the $rss_tag. 
// for example, $rss_tags = "post" will only add pages that have the "post"
// tag added to their header
// optional: if you want to display ALL pages (that could include the help
// pages unless you delete them!), set $rss_tags = "all";
// otherwise, include multiple tags by adding a comma between them
$rss_tags = "post";

// sets the filename used for the generated rss feed
$rss_file = "rss.xml";

// manually set the publication time for all posts
// this is sometimes necessary for misbehaving RSS readers 
// uses 24-hour notation in HH:mm:ss format
$default_page_publication_time = "08:00:00";

//////////// Static Site Generator /////////////
// kiki can run in SSG mode, which lets it barf out static html
// instead of serving each page via dynamic php generation

// static site generator mode
// when you want kiki to generate a bunch of static html files
// instead of serving pages dynamically
$static_generation = false;

// by default, all pages are generated *including the help files*
// if you'd like to only generate a subset of pages, give each
// page a tag, and use that tag here.
$static_generation_tags = "all";

// when static generation mode is set, the site's base url MUST be set
// you *must* have a trailing slash at the end of the url
$site_base_url = "https://my.domain.com/mysite/";

// static sites generate html files. you can set the file extension
// used for the generated files. set this to either htm or html, 
// or whatever file extension you prefer
$html_file_extension = "html";

// the static generation directory and its sub-directories
// get automatically created with these folder permissions
// note: do NOT wrap this number in quotes!
$static_generation_permissions = 0755;

// by default, the current theme folder is not copied to the 
// $static_pages_dir output folder during generation.
// to have the current them added to the output, 
// set $include_theme = true;
$static_generation_copy_theme = false;

////////// Gopher Generation ///////////

// when true, parses pages into gophermaps
// in the gopher_dir output directory
$gopher_enabled = false;

// set the desired file extension of gophermaps
$gopher_file_extension = "gophermap";

// set desired file permissions for gophermaps
$gopher_file_permissions = 0755;

// by default, all pages are generated *including the help files*
// if you'd like to only generate a subset of pages, give each
// page a tag, and use that tag here.
$gopher_generation_tags = "all";

////////// Content Locations ///////////
// here you can customize any of the sub-folders for content
// IMPORTANT note: trailing slashes are required on *all*
// directory paths.

$theme_dir = "themes/";
$theme_content_layout = $theme_dir . $theme . "/page_layout.php";
$theme_stylesheet = $theme_dir . $theme . "/style.css";

// set this variable to you wherever you installed the kiki library, *RELATIVE TO* this file
// by default the kiki library is in ./library
$library_dir = "library/";

// file area directory for uploads
// when wiki uploads are enabled
$file_area_dir = "files/";

// gopher service page output directory
$gopher_dir = "gopher/";

// path to your kiki-formatted pages *RELATIVE TO* this file,
// by default, it assumes your pages are in ./pages
$pages_dir = "pages/";

// path to kiki's built-in online help files
$help_dir = $pages_dir . "help/";

// layout pages are used for adding elements to the page,
// like the footer and 404 pages
$layout_dir = "layout/";

// plugins directory
$plugin_dir = "plugins/";

// rss directory
// by default, uses the site's root folder
$rss_dir = "./";

// error and layout pages
$error_404 = $layout_dir . "404.bug";
$error_503 = $layout_dir . "503.bug";
$error_general = $layout_dir . "error.bug";
$footer_file = $layout_dir . "footer.bug";
$generate_file = $layout_dir . "generate.bug";
$page_template = $layout_dir . "page_template.bug";
$gophermap_template = $layout_dir . "gophermap_template.bug";
$delete_document = $layout_dir . "delete.bug";
$dynamic_variable_file = $layout_dir . "dynamic.bug";
$rss_document = $rss_dir . $rss_file;

// bug markup location
$bug_dir = $library_dir . "bug/";
$bug_syntax_document = $help_dir . "bug_syntax.bug";

// default navigation menu file
$navmenu_dir = "menus/";
$navmenu = $navmenu_dir . "navmenu.bug";

// set the *output directory* of statically generated pages
// by default, they're all dumped into the root of your webspace
// but they can be also set to specific folders, e.g. "/pages"
$static_pages_dir = "generated/";

?>