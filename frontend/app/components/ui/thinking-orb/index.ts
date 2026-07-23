export { default as ThinkingOrb } from "./ThinkingOrb.vue";

export type { OrbAnchorSize, OrbSize, OrbState, OrbTheme } from "./types";

// Power-user surface: the resolved presets + raw frame painters, for
// consumers driving their own canvas outside the component.
export {
  resolvePreset,
  STATE_TO_MODE,
  type ModeKey,
  type Resolved,
} from "./presets";
export { MODE_DRAWS } from "./engine/registry";
