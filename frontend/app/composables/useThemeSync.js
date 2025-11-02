import { useDebounceFn } from "@vueuse/core";

/**
 * Centralized theme synchronization composable
 * Handles instant UI updates with debounced backend sync
 */
export function useThemeSync() {
  const colorMode = useColorMode();
  const { user } = useSanctumAuth();
  const sanctumFetch = useSanctumClient();

  // Sync status for UI feedback
  const isSyncing = ref(false);
  const lastSyncedAt = ref(null);
  const syncError = ref(null);
  const hasPendingSync = ref(false);

  /**
   * Update meta theme-color tag based on current color mode
   * This affects browser UI chrome (address bar, tab bar, etc.)
   */
  const updateMetaThemeColor = () => {
    // Get actual color mode from localStorage
    const currentColorMode = localStorage.getItem("color-mode") || "dark";

    // Dynamic theme color based on actual color mode
    const themeColor = currentColorMode === "light" ? "#ffffff" : "#09090b";

    const meta = document.querySelector("meta[name=theme-color]");

    if (meta) {
      meta.setAttribute("content", themeColor);
    } else {
      const newMeta = document.createElement("meta");
      newMeta.name = "theme-color";
      newMeta.content = themeColor;
      document.head.appendChild(newMeta);
    }
  };

  /**
   * Save theme to backend user settings
   * Silent sync - does NOT update local state to prevent glitches
   */
  const saveThemeToBackend = async (theme) => {
    if (!user.value) return; // Skip if not authenticated

    try {
      isSyncing.value = true;
      syncError.value = null;

      const currentSettings = user.value.user_settings || {};
      const updatedSettings = {
        ...currentSettings,
        theme: theme,
      };

      await sanctumFetch("/api/user/settings", {
        method: "PATCH",
        body: {
          settings: updatedSettings,
        },
      });

      // Silent sync - don't update local user data to prevent watchers triggering
      // Backend is now in sync, but we don't reflect it back to prevent glitches

      lastSyncedAt.value = new Date();
      hasPendingSync.value = false;
    } catch (error) {
      console.error("Failed to save theme to backend:", error);
      syncError.value = "Failed to sync theme preference";
      // Fail silently - localStorage will still work
    } finally {
      isSyncing.value = false;
    }
  };

  /**
   * Debounced save function (1 second delay)
   * Will only execute after user stops changing theme for 1 second
   */
  const debouncedSave = useDebounceFn((theme) => {
    saveThemeToBackend(theme);
  }, 1000);

  /**
   * Set theme with instant UI update and debounced backend sync
   */
  const setTheme = (theme) => {
    hasPendingSync.value = true;

    // 1. Instant UI update (localStorage)
    colorMode.preference = theme;

    // 2. Update meta theme color
    nextTick(() => {
      updateMetaThemeColor();
    });

    // 3. Debounced backend sync (only if authenticated)
    if (user.value) {
      debouncedSave(theme);
    }
  };

  /**
   * Load theme preference from user settings on initial login
   * Only runs once - does not re-sync from backend after initial load
   */
  const loadThemePreference = () => {
    // Only load backend theme if localStorage is empty (first time login)
    const hasLocalTheme = localStorage.getItem("color-mode");
    if (hasLocalTheme) {
      console.log("Using local theme preference");
      return;
    }

    // Only load if backend has a theme
    if (user.value?.user_settings?.theme) {
      console.log("Loading theme from backend (first time)");
      colorMode.preference = user.value.user_settings.theme;
    }
    // If no user settings, colorMode will use its default behavior
  };

  /**
   * Force immediate sync (useful for critical scenarios)
   */
  const forceSyncNow = async () => {
    if (user.value && colorMode.preference) {
      await saveThemeToBackend(colorMode.preference);
    }
  };

  // Load theme preference on initialization (only once)
  onMounted(() => {
    loadThemePreference();

    // Update meta theme color on mount
    updateMetaThemeColor();

    // Watch for color mode changes and update meta theme color
    watch(
      () => colorMode.value,
      () => {
        nextTick(() => {
          updateMetaThemeColor();
        });
      }
    );
  });

  // No watcher for backend sync - we use one-way sync (local → backend only)
  // This prevents glitches from backend responses triggering local state changes

  return {
    colorMode,
    setTheme,
    updateMetaThemeColor,
    loadThemePreference,
    forceSyncNow,
    isSyncing,
    lastSyncedAt,
    syncError,
    hasPendingSync,
  };
}
