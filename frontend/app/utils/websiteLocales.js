// Locales for dashboard-managed per-locale website content (SEO meta titles/
// descriptions, legal page bodies). Mirrors the pmone-events i18n locales.
// Auto-imported.
export const WEBSITE_LOCALES = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
  { value: "ja", label: "日本語" },
  { value: "ko", label: "한국어" },
  { value: "zh", label: "中文" },
];

// A blank input becomes "" via v-model, but the backend's nullable rules only
// accept null or a non-empty string. Send blank values as null so the public
// site fails open to its baked copy for that locale instead of persisting an
// empty override. Non-blank values are returned untouched (not trimmed) so
// rich-text/HTML content is preserved exactly.
export function blankToNull(value) {
  return (value ?? "").trim() === "" ? null : value;
}
