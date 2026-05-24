#!/usr/bin/env python3

from __future__ import annotations

import html
import hashlib
import os
import re
import shutil
import sys
import tempfile
from functools import partial
from pathlib import Path

try:
    from PyQt6.QtCore import QProcess, QProcessEnvironment, QRegularExpression, QSettings, QSignalBlocker, QTimer, QUrl, Qt, QSize, QRect
    from PyQt6.QtGui import QAction, QColor, QDesktopServices, QFontDatabase, QIcon, QKeySequence, QPainter, QPixmap, QTextCharFormat, QTextCursor, QTextDocument, QTextFormat, QSyntaxHighlighter
    from PyQt6.QtWebEngineWidgets import QWebEngineView
    from PyQt6.QtWidgets import (
        QApplication,
        QComboBox,
        QFileDialog,
        QHBoxLayout,
        QLabel,
        QMainWindow,
        QMessageBox,
        QLineEdit,
        QPlainTextEdit,
        QPushButton,
        QSpinBox,
        QSplitter,
        QStatusBar,
        QToolButton,
        QToolBar,
        QTextEdit,
        QStyle,
        QWidget,
    )
except ImportError as exc:  # pragma: no cover - user environment issue
    sys.stderr.write(
        "PyQt6 and PyQt6-WebEngine are required. Install them with "
        "'pip install -r requirements.txt'.\n"
    )
    raise SystemExit(1) from exc


def resolve_app_root() -> Path:
    if getattr(sys, "frozen", False):
        bundle_root = getattr(sys, "_MEIPASS", None)
        if bundle_root:
            return Path(bundle_root)
        return Path(sys.executable).resolve().parent
    return Path(__file__).resolve().parent


class LineNumberArea(QWidget):
    def __init__(self, editor: "CodeEditor") -> None:
        super().__init__(editor)
        self.code_editor = editor

    def sizeHint(self) -> QSize:
        return QSize(self.code_editor.line_number_area_width(), 0)

    def paintEvent(self, event) -> None:  # type: ignore[override]
        self.code_editor.line_number_area_paint_event(event)


class CodeEditor(QPlainTextEdit):
    def __init__(self) -> None:
        super().__init__()
        self.document().setDocumentMargin(8)
        self.line_number_area = LineNumberArea(self)
        self.blockCountChanged.connect(self.update_line_number_area_width)
        self.updateRequest.connect(self.update_line_number_area)
        self.cursorPositionChanged.connect(self.highlight_current_line)
        self._line_number_background = QColor(245, 245, 245)
        self._line_number_foreground = QColor(120, 120, 120)
        self._current_line_background = QColor(235, 245, 255)
        self._editor_background = QColor("#ffffff")
        self._editor_foreground = QColor("#111111")
        self._left_inset = 8
        self.update_line_number_area_width(0)
        self.highlight_current_line()

    def set_theme_colors(self, background: str, foreground: str, highlight: str, editor_background: str, editor_foreground: str) -> None:
        self._line_number_background = QColor(background)
        self._line_number_foreground = QColor(foreground)
        self._current_line_background = QColor(highlight)
        self._editor_background = QColor(editor_background)
        self._editor_foreground = QColor(editor_foreground)
        self.setStyleSheet(
            f"QPlainTextEdit {{ background: {editor_background}; color: {editor_foreground}; border: none; }}"
        )
        self.viewport().update()
        self.line_number_area.update()
        self.highlight_current_line()

    def line_number_area_width(self) -> int:
        digits = len(str(max(1, self.blockCount())))
        return 18 + self.fontMetrics().horizontalAdvance("9") * digits

    def update_line_number_area_width(self, _new_block_count: int) -> None:
        self.setViewportMargins(self.line_number_area_width(), 0, 0, 0)

    def update_line_number_area(self, rect, dy: int) -> None:
        if dy:
            self.line_number_area.scroll(0, dy)
        else:
            self.line_number_area.update(0, rect.y(), self.line_number_area.width(), rect.height())

        if rect.contains(self.viewport().rect()):
            self.update_line_number_area_width(0)

    def resizeEvent(self, event) -> None:  # type: ignore[override]
        super().resizeEvent(event)
        cr = self.contentsRect()
        self.line_number_area.setGeometry(QRect(cr.left(), cr.top(), self.line_number_area_width(), cr.height()))

    def line_number_area_paint_event(self, event) -> None:
        painter = QPainter(self.line_number_area)
        painter.fillRect(event.rect(), self._line_number_background)

        block = self.firstVisibleBlock()
        block_number = block.blockNumber()
        top = round(self.blockBoundingGeometry(block).translated(self.contentOffset()).top())
        bottom = top + round(self.blockBoundingRect(block).height())

        while block.isValid() and top <= event.rect().bottom():
            if block.isVisible() and bottom >= event.rect().top():
                painter.setPen(self._line_number_foreground)
                painter.drawText(
                    0,
                    top,
                    self.line_number_area.width() - 4,
                    self.fontMetrics().height(),
                    Qt.AlignmentFlag.AlignRight,
                    str(block_number + 1),
                )

            block = block.next()
            top = bottom
            bottom = top + round(self.blockBoundingRect(block).height())
            block_number += 1

    def highlight_current_line(self) -> None:
        selection = QTextEdit.ExtraSelection()
        selection.format.setBackground(self._current_line_background)
        selection.format.setProperty(QTextFormat.Property.FullWidthSelection, True)
        selection.cursor = self.textCursor()
        selection.cursor.clearSelection()
        self.setExtraSelections([selection])


class BugHighlighter(QSyntaxHighlighter):
    def __init__(self, document) -> None:
        super().__init__(document)

        def fmt(color: str, bold: bool = False, italic: bool = False) -> QTextCharFormat:
            text_format = QTextCharFormat()
            text_format.setForeground(QColor(color))
            if bold:
                text_format.setFontWeight(700)
            if italic:
                text_format.setFontItalic(True)
            return text_format

        self.rules: list[tuple[QRegularExpression, QTextCharFormat]] = [
            (QRegularExpression(r"^\(\((header|content)\)\)$"), fmt("#a65b00", True)),
            (QRegularExpression(r"^\w[\w-]*:"), fmt("#8c3d92", True)),
            (QRegularExpression(r"\*\*[^*\n]+\*\*"), fmt("#b4235a", True)),
            (QRegularExpression(r"~~[^~\n]+~~"), fmt("#0b7285", False, True)),
            (QRegularExpression(r"==[^=\n]+=="), fmt("#6d4c41", True)),
            (QRegularExpression(r"'''[^'\n]+'''"), fmt("#2f855a", True)),
            (QRegularExpression(r"```[^`\n]+```"), fmt("#4b5563", True)),
            (QRegularExpression(r">>>[^>\n]+>>>"), fmt("#1f7a1f", True)),
            (QRegularExpression(r"\[[^\]]+\]\{[^\}]+\}"), fmt("#155e75")),
            (QRegularExpression(r"\#\[[^\]]+\]\{[^\}]+\}"), fmt("#7c3aed")),
            (QRegularExpression(r"^\={2,6}.*\={2,6}$"), fmt("#5b4b8a", True)),
            (QRegularExpression(r"^-----\s*$"), fmt("#777777")),
            (QRegularExpression(r"^@@@[^@]*@@@$"), fmt("#9a3412", True)),
        ]

    def highlightBlock(self, text: str) -> None:  # type: ignore[override]
        for pattern, text_format in self.rules:
            iterator = pattern.globalMatch(text)
            while iterator.hasNext():
                match = iterator.next()
                self.setFormat(match.capturedStart(), match.capturedLength(), text_format)


