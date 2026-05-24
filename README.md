# kiki editor

`kiki editor` is a small desktop editor for kiki `.bug` pages.

It provides:

- a split view with bug source on the left and live preview on the right
- the existing kiki PHP renderer for preview parity
- theme switching using `kiki_registered/themes/*/style.css`
- a compact bug-markup toolbar
- in-file search, go-to-line, and metadata editing
- per-file splitter and preview zoom persistence

## Requirements

- Python 3
- `PyQt6`
- `PyQt6-WebEngine`
- `PyInstaller` in the project virtual environment for packaging
- PHP with `mbstring` enabled

Check PHP support with:

```bash
php -m | grep mbstring
```

## First-time Setup

From a fresh clone:

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

If you want to package the app from the same environment:

```bash
pip install pyinstaller
```

## Run from source

Launch the editor:

```bash
python editor.py
```

If you prefer the wrapper targets:

```bash
make run
```

The app expects these files next to `editor.py`:

- `render.php`
- `kiki_registered/`

## Project layout

- `editor.py` - Qt GUI
- `render.php` - PHP preview renderer
- `kiki_registered/` - bundled kiki application files
- `packaging/` - desktop, icon, and build helpers
- `kiki-editor.spec` - PyInstaller spec

## Packaging

The packaging flow is intentionally simple: use PyInstaller on each target OS.

Do not cross-build. Build on the platform you want to ship.

The `Makefile` provides the common shortcuts:

- `make run`
- `make build`
- `make smoke`
- `make install`
- `make clean`

### Debian Linux

Build:

```bash
bash packaging/build-debian.sh
# or
make build
```

Check the bundle layout:

```bash
bash packaging/smoke-bundle.sh dist/kiki-editor
# or
make smoke
```

Install a local desktop launcher:

```bash
bash packaging/install-debian.sh dist/kiki-editor/kiki-editor
# or
make install
```

If you want to use the source tree directly instead of a bundle:

```bash
bash packaging/install-debian.sh /path/to/kiki_editor/editor.py
```

That installs:

- `~/.local/bin/kiki-editor`
- `~/.local/share/applications/kiki-editor.desktop`
- `~/.local/share/icons/hicolor/scalable/apps/kiki-editor.svg`

### Windows

Build the same `kiki-editor.spec` on Windows with PyInstaller.

Expected output:

- `dist/kiki-editor/kiki-editor.exe`

Bundle `render.php` and `kiki_registered/` with the executable.

### macOS

Build the same `kiki-editor.spec` on macOS with PyInstaller.

Expected output:

- a `.app` bundle under `dist/`

Bundle `render.php` and `kiki_registered/` inside the app resources.

## Notes

- The app resolves bundled resources automatically when packaged.
- PHP still needs to be available on the target system.
- The editor preview uses the same kiki theme CSS as the site files.

## Verification Checklist

After making changes, verify these before shipping:

- `python3 -m py_compile editor.py`
- `python editor.py` launches and opens a page
- preview updates when you edit text
- search, go-to-line, and metadata controls work
- `make build` completes successfully
- `make smoke` confirms the bundle layout
- `make install` installs the desktop launcher on Debian
