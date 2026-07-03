import { computed } from "vue";
import { toast } from "vue-sonner";
import type { AppearanceConfig } from "@/lib/appearance";
import { encodePreset, parsePresetInput, randomPreset } from "@/lib/appearance/preset";

/** Single-level undo snapshot. `undefined` = nothing to undo. */
type UndoSnapshot = Partial<AppearanceConfig> | null | undefined;

/**
 * Shared customizer actions (Copy / Open / Shuffle / Reset) with single-level
 * undo, plus the two dialogs' open-state. Everything is `useState`-backed so the
 * settings-page customizer and the header-popover customizer share ONE source of
 * truth — which lets the dialogs be mounted exactly once (see AppearanceDialogs).
 */
export function useAppearanceActions() {
  const { applyConfig, reset, setConfig, config, presetConfig } = useAppearance();

  const resetDialogOpen = useState("appearance:reset-dialog", () => false);
  const openPresetDialogOpen = useState("appearance:open-preset-dialog", () => false);
  const undoSnapshot = useState<UndoSnapshot>("appearance:undo", () => undefined);
  const hasCopied = useState("appearance:copied", () => false);

  // SSR-safe (manual base64 in preset.ts) → renders during SSR with no swap.
  const presetCode = computed(() => encodePreset(presetConfig.value));
  const copyLabel = computed(() =>
    hasCopied.value ? "Copied" : `--preset ${presetCode.value}`,
  );

  function captureUndo() {
    undoSnapshot.value = config.value ? { ...config.value } : null;
  }

  function undoLast() {
    if (undoSnapshot.value === undefined) {
      return;
    }
    setConfig(undoSnapshot.value ?? null);
    undoSnapshot.value = undefined;
  }

  function toastWithUndo(message: string) {
    toast(message, { action: { label: "Undo", onClick: undoLast } });
  }

  function shuffle() {
    captureUndo();
    applyConfig(randomPreset());
    toastWithUndo("Shuffled appearance");
  }

  function applyPreset(input: string): boolean {
    const next = parsePresetInput(input);
    if (!next) {
      toast.error("Invalid preset code");
      return false;
    }
    captureUndo();
    applyConfig(next);
    toastWithUndo("Preset applied");
    return true;
  }

  function confirmReset() {
    captureUndo();
    reset();
    resetDialogOpen.value = false;
    toastWithUndo("Reset to defaults");
  }

  let copyTimer: ReturnType<typeof setTimeout> | undefined;
  function copyPreset() {
    const code = presetCode.value;
    if (!code) {
      return;
    }
    if (!navigator.clipboard?.writeText) {
      toast.error("Clipboard is not available");
      return;
    }
    navigator.clipboard
      .writeText(`--preset ${code}`)
      .then(() => {
        hasCopied.value = true;
        clearTimeout(copyTimer);
        copyTimer = setTimeout(() => (hasCopied.value = false), 2000);
      })
      .catch(() => toast.error("Failed to copy preset"));
  }

  return {
    resetDialogOpen,
    openPresetDialogOpen,
    presetCode,
    copyLabel,
    hasCopied,
    shuffle,
    applyPreset,
    confirmReset,
    copyPreset,
    undoLast,
    openReset: () => (resetDialogOpen.value = true),
    openPreset: () => (openPresetDialogOpen.value = true),
  };
}
