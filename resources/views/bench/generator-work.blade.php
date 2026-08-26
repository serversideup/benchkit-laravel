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
        UPLOAD_NOTE=$(head -n 1 "$TMP/err.txt" 2>/dev/null | cut -c1-90)
        [ -n "$UPLOAD_NOTE" ] || UPLOAD_NOTE='no output captured'

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

# A window the generator could not measure. Best effort: if this request does
# not land the run falls back to its no-progress timeout, which is what used to
# happen every time.
report_failed() {
    $CURL -o /dev/null -X POST -H 'Content-Type: text/plain' \
        --data-binary "@$TMP/err.txt" \
        "$BASE/bench/generator/$TOKEN/failed/$1" >/dev/null 2>&1 || true
}

# A measured window occupies one line for its whole life: claimed while it
# runs, then rewritten in place with the number it measured.
step_start() {
    if [ -n "$TTY" ]; then
        printf '  %s%s%s  %-16s %srunning…%s' "$C_DIM" "$1" "$C_RESET" "$2" "$C_DIM" "$C_RESET"
    fi
}

step_done() {
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
printf '  %sLoad test%s %s·%s %s measured windows\n' \
    "$C_BOLD$C_TEXT" "$C_RESET" "$C_DIM" "$C_RESET" '{{ $measured }}'
rule
blank
@foreach ($steps as $step)
@if ($step['capture'])
step_start '{{ $step['done'] }}/{{ $measured }}' {!! $step['label_quoted'] !!}
# stderr is kept rather than discarded. A window that produced nothing used
# to say only "no output captured", which is the symptom and never the cause —
# and the cause is on the machine running this script, where nobody can see it.
{!! $step['command'] !!} > "$TMP/out.json" 2> "$TMP/err.txt" || true
RPS=$(sed -n 's/.*"requestsPerSec":[[:space:]]*\([0-9.eE+-]*\).*/\1/p' "$TMP/out.json" 2>/dev/null | head -n 1)

if upload '{{ $step['slot'] }}' "$TMP/out.json"; then
    step_done '{{ $step['done'] }}/{{ $measured }}' {!! $step['label_quoted'] !!} "$RPS" ok
else
    # Tell the server this one is not coming, or it waits out its whole
    # timeout for a result nobody is going to send.
    report_failed '{{ $step['slot'] }}'
    step_done '{{ $step['done'] }}/{{ $measured }}' {!! $step['label_quoted'] !!} "$RPS" "$UPLOAD_NOTE"
fi
@else
{!! $step['command'] !!} > /dev/null 2>&1 || true
# A moment between windows so one does not measure the last one's sockets
# still draining. Cheap next to a six-second window.
sleep 1
@endif
@endforeach
