<?php

$repo_root = __DIR__;
$kiki_root = $repo_root . DIRECTORY_SEPARATOR . "kiki_registered";

if (!is_dir($kiki_root)) {
	fwrite(STDERR, "kiki_registered/ not found\n");
	exit(1);
}

chdir($kiki_root);

require_once "settings.php";
require_once "library/utils.php";
require_once "library/page.php";

$current_page_env = getenv("KIKI_EDITOR_CURRENT_PAGE");
if ($current_page_env !== false && $current_page_env !== "") {
	$current_page = $current_page_env;
}

$raw_input = stream_get_contents(STDIN);
if ($raw_input === false || $raw_input === "") {
	exit(0);
}

$lines = preg_split("/\r\n|\r|\n/", $raw_input);
if (!is_array($lines)) {
	$lines = [$raw_input];
}

$extracted = extract_header_and_content($lines);
$content = [];

if (is_array($extracted) && array_key_exists("content", $extracted)) {
	$content = $extracted["content"];
} else {
	$content = $lines;
}

$html = parse_markup($content);

// kiki's bug parser can wrap block tags in stray <p> tags when a heading or
// other block element lands on an otherwise paragraph-managed line. Normalize
// those cases here so the preview better reflects the intended block structure.
$html = preg_replace('#<p>\s*(<(?:h[1-6]|blockquote|pre|code)[^>]*>)#i', '$1', $html);
$html = preg_replace('#(</(?:h[1-6]|blockquote|pre|code)>)\s*</p>#i', '$1', $html);

echo $html;
