// Ported verbatim from thinking-orbs by Jakub Antalik (MIT License).
// https://github.com/Jakubantalik/thinking-orbs
// Framework-agnostic canvas engine: no React, no Vue. Do not hand-edit —
// re-sync from upstream instead.

// The shipped tunings: six states × two anchor sizes, baked from the
// inkform mini-page tuning session. `count`/`size` are multipliers over the
// base fine profiles; `speed` multiplies the shared clock. Any size in
// between blends the two anchors; outside the range the nearest anchor's
// tuning is held. Resolved once per (state, size) pair and cached — the
// render loop sees plain numbers.

import type { ModeOpts } from "./engine/profiles";
import { BASE_PROFILES, scaleCounts, scaleRadii } from "./engine/profiles";
import type { OrbAnchorSize, OrbSize, OrbState } from "./types";

export type ModeKey =
  | "orbits"
  | "globe"
  | "rubik"
  | "wave"
  | "ribbon"
  | "morph";

export const STATE_TO_MODE: Record<OrbState, ModeKey> = {
  working: "orbits",
  searching: "globe",
  solving: "rubik",
  listening: "wave",
  composing: "ribbon",
  shaping: "morph",
};

interface Preset {
  speed: number;
  count: number;
  size: number;
  /** Extra mode opts merged verbatim after scaling. */
  extra?: ModeOpts;
}

const PRESETS: Record<ModeKey, Record<OrbAnchorSize, Preset>> = {
  orbits: {
    64: { speed: 1.885, count: 1, size: 1 },
    20: { speed: 3.9, count: 0.238, size: 2.4 },
  },
  globe: {
    64: {
      speed: 2.015,
      count: 0.42,
      size: 1.15,
      extra: { scanMul: 4.08, dimBase: 0.45 },
    },
    20: {
      speed: 2.665,
      count: 0.105,
      size: 1.75,
      extra: { scanMul: 4.335, dimBase: 0.45 },
    },
  },
  rubik: {
    64: { speed: 1.82, count: 0.35, size: 1.05 },
    20: { speed: 1.95, count: 0.088, size: 1.9 },
  },
  wave: {
    64: { speed: 4.388, count: 0.341, size: 1 },
    20: { speed: 3.998, count: 0.105, size: 1.6 },
  },
  ribbon: {
    64: {
      speed: 2.34,
      count: 0.25,
      size: 0.85,
      extra: { spin: 0, bandMul: 3.9, wobMul: 1 },
    },
    20: {
      speed: 3.12,
      count: 0.051,
      size: 1.073,
      extra: { spin: 0, bandMul: 4.94, wobMul: 1 },
    },
  },
  morph: {
    64: { speed: 2.405, count: 0.54, size: 0.395, extra: { spread: 1.45 } },
    20: { speed: 2.08, count: 0.53, size: 1.011, extra: { spread: 1.45 } },
  },
};

export interface Resolved {
  mode: ModeKey;
  speed: number;
  opts: ModeOpts;
}

const SMALL = 20;
const LARGE = 64;

/** Multipliers are positive and span wide ranges (count 1 → 0.238, size
 * 1 → 2.4), so they blend geometrically — a linear blend would sit far
 * closer to the larger anchor across most of the range. */
function geoBlend(a: number, b: number, t: number): number {
  return a * (b / a) ** t;
}

function linBlend(a: number, b: number, t: number): number {
  return a + (b - a) * t;
}

/** Blend the two anchor presets. `extra` keys present on only one anchor
 * pass through untouched — every shipped pair is symmetric, but a lone key
 * must not silently vanish. */
function blendPresets(lo: Preset, hi: Preset, t: number): Preset {
  const out: Preset = {
    speed: linBlend(lo.speed, hi.speed, t),
    count: geoBlend(lo.count, hi.count, t),
    size: geoBlend(lo.size, hi.size, t),
  };

  if (lo.extra || hi.extra) {
    const extra: ModeOpts = { ...lo.extra, ...hi.extra };
    for (const k of Object.keys(extra)) {
      const a = lo.extra?.[k];
      const b = hi.extra?.[k];
      // `spin` is 0 at both anchors, so extras blend linearly.
      if (a != null && b != null) extra[k] = linBlend(a, b, t);
    }
    out.extra = extra;
  }

  return out;
}

const cache = new Map<string, Resolved>();

/**
 * Resolve a (state, size) pair to its mode + fully-scaled draw options.
 *
 * `size` is free: the anchors (20, 64) return their baked tuning verbatim,
 * sizes in between blend the two, and sizes outside the range hold the
 * nearest anchor — the drawn geometry still follows `size` because every
 * mode paints relative to it.
 */
export function resolvePreset(state: OrbState, size: OrbSize): Resolved {
  // Quantise the lookup so dragging a size slider cannot grow the cache
  // without bound; the canvas still draws at the exact size it was given.
  const px = Math.round(size * 2) / 2;
  const key = `${state}-${px}`;
  const hit = cache.get(key);
  if (hit) return hit;

  const mode = STATE_TO_MODE[state];
  const anchors = PRESETS[mode];
  const t = Math.min(1, Math.max(0, (px - SMALL) / (LARGE - SMALL)));
  const preset =
    t === 0
      ? anchors[SMALL]
      : t === 1
        ? anchors[LARGE]
        : blendPresets(anchors[SMALL], anchors[LARGE], t);

  let opts: ModeOpts = { ...BASE_PROFILES[mode] };
  if (preset.count !== 1) opts = scaleCounts(opts, preset.count);
  if (preset.size !== 1) opts = scaleRadii(opts, preset.size);
  if (preset.extra) opts = { ...opts, ...preset.extra };

  const resolved: Resolved = { mode, speed: preset.speed, opts };
  cache.set(key, resolved);
  return resolved;
}
