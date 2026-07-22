export { default as MetalFx } from "./MetalFx.vue";

export type { MetalFxPreset, MetalFxTheme, MetalFxVariant } from "./types";

// Power-user surface: the engine primitives, for consumers driving the same
// renderer outside the component.
export {
  PRESETS,
  hexToRgb,
  type Preset,
  type PresetMode,
  type PresetName,
  type PresetTheme,
} from "./engine/presets";

export {
  createInstance,
  destroyInstance,
  updateInstance,
  setSharedPreset,
  pauseShared,
  resumeShared,
} from "./engine/renderer/loop";

export type { MetalFxInstance } from "./engine/renderer/core";
