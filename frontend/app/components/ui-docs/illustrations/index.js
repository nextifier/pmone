import badge from "./badge.vue";
import button from "./button.vue";
import card from "./card.vue";
import dialog from "./dialog.vue";
import dialogResponsive from "./dialog-responsive.vue";
import field from "./field.vue";
import input from "./input.vue";
import select from "./select.vue";
import table from "./table.vue";
import tabs from "./tabs.vue";
import introduction from "./introduction.vue";

export const illustrations = {
  badge,
  button,
  card,
  dialog,
  "dialog-responsive": dialogResponsive,
  field,
  input,
  select,
  table,
  tabs,
  introduction,
};

export function getIllustration(name) {
  return illustrations[name] || null;
}
