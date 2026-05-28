export const sidebarNav = [
  {
    label: "Getting Started",
    items: [{ name: "introduction", title: "Introduction" }],
  },
  {
    label: "Components",
    items: [
      { name: "badge", title: "Badge" },
      { name: "button", title: "Button" },
      { name: "card", title: "Card" },
      { name: "dialog", title: "Dialog" },
      { name: "dialog-responsive", title: "Dialog Responsive" },
      { name: "field", title: "Field" },
      { name: "input", title: "Input" },
      { name: "select", title: "Select" },
      { name: "table", title: "Table" },
      { name: "tabs", title: "Tabs" },
    ],
  },
];

export const flatNav = sidebarNav.flatMap((group) =>
  group.items.map((item) => ({ ...item, group: group.label })),
);

export function findNavEntry(name) {
  return flatNav.find((item) => item.name === name) || null;
}

export function findAdjacent(name) {
  const index = flatNav.findIndex((item) => item.name === name);
  if (index === -1) return { prev: null, next: null };
  return {
    prev: index > 0 ? flatNav[index - 1] : null,
    next: index < flatNav.length - 1 ? flatNav[index + 1] : null,
  };
}
