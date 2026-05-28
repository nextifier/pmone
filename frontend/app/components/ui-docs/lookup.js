import { getEntry } from "./registry";
import { getGuide } from "./guides";

export function getDocsEntry(name) {
  return getEntry(name) || getGuide(name) || null;
}
