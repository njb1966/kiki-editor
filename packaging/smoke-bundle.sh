#!/bin/sh
set -eu

if [ $# -ne 1 ]; then
    printf 'Usage: %s /path/to/bundle-root\n' "$0" >&2
    printf 'Example: %s dist/kiki-editor\n' "$0" >&2
    exit 1
fi

bundle_root=$(realpath "$1")
bundle_exe="$bundle_root/kiki-editor"
bundle_render="$bundle_root/render.php"
bundle_kiki="$bundle_root/kiki_registered"

if [ ! -e "$bundle_exe" ]; then
    printf 'Missing bundle executable: %s\n' "$bundle_exe" >&2
    exit 1
fi

if [ ! -f "$bundle_render" ]; then
    printf 'Missing render.php in bundle: %s\n' "$bundle_render" >&2
    exit 1
fi

if [ ! -d "$bundle_kiki" ]; then
    printf 'Missing kiki_registered/ in bundle: %s\n' "$bundle_kiki" >&2
    exit 1
fi

printf 'Bundle layout looks correct:\n'
printf '  %s\n' "$bundle_exe"
printf '  %s\n' "$bundle_render"
printf '  %s\n' "$bundle_kiki"
