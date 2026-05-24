<?php
// static site generation
// when this page is loaded in the browser, 
// and static_generation = true (in Settings)
// it will generate the entire site as html files
// in the static_pages_dir folder set in settings

// site owner has turned on statically-generated HTML, so let's do it.
// returns a summary page showing successes or failures
function generate_static_site()
{
	// build a list of kiki-formatted pages
	global $static_generation_tags, $static_pages_dir, $html_file_extension, $static_generation_permissions;
	
	return generate_static_pages($static_generation_tags, $static_pages_dir, $html_file_extension, $static_generation_permissions, "html");
}

// generates all of the desired pages that match $tags
// and returns a summary page showing the success/failures
function generate_static_pages($tags, $destination_dir, $file_extension, $file_permissions, $page_output_format)
{
	global $generate_file;
	$pages = get_pages_by_tag($tags);
    if ($pages)
    {
        $page_count = 0;
		$failed_count = 0;
        foreach ($pages as $page_file)
        {
			if (generate_static_page($page_file, $destination_dir, $file_extension, $file_permissions, $page_output_format))
	            $page_count++;
			else
				$failed_count++;
        }
        $dynamic_content["generated_num_pages"] = "<p>Success generating: " . $page_count . " pages.</p> <p>Failed generating: " . $failed_count . " pages.</p>";
    }

	// when enabled, copies the current theme to the output directory
    // original code by @marlena@kif.rocks - thank you!
	global $static_generation_copy_theme, $theme_dir, $theme;
	if ($static_generation_copy_theme)
	{
		if (!file_exists($destination_dir . $theme_dir))
			mkdir($destination_dir . $theme_dir);
		
		if (is_dir($destination_dir . $theme_dir))
			recursive_copy($theme_dir . $theme, $destination_dir . $theme_dir . $theme);
	}

    $outcome_page = get_page($generate_file, false, $dynamic_content);

    return $outcome_page;
}

// prepares the correct filepath for a static page, and generates it, returning
// the success or failure of its file write procedure
function generate_static_page($filepath, $destination_dir, $file_extension, $file_permissions, $page_output_format)
{
    global $pages_dir, $current_page;
	// set the global current page so permalinks are formed correctly
	$current_page = strip_filepath($filepath, $pages_dir);

	// grab the page content in the desired format
    $file_source_text = get_page($filepath, false, false, $page_output_format);
	if ($file_source_text)
	{
		$filepath = strip_filepath($filepath, $pages_dir);
		$filepath = $destination_dir . $filepath;

		return write_text_file($filepath, $file_source_text, $file_extension, $file_permissions);
	}
	else
	{
		// sorry, page couldn't be loaded.
		$current_page = NULL;
		return false;
	}
}

// writes a text file to local storage using the
// filepath, source text, and desired file extension and
// permissions of the destination file.
function write_text_file($filepath, $file_source_text, $file_extension, $file_permissions)
{
    // add the html file extension to the filename
    $filename = $filepath . "." . $file_extension;

	// recursively create static generation sub-folders if necessary
	if (!file_exists(dirname($filename)))
		mkdir(dirname($filename), $file_permissions, true);

    // create/overwrite the file in the same folder as the source file
	$newfile = fopen($filename, "w");
	// this is SUCH an ugly hack, but is 100% necessary
	// the BOM \xEF\xBBxBF creates a utf8 header for the file
	// source: https://stackoverflow.com/questions/4839402/how-can-i-write-a-file-in-utf-8-format
	if (fwrite($newfile, "\xEF\xBB\xBF".$file_source_text))
	{
		fclose($newfile);
		return true;
	}
	else
	{
		echo '<p class="error">Error: ' .$filename . ' was not writable.';
		return false;
	}
}

?>