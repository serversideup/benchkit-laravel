#!/bin/sh
# The standard BenchKit HTTP load, driven from this machine. Rendered from the
# run's actual settings at the moment its web server stage armed — the same
# plan the local self-test chain is built from.
#
# Runs as its own sh process, so it carries its own copy of the presentation
# helpers rather than borrowing the bootstrap's.
set -u

BASE={!! $base !!}
TOKEN={!! $token !!}
CURL="curl {!! $curlInsecure !!} -sS"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

@include('bench.partials.generator-style')

# Sets UPLOAD_NOTE and returns non-zero when the server refuses, so the route's
# own line can carry the verdict. Transport failures and 5xx retry, a 4xx is
# an answer.
upload() {
    ROUTE=$1
    FILE=$2
    ATTEMPT=1
    UPLOAD_NOTE=''

    if [ ! -s "$FILE" ]; then
        UPLOAD_NOTE='no output captured'

        return 1
    fi

    while [ "$ATTEMPT" -le 3 ]; do
        CODE=$($CURL -o "$TMP/response" -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
            --data-binary "@$FILE" "$BASE/bench/generator/$TOKEN/results/$ROUTE" 2>/dev/null || echo 000)

        case "$CODE" in
            2*) return 0 ;;
            000|5*)
                ATTEMPT=$((ATTEMPT + 1))
                sleep 2 ;;
            *)
                UPLOAD_NOTE=$(cat "$TMP/response" 2>/dev/null)

                return 1 ;;
        esac
    done

    UPLOAD_NOTE='could not reach the server after 3 attempts'

    return 1
}

# A route occupies one line for its whole life: claimed while it runs, then
# rewritten in place with the number it measured.
route_start() {
    if [ -n "$TTY" ]; then
        printf '  %s%s%s  %-16s %srunning…%s' "$C_DIM" "$1" "$C_RESET" "$2" "$C_DIM" "$C_RESET"
    fi
}

route_done() {
    if [ -n "$TTY" ]; then printf '%s' "$ERASE"; fi

    if [ "$4" = ok ]; then
        MARK="${C_OK}✓${C_RESET}"
    else
        MARK="${C_BRAND}✗ $4${C_RESET}"
    fi

    printf '  %s%s%s  %-16s %s%9s%s %sreq/s%s  %s\n' \
        "$C_DIM" "$1" "$C_RESET" "$2" \
        "$C_BOLD$C_TEXT" "$(fmt_rps "$3")" "$C_RESET" "$C_DIM" "$C_RESET" "$MARK"
}

blank
rule
printf '  %sLoad test%s %s·%s %s routes %s·%s {{ $routes[0]['duration_seconds'] }}s each at {{ $routes[0]['connections'] }} connections\n' \
    "$C_BOLD$C_TEXT" "$C_RESET" "$C_DIM" "$C_RESET" '{{ count($routes) }}' "$C_DIM" "$C_RESET"
rule
blank
@foreach ($routes as $route)
route_start '{{ $loop->iteration }}/{{ count($routes) }}' {!! $route['path_quoted'] !!}
oha -z {{ $route['warmup_seconds'] }}s {!! $route['load_flags'] !!} {!! $route['url_quoted'] !!} > /dev/null 2>&1 || true
oha -z {{ $route['duration_seconds'] }}s {!! $route['load_flags'] !!} --no-tui --output-format json {!! $route['url_quoted'] !!} > "$TMP/{{ $route['upload_key'] }}.json" 2>/dev/null || true
RPS=$(sed -n 's/.*"requestsPerSec":[[:space:]]*\([0-9.eE+-]*\).*/\1/p' "$TMP/{{ $route['upload_key'] }}.json" 2>/dev/null | head -n 1)

if upload '{{ $route['upload_key'] }}' "$TMP/{{ $route['upload_key'] }}.json"; then
    route_done '{{ $loop->iteration }}/{{ count($routes) }}' {!! $route['path_quoted'] !!} "$RPS" ok
else
    route_done '{{ $loop->iteration }}/{{ count($routes) }}' {!! $route['path_quoted'] !!} "$RPS" "$UPLOAD_NOTE"
fi
@endforeach
