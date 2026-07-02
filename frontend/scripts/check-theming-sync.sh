#!/usr/bin/env bash
#
# check-theming-sync.sh — verify the shared theming files are still identical
# across the 3 repos (pmone, pmone-events, levenium). Run it any time:
#
#   bash frontend/scripts/check-theming-sync.sh
#
# Read-only: it NEVER changes anything, it only reports drift. Exit code 1 if any
# TIER 1 file (the shared engine that MUST match) has drifted, else 0.
#
# Override repo roots via env if your paths differ:
#   PMONE=~/Herd/pmone/frontend EVENTS=~/Frontend/pmone-events LEVENIUM=~/Frontend/levenium bash ...

set -uo pipefail

PMONE="${PMONE:-$HOME/Herd/pmone/frontend}"
EVENTS="${EVENTS:-$HOME/Frontend/pmone-events}"
LEVENIUM="${LEVENIUM:-$HOME/Frontend/levenium}"

# The 4 component trees. pmone is canonical; the others are compared against it.
CANON="$PMONE/app"
declare -a TREES=(
  "events|$EVENTS/layers/base/app"
  "lev-base|$LEVENIUM/layers/base/app"
  "lev-ui|$LEVENIUM/apps/ui/app"
)

# Bespoke components that are INTENTIONALLY per-repo (do NOT flag as errors).
INTENTIONAL_DRIFT=$'chart/ChartSemiCircle.vue\nchart/ChartTooltipContent.vue\nlightbox/\ntable-data/TableData.vue'

RED=$'\033[31m'; GRN=$'\033[32m'; YEL=$'\033[33m'; DIM=$'\033[2m'; RST=$'\033[0m'
fail=0

hash() {
  if command -v shasum >/dev/null 2>&1; then shasum -a 256 "$1" | awk '{print $1}';
  elif command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}';
  elif command -v md5sum >/dev/null 2>&1; then md5sum "$1" | awk '{print $1}';
  elif command -v md5 >/dev/null 2>&1; then md5 -q "$1";
  else cksum "$1" | awk '{print $1"-"$2}'; fi
}

# Guard: a broken/empty hash tool would make everything look "identical" (a false
# pass). Verify the hasher returns something non-empty before trusting any result.
if [ -z "$(hash "${BASH_SOURCE[0]}")" ]; then
  echo "${RED}FATAL: no working hash tool (shasum/sha256sum/md5sum/md5/cksum) on PATH.${RST}" >&2
  exit 2
fi

is_intentional() {
  local f="$1"
  while IFS= read -r pat; do
    [ -n "$pat" ] && [[ "$f" == *"$pat"* ]] && return 0
  done <<< "$INTENTIONAL_DRIFT"
  return 1
}

echo "== Theming sync check =="
echo "${DIM}canonical: $CANON${RST}"
echo

# ---- TIER 1: shared engine (MUST be byte-identical) ------------------------
echo "TIER 1 — shared engine (lib/appearance + style-*.css): must be identical"
tier1_files=()
for f in "$CANON"/lib/appearance/index.ts "$CANON"/lib/appearance/themes.ts "$CANON"/assets/css/styles/style-*.css; do
  [ -f "$f" ] && tier1_files+=("${f#$CANON/}")
done

for entry in "${TREES[@]}"; do
  name="${entry%%|*}"; tree="${entry##*|}"
  for rel in "${tier1_files[@]}"; do
    a="$CANON/$rel"; b="$tree/$rel"
    if [ ! -f "$b" ]; then
      echo "  ${RED}MISSING${RST} $name: $rel"; fail=1
    elif [ "$(hash "$a")" != "$(hash "$b")" ]; then
      echo "  ${RED}DRIFT  ${RST} $name: $rel"; fail=1
    fi
  done
done
[ "$fail" = 0 ] && echo "  ${GRN}OK${RST} — all ${#tier1_files[@]} engine files identical across repos"
echo

# ---- Single source of truth: active @import must match DEFAULT_STYLE --------
echo "SINGLE-SOURCE — active style @import matches DEFAULT_STYLE"
def_style="$(grep -oE 'DEFAULT_STYLE = "[a-z]+"' "$CANON/lib/appearance/index.ts" | grep -oE '"[a-z]+"' | tr -d '"')"
for entry in "events|$EVENTS/layers/base/app" "lev-base|$LEVENIUM/layers/base/app" "lev-ui|$LEVENIUM/apps/ui/app"; do
  name="${entry%%|*}"; tree="${entry##*|}"
  mc="$tree/assets/css/main.css"
  [ -f "$mc" ] || continue
  active="$(grep -E '^@import "\./styles/style-[a-z]+\.css"' "$mc" | grep -oE 'style-[a-z]+' | head -1 | sed 's/style-//')"
  if [ -n "$active" ] && [ "$active" != "$def_style" ]; then
    echo "  ${RED}MISMATCH${RST} $name: active @import = '$active' but DEFAULT_STYLE = '$def_style'"; fail=1
  else
    echo "  ${GRN}OK${RST} $name: active '$active' == DEFAULT_STYLE '$def_style'"
  fi
done
echo

# ---- TIER 2: components/ui (report drift; intentional ones are INFO) --------
echo "TIER 2 — components/ui (cn-* + shared bespoke): should be identical"
t2_drift=0
for entry in "${TREES[@]}"; do
  name="${entry%%|*}"; tree="${entry##*|}"
  # files present in BOTH trees that differ
  while IFS= read -r rel; do
    [ -z "$rel" ] && continue
    if is_intentional "$rel"; then
      echo "  ${DIM}info   $name: $rel (intentional per-repo)${RST}"
    else
      echo "  ${YEL}DRIFT  ${RST} $name: components/ui/$rel"; t2_drift=1
    fi
  done < <(cd "$CANON/components/ui" && for f in $(find . -type f | sed 's|^\./||'); do
             [ -f "$tree/components/ui/$f" ] && [ "$(hash "$CANON/components/ui/$f")" != "$(hash "$tree/components/ui/$f")" ] && echo "$f"
           done)
  # set differences (present in one, missing in other)
  only_canon="$(comm -23 <(cd "$CANON/components/ui" && find . -type d -mindepth 1 -maxdepth 1 | sort) <(cd "$tree/components/ui" 2>/dev/null && find . -type d -mindepth 1 -maxdepth 1 | sort) 2>/dev/null)"
  [ -n "$only_canon" ] && echo "  ${YEL}ONLY-IN-PMONE${RST} $name is missing:$(echo "$only_canon" | tr '\n' ' ')" && t2_drift=1
done
[ "$t2_drift" = 0 ] && echo "  ${GRN}OK${RST} — components/ui identical (apart from intentional bespoke)"
echo

if [ "$fail" != 0 ]; then
  echo "${RED}✗ Theming drift detected in TIER 1 / single-source. Re-sync from pmone.${RST}"
  exit 1
fi
echo "${GRN}✓ Shared theming engine is in sync.${RST}"
exit 0
