/**
 * Hardware scanner-gun capture for the check-in scanner.
 *
 * Two ergonomic goals from the field:
 *  1. Zero-touch: the operator can fire the gun without first tapping any
 *     field. A document-level keydown listener (capture phase) buffers the
 *     keystrokes wherever focus is, mirrors them into the visible input, and
 *     submits - no need to focus the field first.
 *  2. Works with guns configured WITHOUT a trailing Enter/CR: besides
 *     submitting on Enter, a fast burst that then goes silent auto-submits via
 *     an idle timeout. Human typing (slow, irregular gaps) never triggers it.
 *
 * The visible gun <Input> must carry a `data-scanner-gun` attribute so the
 * global capture can tell "the gun field is focused" (let its native v-model
 * fill the buffer) from "some other field is focused" (skip - never hijack a
 * human typing into the manual-search box).
 *
 * Escape hatch: if the global capture ever fights another surface, pass
 * `enabled: () => <predicate>` to gate it (the scanner passes
 * `() => !cameraActive` so the camera overlay owns detection while open).
 */
export interface ScannerGunOptions {
  /** Min buffered length before an idle burst counts as a scan. */
  minLength?: number;
  /** Silence (ms) after the last key before an idle burst auto-submits. */
  idleMs?: number;
  /** Max gap (ms) between keys to still count the burst as gun-fast. */
  fastKeyMs?: number;
  /** Gap (ms) above which a key starts a fresh sequence (resets the buffer). */
  newSequenceMs?: number;
  /** Optional gate; when it returns false the listener ignores keystrokes. */
  enabled?: () => boolean;
}

export function useScannerGun(onScan: (value: string) => void, opts: ScannerGunOptions = {}) {
  const minLength = opts.minLength ?? 4;
  const idleMs = opts.idleMs ?? 80;
  const fastKeyMs = opts.fastKeyMs ?? 50;
  const newSequenceMs = opts.newSequenceMs ?? 100;

  /** Bound to the visible gun <Input v-model>; also the global-capture buffer. */
  const inputValue = ref("");
  let lastKeyAt = 0;
  let fastSequence = true;
  let idleTimer: ReturnType<typeof setTimeout> | null = null;
  let lastFlushAt = 0;

  const clearIdle = (): void => {
    if (idleTimer) {
      clearTimeout(idleTimer);
      idleTimer = null;
    }
  };

  const submit = (value: string): void => {
    const trimmed = value.trim();
    if (!trimmed) {
      return;
    }
    // Guard the Enter + idle-timeout double-fire for the same scan.
    const now = Date.now();
    if (now - lastFlushAt < 30) {
      return;
    }
    lastFlushAt = now;
    onScan(trimmed);
  };

  const flush = (): void => {
    clearIdle();
    const value = inputValue.value;
    inputValue.value = "";
    fastSequence = true;
    submit(value);
  };

  const isEditable = (el: Element | null): boolean => {
    if (!(el instanceof HTMLElement)) {
      return false;
    }
    const tag = el.tagName;
    return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || el.isContentEditable;
  };

  const onKeydown = (e: KeyboardEvent): void => {
    if (e.ctrlKey || e.metaKey || e.altKey) {
      return;
    }
    if (opts.enabled && !opts.enabled()) {
      return;
    }

    const active = document.activeElement;
    const gunFocused = active instanceof HTMLElement && active.hasAttribute("data-scanner-gun");
    // Never hijack a human typing in another field (manual search, etc).
    if (!gunFocused && isEditable(active)) {
      return;
    }

    const now = Date.now();
    const gap = now - lastKeyAt;
    if (gap > newSequenceMs) {
      // A long pause means a fresh scan; drop any stale partial buffer.
      if (!gunFocused) {
        inputValue.value = "";
      }
      fastSequence = true;
    } else {
      fastSequence = fastSequence && gap < fastKeyMs;
    }
    lastKeyAt = now;

    if (e.key === "Enter") {
      if (inputValue.value) {
        e.preventDefault();
        flush();
      }
      return;
    }

    // Only printable single characters contribute to the token. When the gun
    // field is focused its own v-model fills `inputValue`; we only mirror the
    // keystroke ourselves when capturing globally with no field focused.
    if (e.key.length === 1) {
      if (!gunFocused) {
        inputValue.value += e.key;
      }
      clearIdle();
      idleTimer = setTimeout(() => {
        if (fastSequence && inputValue.value.length >= minLength) {
          flush();
        }
      }, idleMs);
    }
  };

  /** Bind to the gun input via `@paste`: a manual copy-paste submits at once. */
  const onPaste = (e: ClipboardEvent): void => {
    const text = e.clipboardData?.getData("text") ?? "";
    const value = text.trim();
    if (!value) {
      return;
    }
    e.preventDefault();
    inputValue.value = "";
    submit(value);
  };

  onMounted(() => {
    document.addEventListener("keydown", onKeydown, true);
  });

  onBeforeUnmount(() => {
    document.removeEventListener("keydown", onKeydown, true);
    clearIdle();
  });

  return { inputValue, onPaste, flush };
}
