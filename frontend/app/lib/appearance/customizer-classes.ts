// Shared class strings for the forced-dark customizer dropdowns/menus (the
// shadcn /create look). Kept in one place so the AppearanceCustomizer menus and
// the AppearancePicker dropdown never drift apart. Per-use sizing (w-52 / w-48 /
// w-56) is appended at the call site.

/** Dark, translucent, blurred menu/dropdown surface (portals to <body>). */
export const DARK_MENU =
  "dark rounded-xl border-0 bg-neutral-900/90 p-1.5 text-neutral-100 ring-1 ring-neutral-700/60 shadow-xl backdrop-blur-xl";

/** Dark menu item (radio item / plain item) inside a DARK_MENU surface. */
export const DARK_ITEM =
  "rounded-lg px-2 py-1.5 text-sm font-medium text-neutral-100 focus:bg-neutral-700/70 focus:text-neutral-100 focus:**:text-neutral-100 data-highlighted:bg-neutral-700/70";
