const modules = import.meta.glob("./*.vue", { eager: true });

export const illustrations = Object.fromEntries(
  Object.entries(modules)
    .filter(([path]) => !path.endsWith("/IllustrationFrame.vue"))
    .map(([path, mod]) => {
      const name = path.replace(/^\.\//, "").replace(/\.vue$/, "");
      return [name, mod.default];
    }),
);

export function getIllustration(name) {
  return illustrations[name] || null;
}