def make_markup_icon(kind: str) -> QIcon:
    pixmap = QPixmap(24, 24)
    pixmap.fill(Qt.GlobalColor.transparent)

    painter = QPainter(pixmap)
    painter.setRenderHint(QPainter.RenderHint.Antialiasing, True)
    painter.setPen(QColor("#2d2a26"))

    accent_map = {
        "bold": "#f6d8b2",
        "italic": "#d8eadf",
        "heading": "#e6d5f4",
        "center": "#d6e7f2",
        "pre": "#f0e2cf",
        "code": "#dde8f7",
        "quote": "#e0f0d8",
        "link": "#d6edf4",
        "image": "#f4e0df",
        "hr": "#dedede",
        "noparse": "#ead9c2",
        "escape": "#ece8db",
    }
    painter.setBrush(QColor(accent_map.get(kind, "#f0f0f0")))
    painter.drawRoundedRect(2, 2, 20, 20, 4, 4)
    painter.setBrush(Qt.BrushStyle.NoBrush)

    if kind == "bold":
        painter.setFont(QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont))
        font = painter.font()
        font.setBold(True)
        font.setPointSize(11)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "B")
    elif kind == "italic":
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setItalic(True)
        font.setPointSize(11)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "I")
    elif kind == "heading":
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setBold(True)
        font.setPointSize(11)
        painter.setFont(font)
        painter.drawText(0, 2, 24, 20, Qt.AlignmentFlag.AlignCenter, "H")
        painter.drawLine(4, 5, 20, 5)
        painter.drawLine(4, 19, 20, 19)
    elif kind == "center":
        painter.drawLine(4, 6, 20, 6)
        painter.drawLine(6, 12, 18, 12)
        painter.drawLine(4, 18, 20, 18)
    elif kind == "pre":
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setPointSize(10)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "[]")
    elif kind == "code":
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setPointSize(10)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "<>")
    elif kind == "quote":
        painter.drawLine(7, 7, 17, 7)
        painter.drawLine(7, 12, 17, 12)
        painter.drawLine(7, 17, 17, 17)
        painter.drawLine(5, 5, 9, 9)
        painter.drawLine(5, 10, 9, 14)
        painter.drawLine(5, 15, 9, 19)
    elif kind == "link":
        painter.drawRoundedRect(4, 8, 7, 8, 2, 2)
        painter.drawRoundedRect(13, 8, 7, 8, 2, 2)
        painter.drawLine(11, 12, 13, 12)
        painter.drawLine(12, 10, 12, 14)
    elif kind == "image":
        painter.drawRoundedRect(4, 6, 16, 12, 2, 2)
        painter.drawEllipse(16, 7, 2, 2)
        painter.drawLine(6, 16, 11, 11)
        painter.drawLine(11, 11, 15, 15)
        painter.drawLine(15, 15, 18, 12)
    elif kind == "hr":
        painter.drawLine(4, 12, 20, 12)
        painter.drawLine(4, 10, 20, 10)
    elif kind == "noparse":
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setBold(True)
        font.setPointSize(11)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "@@")
    elif kind == "escape":
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setPointSize(12)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "``")
    elif kind == "header":
        painter.drawLine(5, 7, 19, 7)
        painter.drawLine(5, 12, 19, 12)
        painter.drawLine(5, 17, 19, 17)
        painter.drawLine(5, 7, 5, 17)
    elif kind == "content":
        painter.drawLine(5, 7, 17, 7)
        painter.drawLine(5, 12, 19, 12)
        painter.drawLine(5, 17, 15, 17)
    elif kind == "title":
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setBold(True)
        font.setPointSize(10)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "T:")
    elif kind == "tags":
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setBold(True)
        font.setPointSize(10)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "#")
    elif kind == "date":
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setBold(True)
        font.setPointSize(10)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "D")
    else:
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        font.setPointSize(11)
        painter.setFont(font)
        painter.drawText(pixmap.rect(), Qt.AlignmentFlag.AlignCenter, "?")

    painter.end()
    return QIcon(pixmap)


