#!/bin/sh
set -eu

if [ $# -ne 1 ]; then
    printf 'Usage: %s /path/to/kiki-editor-target\n' "$0" >&2
    printf 'Target may be the bundled executable or editor.py.\n' >&2
    exit 1
fi

target_path=$(realpath "$1")
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
home_bin="$HOME/.local/bin"
home_apps="$HOME/.local/share/applications"
home_icons="$HOME/.local/share/icons/hicolor/scalable/apps"

mkdir -p "$home_bin" "$home_apps" "$home_icons"

cat > "$home_bin/kiki-editor" <<EOF
#!/bin/sh
set -eu

target="$target_path"

if [ -x "\$target" ] && [ "\${target##*.}" != "py" ]; then
    exec "\$target" "\$@"
fi

exec python3 "\$target" "\$@"
EOF
chmod 755 "$home_bin/kiki-editor"

cp "$script_dir/kiki-editor.desktop" "$home_apps/kiki-editor.desktop"
cp "$script_dir/kiki-editor.svg" "$home_icons/kiki-editor.svg"

printf 'Installed launcher to %s\n' "$home_bin/kiki-editor"
printf 'Installed desktop file to %s\n' "$home_apps/kiki-editor.desktop"
printf 'Installed icon to %s\n' "$home_icons/kiki-editor.svg"
