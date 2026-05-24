<?php
// translates gopher requests into a browsable kiki gopherspace instance

// the gopher tags are all stored in the bug/tags.bug
$bug_tags = extract_bug_symbols();
extract($bug_tags);

// digging a gopher hole: generate all of the 
// gophermap-formatted pages using the source pages
// and write them to the appropriate directory
function dig_gopher_hole()
{
	// build a list of kiki-formatted pages
	global $gopher_generation_tags, $gopher_dir, $gopher_file_extension, $gopher_file_permissions;
	return generate_static_pages($gopher_generation_tags, $gopher_dir, $gopher_file_extension, $gopher_file_permissions, "gopher");
}

// builds a single gophermap page using a template
// and returns the complete rendered file source
// $pageData is page source that was parsed by load_page()
function build_gophermap($pageData)
{
	global $gophermap_template;

	if ($gophermap_template)
	{
		$gophermap_source = file($gophermap_template);
		$gophermap_pagedata = extract_header_and_content($gophermap_source);
		if ($pageData && $gophermap_pagedata)
		{
			$parsed_menu = build_gopher_menu($pageData["menu"]);
			$gophermap_parsed = parse_bug_to_gopher($gophermap_pagedata["content"]);
			$parsed_page_content = parse_bug_to_gopher($pageData["content"]);
			// unfortunately, due to the function returning an array of lines,
			// we need to convert them all to text
			$page_content = [];
			$page_content["content"] = implode($parsed_page_content["content"]);
			$page_content["footnotes"] = implode(PHP_EOL, $parsed_page_content["footnotes"]);
			$page_content["menu"] = implode(PHP_EOL, $parsed_menu);

			// add the parsed output to the dynamic content
			$dynamic_content = array_merge($pageData["dynamic_content"], $page_content);

			$parsed_lines = interpret_dynamic_content($gophermap_parsed["content"], $dynamic_content);

			// finally, glue together the rendered lines as text
			$output_text = implode($parsed_lines);

			// all gophermaps must terminate with a period on a line by itself.
			$output_text .= ".";

			return $output_text;
		}
	}
	return false;
}

// parses bug-formatted pages and returns gopher-formatted text
// in an array with two keys: "content" and "footnotes"
// both keys contain an array of parsed lines
function parse_bug_to_gopher($text)
{
	global $escapetag, $gophertags;
	extract($gophertags);
	$parsed_lines = [];
	$footnote_lines = [];

	// before any parsing can occur, all newline endings must be
	// converted to the current architecture's line ending
	$text = convertNewline($text);

	// parse multi-line text
	if (is_array($text))
	{
		$blockquoteOpen = false;
		$codeOpen = false;

		// insert the title of the page if present

		foreach ($text as $line)
		{
			// store a copy of the line for use later
			$unparsedLine = $line;

			// process multi-line tags and others that
			// require special processing

			// all html entities returned to ascii
			$line = html_entity_decode($line);

			// strip escape characters, preformatted
			$line = strip_bugtags($line, $escapetag["escape"][0], $escapetag["escape"][1]);

			// single line <blockquote>
			$detectBlockquoteTag = extract_text_from_tags($line, $blockquote[2], $blockquote[3]);
			// multiline <blockquote>
			$detectBlockquoteTagOpen = tag_exists($line, $blockquote[2]);
			$detectBlockquoteTagClose = tag_exists($line, $blockquote[3]);

			// single line <code>
			$detectCodeTag = extract_text_from_tags($line, $code[2], $code[3]);
			// multiline <code> 
			$detectCodeTagOpen = tag_exists($line, $code[2]);
			$detectCodeTagClose = tag_exists($line, $code[3]);

			// single line code-formatted text is replaced by <code> tags
			if ($detectCodeTag)
			{
				$codeOpen = true;
				$line = replace_bugtags($line, $code[2], $code[3], $code[0], $code[1]);
			}
			// multiline code is replaced by <pre><code>
			elseif ($detectCodeTagOpen && !$codeOpen)
			{
				$codeOpen = true;
				$line = replace_bugtags($line, $code[2], PHP_EOL, $code[0], PHP_EOL);
			}
			elseif ($detectCodeTagClose && $codeOpen)
			{
				$codeOpen = false;
				$line = replace_bugtags($line, $code[3], PHP_EOL, $code[1], PHP_EOL);
			}

			// single line blockquote
			if ($detectBlockquoteTag)
			{
				$blockquoteOpen = true;
				$line = replace_bugtags($line, $blockquote[2], $blockquote[3], $blockquote[0], $blockquote[1]);
			}
			// multiline blockquote
			elseif ($detectBlockquoteTagOpen && !$blockquoteOpen)
			{
				$blockquoteOpen = true;
				$line = replace_bugtags($line, $blockquote[2], PHP_EOL, $blockquote[0], PHP_EOL);
			}
			elseif ($detectBlockquoteTagClose && $blockquoteOpen)
			{
				$blockquoteOpen = false;
				$line = replace_bugtags($line, $blockquote[3], PHP_EOL, $blockquote[1], PHP_EOL);
			}

			// do not parse lines wrapped in <code>
			if (!$codeOpen)
				$parsed_line = line_to_gopher($line);
			else
				$parsed_line["line"] = $line;

			// if the parsing results in a completely empty line, delete the line
			if ($line != $unparsedLine && ($line == PHP_EOL || $line == ""))
				$parsed_line["line"] = NULL;

			$parsed_lines[] = $parsed_line["line"];
			if (array_key_exists("footnotes", $parsed_line))
				$footnote_lines = array_merge($footnote_lines, $parsed_line["footnotes"]);
		}
	}
	// parse single line of text
	else
	{
		$parsed_line = line_to_gopher($text);
		$parsed_lines[] = $parsed_line["line"];
		$footnote_lines[] = array_merge($footnote_lines, $parsed_line["footnotes"]);
	}

	return ["content" => $parsed_lines, "footnotes" => $footnote_lines];
}

