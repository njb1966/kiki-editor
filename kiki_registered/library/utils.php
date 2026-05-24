<?php
// kikiutils - used by kikimarkup and kikirss

///////////////////Time////////////////////////////

// returns the file creation time, only on Mac OS X
function get_file_creation_time_mac($filename)
{
	if ($handle = popen('stat -f %B ' . escapeshellarg($filename), 'r')) {
	    $btime = trim(fread($handle, 100));
	    return date('h:i:s A', $btime);
	    pclose($handle);
	}
}

// returns file creation time on linux-based systems
// in hours, minutes, seconds, uppercase AM/PM
function get_file_modified_time($filename)
{
	if (file_exists($filename))
		return date( "h:i:s A", filemtime($filename));
	else
		return false;
}

// returns a datetime set at a specified time in H:i:s T format
// clocktime must be supplied in 24-hour HH:mm:ss format
// date must be in Y-m-d format
// optional: the datetime format
function create_timestamp($date, $time, $format = "Y-m-d H:i:s", $useGMT = false)
{
	global $timezone;

    // when date/time are not supplied, use current day/time
    if (!$date)
        $date = date("Y-m-d");
    if (!$time)
        $time = date("H:i:s");

	if ($useGMT)
	{
		$GMT_zone = new DateTimeZone("GMT");
        $original_timezone = new DateTimeZone($timezone);
		$dateTime = new DateTime($date . ' ' . $time, $original_timezone);
		$dateTime->setTimeZone($GMT_zone);
	}
	else
		$dateTime = new DateTime($date . ' ' . $time, new DateTimeZone($timezone));

    return $dateTime->format($format);
}

////////////////Filename De-munging//////////////
// sometimes we just have a relative path and filename
// but we can strip away the basepath AND extension to 
// get only the page name itself
// this is ONLY to be used on markup pages
function strip_filepath($pathedFilename, $basepath)
{
	$filename = strip_file_extension($pathedFilename);
	$filename = substr($filename, strlen($basepath));

	return $filename;
}

// returns the file extension of a page based on the
// type of markup it is
// returns false if it does not match any known extension
function get_markup_file_extension($pathedFilename)
{
	global $markup_file_extension, $bug_file_extension;
	$extensions = [$markup_file_extension, $bug_file_extension];
	foreach ($extensions as $extension)
	{
		if (substr($pathedFilename, -strlen($extension)) == $extension)
			return $extension;
	}
	return false;
}


// strips the extension from a filepath, leaving both
// the filename and its path alone
function strip_file_extension($pathedFilename)
{
	global $markup_file_extension, $bug_file_extension;
	// check which file extension we're working with
	// to determine how many characters to trim off
	$extension = get_markup_file_extension($pathedFilename);
	if ($extension)
	{
		$filename = substr($pathedFilename, 0, (-strlen($extension) - 1));
		return $filename;
	}
	else
		return false;
}

// implements a recursive version of the "glob" function
// which allows for sub-directory searches of files
// from: https://stackoverflow.com/questions/12109042/php-get-file-listing-including-sub-directories
// note: Does not support flag GLOB_BRACE
function recursive_glob($pattern, $flags = 0)
{
	$filesFound = glob($pattern, $flags);
	// build a list of every subdirectory
	foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $subdirectory)
	{
		// recursively calls itself while listing files in each subdirectory that match the name pattern
		$filesFound = array_merge($filesFound, recursive_glob($subdirectory.'/'.basename($pattern), $flags));
	}
	return $filesFound;
}

// copies files and non-empty directories to a 
// destination recursively.
// sourcePath: file or directory
// destinationPath: directory
// original code by @marlena@kif.rocks - thank you!
function recursive_copy($sourcePath, $destinationPath) 
{
	// recursively work its way down through directories
	if (is_dir($sourcePath)) 
	{
		if (!file_exists($destinationPath))
			mkdir($destinationPath);
		
		if (is_dir($destinationPath))
		{
			// exclude "." and ".." paths
			$files = array_diff(scandir($sourcePath), [".", ".."]);
			foreach ($files as $file)
			{
				// build the final source/destination filepaths
				$sourceFile = $sourcePath . "/" . $file;
				$destFile = $destinationPath . "/" . $file;
				recursive_copy($sourceFile, $destFile);
			}
		}
	}
	// not a directory - just copy the file
	else
	{
		if (file_exists($sourcePath))
			copy($sourcePath, $destinationPath);
	}
}

// compares two file dates - returns the newer modified time file
function sort_files_by_date_newer($fileA, $fileB)
{
		return (get_file_modified_time($fileA) < get_file_modified_time($fileB));
}

// compares two file dates - returns the older modified time file
function sort_files_by_date_older($fileA, $fileB)
{
		return (get_file_modified_time($fileA) > get_file_modified_time($fileB));
}

// sorts files according to a sort style
// permitted sort styles: 
// "newest": newest to oldest by date
// "oldest": oldest to newest by date
// "name_ascending": alphabetically, by name, from A-Z
// "name_descending": alphabetically, by name, from Z-A
function sort_files($files, $sortStyle)
{
	if ($files)
	{
		// sort in place by name A-Z or Z-A
		if ($sortStyle == "name_ascending")
			sort($files);
		elseif ($sortStyle == "name_descending")
			rsort($files);
		// sort in-place by date
		elseif ($sortStyle == "newest" || $sortStyle == "oldest")
		{
			if ($sortStyle == "newest")
				usort($files, "sort_files_by_date_newer");
			else
				usort($files, "sort_files_by_date_older");
		}
	}
	return $files;
}

////////////////////URLs/////////////////////////////

// checks if the PHP _SERVER variable has been set, and returns
// the base name URL of the page
// in static generation mode, we just use the site base url entered in Settings
// in live mode, we can figure out the base url from the server variables
function get_site_basename($link_style = false)
{
	global $static_generation, $url_protocol;
	// in static generation mode we rely upon the site_base_url which MUST be set in settings
	if ($static_generation)
	{
		global $site_base_url;
		return $site_base_url;
	}
	// in live mode, we can automagically determine the site base url from the server variables
	elseif (isset($_SERVER['SERVER_NAME']) && isset($_SERVER['SCRIPT_NAME']))
	{
		if ($link_style == "classic")
			return $url_protocol . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
		elseif ($link_style == "easy" || !$link_style)
			return $url_protocol . $_SERVER['SERVER_NAME'] . substr($_SERVER['SCRIPT_NAME'], 0, -strlen(basename($_SERVER['SCRIPT_NAME'])));
	}
	else return "";
}

////////////////Kiki Versioning//////////////
// returns the current kiki release version+type
function get_kiki_version()
{
	$version = "1.1.9";
	$type = "F";
	return $version . $type;
}

?>