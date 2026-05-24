# Packaging

`kiki editor` is designed to be packaged with the same layout on Linux, Windows, and macOS:

- `editor.py` is the GUI entry point
- `render.php` is bundled beside it
- `kiki_registered/` is bundled as application data

The app now resolves those resources from either the source tree or a bundled runtime root, so the same code path works in development and in a packaged build.

## Recommended build tool

Use `PyInstaller` on each target OS. Build on the OS you want to ship. Do not cross-build.

Suggested install for a build environment:

```bash
pip install pyinstaller
```

## Build

From the repository root:

```bash
bash packaging/build-debian.sh
```

That produces a `dist/kiki-editor/` bundle on Linux and Windows, and a `.app` bundle on macOS when run there.

After building, verify the bundle layout:

```bash
bash packaging/smoke-bundle.sh dist/kiki-editor
```

## Debian Linux

For a local desktop install, use the helper script in `packaging/` to install:

- a launcher in `~/.local/bin/kiki-editor`
- a desktop file in `~/.local/share/applications/`
- an icon in `~/.local/share/icons/hicolor/scalable/apps/`

Example:

```bash
bash packaging/install-debian.sh dist/kiki-editor/kiki-editor
```

If you are running from source instead of a bundle, pass `editor.py` as the target:

```bash
bash packaging/install-debian.sh /path/to/kiki_editor/editor.py
```

The launcher script detects whether the target is a Python file or an executable.

## Windows

Build the same `kiki-editor.spec` with PyInstaller on Windows.

Recommended output:

- `dist/kiki-editor/kiki-editor.exe`

Notes:

- keep `render.php` and `kiki_registered/` bundled with the executable
- ship the included `.desktop` equivalent as a Windows shortcut or installer entry if desired
- if you later create an installer, point it at the PyInstaller output directory, not the source tree

## macOS

Build the same `kiki-editor.spec` with PyInstaller on macOS.

Recommended output:

- a `.app` bundle under `dist/`

Notes:

- sign and notarize after the bundle is correct
- if you add an icon later, wire it into the app bundle after the PyInstaller step
- keep the bundled `render.php` and `kiki_registered/` inside the app resources

## What stays the same across platforms

- the editor code does not need platform-specific changes
- PHP still needs to be present on the target machine
- the bundled kiki resources stay in the app payload
