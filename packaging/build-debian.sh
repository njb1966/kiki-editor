#!/bin/sh
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_root=$(CDPATH= cd -- "$script_dir/.." && pwd)

if [ -x "$project_root/.venv/bin/pyinstaller" ]; then
    pyinstaller_bin="$project_root/.venv/bin/pyinstaller"
elif command -v pyinstaller >/dev/null 2>&1; then
    pyinstaller_bin=$(command -v pyinstaller)
else
    printf 'PyInstaller was not found.\n' >&2
    printf 'Install it with: pip install pyinstaller\n' >&2
    exit 1
fi

cd "$project_root"
exec "$pyinstaller_bin" --noconfirm kiki-editor.spec
