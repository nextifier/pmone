import button from "./button";
import badge from "./badge";
import input from "./input";
import card from "./card";
import field from "./field";
import dialog from "./dialog";
import dialogResponsive from "./dialog-responsive";
import select from "./select";
import table from "./table";
import tabs from "./tabs";

export const registry = {
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
};

export function getEntry(name) {
  return registry[name] || null;
}

export function getAllEntries() {
  return Object.values(registry);
}
