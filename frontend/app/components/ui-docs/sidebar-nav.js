import { registry } from "./registry";
import { guides } from "./guides";

function titleCase(name) {
  return name
    .split("-")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

const componentItems = Object.keys(registry)
  .sort()
  .map((name) => ({
    name,
    title: registry[name].title || titleCase(name),
  }));

const guideItems = Object.keys(guides).map((name) => ({
  name,
  title: guides[name].title || titleCase(name),
}));

export const sidebarNav = [
  { label: "Getting Started", items: guideItems },
  { label: "Components", items: componentItems },
];

export const flatNav = sidebarNav.flatMap((group) =>
  group.items.map((item) => ({ ...item, group: group.label })),
);

export function findAdjacent(name) {
  const index = flatNav.findIndex((item) => item.name === name);
  if (index === -1) return { prev: null, next: null };
  return {
    prev: index > 0 ? flatNav[index - 1] : null,
    next: index < flatNav.length - 1 ? flatNav[index + 1] : null,
  };
}
