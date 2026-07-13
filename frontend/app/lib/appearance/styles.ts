// shadcn-vue "Style" metadata (name + title + short description) for the
// Appearance pickers and the ported showcase (StyleOverview.vue). Each name maps
// to a `.style-X .cn-*` file in app/assets/css/styles. Unlike levenium's list,
// pmone KEEPS `mono` (the original PM One look) as the default.

export interface StyleMeta {
  name: string;
  title: string;
  description: string;
}

export const STYLES: StyleMeta[] = [
  { name: "mono", title: "Mono", description: "The original look. Clean and neutral." },
  { name: "vega", title: "Vega", description: "The classic shadcn/ui look. Clean, neutral, and familiar." },
  { name: "nova", title: "Nova", description: "Reduced padding and margins for compact layouts." },
  { name: "maia", title: "Maia", description: "Soft and rounded, with generous spacing." },
  { name: "lyra", title: "Lyra", description: "Boxy and sharp. Pairs well with mono fonts." },
  { name: "mira", title: "Mira", description: "Compact. Made for dense interfaces." },
  { name: "luma", title: "Luma", description: "Fluid, luminous, and glassy." },
  { name: "sera", title: "Sera", description: "Editorial and typographic." },
  { name: "rhea", title: "Rhea", description: "Like Luma but compact." },
];
