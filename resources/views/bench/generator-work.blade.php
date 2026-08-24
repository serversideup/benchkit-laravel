#!/bin/sh
# The standard BenchKit HTTP load, driven from this machine. Rendered from the
# run's actual settings at the moment its web server stage armed — the same
# plan the local self-test chain is built from.
set -u

BASE={!! $base !!}
TOKEN={!! $token !!}
CURL="curl {!! $curlInsecure !!} -sS"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

# Prints the server's verdict either way; a rejected route is worth exactly
# as much explanation as an accepted one. Transport failures and 5xx retry,
# a 4xx is an answer.
upload() {
    ROUTE=$1
    FILE=$2
    ATTEMPT=1

    if [ ! -s "$FILE" ]; then
        printf '  No output captured for %s — skipping its upload.\n' "$ROUTE"
        return 0
    fi

    while [ "$ATTEMPT" -le 3 ]; do
        CODE=$($CURL -o "$TMP/response" -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
            --data-binary "@$FILE" "$BASE/bench/generator/$TOKEN/results/$ROUTE" 2>/dev/null || echo 000)

        case "$CODE" in
            2*)
                printf '  Uploaded %s.\n' "$ROUTE"
                return 0 ;;
            000|5*)
                ATTEMPT=$((ATTEMPT + 1))
                sleep 2 ;;
            *)
                printf '  %s was rejected (%s): %s\n' "$ROUTE" "$CODE" "$(cat "$TMP/response" 2>/dev/null)"
                return 0 ;;
        esac
    done

    printf '  Could not upload %s after 3 attempts.\n' "$ROUTE"
    return 0
}
@foreach ($routes as $route)

printf '%s\n' {!! $route['banner_quoted'] !!}
oha -z {{ $route['warmup_seconds'] }}s {!! $route['load_flags'] !!} {!! $route['url_quoted'] !!} > /dev/null 2>&1 || true
oha -z {{ $route['duration_seconds'] }}s {!! $route['load_flags'] !!} --no-tui --output-format json {!! $route['url_quoted'] !!} > "$TMP/{{ $route['upload_key'] }}.json" 2>/dev/null || true
upload '{{ $route['upload_key'] }}' "$TMP/{{ $route['upload_key'] }}.json"
@endforeach
