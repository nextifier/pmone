// Booth number formatting, mirroring App\Support\InputNormalizer::boothNumber
// on the API. Keep both in sync when the rule changes.
//
// Tokens are separated by comma, slash, ampersand or whitespace, and every
// separator survives as typed. Within a token a "-" goes in front of that
// token's FINAL digit group, so "8A81" becomes "8A-81" and "B1A01" becomes
// "B1A-01" (not "B-1A-01"), while values that are already correct ("6E-01",
// "A-04A", "AA-105") are left alone. Letters are uppercased - a booth number is
// signage, and case never carries meaning in one. Idempotent.
//
// Only the display format is mirrored here. The key that puts booths in physical
// order (InputNormalizer::boothSortKey) is built server-side and arrives on the
// row as `booth_sort_key` - there is nothing to duplicate in the browser.

const dashRe = () => /([A-Z])(\d+)(?![^\s,/&]*\d)/g;

/**
 * Insert the dashes and report where they landed, so a live input can tell its
 * own dashes apart from the ones the user typed.
 *
 * @param {string} raw
 * @returns {{ value: string, auto: Set<number> }}
 */
export function insertBoothDashes(raw) {
  const auto = new Set();
  let shift = 0;

  // Uppercasing first keeps the offsets below aligned with the returned string.
  // It is 1:1 on every character this field accepts, so it cannot shift a caret.
  const value = String(raw ?? "")
    .toUpperCase()
    .replace(dashRe(), (match, letter, digits, offset) => {
      auto.add(offset + 1 + shift); // the "-" lands right after the letter
      shift += 1;
      return `${letter}-${digits}`;
    });

  return { value, auto };
}

/** Trim, collapse whitespace runs, and give every comma exactly one space after it. */
export function tidyBoothSeparators(value) {
  const trimmed = String(value ?? "")
    .replace(/\s+/g, " ")
    .trim();

  return trimmed === "" ? "" : trimmed.replace(/\s*,\s*/g, ", ");
}

/** Full normalization - what the API will store. */
export function normalizeBoothNumber(value) {
  return insertBoothDashes(tidyBoothSeparators(value)).value;
}
