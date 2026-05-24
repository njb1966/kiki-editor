<?php
// this module enables a browser-based editor for pages

// checks if the wiki uses a password, and compares it to
// the supplied password. returns true if passes the password check
function check_password($password)
{
    global $wiki_password;

    if ($wiki_password)
    {
        if ($wiki_password != $password)
            return false;
    }

    return true;
}

// a page edit has been requested
function edit($filepath, $title, $text)
{
    global $wiki_password, $wiki_delete_allowed, $wiki_upload_allowed, $bug_syntax_document, $markup_interpreter;

    // the delete button only shows up on the edit form if the file exists
    if (file_exists($filepath))
        $delete_enabled = true;
    else
        $delete_enabled = false;

    $textarea = '<div class="edit_form">';
    // loads a quick-reference sheet for the markup type being used in the document
    $syntax_quickref = "";
    if ($markup_interpreter == "bug")
    {
        $markup_syntax = file($bug_syntax_document);
        if ($markup_syntax)
            $syntax_quickref = parse_markup(extract_header_and_content($markup_syntax)["content"]);
    }
    // todo: add markdown support
    $textarea .= "<div>" . $syntax_quickref . "</div>";
    $textarea .= '<h3>Editing: ' . $title . '</h3>';
    $textarea .= '<form action="" method="POST" enctype="multipart/form-data">';
    $textarea .= '<div class="textarea"><textarea class="textarea" id="page_edit" name="publish_content">';
	$textarea .= "@@@"; // DO NOT PARSE next line
	$textarea .= $text; // tell the interpreter NOT to parse the text, just add it
	$textarea .= "@@@"; // DO NOT PARSE previous line
	$textarea .= "</textarea></div>";
    $textarea .= '<div>';

    // only show the password field when a password has been set
    if ($wiki_password)
        $textarea .= '<span>Publish Password: <input type="password" name="password"></span>';

    // file upload field
    if ($wiki_upload_allowed)
        $textarea .= '<div><span>Upload attachment:</span><input type="file" name="file_upload" id="file_upload"></div>';

    // publish button
    $textarea .= '<div><button class="submit" type="submit" name="command" value="publish">Publish Changes</button></div>';
    // add delete button if enabled
    if ($delete_enabled && $wiki_delete_allowed)
        $textarea .= '<div><button class="submit" type="submit" name="command" value="delete">Delete Article</button></div>';

    $textarea .= '</form>';
    $textarea .= '<div><span class="button">' . build_local_permalink($title, "Cancel Changes") . '</span></div>';
    $textarea .= '</div>'; // close edit_form
    return $textarea;
}

// allows a user to delete a wiki page. this is irreversible.
// returns true if the file was successfully deleted
function delete($filepath, $document_name, $password = false)
{
    global $wiki_delete_allowed;

    if ($wiki_delete_allowed && check_password($password))
    {
        if (file_exists($filepath))
        {
            // deletion just changes the extension to .deleted
            $renamed_file = strip_file_extension($filepath) . ".deleted";
            rename($filepath, $renamed_file);
            return true;
        }
    }
    return false;
}

// publishes the page at the specified filepath
// returns an array with success (false/true) and
// any returned html output that goes with the
// success/fail state
function publish($filepath, $action)
{
    // a small array that stores both the outcome
    // and the returned html that goes with the outcome
    $outcome = [];
    $outcome["publish"]["success"] = false;
    $outcome["upload"]["success"] = false;

    $password = $action["password"];
    $text = $action["publish_content"];
    $file_upload = $action["file_upload"];

    // check for a password if enabled
    if (check_password($password))
    {
        // write the new text to the file
        $publish_page = fopen($filepath, "w");
        if ($publish_page)
        {
            fwrite($publish_page, $text);
            $outcome["publish"]["success"] = true;
            $outcome["publish"]["html_output"] = '<div class="error"><span>Published ' . strip_filepath($filepath, "") .' successfully.</span></div>';
            fclose($publish_page);
        }
        else
        {
            $outcome["publish"]["success"] = false;
            $outcome["publish"]["html_output"] = '<div class="error"><span>Publish failed. Do you have permission to modify this file?</span></div>';
        }

        // handle file upload if one was sent
        global $wiki_upload_allowed;
        if ($wiki_upload_allowed && $file_upload)
        {
            $outcome["upload"]["success"] = process_file_upload($file_upload);
            if ($outcome["upload"]["success"])
                $outcome["upload"]["html_output"] = '<div class="error"><span>Uploaded ' . $file_upload["name"] .' successfully</span></div>';
            else
                $outcome["upload"]["html_output"] = '<div class="error"><span>File attachment upload failed.</span></div>';
        }
    }
    // password failure
    else
    {
        $outcome["publish"]["success"] = false;
        $outcome["publish"]["html_output"] = '<div class="error"><span>Publish failed. Do you have permission to edit this page?</span></div>';
    }
    return $outcome;
}

// creates a new page from a template
// if a template isn't supplied, it pulls the default document template from settings
function create($template = false)
{
    global $page_template, $current_page;

    if (!$template && $current_page)
        $template = $page_template;

    // load the template into an array
    $template_doc = file($template);

    // returns an array of either the new template, or an error
    if ($template_doc)
        return $template_doc;
    else
    {
        $error_output[0] = '<div class="error"><span>Could not build a new document from a template. Is the template file missing?</span></div>';
        return $error_output;
    }
}

// processes a file upload
// by doing some basic sanity checks
// to prevent abuse and avoid vulnerabilities
// optional: targetDir specifies a path 
// relative to the server's document root that the file
// should be moved to after upload
function process_file_upload($file, $targetDir = false)
{
    if ($file)
    {
        global $file_area_dir;
        $tempfile = $file["tmp_name"];
        $uploaded_filename = $file["name"];
        // this is a bit gnarly: it gets the kiki install root folder
        $basepath = $_SERVER['DOCUMENT_ROOT'] . substr($_SERVER['PHP_SELF'], 0, -strlen(basename($_SERVER['PHP_SELF'])));
        // when targetDir hasn't been specified, use
        // the default file area directory (settings.php)
        if (!$targetDir)
            $targetDir = $basepath . $file_area_dir . basename($uploaded_filename);
        else
            $targetDir = $basepath . $targetDir . basename($uploaded_filename);

        // try moving the file from the temp area to
        // the final destination
        $move_attempt = move_uploaded_file($tempfile, $targetDir);
        return $move_attempt;
    }
    return false;
}

?>