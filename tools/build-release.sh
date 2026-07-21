#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_ID="journalnotes"
INFO_XML="$ROOT_DIR/appinfo/info.xml"
DIST_DIR="$ROOT_DIR/dist"
STAGING_ROOT="$DIST_DIR/staging"
STAGING_APP="$STAGING_ROOT/$APP_ID"

log() {
	printf '\n\033[1;34m==> %s\033[0m\n' "$*"
}

fail() {
	printf '\n\033[1;31mERROR: %s\033[0m\n' "$*" >&2
	exit 1
}

require_command() {
	command -v "$1" >/dev/null 2>&1 \
		|| fail "No se encontró el comando requerido: $1"
}

cleanup_on_error() {
	local exit_code=$?

	if (( exit_code != 0 )); then
		printf '\nEl build terminó con errores.\n' >&2
	fi

	exit "$exit_code"
}

trap cleanup_on_error EXIT

cd "$ROOT_DIR"

require_command php
require_command python3
require_command composer
require_command rsync
require_command tar
require_command zip
require_command find
require_command grep

[[ -f "$INFO_XML" ]] \
	|| fail "No existe appinfo/info.xml"

[[ -x "$ROOT_DIR/tools/build-scoped-vendor.sh" ]] \
	|| fail "tools/build-scoped-vendor.sh no existe o no es ejecutable"

[[ -x "$ROOT_DIR/tools/sign-release.sh" ]] \
	|| fail "tools/sign-release.sh no existe o no es ejecutable"

VERSION="$(
	python3 - "$INFO_XML" <<'PY'
import sys
import xml.etree.ElementTree as ET

path = sys.argv[1]
root = ET.parse(path).getroot()

version = root.findtext("version", "").strip()
app_id = root.findtext("id", "").strip()

if not version:
    raise SystemExit("No se encontró <version> en info.xml")

if app_id != "journalnotes":
    raise SystemExit(
        f"El ID encontrado es {app_id!r}; se esperaba 'journalnotes'"
    )

print(version)
PY
)"

ARCHIVE_BASENAME="${APP_ID}-${VERSION}"
TAR_FILE="$DIST_DIR/${ARCHIVE_BASENAME}.tar.gz"
ZIP_FILE="$DIST_DIR/${ARCHIVE_BASENAME}.zip"
SHA_FILE="$DIST_DIR/${ARCHIVE_BASENAME}.sha256"

log "Preparando Journal ${VERSION}"

log "Validando composer.json y composer.lock"
COMPOSER_ALLOW_SUPERUSER=1 \
composer validate --no-check-publish --strict

log "Validando appinfo/info.xml"
python3 - "$INFO_XML" <<'PY'
import sys
import xml.etree.ElementTree as ET

path = sys.argv[1]
root = ET.parse(path).getroot()

required = [
    "id",
    "name",
    "summary",
    "description",
    "version",
    "licence",
    "namespace",
    "dependencies",
]

missing = [
    field
    for field in required
    if root.find(field) is None
]

if missing:
    raise SystemExit(
        "Faltan elementos requeridos: " + ", ".join(missing)
    )

print("info.xml válido")
PY

log "Construyendo dependencias PHP aisladas"
"$ROOT_DIR/tools/build-scoped-vendor.sh"

log "Validando vendor-scoped"
php -r "
require '$ROOT_DIR/vendor-scoped/autoload.php';

\$required = [
	'OCA\\\\JournalNotes\\\\Vendor\\\\Dompdf\\\\Dompdf',
	'OCA\\\\JournalNotes\\\\Vendor\\\\League\\\\CommonMark\\\\CommonMarkConverter',
	'OCA\\\\JournalNotes\\\\Vendor\\\\iio\\\\libmergepdf\\\\Merger',
	'OCA\\\\JournalNotes\\\\Vendor\\\\Masterminds\\\\HTML5',
	'OCA\\\\JournalNotes\\\\Vendor\\\\Masterminds\\\\HTML5\\\\Parser\\\\EventHandler',
	'OCA\\\\JournalNotes\\\\Vendor\\\\TCPDF',
];

