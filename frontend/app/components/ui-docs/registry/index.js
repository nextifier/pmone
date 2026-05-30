// Component docs are auto-discovered from ./<name>.js via import.meta.glob.
const modules = import.meta.glob("./*.js", { eager: true });

export const registry = Object.fromEntries(
  Object.entries(modules)
    .filter(([path]) => !path.endsWith("/index.js") && !path.endsWith("/define.js"))
    .map(([path, mod]) => {
      const name = path.replace(/^\.\//, "").replace(/\.js$/, "");
      return [name, mod.default];
    }),
);

export function getEntry(name) {
  return registry[name] || null;
}
