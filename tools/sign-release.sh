#!/usr/bin/env bash

set -Eeuo pipefail

APP_ID="journalnotes"
NEXTCLOUD_ROOT="${NEXTCLOUD_ROOT:-/var/www/html/nextcloud}"
PRIVATE_KEY="${JOURNAL_SIGN_KEY:-/root/.nextcloud/certificates/journalnotes.key}"
CERTIFICATE="${JOURNAL_SIGN_CERT:-/root/.nextcloud/certificates/journalnotes.crt}"
APP_PATH="${1:-}"

fail() {
	printf 'ERROR: %s\n' "$*" >&2
	exit 1
}

[[ -n "$APP_PATH" ]] \
	|| fail "Uso: tools/sign-release.sh /ruta/a/journalnotes"

[[ -d "$APP_PATH" ]] \
	|| fail "No existe la carpeta de la aplicación: $APP_PATH"

[[ -f "$APP_PATH/appinfo/info.xml" ]] \
	|| fail "Falta appinfo/info.xml"

[[ -f "$PRIVATE_KEY" ]] \
	|| fail "No existe la clave privada: $PRIVATE_KEY"

[[ -f "$CERTIFICATE" ]] \
	|| fail "No existe el certificado: $CERTIFICATE"

[[ -f "$NEXTCLOUD_ROOT/occ" ]] \
	|| fail "No se encontró occ en: $NEXTCLOUD_ROOT"

CERT_CN="$(
	openssl x509 \
		-in "$CERTIFICATE" \
		-noout \
		-subject \
		-nameopt RFC2253 \
	| sed -n 's/^subject=CN=//p'
)"

[[ "$CERT_CN" == "$APP_ID" ]] \
	|| fail "El certificado pertenece a '$CERT_CN', no a '$APP_ID'"

KEY_HASH="$(
	openssl rsa \
		-noout \
		-modulus \
		-in "$PRIVATE_KEY" 2>/dev/null \
	| sha256sum \
	| awk '{print $1}'
)"

CERT_HASH="$(
	openssl x509 \
		-noout \
		-modulus \
		-in "$CERTIFICATE" \
	| sha256sum \
	| awk '{print $1}'
)"

[[ "$KEY_HASH" == "$CERT_HASH" ]] \
	|| fail "La clave privada y el certificado no coinciden"

TMP_DIR="$(mktemp -d /tmp/journalnotes-signing.XXXXXX)"

cleanup() {
	rm -rf "$TMP_DIR"
}

trap cleanup EXIT

install -m 600 \
	"$PRIVATE_KEY" \
	"$TMP_DIR/journalnotes.key"

install -m 644 \
	"$CERTIFICATE" \
	"$TMP_DIR/journalnotes.crt"

chown -R www-data:www-data "$TMP_DIR"

# Eliminar cualquier firma anterior antes de crear una nueva.
rm -f "$APP_PATH/appinfo/signature.json"

sudo -u www-data php "$NEXTCLOUD_ROOT/occ" integrity:sign-app \
	--privateKey="$TMP_DIR/journalnotes.key" \
	--certificate="$TMP_DIR/journalnotes.crt" \
	--path="$APP_PATH"

[[ -s "$APP_PATH/appinfo/signature.json" ]] \
	|| fail "No se generó appinfo/signature.json"

chown root:root "$APP_PATH/appinfo/signature.json"
chmod 644 "$APP_PATH/appinfo/signature.json"

echo
echo "Aplicación firmada correctamente:"
echo "  $APP_PATH/appinfo/signature.json"
