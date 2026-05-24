<?php
// bug is used for all file header formats, regardless of the markup
// interpreter used for content
require_once($bug_dir . "markup.php");

// store current bug tags; used by interpret_keywords
$bug_tags = extract_bug_symbols();

// steps through a bug-formatted page or post, extracting the header information
// and returning it as an array of lines, and the remaining lines
// which are content, returned as a separate array of lines
// e.g. ['header' => lines after ((header)), 'content' => lines after ((content))
function extract_header_and_content($source_text)
{
	if ($source_text)
	{
		// when true, insert values into a new array
		$build_array = false;
		$current_array = [];
		$current_key = "";
		foreach ($source_text as $line)
		{
			$line_start = trim($line);

			// before any tag parsing, we must handle any escapes
            $line_start = escapes_to_htmlnumerics($line_start);

            // load basic script processing tags from plugins/bug/markup.php
            $basic_tags = get_basic_script_tags();

			// check for an array definition
			// arrays are defined like this:
			// ((arrayname))
			// variablename:value
			$array_start_key = extract_text_from_tags($line_start, $basic_tags["array"][0], $basic_tags["array"][1]);
			if ($array_start_key)
			{
				$build_array = true;
				$current_key = $array_start_key;
				$current_array[$current_key] = [];
				continue;
			}

            // headers are read for key-value pairs
            if ($current_key == "header")
            {
                // look for comments at the beginning of the line and ignore that line
                if (strpos($line_start, $basic_tags["comment"]) === 0)
                    continue;
            
                // everything before a colon is the key; everything after it is the value
                $line_symbols = explode(':', $line, 2);

				// make sure it's not an empty array
                if (trim($line_symbols[0]) !== "")
                {
                    // the first symbol should be extracted and used as the
                    // key for the entire array, then removed from line_symbols
                    $line_key = $line_symbols[0];
					$line_value = $line_symbols[1];
                    // store the value in the key
					$current_array[$current_key][$line_key] = $line_value;
                }
            }
            // content is just ingested as lines of text with no key/value checks
            elseif ($current_key == "content")
                $current_array[$current_key][] = $line;
            else
                // when the writer has forgotten to include either the header or content
                // key, just treat the entire document as content
                $current_array["content"][] = $line;
		}
        // return the array of keys (which should include 'header' and 'content'
        // when the page has been formatted properly
        return $current_array;
	}
}

// steps through an array of lines of text in bug format
// searches for a specific key $searchForKey, 
// and returns its value
// when only a single value is found, return the value
// otherwise, return all values in an array
function get_header_key($header, $searchForKey)
{
    $extractedValues = [];
    if ($header)
    {
        if (array_key_exists($searchForKey, $header))
            $extractedValues[] = $header[$searchForKey];
        $foundValues = count($extractedValues);
		// return an array of values
        if ($foundValues > 1)
            return $extractedValues;
        // only a single value found
		elseif ($foundValues == 1)
            return $extractedValues[0];
    }
    return false;
}

// searches a header for a key and returns its **parsed** value
function parse_key($header, $searchForKey)
{
	$parsedValue = trim(parse_bug_markup(get_header_key($header, $searchForKey)));
	return $parsedValue;
}

