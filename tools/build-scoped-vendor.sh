#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCOPER_BIN="${PHP_SCOPER_BIN:-/opt/php-scoper/php-scoper.phar}"

cd "$ROOT_DIR"

if [[ ! -f "$SCOPER_BIN" ]]; then
	echo "ERROR: no se encontró PHP-Scoper en:"
	echo "  $SCOPER_BIN"
	exit 1
fi

if [[ ! -f composer.json ]]; then
	echo "ERROR: no se encontró composer.json"
	exit 1
fi

echo "1/7 Instalando dependencias de producción..."

COMPOSER_ALLOW_SUPERUSER=1 composer install \
	--no-dev \
	--prefer-dist \
	--optimize-autoloader \
	--no-interaction

echo "2/7 Eliminando builds anteriores..."

rm -rf build/scoped
rm -rf vendor-scoped

echo "3/7 Aislando dependencias con PHP-Scoper..."

php "$SCOPER_BIN" add-prefix \
	--config=scoper.inc.php \
	--force

echo "4/7 Corrigiendo clases dinámicas de Dompdf y FontLib..."

python3 tools/patch-scoped-vendor.py

echo "5/7 Corrigiendo classmap de TCPDF..."

python3 tools/patch-scoped-classmap.py

echo "6/7 Eliminando mapas PSR-4 globales..."

python3 tools/patch-scoped-psr4.py

echo "7/7 Creando vendor-scoped definitivo..."

mkdir -p vendor-scoped
cp -a build/scoped/. vendor-scoped/

echo
echo "Verificando clases principales..."

php -r "
require 'vendor-scoped/autoload.php';

\$symbols = [
	'OCA\\\\JournalNotes\\\\Vendor\\\\Dompdf\\\\Dompdf',
	'OCA\\\\JournalNotes\\\\Vendor\\\\League\\\\CommonMark\\\\CommonMarkConverter',
	'OCA\\\\JournalNotes\\\\Vendor\\\\iio\\\\libmergepdf\\\\Merger',
	'OCA\\\\JournalNotes\\\\Vendor\\\\Masterminds\\\\HTML5',
	'OCA\\\\JournalNotes\\\\Vendor\\\\Masterminds\\\\HTML5\\\\Parser\\\\EventHandler',
	'OCA\\\\JournalNotes\\\\Vendor\\\\TCPDF',
];

foreach (\$symbols as \$symbol) {
	\$exists = class_exists(\$symbol) || interface_exists(\$symbol);

	if (!\$exists) {
		fwrite(STDERR, \"FALTA: {\$symbol}\n\");
		exit(1);
	}

	echo \"OK: {\$symbol}\n\";
}

\$globals = [
	'Dompdf\\\\Dompdf',
	'Masterminds\\\\HTML5',
	'League\\\\CommonMark\\\\CommonMarkConverter',
];

foreach (\$globals as \$symbol) {
	if (class_exists(\$symbol) || interface_exists(\$symbol)) {
		fwrite(STDERR, \"ERROR: símbolo global disponible: {\$symbol}\n\");
		exit(1);
	}

	echo \"OK NO GLOBAL: {\$symbol}\n\";
}
"

echo
echo "Probando generación PDF..."

php -r "
require 'vendor-scoped/autoload.php';

\$pdf = new OCA\\JournalNotes\\Vendor\\Dompdf\\Dompdf();

\$pdf->loadHtml(
	'<h1>Journal</h1>'
	. '<p>Prueba automática del vendor aislado.</p>'
);

\$pdf->render();
\$output = \$pdf->output();

if (!str_starts_with(\$output, '%PDF')) {
	fwrite(STDERR, \"ERROR: Dompdf no produjo un PDF válido\n\");
	exit(1);
}

echo 'OK PDF: ', strlen(\$output), \" bytes\n\";
"

echo
echo "Vendor aislado generado correctamente."
