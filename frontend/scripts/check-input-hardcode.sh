#!/usr/bin/env bash
#
# check-input-hardcode.sh — catch call sites that hardcode visual values which the
# `cn-*` rules in assets/css/styles/style-*.css already own. Read-only, exits 1 on
# a finding.
#
#   bash frontend/scripts/check-input-hardcode.sh
#
# WHY: components/ui/** and every call site are supposed to attach only semantic
# `cn-*` classes; background, border, height, radius and padding belong to the
# style files so a field re-skins when the user switches Style. Twice now a
# hardcoded value slipped in and pinned a field to one look — most visibly the
# "Add Brand to Event" dialog, whose Brand Name / Sales fields carried
# `dark:bg-background` and so melted into the dialog in dark mode.
#
# HEURISTIC: a `class=` line is flagged when it carries all three of
#   (1) geometry : h-7..11 / min-h-* / rounded-*
#   (2) surface  : a real `border` utility (borderless-by-design inline editors,
#                  e.g. the quick-add task textarea, are intentionally exempt)
#   (3) field-ness: placeholder:text- / cursor-text / data-[placeholder] / file:bg-
#       (deliberately narrow — focus-visible:ring, shadow-xs and outline-none also
#        show up on buttons and cards, and made this noisy)
# Three signals at once is rare on non-input elements, so false positives stay low.
# Lines that already carry a field `cn-*` class are skipped, as is components/ui/.
#
# LIMITS: line-based. A class split across lines, or moved into `:class="[a, b]"`,
# slips through. This is a lint, not a proof.
# ESCAPE HATCH: put `cn-allow` in a comment on the same line.

set -uo pipefail

ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}/app"
RED=$'\033[31m'; GRN=$'\033[32m'; DIM=$'\033[2m'; RST=$'\033[0m'

GEOMETRY='(^|[" ])(h-(7|8|9|10|11)|min-h-[0-9[]|rounded-(sm|md|lg|xl|2xl))([" ]|$)'
SURFACE='(^|[" ])border(-border|-input)?([" ]|$)'
FIELDNESS='(placeholder:text-|cursor-text|data-\[placeholder\]|file:bg-)'
ALREADY_CN='cn-(input|textarea|select-trigger|combobox|input-group|native-select|checkbox|radio-group-item|switch|slider-track)'

echo "== Input hardcode check =="
echo "${DIM}root: $ROOT${RST}"
echo

hits=0

while IFS= read -r line; do
  [[ "$line" == *cn-allow* ]] && continue
  echo "  ${RED}HARDCODE${RST} ${line}"
  hits=$((hits + 1))
done < <(
  grep -rnE 'class="[^"]*"' --include="*.vue" "$ROOT/components" "$ROOT/pages" 2>/dev/null \
    | grep -v "/components/ui/" \
    | grep -vE "$ALREADY_CN" \
    | grep -E "$GEOMETRY" | grep -E "$SURFACE" | grep -E "$FIELDNESS" \
    | cut -c1-160
)

# An input nested in an `<InputGroup>` must carry `cn-input-group-input`, which
# strips its border/radius/shadow/ring AND its background. Hand-writing that
# stripping is what the pattern below catches: 23 call sites had spelled it out
# as `rounded-none border-0 shadow-none ... dark:bg-transparent`, which clears
# the background in dark mode ONLY. In light mode the input kept `bg-background`
# and, being 1px taller than the group's content box, painted over the group's
# top and bottom border — the field read as a broken half-drawn box.
# The heuristic above misses this: its GEOMETRY regex knows `rounded-sm..2xl`
# but not `rounded-none`, and its SURFACE regex knows `border`/`border-border`
# but not `border-0`.
group_input=$(grep -rnE 'class="[^"]*\brounded-none\b[^"]*"' --include="*.vue" "$ROOT/components" "$ROOT/pages" 2>/dev/null \
  | grep -v "/components/ui/" \
  | grep -vE 'cn-input-group-(input|textarea)' \
  | grep -E '\bborder-0\b' \
  | grep -v 'cn-allow' \
  | cut -c1-160 || true)
if [ -n "$group_input" ]; then
  echo "  ${RED}GROUP-INPUT${RST} strips field chrome by hand; use \`cn-input-group-input flex-1\`:"
  echo "$group_input" | sed 's/^/    /'
  hits=$((hits + 1))
fi

# The legacy `.input-base` / `.input-group` rules were removed from main.css; both
# selectors were unlayered and therefore beat every `cn-*` rule, which silently
# pinned six auth pages to a 40px/rounded-md field.
legacy=$(grep -rnE 'class="(input-base|input-group)"|class="[^"]* (input-base|input-group)( |")' \
  --include="*.vue" "$ROOT" 2>/dev/null || true)
if [ -n "$legacy" ]; then
  echo "  ${RED}LEGACY${RST}   .input-base / .input-group no longer exist in main.css:"
  echo "$legacy" | sed 's/^/    /'
  hits=$((hits + 1))
fi

echo
if [ "$hits" != 0 ]; then
  echo "${RED}✗ $hits call site(s) hardcode field visuals. Use a components/ui element, or attach the cn-* class.${RST}"
  exit 1
fi
echo "${GRN}✓ No hardcoded field visuals outside components/ui.${RST}"
