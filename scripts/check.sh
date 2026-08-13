#!/bin/sh

set -eu

REPO_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
PLUGIN_ROOT="$REPO_ROOT/plugins/gateway/easy_pay_gateway"

fail()
{
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

command -v php >/dev/null 2>&1 || fail 'php is required'
command -v grep >/dev/null 2>&1 || fail 'grep is required'
command -v strings >/dev/null 2>&1 || fail 'strings is required'

EXPECTED_RUNTIME_FILES='EasyPayGateway.php
EasyPayGateway.png
config.php
controller/IndexController.php
lib/EasyPaySigner.php
lib/Money.php
service/EasyPayService.php'

ACTUAL_RUNTIME_FILES=$(cd "$PLUGIN_ROOT" && find . -type f -print | sed 's#^./##' | LC_ALL=C sort)
[ "$ACTUAL_RUNTIME_FILES" = "$EXPECTED_RUNTIME_FILES" ] || {
    printf '%s\n' 'Expected runtime files:' >&2
    printf '%s\n' "$EXPECTED_RUNTIME_FILES" >&2
    printf '%s\n' 'Actual runtime files:' >&2
    printf '%s\n' "$ACTUAL_RUNTIME_FILES" >&2
    fail 'runtime package whitelist changed'
}

find "$PLUGIN_ROOT" "$REPO_ROOT/tests" -type f -name '*.php' -print | LC_ALL=C sort | while IFS= read -r file; do
    php -l "$file"
done

php "$REPO_ROOT/tests/contract_test.php"

BLOCKED_IDENTITY='ex[c]ashier|175[.]178[.]97[.]112|7W[z]W|private[_ -]*updat'
if grep -RInE -I "$BLOCKED_IDENTITY" "$REPO_ROOT" --exclude-dir=.git --exclude-dir=dist --exclude='*.zip'; then
    fail 'private identity or infrastructure data found'
fi

BLOCKED_LOCAL='/U[s]ers/|BEGIN (RSA |EC |OPENSSH |ED25519 )?PRIVATE KEY|AKIA[0-9A-Z]{16}'
if grep -RInE -I "$BLOCKED_LOCAL" "$REPO_ROOT" --exclude-dir=.git --exclude-dir=dist --exclude='*.zip'; then
    fail 'local path, private key, or credential pattern found'
fi

ICON_METADATA=$(strings -a "$PLUGIN_ROOT/EasyPayGateway.png")
if printf '%s\n' "$ICON_METADATA" | grep -Ei 'https?://|/U[s]ers/|xmp|exif|create(date)?|author|artist|copyright|company'; then
    fail 'icon contains URL, authoring, or creation metadata'
fi

URLS=$(grep -RhoE -I 'https?://[A-Za-z0-9._:-]+' "$REPO_ROOT" --exclude-dir=.git --exclude-dir=dist --exclude='*.zip' || true)
if [ -n "$URLS" ]; then
    BAD_URLS=$(printf '%s\n' "$URLS" | sed -E 's#^https?://##' | grep -Eiv '^(github[.]com|img[.]shields[.]io|([A-Za-z0-9-]+[.])*example[.]com)(:[0-9]+)?$' || true)
    [ -z "$BAD_URLS" ] || {
        printf '%s\n' "$BAD_URLS" >&2
        fail 'non-example URL found'
    }
fi

if grep -RInE -I 'eval[[:space:]]*\(|shell_exec[[:space:]]*\(|passthru[[:space:]]*\(|proc_open[[:space:]]*\(|popen[[:space:]]*\(' "$PLUGIN_ROOT" --include='*.php'; then
    fail 'dangerous PHP execution primitive found'
fi

[ "$(grep -Rho 'data-v10-payment-mode="redirect"' "$PLUGIN_ROOT" --include='*.php' | wc -l | tr -d ' ')" = '1' ] \
    || fail 'runtime source must declare exactly one redirect form marker'

printf '%s\n' 'All lint, contract, redaction, URL, and runtime whitelist checks passed.'
