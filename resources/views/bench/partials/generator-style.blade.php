# Shared presentation for both generator scripts. They run as separate sh
# processes, so neither can borrow the other's functions; this is rendered
# into each one instead of duplicated by hand.

# Color only where it is wanted: a real terminal, NO_COLOR unset (the
# convention at no-color.org), and a TERM that can render it.
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ] && [ "${TERM:-dumb}" != dumb ]; then
    TTY=1
    C_RESET=$(printf '\033[0m')
    C_BOLD=$(printf '\033[1m')
    C_DIM=$(printf '\033[38;5;244m')
    C_BRAND=$(printf '\033[38;5;202m')
    C_OK=$(printf '\033[38;5;78m')
    C_TEXT=$(printf '\033[38;5;252m')
    ERASE=$(printf '\r\033[K')
else
    TTY=''
    C_RESET='' C_BOLD='' C_DIM='' C_BRAND='' C_OK='' C_TEXT='' ERASE=''
fi

say()  { printf '  %s\n' "$*"; }
note() { printf '  %s%s%s\n' "$C_DIM" "$*" "$C_RESET"; }
blank() { printf '\n'; }
step() { printf '  %s✓%s  %s%s%s\n' "$C_OK" "$C_RESET" "$C_TEXT" "$*" "$C_RESET"; }
rule() { printf '  %s────────────────────────────────────────────────%s\n' "$C_DIM" "$C_RESET"; }

# A headline and, optionally, an indented body explaining what to do next.
fail() {
    printf '\n  %s✗  %s%s%s\n' "$C_BRAND" "$C_BOLD" "$1" "$C_RESET"

    if [ $# -gt 1 ]; then
        shift
        printf '\n%s%s%s\n' "$C_DIM" "$*" "$C_RESET"
    fi

    printf '\n'
    exit 1
}

# Requests per second, rounded and grouped, from oha's summary. An empty or
# unparseable value prints a dash rather than a misleading zero.
fmt_rps() {
    awk -v v="$1" 'BEGIN {
        if (v == "") { printf "-"; exit }
        n = sprintf("%d", v + 0.5); g = ""
        while (length(n) > 3) { g = "," substr(n, length(n) - 2) g; n = substr(n, 1, length(n) - 3) }
        printf "%s%s", n, g
    }'
}