class KikiEditor(QMainWindow):
    def __init__(self) -> None:
        super().__init__()

        self.app_root = resolve_app_root()
        self.app_icon = QIcon(str(self.app_root / "kiki-icon2.png"))
        self.repo_root = self.app_root
        self.kiki_root = self.app_root / "kiki_registered"
        self.pages_root = self.kiki_root / "pages"
        self.render_script = self.app_root / "render.php"
        self.php_path = shutil.which("php")
        self._base_url = QUrl.fromLocalFile(str(self.kiki_root) + os.sep)
        self.settings = QSettings("kiki", "editor")
        self._last_preview_html = ""
        self._last_preview_base_url = self._base_url
        self._temp_preview_file: Path | None = None

        if not self.php_path:
            QMessageBox.critical(self, "kiki editor", "PHP was not found on PATH.")
            raise SystemExit(1)

        if not self.render_script.is_file():
            QMessageBox.critical(self, "kiki editor", "render.php was not found.")
            raise SystemExit(1)

        self.current_path: Path | None = None
        self.current_page_name: str | None = None
        self.themes = self._discover_themes()
        self.current_theme = "onecolumn" if "onecolumn" in self.themes else (self.themes[0] if self.themes else "onecolumn")

        self._render_token = 0
        self._active_render_token = -1
        self._active_process: QProcess | None = None
        self._suppress_modified_signal = False
        self._render_status_text = "Ready"

        self._build_ui()
        self._configure_editor()
        self._sync_navigation_state()
        self._register_navigation_shortcuts()
        self._populate_themes()
        self._restore_window_state()
        restored_file = self._restore_last_file()
        self._refresh_status()
        if not restored_file:
            self._schedule_render()

    def _build_ui(self) -> None:
        self.setWindowTitle("kiki editor")
        self.setWindowIcon(self.app_icon)
        self.resize(1400, 900)

        self.main_toolbar = QToolBar("Tools", self)
        self.main_toolbar.setMovable(False)
        self.main_toolbar.setIconSize(QSize(20, 20))
        self.addToolBar(self.main_toolbar)

        theme_label = QLabel("Theme")
        self.main_toolbar.addWidget(theme_label)

        self.theme_combo = QComboBox()
        self.theme_combo.currentTextChanged.connect(self._on_theme_changed)
        self.main_toolbar.addWidget(self.theme_combo)

        self.main_toolbar.addSeparator()
        reload_action = QAction("Render", self)
        reload_action.setToolTip("Render the preview now")
        reload_action.setIcon(self.style().standardIcon(QStyle.StandardPixmap.SP_BrowserReload))
        reload_action.triggered.connect(self.render_now)
        self.main_toolbar.addAction(reload_action)

        self.markup_toolbar = QToolBar("Bug Markup", self)
        self.markup_toolbar.setMovable(False)
        self.markup_toolbar.setIconSize(QSize(20, 20))
        self.addToolBarBreak()
        self.addToolBar(self.markup_toolbar)
        self._add_markup_actions(self.markup_toolbar)

        self.preview_toolbar = QToolBar("Preview", self)
        self.preview_toolbar.setMovable(False)
        self.preview_toolbar.setIconSize(QSize(20, 20))
        self.addToolBarBreak()
        self.addToolBar(self.preview_toolbar)
        self._build_preview_toolbar(self.preview_toolbar)

        self.navigation_toolbar = QToolBar("Navigate", self)
        self.navigation_toolbar.setMovable(False)
        self.navigation_toolbar.setIconSize(QSize(18, 18))
        self.addToolBarBreak()
        self.addToolBar(self.navigation_toolbar)
        self._build_navigation_toolbar(self.navigation_toolbar)

        self.metadata_toolbar = QToolBar("Metadata", self)
        self.metadata_toolbar.setMovable(False)
        self.metadata_toolbar.setIconSize(QSize(18, 18))
        self.addToolBarBreak()
        self.addToolBar(self.metadata_toolbar)
        self._build_metadata_toolbar(self.metadata_toolbar)

        self.splitter = QSplitter(Qt.Orientation.Horizontal, self)
        self.editor = CodeEditor()
        self.preview = QWebEngineView()
        self.preview.setMinimumWidth(420)
        self.splitter.setChildrenCollapsible(False)
        self.splitter.addWidget(self.editor)
        self.splitter.addWidget(self.preview)
        self.splitter.setStretchFactor(0, 1)
        self.splitter.setStretchFactor(1, 1)
        self.splitter.splitterMoved.connect(lambda _pos, _index: self._save_current_view_state())
        QTimer.singleShot(0, lambda: self.splitter.setSizes([700, 700]))

        container = QWidget(self)
        layout = QHBoxLayout(container)
        layout.setContentsMargins(0, 0, 0, 0)
        layout.addWidget(self.splitter)
        self.setCentralWidget(container)

        self.status_bar = QStatusBar(self)
        self.path_label = QLabel()
        self.render_label = QLabel()
        self.metadata_label = QLabel("Meta 0/4")
        self.zoom_label = QLabel("100%")
        self.status_bar.addPermanentWidget(self.path_label, 1)
        self.status_bar.addPermanentWidget(self.render_label)
        self.status_bar.addPermanentWidget(self.metadata_label)
        self.status_bar.addPermanentWidget(self.zoom_label)
        self.setStatusBar(self.status_bar)

        self._build_menus()

        self.render_timer = QTimer(self)
        self.render_timer.setSingleShot(True)
        self.render_timer.setInterval(300)
        self.render_timer.timeout.connect(self._start_render)

        self.render_timeout_timer = QTimer(self)
        self.render_timeout_timer.setSingleShot(True)
        self.render_timeout_timer.setInterval(3000)
        self.render_timeout_timer.timeout.connect(self._on_render_timeout)

    def _build_menus(self) -> None:
        file_menu = self.menuBar().addMenu("&File")

        new_action = QAction("&New", self)
        new_action.setShortcut(QKeySequence.StandardKey.New)
        new_action.triggered.connect(self.new_file)
        file_menu.addAction(new_action)

        open_action = QAction("&Open", self)
        open_action.setShortcut(QKeySequence.StandardKey.Open)
        open_action.triggered.connect(self.open_file)
        file_menu.addAction(open_action)

        save_action = QAction("&Save", self)
        save_action.setShortcut(QKeySequence.StandardKey.Save)
        save_action.triggered.connect(self.save_file)
        file_menu.addAction(save_action)

        save_as_action = QAction("Save &As", self)
        save_as_action.setShortcut(QKeySequence.StandardKey.SaveAs)
        save_as_action.triggered.connect(self.save_file_as)
        file_menu.addAction(save_as_action)

        file_menu.addSeparator()

        exit_action = QAction("E&xit", self)
        exit_action.setShortcut(QKeySequence.StandardKey.Quit)
        exit_action.triggered.connect(self.close)
        file_menu.addAction(exit_action)

    def _add_markup_actions(self, toolbar: QToolBar) -> None:
        toolbar.addAction(self._markup_action("Bold", "**", "**", "Wrap selection in bold markup", "Ctrl+B", icon=make_markup_icon("bold")))
        toolbar.addAction(self._markup_action("Italic", "~~", "~~", "Wrap selection in italic markup", "Ctrl+I", icon=make_markup_icon("italic")))
        toolbar.addAction(self._markup_action("Heading", "==", "==", "Wrap selection in heading markup", "Ctrl+H", icon=make_markup_icon("heading")))
        toolbar.addSeparator()
        toolbar.addAction(self._markup_action("Center", "--", "--", "Wrap selection in centered text", "Ctrl+Alt+M", icon=make_markup_icon("center")))
        toolbar.addAction(self._markup_action("Escape", "`", "`", "Wrap selection in escape markup", "Ctrl+Alt+E", icon=make_markup_icon("escape")))
        toolbar.addAction(self._markup_action("NoParse", "@@@", "@@@", "Insert a no-parse section", "Ctrl+Alt+N", block=True, icon=make_markup_icon("noparse")))
        toolbar.addSeparator()
        toolbar.addAction(self._markup_action("Pre", "'''", "'''", "Insert or wrap a preformatted block", "Ctrl+Alt+P", block=True, icon=make_markup_icon("pre")))
        toolbar.addAction(self._markup_action("Code", "```", "```", "Insert or wrap a code block", "Ctrl+Alt+C", block=True, icon=make_markup_icon("code")))
        toolbar.addAction(self._markup_action("Quote", ">>>", ">>>", "Insert or wrap a blockquote", "Ctrl+Alt+Q", block=True, icon=make_markup_icon("quote")))
        toolbar.addSeparator()
        toolbar.addAction(self._markup_action("Link", "[", "]{text}", "Insert a basic local link template", "Ctrl+L", icon=make_markup_icon("link")))
        toolbar.addAction(self._markup_action("Image", "#[", "]{alt}", "Insert a basic image template", "Ctrl+Alt+I", icon=make_markup_icon("image")))
        toolbar.addAction(self._insert_action("HR", "-----\n", "Insert a horizontal rule", "Ctrl+R", icon=make_markup_icon("hr")))
        toolbar.addSeparator()
        header_action = QAction("Header", self)
        header_action.setToolTip("Insert a full header/content scaffold")
        header_action.setIcon(make_markup_icon("header"))
        header_action.triggered.connect(self._insert_block_template)
        toolbar.addAction(header_action)
        toolbar.addAction(self._insert_action("Content", "((content))\n", "Insert a content block marker", None, icon=make_markup_icon("content")))
        toolbar.addAction(self._insert_action("Title", "title: ", "Insert a title field", None, icon=make_markup_icon("title")))
        toolbar.addAction(self._insert_action("Tags", "tags: ", "Insert a tags field", None, icon=make_markup_icon("tags")))
        toolbar.addAction(self._insert_action("Date", "date: ", "Insert a date field", None, icon=make_markup_icon("date")))

    def _markup_action(self, label: str, prefix: str, suffix: str, tooltip: str, shortcut: str | None = None, block: bool = False, icon=None) -> QAction:
        action = QAction(label, self)
        action.setToolTip(tooltip)
        if icon is not None:
            action.setIcon(icon)
        if shortcut:
            action.setShortcut(QKeySequence(shortcut))
        action.triggered.connect(lambda _checked=False, p=prefix, s=suffix, is_block=block: self._wrap_or_insert(p, s, is_block))
        return action

    def _insert_action(self, label: str, text: str, tooltip: str, shortcut: str | None = None, icon=None) -> QAction:
        action = QAction(label, self)
        action.setToolTip(tooltip)
        if icon is not None:
            action.setIcon(icon)
        if shortcut:
            action.setShortcut(QKeySequence(shortcut))
        action.triggered.connect(lambda _checked=False, t=text: self._insert_text(t))
        return action

    def _insert_snippet(self, text: str, cursor_offset: int | None = None) -> None:
        cursor = self.editor.textCursor()
        anchor = cursor.position()
        cursor.insertText(text)
        if cursor_offset is not None:
            cursor.setPosition(anchor + cursor_offset)
            self.editor.setTextCursor(cursor)

    def _insert_block_template(self) -> None:
        template = "((header))\ntitle: \ntags: \nmarkup: bug\n((content))\n"
        self._insert_snippet(template, len("((header))\ntitle: "))

    def _build_preview_toolbar(self, toolbar: QToolBar) -> None:
        reload_action = QAction("Reload", self)
        reload_action.setToolTip("Render the preview again")
        reload_action.setIcon(self.style().standardIcon(QStyle.StandardPixmap.SP_BrowserReload))
        reload_action.triggered.connect(self.render_now)
        toolbar.addAction(reload_action)

        zoom_out_action = QAction("Zoom Out", self)
        zoom_out_action.setToolTip("Decrease preview zoom")
        zoom_out_action.setIcon(self.style().standardIcon(QStyle.StandardPixmap.SP_ArrowDown))
        zoom_out_action.triggered.connect(lambda: self.adjust_preview_zoom(-0.1))
        toolbar.addAction(zoom_out_action)

        zoom_in_action = QAction("Zoom In", self)
        zoom_in_action.setToolTip("Increase preview zoom")
        zoom_in_action.setIcon(self.style().standardIcon(QStyle.StandardPixmap.SP_ArrowUp))
        zoom_in_action.triggered.connect(lambda: self.adjust_preview_zoom(0.1))
        toolbar.addAction(zoom_in_action)

        reset_action = QAction("Reset Zoom", self)
        reset_action.setToolTip("Reset preview zoom to 100%")
        reset_action.setIcon(self.style().standardIcon(QStyle.StandardPixmap.SP_CommandLink))
        reset_action.triggered.connect(self.reset_preview_zoom)
        toolbar.addAction(reset_action)

        toolbar.addSeparator()

        browser_action = QAction("Open Browser", self)
        browser_action.setToolTip("Open the current preview in the default browser")
        browser_action.setIcon(self.style().standardIcon(QStyle.StandardPixmap.SP_DirOpenIcon))
        browser_action.triggered.connect(self.open_preview_in_browser)
        toolbar.addAction(browser_action)

    def _build_navigation_toolbar(self, toolbar: QToolBar) -> None:
        toolbar.addWidget(QLabel("Find"))
        self.find_input = QLineEdit()
        self.find_input.setPlaceholderText("Search current file")
        self.find_input.setClearButtonEnabled(True)
        self.find_input.returnPressed.connect(self.find_next)
        self.find_input.textChanged.connect(self._update_find_match_count)
        self.find_input.setMaximumWidth(260)
        toolbar.addWidget(self.find_input)

        find_prev_button = QPushButton("Prev")
        find_prev_button.clicked.connect(self.find_previous)
        toolbar.addWidget(find_prev_button)

        find_next_button = QPushButton("Next")
        find_next_button.clicked.connect(self.find_next)
        toolbar.addWidget(find_next_button)

        self.find_count_label = QLabel("")
        toolbar.addWidget(self.find_count_label)

        toolbar.addSeparator()
        toolbar.addWidget(QLabel("Line"))
        self.goto_line_spin = QSpinBox()
        self.goto_line_spin.setMinimum(1)
        self.goto_line_spin.setMaximum(1)
        self.goto_line_spin.setPrefix("#")
        self.goto_line_spin.setMaximumWidth(100)
        toolbar.addWidget(self.goto_line_spin)

        goto_button = QPushButton("Go")
        goto_button.clicked.connect(self.goto_line)
        toolbar.addWidget(goto_button)

    def _build_metadata_toolbar(self, toolbar: QToolBar) -> None:
        toolbar.addWidget(QLabel("Title"))
        self.meta_title = QLineEdit()
        self.meta_title.setPlaceholderText("Page title")
        self.meta_title.setMaximumWidth(240)
        toolbar.addWidget(self.meta_title)

        toolbar.addWidget(QLabel("Tags"))
        self.meta_tags = QLineEdit()
        self.meta_tags.setPlaceholderText("Comma-separated tags")
        self.meta_tags.setMaximumWidth(260)
        toolbar.addWidget(self.meta_tags)

        toolbar.addWidget(QLabel("Date"))
        self.meta_date = QLineEdit()
        self.meta_date.setPlaceholderText("YYYY-MM-DD")
        self.meta_date.setMaximumWidth(120)
        toolbar.addWidget(self.meta_date)

        toolbar.addWidget(QLabel("Markup"))
        self.meta_markup = QLineEdit()
        self.meta_markup.setPlaceholderText("bug")
        self.meta_markup.setMaximumWidth(90)
        toolbar.addWidget(self.meta_markup)

        apply_button = QPushButton("Apply")
        apply_button.clicked.connect(self._apply_metadata)
        toolbar.addWidget(apply_button)

        sync_button = QPushButton("Sync")
        sync_button.setToolTip("Reload metadata fields from the current header")
        sync_button.clicked.connect(lambda: self._sync_metadata_panel_from_text(self.editor.toPlainText()))
        toolbar.addWidget(sync_button)

    def _configure_editor(self) -> None:
        font = QFontDatabase.systemFont(QFontDatabase.SystemFont.FixedFont)
        self.editor.setFont(font)
        self.highlighter = BugHighlighter(self.editor.document())
        self.editor.textChanged.connect(self._on_text_changed)
        self.editor.document().modificationChanged.connect(self._on_modified_changed)
        self.editor.cursorPositionChanged.connect(self._sync_navigation_state)
        self.editor.blockCountChanged.connect(self._sync_navigation_state)
        self.editor.textChanged.connect(self._update_find_match_count)

    def _discover_themes(self) -> list[str]:
        themes_root = self.kiki_root / "themes"
        themes: list[str] = []
        if themes_root.is_dir():
            for entry in themes_root.iterdir():
                if entry.is_dir() and (entry / "style.css").is_file():
                    themes.append(entry.name)
        themes.sort()
        return themes

    def _populate_themes(self) -> None:
        with QSignalBlocker(self.theme_combo):
            self.theme_combo.clear()
            self.theme_combo.addItems(self.themes)
            if self.current_theme in self.themes:
                self.theme_combo.setCurrentText(self.current_theme)
        self._apply_editor_theme()

    def _current_theme_href(self) -> str:
        return f"themes/{self.current_theme}/style.css"

    def _editor_theme_palette(self) -> tuple[str, str, str]:
        theme_palettes = {
            "onecolumn": ("#f1efe8", "#7d766d", "#e7f2fb"),
            "twocolumn": ("#f6f0df", "#74664c", "#fbf0c9"),
            "mosaic": ("#d9d1c5", "#5f5346", "#eadfc8"),
            "oblivity": ("#f1ede5", "#6b6058", "#e7e1d7"),
            "systemplus": ("#ececec", "#666666", "#d9e6f4"),
        }
        return theme_palettes.get(self.current_theme, ("#f4f4ef", "#7a7a7a", "#e9f4ff"))

    def _apply_editor_theme(self) -> None:
        background, foreground, highlight = self._editor_theme_palette()
        editor_backgrounds = {
            "onecolumn": ("#fbfaf5", "#1f1b16"),
            "twocolumn": ("#fcf7ea", "#241f17"),
            "mosaic": ("#ede2d1", "#2d241c"),
            "oblivity": ("#f5f1ea", "#201b16"),
            "systemplus": ("#f5f5f5", "#1d1d1d"),
        }
        editor_background, editor_foreground = editor_backgrounds.get(self.current_theme, ("#ffffff", "#111111"))
        self.editor.set_theme_colors(background, foreground, highlight, editor_background, editor_foreground)
        self._apply_chrome_theme()

    def _chrome_palette(self) -> tuple[str, str, str, str, str]:
        palette = {
            "onecolumn": ("#f4efe3", "#3a312a", "#b59c7a", "#fbf8f1", "#efe7d6"),
            "twocolumn": ("#f4ead0", "#3f3528", "#b28a57", "#fff7e6", "#f2e0b4"),
            "mosaic": ("#ddd2c1", "#3b3128", "#9f8262", "#f2e9da", "#dccfb9"),
            "oblivity": ("#efebe3", "#3e372f", "#ab927c", "#f8f3ea", "#e7ded2"),
            "systemplus": ("#ededed", "#343434", "#8b8b8b", "#f8f8f8", "#e5e5e5"),
        }
        return palette.get(self.current_theme, ("#f4efe3", "#3a312a", "#b59c7a", "#fbf8f1", "#efe7d6"))

    def _apply_chrome_theme(self) -> None:
        toolbar_bg, text, border, panel_bg, hover_bg = self._chrome_palette()
        chrome_css = (
            f"QToolBar {{ background: {toolbar_bg}; color: {text}; border: none; spacing: 6px; padding: 4px; }}"
            f"QToolBar::separator {{ background: {border}; width: 1px; margin: 4px 6px; }}"
            f"QToolButton {{ background: transparent; color: {text}; border: 1px solid transparent; padding: 3px 6px; border-radius: 4px; }}"
            f"QToolButton:hover {{ background: {hover_bg}; border-color: {border}; }}"
            f"QComboBox, QLineEdit, QSpinBox {{ background: {panel_bg}; color: {text}; border: 1px solid {border}; padding: 2px 6px; border-radius: 4px; }}"
            f"QPushButton {{ background: {panel_bg}; color: {text}; border: 1px solid {border}; padding: 2px 10px; border-radius: 4px; }}"
            f"QPushButton:hover {{ background: {hover_bg}; }}"
            f"QStatusBar {{ background: {toolbar_bg}; color: {text}; border-top: 1px solid {border}; }}"
            f"QLabel {{ color: {text}; }}"
        )
        self.main_toolbar.setStyleSheet(chrome_css)
        self.markup_toolbar.setStyleSheet(chrome_css)
        self.preview_toolbar.setStyleSheet(chrome_css)
        self.navigation_toolbar.setStyleSheet(chrome_css)
        self.metadata_toolbar.setStyleSheet(chrome_css)
        self.status_bar.setStyleSheet(chrome_css)
        self.theme_combo.setStyleSheet(f"QComboBox {{ background: {panel_bg}; color: {text}; border: 1px solid {border}; padding: 2px 6px; border-radius: 4px; }}")
        self.preview.setStyleSheet(f"QWebEngineView {{ background: {panel_bg}; border-left: 1px solid {border}; }}")

    def _restore_window_state(self) -> None:
        geometry = self.settings.value("window/geometry")
        window_state = self.settings.value("window/state")
        splitter_sizes = self.settings.value("window/splitter_sizes")
        preview_zoom = self.settings.value("preview/zoom")
        saved_theme = self.settings.value("editor/theme")

        if geometry is not None:
            self.restoreGeometry(geometry)
        if window_state is not None:
            self.restoreState(window_state)
        if splitter_sizes is not None:
            try:
                sizes = [int(value) for value in splitter_sizes]
                if sizes:
                    QTimer.singleShot(0, lambda: self.splitter.setSizes(sizes))
            except (TypeError, ValueError):
                pass
        if preview_zoom is not None:
            try:
                self.preview.setZoomFactor(float(preview_zoom))
            except (TypeError, ValueError):
                pass
        if saved_theme is not None and saved_theme in self.themes:
            self.current_theme = saved_theme
            with QSignalBlocker(self.theme_combo):
                self.theme_combo.setCurrentText(saved_theme)
            self._apply_editor_theme()

    def _save_window_state(self) -> None:
        self.settings.setValue("window/geometry", self.saveGeometry())
        self.settings.setValue("window/state", self.saveState())
        self.settings.setValue("window/splitter_sizes", self.splitter.sizes())
        self.settings.setValue("preview/zoom", self.preview.zoomFactor())
        self.settings.setValue("editor/theme", self.current_theme)

    def _view_state_key(self, path: Path) -> str:
        normalized = str(path.resolve())
        digest = hashlib.sha1(normalized.encode("utf-8")).hexdigest()
        return f"files/{digest}"

    def _save_view_state_for_path(self, path: Path) -> None:
        key = self._view_state_key(path)
        self.settings.setValue(f"{key}/splitter_sizes", self.splitter.sizes())
        self.settings.setValue(f"{key}/preview_zoom", self.preview.zoomFactor())

    def _save_current_view_state(self) -> None:
        if self.current_path is not None:
            self._save_view_state_for_path(self.current_path)

    def _restore_view_state_for_path(self, path: Path) -> None:
        key = self._view_state_key(path)
        splitter_sizes = self.settings.value(f"{key}/splitter_sizes")
        preview_zoom = self.settings.value(f"{key}/preview_zoom")

        if splitter_sizes is not None:
            try:
                sizes = [int(value) for value in splitter_sizes]
                if sizes:
                    QTimer.singleShot(0, lambda: self.splitter.setSizes(sizes))
            except (TypeError, ValueError):
                pass

        if preview_zoom is not None:
            try:
                self.preview.setZoomFactor(float(preview_zoom))
            except (TypeError, ValueError):
                pass

    def _restore_last_file(self) -> bool:
        last_file = self.settings.value("editor/last_file")
        if not last_file:
            return False

        path = Path(str(last_file))
        if path.is_file():
            self._load_file(path)
            return True
        return False

    def _sync_navigation_state(self) -> None:
        if not hasattr(self, "goto_line_spin"):
            return

        block_count = max(1, self.editor.blockCount())
        cursor_line = self.editor.textCursor().blockNumber() + 1
        with QSignalBlocker(self.goto_line_spin):
            self.goto_line_spin.setMaximum(block_count)
            self.goto_line_spin.setValue(cursor_line)

    def _update_find_match_count(self) -> None:
        if not hasattr(self, "find_count_label"):
            return

        query = self.find_input.text().strip()
        if not query:
            self.find_count_label.setText("")
            return

        count = self._count_find_matches(query)
        if count == 0:
            self.find_count_label.setText("0 matches")
        elif count == 1:
            self.find_count_label.setText("1 match")
        else:
            self.find_count_label.setText(f"{count} matches")

    def _count_find_matches(self, query: str) -> int:
        if not query:
            return 0

        text = self.editor.toPlainText()
        total = 0
        start = 0
        while True:
            index = text.find(query, start)
            if index == -1:
                break
            total += 1
            start = index + max(1, len(query))
        return total

    def _focus_find_input(self) -> None:
        self.find_input.setFocus()
        self.find_input.selectAll()

    def _split_bug_document(self, text: str) -> tuple[list[str], list[str], list[str], bool]:
        lines = text.splitlines()
        header_index = None
        content_index = None

        for index, line in enumerate(lines):
            marker = line.strip()
            if marker == "((header))" and header_index is None:
                header_index = index
            elif marker == "((content))" and header_index is not None:
                content_index = index
                break

        if header_index is not None and content_index is not None and content_index > header_index:
            return lines[:header_index], lines[header_index + 1 : content_index], lines[content_index + 1 :], True

        return [], [], lines, False

    def _metadata_from_header(self, header_lines: list[str]) -> dict[str, str]:
        fields = {"title": "", "tags": "", "date": "", "markup": ""}
        for line in header_lines:
            match = re.match(r"^\s*([A-Za-z0-9_-]+)\s*:\s*(.*)$", line)
            if not match:
                continue
            key = match.group(1).lower()
            if key in fields:
                fields[key] = match.group(2)
        return fields

    def _sync_metadata_panel_from_text(self, text: str) -> None:
        _, header_lines, _, _ = self._split_bug_document(text)
        fields = self._metadata_from_header(header_lines)
        with QSignalBlocker(self.meta_title):
            self.meta_title.setText(fields["title"])
        with QSignalBlocker(self.meta_tags):
            self.meta_tags.setText(fields["tags"])
        with QSignalBlocker(self.meta_date):
            self.meta_date.setText(fields["date"])
        with QSignalBlocker(self.meta_markup):
            self.meta_markup.setText(fields["markup"] or "bug")

    def _metadata_completeness(self, text: str) -> tuple[int, int, list[str]]:
        _, header_lines, _, structured = self._split_bug_document(text)
        fields = self._metadata_from_header(header_lines) if structured else {"title": "", "tags": "", "date": "", "markup": ""}
        required = ("title", "tags", "date", "markup")
        present = [key for key in required if fields.get(key, "").strip()]
        missing = [key for key in required if key not in present]
        return len(present), len(required), missing

    def _register_navigation_shortcuts(self) -> None:
        find_action = QAction("Find", self)
        find_action.setShortcut(QKeySequence.StandardKey.Find)
        find_action.triggered.connect(self._focus_find_input)
        self.addAction(find_action)

        find_next_action = QAction("Find Next", self)
        find_next_action.setShortcut(QKeySequence("F3"))
        find_next_action.triggered.connect(self.find_next)
        self.addAction(find_next_action)

        find_previous_action = QAction("Find Previous", self)
        find_previous_action.setShortcut(QKeySequence("Shift+F3"))
        find_previous_action.triggered.connect(self.find_previous)
        self.addAction(find_previous_action)

        goto_line_action = QAction("Go to Line", self)
        goto_line_action.setShortcut(QKeySequence("Ctrl+G"))
        goto_line_action.triggered.connect(self.goto_line)
        self.addAction(goto_line_action)

    def _merge_metadata_header(self, header_lines: list[str], fields: dict[str, str]) -> list[str]:
        merged: list[str] = []
        seen: set[str] = set()
        normalized_fields = {
            "title": fields.get("title", "").strip(),
            "tags": fields.get("tags", "").strip(),
            "date": fields.get("date", "").strip(),
            "markup": fields.get("markup", "").strip() or "bug",
        }

        for line in header_lines:
            match = re.match(r"^\s*([A-Za-z0-9_-]+)\s*:\s*(.*)$", line)
            if not match:
                merged.append(line)
                continue

            key = match.group(1).lower()
            if key in normalized_fields:
                merged.append(f"{key}: {normalized_fields[key]}")
                seen.add(key)
            else:
                merged.append(line)

        for key in ("title", "tags", "date", "markup"):
            if key not in seen:
                merged.append(f"{key}: {normalized_fields[key]}")

        return merged

    def _apply_metadata(self) -> None:
        current_text = self.editor.toPlainText()
        prefix_lines, header_lines, content_lines, structured = self._split_bug_document(current_text)
        if not structured:
            content_lines = current_text.splitlines()

        fields = {
            "title": self.meta_title.text(),
            "tags": self.meta_tags.text(),
            "date": self.meta_date.text(),
            "markup": self.meta_markup.text(),
        }

        updated_header = self._merge_metadata_header(header_lines, fields)
        new_lines = list(prefix_lines)
        new_lines.append("((header))")
        new_lines.extend(updated_header)
        new_lines.append("((content))")
        new_lines.extend(content_lines)

        new_text = "\n".join(new_lines)
        if current_text.endswith("\n"):
            new_text += "\n"

        self._save_current_view_state()
        self._set_editor_text(new_text)
        self.editor.document().setModified(True)
        self._sync_metadata_panel_from_text(new_text)
        self._update_find_match_count()
        self._refresh_status("Metadata updated")
        self._schedule_render()

    def find_next(self) -> None:
        self._find_text(forward=True)

    def find_previous(self) -> None:
        self._find_text(forward=False)

    def _find_text(self, forward: bool) -> None:
        query = self.find_input.text().strip()
        if not query:
            self._refresh_status("Find needs text")
            return

        flags = QTextDocument.FindFlag(0)
        if not forward:
            flags |= QTextDocument.FindFlag.FindBackward

        cursor = self.editor.textCursor()
        if forward and cursor.hasSelection():
            search_cursor = QTextCursor(cursor)
            search_cursor.setPosition(cursor.selectionEnd())
        elif not forward and cursor.hasSelection():
            search_cursor = QTextCursor(cursor)
            search_cursor.setPosition(cursor.selectionStart())
        else:
            search_cursor = cursor

        found = self.editor.document().find(query, search_cursor, flags)
        if found.isNull():
            wrap_cursor = QTextCursor(self.editor.document())
            if forward:
                found = self.editor.document().find(query, wrap_cursor, flags)
            else:
                wrap_cursor.movePosition(QTextCursor.MoveOperation.End)
                found = self.editor.document().find(query, wrap_cursor, flags)

        if found.isNull():
            self._refresh_status(f'No match for "{query}"')
            return

        self.editor.setTextCursor(found)
        self.editor.centerCursor()
        self._sync_navigation_state()
        self._refresh_status(f'Found "{query}"')

    def goto_line(self) -> None:
        line = self.goto_line_spin.value()
        block_count = max(1, self.editor.blockCount())
        line = max(1, min(line, block_count))

        cursor = QTextCursor(self.editor.document())
        if line > 1:
            cursor.movePosition(QTextCursor.MoveOperation.Down, QTextCursor.MoveMode.MoveAnchor, line - 1)

        self.editor.setTextCursor(cursor)
        self.editor.centerCursor()
        self.editor.setFocus()
        self._refresh_status(f"Line {line}")

    def render_now(self) -> None:
        self.render_timer.stop()
        self._start_render()

    def adjust_preview_zoom(self, delta: float) -> None:
        self.preview.setZoomFactor(max(0.25, min(3.0, self.preview.zoomFactor() + delta)))
        self.settings.setValue("preview/zoom", self.preview.zoomFactor())
        self._save_current_view_state()
        self._refresh_status(f"Rendered at {int(self.preview.zoomFactor() * 100)}%")

    def reset_preview_zoom(self) -> None:
        self.preview.setZoomFactor(1.0)
        self.settings.setValue("preview/zoom", self.preview.zoomFactor())
        self._save_current_view_state()
        self._refresh_status("Rendered")

    def open_preview_in_browser(self) -> None:
        if not self._last_preview_html:
            return

        try:
            with tempfile.NamedTemporaryFile("w", delete=False, suffix=".html", encoding="utf-8") as handle:
                handle.write(self._last_preview_html)
                temp_path = Path(handle.name)
            self._temp_preview_file = temp_path
            QDesktopServices.openUrl(QUrl.fromLocalFile(str(temp_path)))
        except OSError as exc:
            QMessageBox.critical(self, "Open preview failed", f"Could not create a browser preview file.\n\n{exc}")

    def _update_zoom_status(self) -> None:
        self.zoom_label.setText(f"{int(self.preview.zoomFactor() * 100)}%")

    def _current_file_display(self) -> str:
        if self.current_path is None:
            return "Untitled"
        try:
            return str(self.current_path.relative_to(self.repo_root))
        except ValueError:
            return str(self.current_path)

    def _derive_page_name(self, path: Path) -> str | None:
        try:
            relative = path.resolve().relative_to(self.pages_root.resolve())
        except ValueError:
            relative = path.name
            stem = Path(relative).with_suffix("").as_posix()
            return stem.replace(" ", "_")

        return relative.with_suffix("").as_posix().replace(" ", "_")

    def _refresh_status(self, render_status: str | None = None) -> None:
        if render_status is not None:
            self._render_status_text = render_status

        self.path_label.setText(self._current_file_display())
        self.render_label.setText(self._render_status_text)
        meta_complete, meta_total, meta_missing = self._metadata_completeness(self.editor.toPlainText())
        if meta_missing:
            missing_text = ", ".join(meta_missing)
            self.metadata_label.setText(f"Meta {meta_complete}/{meta_total}")
            self.metadata_label.setToolTip(f"Missing: {missing_text}")
        else:
            self.metadata_label.setText(f"Meta {meta_complete}/{meta_total}")
            self.metadata_label.setToolTip("All metadata fields are present.")
        self._update_zoom_status()
        modified_suffix = "*" if self.editor.document().isModified() else ""
        self.setWindowTitle(f"kiki editor - {self._current_file_display()}{modified_suffix}")

    def _set_editor_text(self, text: str) -> None:
        self._suppress_modified_signal = True
        try:
            with QSignalBlocker(self.editor):
                self.editor.setPlainText(text)
        finally:
            self._suppress_modified_signal = False
        self.editor.document().setModified(False)

    def _wrap_or_insert(self, prefix: str, suffix: str, block: bool = False) -> None:
        cursor = self.editor.textCursor()
        if cursor.hasSelection():
            selected = cursor.selectedText().replace("\u2029", "\n")
            if block:
                cursor.insertText(f"{prefix}\n{selected}\n{suffix}\n")
            else:
                cursor.insertText(f"{prefix}{selected}{suffix}")
            self.editor.setTextCursor(cursor)
            return

        if block:
            template = f"{prefix}\n\n{suffix}\n"
            anchor = cursor.position()
            cursor.insertText(template)
            cursor.setPosition(anchor + len(prefix) + 1)
            self.editor.setTextCursor(cursor)
            return

        template, select_start, select_length = self._template_for_markup(prefix, suffix)
        anchor = cursor.position()
        cursor.insertText(template)
        if select_start is not None and select_length is not None:
            cursor.setPosition(anchor + select_start)
            cursor.setPosition(anchor + select_start + select_length, QTextCursor.MoveMode.KeepAnchor)
        self.editor.setTextCursor(cursor)

    def _insert_text(self, text: str) -> None:
        cursor = self.editor.textCursor()
        cursor.insertText(text)

    def _template_for_markup(self, prefix: str, suffix: str) -> tuple[str, int | None, int | None]:
        templates = {
            ("[", "]{text}"): ("[page]{text}", 1, 4),
            ("#[", "]{alt}"): ("#[path/to/image]{alt}", 2, 13),
            ("--", "--"): ("--text--", 2, 4),
            ("`", "`"): ("`text`", 1, 4),
        }
        if (prefix, suffix) in templates:
            return templates[(prefix, suffix)]

        template = f"{prefix}text{suffix}"
        return template, len(prefix), len(prefix) + 4

    def _maybe_save_changes(self) -> bool:
        if not self.editor.document().isModified():
            return True

        box = QMessageBox(self)
        box.setIcon(QMessageBox.Icon.Warning)
        box.setWindowTitle("Unsaved changes")
        box.setText("The current document has unsaved changes.")
        box.setInformativeText("Save them before continuing?")
        box.setStandardButtons(
            QMessageBox.StandardButton.Save
            | QMessageBox.StandardButton.Discard
            | QMessageBox.StandardButton.Cancel
        )
        box.setDefaultButton(QMessageBox.StandardButton.Save)
        result = box.exec()

        if result == QMessageBox.StandardButton.Save:
            return self._save_to_current_path()
        if result == QMessageBox.StandardButton.Discard:
            return True
        return False

    def _load_file(self, path: Path) -> None:
        self._save_current_view_state()
        try:
            text = path.read_text(encoding="utf-8")
        except OSError as exc:
            QMessageBox.critical(self, "Open failed", f"Could not open file:\n{path}\n\n{exc}")
            return

        self.current_path = path
        self.current_page_name = self._derive_page_name(path)
        self.settings.setValue("editor/last_file", str(path))
        self._restore_view_state_for_path(path)
        self._set_editor_text(text)
        self._sync_metadata_panel_from_text(text)
        self._sync_navigation_state()
        self._update_find_match_count()
        self._refresh_status("Loaded")
        self._schedule_render()

    def new_file(self) -> None:
        if not self._maybe_save_changes():
            return

        self._save_current_view_state()
        self.current_path = None
        self.current_page_name = None
        self._set_editor_text("")
        self._sync_metadata_panel_from_text("")
        self._sync_navigation_state()
        self._update_find_match_count()
        self._refresh_status("New document")
        self._schedule_render()

    def open_file(self) -> None:
        if not self._maybe_save_changes():
            return

        start_dir = str(self.current_path.parent) if self.current_path else str(self.pages_root)
        path_text, _ = QFileDialog.getOpenFileName(
            self,
            "Open kiki page",
            start_dir,
            "Bug files (*.bug);;All files (*)",
        )
        if not path_text:
            return

        self._load_file(Path(path_text))

    def _save_to_current_path(self) -> bool:
        if self.current_path is None:
            return self.save_file_as()

        try:
            self.current_path.write_text(self.editor.toPlainText(), encoding="utf-8")
        except OSError as exc:
            QMessageBox.critical(self, "Save failed", f"Could not save file:\n{self.current_path}\n\n{exc}")
            return False

        self.settings.setValue("editor/last_file", str(self.current_path))
        self._save_view_state_for_path(self.current_path)
        self.editor.document().setModified(False)
        self._refresh_status("Saved")
        return True

    def save_file(self) -> bool:
        return self._save_to_current_path()

    def save_file_as(self) -> bool:
        start_dir = str(self.current_path.parent) if self.current_path else str(self.pages_root)
        path_text, _ = QFileDialog.getSaveFileName(
            self,
            "Save kiki page",
            start_dir,
            "Bug files (*.bug);;All files (*)",
        )
        if not path_text:
            return False

        path = Path(path_text)
        if path.suffix.lower() != ".bug":
            path = path.with_suffix(".bug")

        try:
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(self.editor.toPlainText(), encoding="utf-8")
        except OSError as exc:
            QMessageBox.critical(self, "Save failed", f"Could not save file:\n{path}\n\n{exc}")
            return False

        self.current_path = path
        self.current_page_name = self._derive_page_name(path)
        self.settings.setValue("editor/last_file", str(path))
        self._save_view_state_for_path(path)
        self.editor.document().setModified(False)
        self._refresh_status("Saved")
        return True

    def _on_text_changed(self) -> None:
        if self._suppress_modified_signal:
            return
        self._schedule_render()

    def _on_modified_changed(self, modified: bool) -> None:
        if self._suppress_modified_signal:
            return
        self._refresh_status()

    def _schedule_render(self) -> None:
        self.render_timer.start()
        self._refresh_status("Waiting to render")

    def _preview_shell_html(self, fragment: str) -> str:
        theme_href = self._current_theme_href()
        return (
            "<!doctype html>"
            "<html lang='en'>"
            "<head>"
            "<meta charset='utf-8'>"
            "<meta name='viewport' content='width=device-width, initial-scale=1.0'>"
            f"<base href='{self._base_url.toString()}' />"
            "<link rel='icon' type='image/x-icon' href='../favicon.ico'>"
            "<link rel='icon' type='image/png' sizes='512x512' href='../kiki-icon2.png'>"
            f"<link rel='stylesheet' type='text/css' href='{theme_href}'>"
            "<style>"
            "div.page_content { line-height: 1.6; }"
            "div.page_content p { margin: 0 0 1em 0; }"
            "div.page_content h1, div.page_content h2, div.page_content h3, "
            "div.page_content h4, div.page_content h5, div.page_content h6 { "
            "margin: 1.1em 0 0.6em 0; line-height: 1.2; }"
            "div.page_content h1:first-child, div.page_content h2:first-child, "
            "div.page_content h3:first-child, div.page_content h4:first-child, "
            "div.page_content h5:first-child, div.page_content h6:first-child { "
            "margin-top: 0; }"
            "</style>"
            "</head>"
            "<body>"
            "<div id='container'>"
            "<div id='content'>"
            "<div class='page_content'>"
            f"{fragment}"
            "</div>"
            "</div>"
            "</div>"
            "</body>"
            "</html>"
        )

    def _show_preview_error(self, message: str) -> None:
        safe_message = html.escape(message)
        error_html = (
            "<!doctype html><html><head><meta charset='utf-8'></head><body>"
            "<div style='font-family: monospace; white-space: pre-wrap; padding: 1rem;'>"
            f"{safe_message}"
            "</div></body></html>"
        )
        self.preview.setHtml(error_html, self._base_url)

    def _start_render(self) -> None:
        token = self._render_token + 1
        self._render_token = token
        self._active_render_token = token

        if self._active_process is not None:
            self._active_process.kill()
            self._active_process.deleteLater()
            self._active_process = None

        process = QProcess(self)
        process.setProgram(self.php_path)
        process.setArguments([str(self.render_script)])
        process.setWorkingDirectory(str(self.app_root))

        env = QProcessEnvironment.systemEnvironment()
        if self.current_page_name:
            env.insert("KIKI_EDITOR_CURRENT_PAGE", self.current_page_name)
        process.setProcessEnvironment(env)

        process.finished.connect(partial(self._on_render_finished, token, process))
        process.errorOccurred.connect(partial(self._on_render_error, token, process))
        self._active_process = process

        process.start()
        process.write(self.editor.toPlainText().encode("utf-8"))
        process.closeWriteChannel()

        self.render_timeout_timer.start()
        self._refresh_status("Rendering")

    def _clear_active_process(self, token: int, process: QProcess) -> bool:
        if token != self._active_render_token:
            return False

        self.render_timeout_timer.stop()
        if self._active_process is process:
            self._active_process = None
        return True

    def _on_render_error(self, token: int, process: QProcess, _error) -> None:
        if not self._clear_active_process(token, process):
            return
        self._show_preview_error("Failed to start PHP renderer.")
        self._refresh_status("Render failed")

    def _on_render_timeout(self) -> None:
        if self._active_process is not None:
            self._active_process.kill()
            self._active_process.deleteLater()
            self._active_process = None

        self._active_render_token = -1
        self._show_preview_error("Rendering timed out after 3 seconds.")
        self._refresh_status("Render timed out")

    def _on_render_finished(self, token: int, process: QProcess, exit_code: int, exit_status) -> None:
        if not self._clear_active_process(token, process):
            return

        stdout = bytes(process.readAllStandardOutput()).decode("utf-8", "replace")
        stderr = bytes(process.readAllStandardError()).decode("utf-8", "replace").strip()

        if exit_code != 0 or exit_status != QProcess.ExitStatus.NormalExit:
            details = stderr or f"PHP exited with code {exit_code}."
            self._show_preview_error(details)
            self._refresh_status("Render failed")
            return

        preview_html = self._preview_shell_html(stdout)
        self._last_preview_html = preview_html
        self._last_preview_base_url = self._base_url
        self.preview.setHtml(preview_html, self._base_url)
        self.settings.setValue("preview/zoom", self.preview.zoomFactor())

        if stderr:
            self._refresh_status("Rendered with warnings")
        else:
            self._refresh_status("Rendered")

    def _on_theme_changed(self, theme: str) -> None:
        if not theme:
            return
        self.current_theme = theme
        self._apply_editor_theme()
        self._schedule_render()

    def closeEvent(self, event) -> None:  # type: ignore[override]
        if self._maybe_save_changes():
            if self.current_path is not None:
                self.settings.setValue("editor/last_file", str(self.current_path))
                self._save_current_view_state()
            self._save_window_state()
            event.accept()
        else:
            event.ignore()


def main() -> int:
    app = QApplication(sys.argv)
    app.setWindowIcon(QIcon(str(resolve_app_root() / "kiki-icon2.png")))
    window = KikiEditor()
    window.show()
    return app.exec()


if __name__ == "__main__":
    raise SystemExit(main())
