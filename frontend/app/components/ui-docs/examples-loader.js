const componentModules = import.meta.glob("./examples/*/*.vue", { eager: true });
const sourceModules = import.meta.glob("./examples/*/*.vue", {
  eager: true,
  query: "?raw",
  import: "default",
});

function keyOf(component, example) {
  return `./examples/${component}/${example}.vue`;
}

export function getExample(component, example) {
  const key = keyOf(component, example);
  return {
    component: componentModules[key]?.default ?? null,
    source: sourceModules[key] ?? "",
  };
}
