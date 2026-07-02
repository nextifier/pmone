import { useAppearance } from "@/composables/useAppearance";

/**
 * Thin shim → the unified {@link useAppearance} gate. Kept so existing callers
 * (and the byte-identical-across-repos `components/ui`: ColorModeToggle /
 * ColorModeButtons) work unchanged while color-mode storage, backend sync and the
 * reactive theme-color meta all live in one place. `updateMetaThemeColor` /
 * `loadThemePreference` are no-ops now (handled reactively + SSR by useAppearance).
 */
export function useThemeSync() {
  const a = useAppearance();
  return {
    colorMode: a.colorMode,
    setTheme: a.setColorMode,
    updateMetaThemeColor: () => {},
    loadThemePreference: () => {},
    forceSyncNow: async () => {},
    isSyncing: a.isSyncing,
    lastSyncedAt: ref(null),
    syncError: a.syncError,
    hasPendingSync: ref(false),
  };
}
