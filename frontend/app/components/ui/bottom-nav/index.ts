export { default as BottomNav } from "./BottomNav.vue";
export { default as BottomNavItem } from "./BottomNavItem.vue";
export { default as BottomNavAction } from "./BottomNavAction.vue";

// NOTE: Jangan re-export type/const dari ./context di sini. shadcn-nuxt
// me-register SETIAP named-export berhuruf-kapital sebagai komponen, dan
// `BottomNavContext` (type) + `BOTTOM_NAV_CONTEXT` (const) sama-sama
// ter-pascalCase jadi "BottomNavContext" -> warning "Overriding ... component".
// Konsumen import type/const langsung dari "./context".
