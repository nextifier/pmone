import introduction from "./introduction";

export const guides = {
  introduction,
};

export function getGuide(name) {
  return guides[name] || null;
}
