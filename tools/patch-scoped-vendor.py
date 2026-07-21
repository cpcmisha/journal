#!/usr/bin/env python3

from pathlib import Path
import sys


ROOT = Path(__file__).resolve().parents[1]
SCOPED_ROOT = ROOT / "build" / "scoped"
PREFIX = r"OCA\JournalNotes\Vendor"


def patch_file(relative_path: str, replacements: dict[str, str]) -> None:
    path = SCOPED_ROOT / relative_path

    if not path.is_file():
        raise SystemExit(f"Falta el archivo generado: {path}")

    text = path.read_text(encoding="utf-8")
    original = text

    for old, new in replacements.items():
        count = text.count(old)

        if count == 0:
            raise SystemExit(
                f"No se encontró el patrón esperado en {relative_path}:\n{old}"
            )

        text = text.replace(old, new)

    if text == original:
        raise SystemExit(f"No se modificó {relative_path}")

    path.write_text(text, encoding="utf-8")
    print(f"Corregido: {relative_path}")


def main() -> int:
    if not SCOPED_ROOT.is_dir():
        print(
            f"No existe el directorio generado: {SCOPED_ROOT}",
            file=sys.stderr,
        )
        return 1

    patch_file(
        "dompdf/dompdf/src/Frame/Factory.php",
        {
            r'"Dompdf\\FrameDecorator\\{$decorator}"':
                rf'"{PREFIX}\\Dompdf\\FrameDecorator\\{{$decorator}}"',

            r'"Dompdf\\FrameReflower\\{$reflower}"':
                rf'"{PREFIX}\\Dompdf\\FrameReflower\\{{$reflower}}"',

            r"'\Dompdf\Positioner\\' . $type":
                rf"'\{PREFIX}\Dompdf\Positioner\\' . $type",
        },
    )

    patch_file(
        "dompdf/php-font-lib/src/FontLib/TrueType/File.php",
        {
            r'"FontLib\\{$type}\\TableDirectoryEntry"':
                rf'"{PREFIX}\\FontLib\\{{$type}}\\TableDirectoryEntry"',

            r'"FontLib\\Table\\Type\\{$name_canon}"':
                rf'"{PREFIX}\\FontLib\\Table\\Type\\{{$name_canon}}"',
        },
    )

    patch_file(
        "dompdf/php-font-lib/src/FontLib/Font.php",
        {
            r'"FontLib\\{$class}"':
                rf'"{PREFIX}\\FontLib\\{{$class}}"',
        },
    )

    print("Todas las referencias dinámicas fueron aisladas.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
