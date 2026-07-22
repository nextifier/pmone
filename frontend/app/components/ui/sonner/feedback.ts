import { toastSounds, type ToastSoundType } from "./sounds";

export type ToastFeedbackType = ToastSoundType;

const VOLUME = 0.3;

/* One shared gap for sound AND vibration: when several toasts land in the same
   tick, only the first plays — stacked sounds read as a glitch, not feedback. */
const MIN_GAP_MS = 100;

const VIBRATION_PATTERNS: Record<ToastFeedbackType, number[]> = {
  default: [10],
  info: [10],
  success: [12],
  warning: [18],
  error: [20, 40, 20],
};

interface AudioState {
  ctx: AudioContext;
  gain: GainNode;
  buffers: Map<ToastFeedbackType, AudioBuffer>;
}

/* Stashed on globalThis so an HMR reload of this module reuses the existing
   AudioContext — browsers cap the number of live contexts per page. */
const AUDIO_STATE_KEY = Symbol.for("cn-sonner-audio-state");

const getAudioState = (): AudioState | null => {
  const store = globalThis as Record<PropertyKey, unknown>;
  const existing = store[AUDIO_STATE_KEY];
  if (existing) return existing as AudioState;
  const Ctor: typeof AudioContext | undefined =
    (globalThis as { AudioContext?: typeof AudioContext }).AudioContext ??
    (globalThis as { webkitAudioContext?: typeof AudioContext })
      .webkitAudioContext;
  if (!Ctor) return null;
  try {
    const ctx = new Ctor();
    const gain = ctx.createGain();
    gain.gain.value = VOLUME;
    gain.connect(ctx.destination);
    const state: AudioState = { ctx, gain, buffers: new Map() };
    store[AUDIO_STATE_KEY] = state;
    return state;
  } catch {
    return null;
  }
};

const getBuffer = async (
  state: AudioState,
  type: ToastFeedbackType,
): Promise<AudioBuffer> => {
  const cached = state.buffers.get(type);
  if (cached) return cached;
  const { dataUri } = toastSounds[type];
  const binary = atob(dataUri.slice(dataUri.indexOf(",") + 1));
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
  const buffer = await state.ctx.decodeAudioData(bytes.buffer);
  state.buffers.set(type, buffer);
  return buffer;
};

const play = async (type: ToastFeedbackType): Promise<void> => {
  const state = getAudioState();
  if (!state) return;
  if (state.ctx.state === "suspended") {
    await state.ctx.resume().catch(() => {});
  }
  /* Never start a source on a non-running context: it would queue silently and
     every queued sound bursts at once when the context later resumes. */
  if (state.ctx.state !== "running") return;
  const buffer = await getBuffer(state, type);
  const source = state.ctx.createBufferSource();
  source.buffer = buffer;
  source.connect(state.gain);
  source.start(0);
};

const vibrate = (type: ToastFeedbackType): void => {
  if (typeof navigator === "undefined" || !("vibrate" in navigator)) return;
  if (
    typeof matchMedia === "function" &&
    matchMedia("(prefers-reduced-motion: reduce)").matches
  ) {
    return;
  }
  try {
    navigator.vibrate(VIBRATION_PATTERNS[type]);
  } catch {
    // haptics are best-effort only
  }
};

let lastNotifyAt = 0;

export const notify = (
  type: ToastFeedbackType,
  opts: { sound: boolean; vibration: boolean },
): void => {
  const now = Date.now();
  if (now - lastNotifyAt < MIN_GAP_MS) return;
  lastNotifyAt = now;
  if (opts.vibration) vibrate(type);
  if (opts.sound) play(type).catch(() => {});
};

/**
 * Pre-warms audio on the first user gesture so toasts fired outside a gesture
 * (e.g. after an async request settles) can still play: browsers only allow an
 * AudioContext to run once the page has been interacted with. Returns a dispose
 * function for unmount/HMR cleanup.
 */
export const init = (): (() => void) => {
  const unlock = (): void => {
    const state = getAudioState();
    if (!state) return;
    void state.ctx.resume().catch(() => {});
    for (const type of Object.keys(toastSounds) as ToastFeedbackType[]) {
      void getBuffer(state, type).catch(() => {});
    }
  };
  const options = { once: true, capture: true, passive: true } as const;
  window.addEventListener("pointerdown", unlock, options);
  window.addEventListener("keydown", unlock, options);
  return () => {
    window.removeEventListener("pointerdown", unlock, { capture: true });
    window.removeEventListener("keydown", unlock, { capture: true });
  };
};