// parses a single bug-formatted line to gophermap text
// optional: footnote_lines contains an array of lines to add at the end
function line_to_gopher($line)
{
    // put all of the $tags generated by extract_bug_symbols
    // into the local scope
	// this allows $code, $pre to exist in this scope
	global $gophertags, $imagetag, $gophertab, $gopherhr, $linktag, $escapetag;
	extract($gophertags);

	// the gophermap is a menu created on the fly by the source content
	// one line of text may end up generating multiple gophermap lines
	$footnote_lines = [];

	if ($line != PHP_EOL && $line !== "")
	{
		do
		{
			// store a copy before modifying
			$unmodifiedString = $line;

			// convert html breaks into newlines
			$line = str_replace("<br>", PHP_EOL, $line);

			// remove pre tags
			$line = str_replace($pre[2], PHP_EOL, $line);
			$line = str_replace($pre[3], PHP_EOL, $line);

			// strip escape characters
			$line = strip_bugtags($line, $escapetag["escape"][0], $escapetag["escape"][1]);

			// tabs must be replaced with spaces, because gopher
			// uses tabs as a data delimiter
			$line = str_replace($gophertab["tab"][1], $gophertab["tab"][0], $line);

			// add horizontal rules when present (includes line spacing)
			$line = str_replace($gopherhr["hr"][1], $gopherhr["hr"][0], $line);

			// parse image tags
			// turns inline images into footnotes at the bottom of the page
			do
			{
				$image = extract_text_from_tags($line, $imagetag["url"][0], $imagetag["url"][1]);
				if ($image)
				{
					$imageAlt = extract_text_from_tags($line, $imagetag["name"][0], $imagetag["name"][1]);
					$imagePieces = count(explode(".", $image));
					if ($imagePieces > 1)
						$image = build_remote_gopherlink($image, $imageAlt);
					else
						$image = build_local_gopherlink($image, $imageAlt);
					// image removed from line and replaced with just the image description
					$line = replace_tags($line, $imagetag["url"][0], $imagetag["url"][1], "");
					$line = replace_tags($line, $imagetag["name"][0], $imagetag["name"][1], $imageAlt);
					$footnote_lines[] = $image;
				}
			} while ($image != false);

			// parse link tags
			// turns inline links into footnotes at the bottom of the page
			do
			{
				$link = extract_text_from_tags($line, $linktag["url"][0], $linktag["url"][1]);
				if ($link)
				{
					$linktitle = extract_text_from_tags($line, $linktag["name"][0], $linktag["name"][1]);

					// no link title specified, just use the link as its name
					if (!$linktitle)
						$linktitle = $link;

					$linkPieces = count(explode('.', $link));
					// a remote link has a 2nd and top-level domain at the very least
					if ($linkPieces > 1)
						$link = build_remote_gopherlink($link, $linktitle);
					else
						$link = build_local_gopherlink($link, $linktitle);

					// hyperlinks are replaced with just the link title text
					$line = replace_tags($line, $linktag["url"][0], $linktag["url"][1], $linktitle);
					$line = replace_tags($line, $linktag["name"][0], $linktag["name"][1], "");
					$footnote_lines[] = $link;
				}
			} while ($link != false);

			// all simple <tag></tag> pairs processed here
			foreach ($gophertags as $tag)
			{
				// following the tag format of tagname:<tag>:</tag>:start_symbol:end_symbol
				$line = replace_bugtags($line, $tag[2], $tag[3], $tag[0], $tag[1]);
			}

			// finally, strip any leftover html and/or php tags
			$line = strip_tags($line);

			// if the parsing results in a completely empty line, delete the line
			if ($line == PHP_EOL || $line == "")
			{
				$line = NULL;
				break;
			}

		} while ($unmodifiedString !== $line);
	}

	// return an array with two keys: "line" and "footnotes"
	return ["line" => $line, "footnotes" => $footnote_lines];
}

