// Single source of truth for the order currency override options used on the
// BrandEvent edit form. The 'auto' sentinel maps to a null override server-side
// (currency resolved from country / project defaults). Auto-imported.

export const CURRENCY_OVERRIDE_OPTIONS = [
  { value: "auto", label: "Auto" },
  { value: "IDR", label: "IDR" },
  { value: "USD", label: "USD" },
];
