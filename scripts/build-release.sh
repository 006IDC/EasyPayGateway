#!/bin/sh

set -eu

REPO_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
PLUGIN_ROOT="$REPO_ROOT/plugins/gateway/easy_pay_gateway"
DIST_ROOT="$REPO_ROOT/dist"
INSTALL_MANIFEST="$REPO_ROOT/tests/fixtures/install-files.txt"
NORMALIZED_TIME=202608120000.00
TMP_BASE=${TMPDIR:-/tmp}
TMP_BASE=${TMP_BASE%/}
BUILD_TMP=$(mktemp -d "$TMP_BASE/easy-pay-gateway.XXXXXX")

cleanup()
{
    case "$BUILD_TMP" in
        "$TMP_BASE"/easy-pay-gateway.*)
            rm -rf -- "$BUILD_TMP"
            ;;
        *)
            printf 'Refusing to clean unexpected temporary path: %s\n' "$BUILD_TMP" >&2
            ;;
    esac
}
trap cleanup EXIT HUP INT TERM

fail()
{
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

command -v zip >/dev/null 2>&1 || fail 'zip is required'
command -v unzip >/dev/null 2>&1 || fail 'unzip is required'

sh "$REPO_ROOT/scripts/check.sh"

VERSION=$(sed -n "s/.*'version'[[:space:]]*=>[[:space:]]*'\([^']*\)'.*/\1/p" "$PLUGIN_ROOT/EasyPayGateway.php" | head -n 1)
printf '%s' "$VERSION" | grep -Eq '^[0-9]+[.][0-9]+[.][0-9]+$' || fail 'invalid plugin version'

INSTALL_NAME="easy_pay_gateway_${VERSION}.zip"

mkdir -p "$DIST_ROOT"
mkdir -p "$BUILD_TMP/install/gateway/easy_pay_gateway/controller"
mkdir -p "$BUILD_TMP/install/gateway/easy_pay_gateway/lib"
mkdir -p "$BUILD_TMP/install/gateway/easy_pay_gateway/service"

while IFS= read -r relative; do
    [ -n "$relative" ] || continue
    cp "$PLUGIN_ROOT/$relative" "$BUILD_TMP/install/gateway/easy_pay_gateway/$relative"
done <<'RUNTIME_FILES'
EasyPayGateway.php
EasyPayGateway.png
config.php
controller/IndexController.php
lib/EasyPaySigner.php
lib/Money.php
service/EasyPayService.php
RUNTIME_FILES

find "$BUILD_TMP/install" -exec touch -t "$NORMALIZED_TIME" {} +

(cd "$BUILD_TMP/install" && zip -X -q "$BUILD_TMP/$INSTALL_NAME" -@ < "$INSTALL_MANIFEST")

unzip -Z1 "$BUILD_TMP/$INSTALL_NAME" | LC_ALL=C sort > "$BUILD_TMP/install.actual"
diff -u "$INSTALL_MANIFEST" "$BUILD_TMP/install.actual"

mv -f "$BUILD_TMP/$INSTALL_NAME" "$DIST_ROOT/$INSTALL_NAME"

if command -v sha256sum >/dev/null 2>&1; then
    (cd "$DIST_ROOT" && sha256sum "$INSTALL_NAME") > "$BUILD_TMP/SHA256SUMS"
else
    (cd "$DIST_ROOT" && shasum -a 256 "$INSTALL_NAME") > "$BUILD_TMP/SHA256SUMS"
fi
mv -f "$BUILD_TMP/SHA256SUMS" "$DIST_ROOT/SHA256SUMS"

printf 'Built release %s\n' "$VERSION"
printf '  %s\n' "$DIST_ROOT/$INSTALL_NAME"
printf '  %s\n' "$DIST_ROOT/SHA256SUMS"
