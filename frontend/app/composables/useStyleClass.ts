import { useAppearance } from "@/composables/useAppearance";

/**
 * Thin shim → the unified {@link useAppearance} gate. Kept so existing callers
 * (and the byte-identical-across-repos `components/ui`) work unchanged while all
 * storage + the `<body class="style-X">` injection live in one place.
 */
export function useStyleClass() {
  const a = useAppearance();
  return { current: a.style, setStyle: a.setStyle };
}