// checks a link for a MIME type
// returns the gopher indicator based on an
// educated guess about what the file contains
// returns false if no indicator found
function get_indicator_by_mimetype($link)
{
	if ($link && file_exists($link))
	{
		$indicator = false;

		// build a list of common MIME types
		// the indicator value is the gopher indicator type
		$file_types = [];
		// text
		$file_types[] = ["indicator" => "0", "mimetype" => ["text/plain"]];
		// gif
		$file_types[] = ["indicator" => "g", "mimetype" => ["image/gif"]];
		// images
		$file_types[] = ["indicator" => "I", "mimetype" => ["image/"]];
		// html
		$file_types[] = ["indicator" => "h", "mimetype" => ["text/html"]];
		// sounds
		$file_types[] = ["indicator" => "s", "mimetype" => ["audio/"]];
		// binary
		$file_types[] = ["indicator" => "9", "mimetype" => ["application/octet-stream"]];
		// video
		$file_types[] = ["indicator" => ";", "mimetype" => ["video/"]];

		// loop through every mime type and if one is found
		// return its appropriate indicator.
		$file_mimetype = mime_content_type($link);
		if ($file_mimetype)
		{
			foreach ($file_types as $file_type)
			{
				if (strpos($file_mimetype, $file_type["mimetype"]) !== false)
				{
					$indicator = $file_type["indicator"];
					return $indicator;
				}
			}
		}
	}
	return false;
}

// returns a gopher-parsed menu
// from a series of menu items
// or builds it using the default menu template
// or auto-adds all pages
function build_gopher_menu($override_menu_items = NULL)
{
	global $navmenu, $menu_auto_add_pages, $pages_dir, $rss_enabled, $rssMenuLink, $markup_file_extension, $current_page;
	$menu_items = [];

	    // when menu items are supplied as a parameter, we override the default
    // menu items for this page or post
    if ($override_menu_items)
    {
        $menu_items = $override_menu_items;
    }
    // auto-add pages when this has been set in Settings
    elseif ($menu_auto_add_pages)
    {
		global $menu_auto_add_tags;
		// grabs the permalink for the page using the menutitle if available
        $pages = get_page_permalinks_by_tag($menu_auto_add_tags, false, true, false, true, "gopher");
        foreach ($pages as $pagelink)
        {
            $menu_items[] = $pagelink;
        }

        // auto-add rss menu item
        if ($rss_enabled)
        {
            $rssmenu =  "[" .$rssMenuLink. "]" . "{RSS}";
            $menu_item = parse_bug_to_gopher($rssmenu);
            $menu_items[] = $menu_item;
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
			 $menu_items[] = parse_bug_to_gopher($keyword_line);
		 }
	 }
	 return $menu_items;
}

// builds a gopherlink from a link, indicator and title
// returns a fully-formatted gopher entry
function build_gopherlink($link, $indicator, $title)
{
	// when an indicator type hasn't been supplied, or is
	// a directory based on the trailing slash,
	// just say that it's a remote server submenu/directory
	if ((substr($link, -1) == "/") || !$indicator)
		$indicator = "1";

	// build the gopherlink in gopher format (using tab as delimiter)
	// trim the title in case an EOL snuck in
	$link = $indicator . trim($title) . "	" . $link;
	return $link;
}

// checks a remote/external hyperlink for certain file extensions 
// and returns a gophermap entry
function build_remote_gopherlink($link, $linktitle)
{
	// we begin by check the link's mime type
	// (if any) to determine the correct indicator
	$indicator = get_indicator_by_mimetype($link);

	// no indicators found for mime type, so let's try URIs next
	if (!$indicator)
	{
		// check for a protocol
		$checkurlhttp = strpos($link, "http://") || strpos($link, "https://");
		$checkurltelnet = strpos($link, "telnet://");
		if ($checkurlhttp !== false)
			$indicator = "h";
		elseif ($checkurltelnet !== false)
			$indicator = "8";
	}

	$link = build_gopherlink($link, $indicator, $linktitle);
	return $link;
}

// checks a local/internal hyperlink for certain file extensions 
// and returns a gophermap entry
function build_local_gopherlink($link, $linktitle)
{
	global $gopher_dir;
	// strip off named anchor hyperlinks
	$anchor = explode("#", $link);
	if (isset($anchor[0]) && isset($anchor[1]))
	{
		// strip the anchor part off
		if ($anchor[0] != "")
			$link = $anchor[0];
		// there was only an anchor, so the link is empty
		else
			$link = "";
	}

	// no title supplied; just use the link itself
	if (!$linktitle)
		$linktitle = $link;

	// replace all spaces with underscores
	$link = str_replace(" ", "_", $link);

	// all internal gopher links use the gopher base dir
	$file_path = $gopher_dir;

	// try to set the indicator by file extension first
	$indicator = get_indicator_by_mimetype($link);

	$link = build_gopherlink($link, $indicator, $linktitle);

	return $link;
}

// checks link for common file extensions

// strips any escape characters found in source text
// the second tag is optional; when missing, uses EOL
function strip_bugtags($line, $starttag, $endtag = PHP_EOL)
{
	do 
	{ 
		$tagtext = extract_text_from_tags($line, $starttag, $endtag);
		if ($tagtext)
		{
			if ($endtag === "PHP_EOL")
				$line = replace_tags($line, $starttag, $endtag, $tagtext . PHP_EOL);
			else
				$line = replace_tags($line, $starttag, $endtag, $tagtext);
		}
	} while ($tagtext != false);
	return $line;
}

?>