// search for keywords in a string and return line(s) of html
// these allow bug to interpret bug-formatted pages
// for important bug keywords like "show", "title" and "author"
// returns the input line with any keywords replaced with commands
function parse_line_keywords($line)
{
    // use the keyword tag
    global $bug_tags;

    // do not parse escaped characters
    $line = escapes_to_htmlnumerics($line);

    $keyword_tag = $bug_tags["keywordstag"]["keywords"];
    $keywords = extract_text_from_tags($line, $keyword_tag[0], $keyword_tag[1]);    

    $replacement_html = "";

    if ($keywords)
    {
        // the "show" keyword prints text to the browser
        if (strpos($keywords, "show") !== false)
        {
            // adds a permalink for every page on the site in a div
            if (strpos($keywords, "links") !== false)
            {
                // when someone types in "date" or "dates" it also adds the date of the page
                if (strpos($keywords, "date") !== false)
                    $showDate = true;
                else
                    $showDate = false;
                
                if (strpos($keywords, "title") !== false)
                    $showTitle = true;
                else
                    $showTitle = false;

                // when the "page" keyword is used
                if (strpos($keywords, "page") !== false)
                    $permalinks = get_page_permalinks_by_tag("all", false, $showTitle, $showDate);

                // add all permalinks found
                if (isset($permalinks))
                {
                    $links = "";
                    // build a list of links
                    foreach ($permalinks as $link)
                    {
                        $link = "<div>" . $link. "</div>";
                        $links .= $link;
                    }
                    if (strlen($links) > 0)
                        $replacement_html .= $links;
                }
                // when someone adds "rss" it retrieves the rss link
                if (strpos($keywords, "rss") !== false)
                {
                    global $rssMenuLink;
					$linktag = $bug_tags["linktag"];
                    $replacement_html .= $linktag["url"][0] . $rssMenuLink . $linktag["url"][1] . $linktag["name"][0] . "RSS" . $linktag["name"][1];
                }            
            }
			// the "page" keyword retrieves and outputs pages
			elseif (strpos($keywords, "page") !== false)
			{
				// must use tags in order to show specific pages
				if (strpos($keywords, "tags:") !== false)
				{
					$tags = explode("tags:", $keywords, 2);
					// now begin interpreting everything after tags:
					if ($tags[1])
					{
						$maxCount = false;
						$sortStyle = false;
						// sort is optional
						if (strpos($tags[1], "sort:") !== false)
						{
							$sortStyle = explode("sort:", $keywords, 2)[1];
							// remove any additional parameters like maximum:
							$sortStyle = explode(" ", $sortStyle, 2)[0];
							// remove sort from the keyword list
							$tags = explode("sort:", $tags[1], 2);
						}
						// the maximum number of pages to retrieve
						if (strpos($tags[1], "maximum:") !== false)
						{
							$maxCount = explode("maximum:", $keywords, 2)[1];
							// remove maximum from the keyword list
							$tags = explode("maximum:", $tags[0], 2);
						}
						if ($sortStyle)
							$pages = get_pages_by_tag($tags[0], $sortStyle, $maxCount);
						else
							$pages = get_pages_by_tag($tags[1], $sortStyle, $maxCount);

						if ($pages)
							$replacement_html .= get_pages($pages, false);
					}
				}
			}
        }
    }
    // finally, do the bulk replacement of keywords with their corresponding html
    $line = replace_tags($line, $keyword_tag[0], $keyword_tag[1], $replacement_html);
    return $line;
}

// search for keywords in an array or string composed of keys and values 
// process them, and return the corresponding html-formatted text
// returns an array of lines of markup/html
function interpret_keywords($lines)
{
    $html_output = [];

    // note that we can have many of these keywords
    // process multiple lines of text
    foreach ($lines as $line)
    {
        $html_output[] = parse_line_keywords($line);
    }
    return $html_output;
}

// replaces any dynamic content variables with
// the corresponding key in the $dynamic_content array
// requires an array of lines of source text, and an array
// of dynamic content
function interpret_dynamic_content($lines, $dynamic_content)
{
	// when dynamic content tags are found, replace them with their
	// corresponding key in the dynamic_content array
	if ($lines && $dynamic_content)
	{
		// store the parsed lines in a new array
		$parsedLines = [];

		global $dynamictag;
		foreach ($lines as $line)
		{
			$dynamic_var = extract_text_from_tags($line, $dynamictag["dynamicvariable"][0], $dynamictag["dynamicvariable"][1]);
			if ($dynamic_var && array_key_exists($dynamic_var, $dynamic_content))
				$parsedLines[] = replace_tags($line, $dynamictag["dynamicvariable"][0], $dynamictag["dynamicvariable"][1], $dynamic_content[$dynamic_var]);
			else
				$parsedLines[] = $line;
		}
		return $parsedLines;
	}
	else
		return $lines;
}

