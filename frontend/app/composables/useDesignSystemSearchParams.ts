import { computed } from "vue";
import { DEFAULT_STYLE, STYLE_NAMES } from "@/lib/appearance";

/**
 * Shim for the ported shadcn-vue `/create` showcase (levenium origin).
 *
 * The upstream showcase reads live design-system selections from a URL-preset
 * store. In pmone the single source of truth is `useAppearance()` (cookie +
 * `useState('appearance:config')`), so this shim exposes the same read surface
 * the showcase needs — `style`, `font`, `fontHeading`, `iconLibrary`, `item` —
 * backed by that shared reactive state. Read-only (the Customizer/header picker
 * drive writes through `useAppearance` setters). The optional string arg (e.g.
 * "replace") is accepted for signature-compat and ignored.
 */
export function useDesignSystemSearchParams(_mode?: string) {
  const config = useState<Record<string, string> | null>(
    "appearance:config",
    () => null,
  );

  const style = computed(() => {
    const s = config.value?.style;
    return s && (STYLE_NAMES as readonly string[]).includes(s) ? s : DEFAULT_STYLE;
  });
  const font = computed(() => config.value?.font ?? "default");
  const fontHeading = computed(() => config.value?.fontHeading ?? "inherit");

  // Icon Library is intentionally fixed to lucide in pmone (the picker was dropped).
  const iconLibrary = computed(() => "lucide");

  // Which showcase block is visible (01 / 02). Local UI state, shared app-wide.
  const item = useState("appearance:preview-item", () => "preview-02");

  return { style, font, fontHeading, iconLibrary, item };
}
