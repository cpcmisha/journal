#!/usr/bin/env python3

from pathlib import Path
import re


ROOT = Path(__file__).resolve().parents[1]
SCOPED = ROOT / "build" / "scoped"


def patch_autoload_psr4() -> None:
    path = SCOPED / "composer" / "autoload_psr4.php"

    if not path.is_file():
        raise SystemExit(f"Falta el archivo: {path}")

    text = path.read_text(encoding="utf-8")

    start = text.find("return array(")

    if start == -1:
        raise SystemExit("No se encontró el mapa PSR-4.")

    header = text[:start]
    path.write_text(
        header + "return array();\n",
        encoding="utf-8",
    )

    print("Corregido: composer/autoload_psr4.php")


def patch_autoload_static() -> None:
    path = SCOPED / "composer" / "autoload_static.php"

    if not path.is_file():
        raise SystemExit(f"Falta el archivo: {path}")

    text = path.read_text(encoding="utf-8")

    patterns = [
        (
            r"public static \$prefixLengthsPsr4 = array\(.*?\);",
            "public static $prefixLengthsPsr4 = array();",
        ),
        (
            r"public static \$prefixDirsPsr4 = array\(.*?\);",
            "public static $prefixDirsPsr4 = array();",
        ),
    ]

    for pattern, replacement in patterns:
        text, count = re.subn(
            pattern,
            replacement,
            text,
            count=1,
            flags=re.DOTALL,
        )

        if count != 1:
            raise SystemExit(
                f"No se pudo corregir el patrón: {pattern}"
            )

    path.write_text(text, encoding="utf-8")
    print("Corregido: composer/autoload_static.php")


def main() -> int:
    patch_autoload_psr4()
    patch_autoload_static()

    print("Los mapas PSR-4 globales fueron desactivados.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