// returns the header data of the post or page taking
// its filename (including the path) as a parameter
function load_header($filename)
{
    $source_text = file($filename);
    if ($source_text)
    {
        $extracted_data = extract_header_and_content($source_text);
        // return only the header and ignore the content data
        if (is_array($extracted_data) && (array_key_exists('header', $extracted_data)))
            return $extracted_data['header'];
    }
    return false;
}

// parses a string for tags and sort parameters
// returns an array with "tags" and "sortstyle" keys
// returns false if no tags found
function check_request_for_tags($checkString)
{
	$parsedString = [];
	$parsedString["tags"] = false;
	$parsedString["sortstyle"] = false;
	// sometimes the requested page uses tags instead
	if (strpos($checkString, "tags=") !== false)
	{
        $sort = false;
		$tagRequested = explode("tags=", $checkString);
		if (strpos($checkString, "&sort=") !== false)
			$sort = explode("&sort=", $checkString);

		if ($sort !== false)
		{
			$parsedString["tags"] = explode("tags=", $sort[0])[1];
			$parsedString["sortstyle"] = $sort[1];
		}
		elseif ($tagRequested[1])
			$parsedString["tags"] = $tagRequested[1];

		return $parsedString;
	}
	return false;
}

////////////////////Markup///////////////////////////

// allow the user to select their own markup language parser
global $markup_interpreter, $markup_file_extension, $bug_file_extension, $plugin_dir, $bug_dir;

// parse text using a specific interpreter
// right now, just supports bug
// first parameter is the text to be parsed,
// second parameter is the name (string) of interpreter
function parse_markup_using($text, $interpreter)
{
	if ($interpreter == "bug")
		return parse_bug_markup($text);
}

// these lines automatically run when the interpreter is loaded
// and set the default interpreter according the one
// in Settings
if ($markup_interpreter == "bug")
{
	require_once($bug_dir . "markup.php");

	$markup_file_extension = $bug_file_extension;
	function parse_markup($text)
	{
		// bug's markup parser. note that bug is the only
		// parser that supports dynamic content
		return parse_bug_markup($text);
	}
}
// plugin support for Michel Fortin's port of Markdown
// https://michelf.ca/projects/php-markdown/
elseif ($markup_interpreter == "php-markdown")
{
	$markup_file_extension = "md";
	// you may need to change this path if you installed markdown somewhere 
	// other than in /plugins/Markdown/
	require_once("plugins/Markdown/Michelf/Markdown.inc.php");
	
	function parse_markup($text)
	{
		// php-markdown can only process strings, not arrays of strings
		if (is_array($text))
			$text = implode('', $text);
		return Michelf\Markdown::defaultTransform($text); 
	}
}
// plugin support for Michel Fortin's port of Markdown Extra
// https://michelf.ca/projects/php-markdown
elseif ($markup_interpreter == "php-markdown-extra")
{
	$markup_file_extension = "md";
	// you may need to change this path if you installed markdown somewhere 
	// other than in /plugins/Markdown/
	require_once("plugins/Markdown/Michelf/MarkdownExtra.inc.php");
	
	function parse_markup($text)
	{
		// php-markdown can only process strings, not arrays of strings
		if (is_array($text))
			$text = implode('', $text);
		return Michelf\MarkdownExtra::defaultTransform($text); 
	}    
}
// plugin support for Parsedown's port of markdown
// https://parsedown.org
elseif ($markup_interpreter == "parsedown")
{
	$markup_file_extension = "md";
	// you may need to change this path if you installed parsedown somewhere 
	// other than in /plugins/Parsedown/
	require_once("plugins/Parsedown/Parsedown.php");
	$parsedown = new Parsedown();
	
	function parse_markup($text)
	{
		global $parsedown;
		// parsedown can only process strings, not arrays of strings
		if (is_array($text))
			$text = implode('', $text);
		return $parsedown->text($text);
	}
}
// elseif ($markup_interpeter == "") { }
// this is where additional markup interpreters can be included

// when no interpreter is available, just return an error
if (!(function_exists("parse_markup")))
{
    function parse_markup()
    {
        return "No working markup interpreter could be found. Check kiki's settings.";
    }
}
?>