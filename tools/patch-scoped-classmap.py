#!/usr/bin/env python3

from pathlib import Path
import re


ROOT = Path(__file__).resolve().parents[1]
SCOPED = ROOT / "build" / "scoped"
PREFIX = "OCA\\JournalNotes\\Vendor"

CLASSMAP_FILES = [
    SCOPED / "composer" / "autoload_classmap.php",
    SCOPED / "composer" / "autoload_static.php",
]

CLASSES = [
    "Datamatrix",
    "PDF417",
    "QRcode",
    "TCPDF",
    "TCPDF2DBarcode",
    "TCPDFBarcode",
    "TCPDF_COLORS",
    "TCPDF_FILTERS",
    "TCPDF_FONTS",
    "TCPDF_FONT_DATA",
    "TCPDF_IMAGES",
    "TCPDF_STATIC",
    "FPDF",
    "FPDF_TPL",
    "TCPDI",
    "tcpdi_parser",
]


def patch_file(path: Path) -> int:
    if not path.is_file():
        raise SystemExit(f"Falta el archivo: {path}")

    text = path.read_text(encoding="utf-8")
    changed = 0

    for class_name in CLASSES:
        expected = f"{PREFIX}\\{class_name}"

        patterns = [
            rf"'{re.escape(class_name)}'\s*=>",
            rf"'OCA\\+JournalNotes\\+Vendor\\+{re.escape(class_name)}'\s*=>",
        ]

        for pattern in patterns:
            text, count = re.subn(
                pattern,
                lambda _: f"'{expected}' =>",
                text,
            )
            changed += count

    path.write_text(text, encoding="utf-8")
    print(f"{path.relative_to(ROOT)}: {changed} ajuste(s)")
    return changed


def main() -> int:
    total = sum(patch_file(path) for path in CLASSMAP_FILES)
    print(f"Total de ajustes: {total}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