foreach (\$required as \$symbol) {
	if (!class_exists(\$symbol) && !interface_exists(\$symbol)) {
		fwrite(STDERR, \"Falta el símbolo: {\$symbol}\n\");
		exit(1);
	}
}

\$forbidden = [
	'Dompdf\\\\Dompdf',
	'Masterminds\\\\HTML5',
	'League\\\\CommonMark\\\\CommonMarkConverter',
];

foreach (\$forbidden as \$symbol) {
	if (class_exists(\$symbol) || interface_exists(\$symbol)) {
		fwrite(STDERR, \"Símbolo global no permitido: {\$symbol}\n\");
		exit(1);
	}
}

echo \"vendor-scoped válido\n\";
"

log "Validando sintaxis PHP de la aplicación"
while IFS= read -r -d '' file; do
	php -l "$file" >/dev/null
done < <(
	find \
		appinfo \
		lib \
		templates \
		-type f \
		-name '*.php' \
		-print0
)

echo "Sintaxis PHP válida"

log "Validando archivos JSON"
while IFS= read -r -d '' file; do
	python3 -m json.tool "$file" >/dev/null
done < <(
	find \
		l10n \
		-maxdepth 1 \
		-type f \
		-name '*.json' \
		-print0
)

echo "Archivos JSON válidos"

log "Buscando archivos temporales"
TEMP_FILES="$(
	find . \
		-path './.git' -prune -o \
		-path './node_modules' -prune -o \
		-path './vendor' -prune -o \
		-path './vendor-scoped' -prune -o \
		-path './build' -prune -o \
		-path './dist' -prune -o \
		-type f \
		\( \
			-name '*.bak' -o \
			-name '*.bak-*' -o \
			-name '*.orig' -o \
			-name '*.rej' -o \
			-name '*.swp' -o \
			-name '*.tmp' -o \
			-name '*~' -o \
			-name '*.pyc' -o \
			-name '.DS_Store' \
		\) \
		-print
)"

if [[ -n "$TEMP_FILES" ]]; then
	printf '%s\n' "$TEMP_FILES"
	fail "Se encontraron archivos temporales"
fi

log "Preparando directorio limpio de distribución"
rm -rf "$DIST_DIR"
mkdir -p "$STAGING_APP"

# Archivos y directorios necesarios para ejecutar la app.
INCLUDE_PATHS=(
	appinfo
	css
	img
	js
	l10n
        screenshots
	lib
	templates
	vendor-scoped
	CHANGELOG.md
	CHANGELOG.de.md
	CHANGELOG.en.md
	CHANGELOG.es.md
	COPYING
	NOTICE.md
	README.md
	RELEASE.md
	ROADMAP.md
	SECURITY.md
)

for path in "${INCLUDE_PATHS[@]}"; do
	if [[ -e "$ROOT_DIR/$path" ]]; then
		rsync -a "$ROOT_DIR/$path" "$STAGING_APP/"
	fi
done

log "Verificando contenido obligatorio del paquete"
REQUIRED_RELEASE_PATHS=(
	appinfo/info.xml
	js/journalnotes-main.js
	lib/AppInfo/Application.php
	vendor-scoped/autoload.php
	COPYING
	README.md
	CHANGELOG.md
)

for path in "${REQUIRED_RELEASE_PATHS[@]}"; do
	[[ -e "$STAGING_APP/$path" ]] \
		|| fail "Falta en el paquete: $path"
done

log "Comprobando que no entren archivos de desarrollo"
FORBIDDEN_RELEASE_PATHS=(
	node_modules
	vendor
	build
	dist
	src
	tests
	tools
	.git
	.github
	package.json
	package-lock.json
	composer.json
	composer.lock
	webpack.config.js
	phpunit.xml
)

for path in "${FORBIDDEN_RELEASE_PATHS[@]}"; do
	if [[ -e "$STAGING_APP/$path" ]]; then
		fail "El paquete contiene un elemento de desarrollo: $path"
	fi
done

log "Validando sintaxis PHP dentro del paquete"
while IFS= read -r -d '' file; do
	php -l "$file" >/dev/null
done < <(
	find "$STAGING_APP" -type f -name '*.php' -print0
)

log "Firmando la aplicación"
"$ROOT_DIR/tools/sign-release.sh" "$STAGING_APP"

log "Verificando la firma generada"
[[ -s "$STAGING_APP/appinfo/signature.json" ]] \
    || fail "No se generó appinfo/signature.json"

log "Creando archivo tar.gz"
tar \
	-C "$STAGING_ROOT" \
	--owner=0 \
	--group=0 \
	--numeric-owner \
	-czf "$TAR_FILE" \
	"$APP_ID"

log "Creando archivo ZIP"
(
	cd "$STAGING_ROOT"
	zip -qr "$ZIP_FILE" "$APP_ID"
)

log "Generando sumas SHA-256"
(
	cd "$DIST_DIR"
	sha256sum \
		"$(basename "$TAR_FILE")" \
		"$(basename "$ZIP_FILE")" \
		> "$(basename "$SHA_FILE")"
)

log "Revisando estructura de los paquetes"
tar -tzf "$TAR_FILE" > "$DIST_DIR/tar-contents.txt"
sed -n '1,30p' "$DIST_DIR/tar-contents.txt"

echo
unzip -l "$ZIP_FILE" > "$DIST_DIR/zip-contents.txt"
sed -n '1,35p' "$DIST_DIR/zip-contents.txt"

log "Resumen"
du -sh "$STAGING_APP" "$TAR_FILE" "$ZIP_FILE"

echo
cat "$SHA_FILE"

echo
echo "Build completado correctamente:"
echo "  $TAR_FILE"
echo "  $ZIP_FILE"
echo "  $SHA_FILE"
