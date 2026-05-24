# kiki Editor — Implementation Plan

## Goal

Split-pane desktop editor for kiki's `.bug` markup format. Left pane: editable text. Right pane: live preview showing the same rendering kiki would produce for the page content, including local-link behavior and theme CSS. Preview shows only the content section; the `((header))` block is skipped.

## Cross-platform

Not a large undertaking. The full stack (Python 3, PyQt6, PyQt6-WebEngine, PHP) is available on Linux, macOS, and Windows. The only platform-specific detail is PHP binary location, handled by `shutil.which("php")`. Build cross-platform from the start.

---

## Files to create

All new files in the repo root. Nothing inside `kiki_registered/` is modified.

| File | Purpose |
|---|---|
| `render.php` | PHP shim: boots kiki's runtime, extracts page content from stdin, outputs rendered HTML fragment |
| `editor.py` | PyQt6 GUI: split-pane editor + WebEngine preview, file management, debounced render |
| `requirements.txt` | `PyQt6>=6.6`, `PyQt6-WebEngine>=6.6` |

**Read-only references (not modified):**
- `kiki_registered/library/interpreter.php` — header/content extraction and markup dispatch
- `kiki_registered/library/page.php` — page/runtime setup used by link generation
- `kiki_registered/library/build.php` — local permalink behavior and page assembly
- `kiki_registered/themes/*/style.css` — loaded as real stylesheets in preview

---

## Implementation steps

### Step 1 — `render.php`

`render.php` should bootstrap kiki the same way the site does, not just load the raw bug parser. That keeps local links, globals, and theme-aware markup behavior consistent.

Logic:
1. `chdir()` into `kiki_registered/` so kiki's relative includes resolve normally
2. `require_once` `settings.php`, `library/utils.php`, `library/page.php`, and `library/interpreter.php`
3. Read raw content from `php://stdin`
4. Call `extract_header_and_content()` on the input lines
5. Render only the extracted `content` lines with kiki's parser and `echo` the result

Notes:
- If the input file has no explicit header/content split, `extract_header_and_content()` already falls back to treating the whole document as content.
- The preview should not reimplement the header parser in Python.

### Step 2 — `editor.py`

**Widget tree:**
```
KikiEditor (QMainWindow)
├── QToolBar
│   └── QComboBox (theme picker — scans kiki_registered/themes/)
├── QSplitter (horizontal)
│   ├── EditorPane  (QPlainTextEdit)
│   └── PreviewPane (QWebEngineView)
└── QStatusBar (current filepath + render status)
```

**Menu bar:** File → New, Open, Save, Save As, Exit

**Render pipeline:**
1. `EditorPane.textChanged` restarts a `QTimer` (300ms, single-shot)
2. On timeout: call `render(text)` — spawns `php render.php` subprocess, writes editor text to stdin, captures stdout (HTML fragment). Timeout: 3s.
3. `build_preview_html(fragment)` wraps the fragment in a full `<!DOCTYPE html>` document and includes the selected theme stylesheet with a real `<link rel="stylesheet">` element instead of inlining CSS
4. `PreviewPane.setHtml(html, baseUrl=QUrl.fromLocalFile(kiki_registered/))` — base URL lets stylesheet-relative assets resolve correctly

**File operations:**
- `QFileDialog` with `*.bug` filter for open/save
- Track dirty state (`document().isModified()`); prompt on New/Open/Close if unsaved

**PHP detection:**
- `shutil.which("php")` on startup
- Show a `QMessageBox` error and exit if PHP not found

**Theme picker:**
- On startup: scan `kiki_registered/themes/` for subdirectories containing `style.css`
- Default to `onecolumn`
- Switching theme re-renders immediately

### Step 3 — `requirements.txt`

```
PyQt6>=6.6
PyQt6-WebEngine>=6.6
```

---

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| `kiki runtime depends on relative includes and global setup` | Bootstrap from `kiki_registered/` and load the same runtime files kiki uses |
| `local links may render incorrectly if only `markup.php` is loaded` | Use the page/build/runtime stack so `build_local_permalink()` behaves like site rendering |
| `theme CSS uses relative assets and imports` | Load the theme as a real stylesheet URL instead of inlining CSS |
| `QWebEngineView` requires system libs on some Linux distros (`libGL`, `libegl1`, `libxcb-*`) | Document required system packages in setup instructions |
| PHP subprocess cold-start latency per render (~50–100ms) | 300ms debounce absorbs this; if still sluggish on large files, keep a persistent PHP process with stdin/stdout protocol |
| Windows line endings in `.bug` files | kiki already normalizes newlines during parsing; should be transparent |

---

## Verification

```bash
# Install Python deps
pip3 install PyQt6 PyQt6-WebEngine

# Smoke-test renderer directly
echo "**hello world**" | php render.php
# Expected: <p><strong>hello world</strong><br></p>

echo "[home]{Home}" | php render.php
# Expected: a local permalink, not plain text

# Launch editor
python3 editor.py
```

**Manual test cases:**

| Bug markup input | Expected preview |
|---|---|
| `**bold**` | Bold text |
| `~~italic~~` | Italic text |
| `==heading==` | h6 heading |
| `[home]{Link text}` | Anchor tag |
| `'''preformatted'''` | `<pre>` block |
| Full `.bug` file with header | Header skipped; content rendered with theme CSS |
| Switch theme in toolbar | Preview re-renders with new CSS |
| Open `kiki_registered/pages/home.bug` | Content loads and previews correctly |

---

## Decisions logged

- **Preview scope:** content section only (`((content))` and below); `((header))` block is stripped before rendering. Header fields (title, tags, date) are not shown in the preview.
- **Parser:** kiki's own PHP parser via subprocess — no reimplementation. Guarantees exact parity with a live kiki site.
- **Cross-platform:** build for all platforms from the start; PHP binary found via `shutil.which("php")`.
