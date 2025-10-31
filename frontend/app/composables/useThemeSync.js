import { useDebounceFn } from '@vueuse/core'

/**
 * Centralized theme synchronization composable
 * Handles instant UI updates with debounced backend sync
 */
export function useThemeSync() {
  const colorMode = useColorMode()
  const nuxtApp = useNuxtApp()
  const { user } = useSanctumAuth()
  const sanctumFetch = useSanctumClient()

  // Sync status for UI feedback
  const isSyncing = ref(false)
  const lastSyncedAt = ref(null)
  const syncError = ref(null)

  // Track last local change to prevent backend overwriting local changes
  const lastLocalChangeAt = ref(null)
  const hasPendingSync = ref(false)

  /**
   * Save theme to backend user settings
   */
  const saveThemeToBackend = async (theme) => {
    if (!user.value) return // Skip if not authenticated

    try {
      isSyncing.value = true
      syncError.value = null

      const currentSettings = user.value.user_settings || {}
      const updatedSettings = {
        ...currentSettings,
        theme: theme,
      }

      await sanctumFetch('/api/user/settings', {
        method: 'PATCH',
        body: {
          settings: updatedSettings,
        },
      })

      // Update local user data
      if (user.value) {
        user.value.user_settings = updatedSettings
      }

      lastSyncedAt.value = new Date()
      hasPendingSync.value = false
    } catch (error) {
      console.error('Failed to save theme to backend:', error)
      syncError.value = 'Failed to sync theme preference'
      // Fail silently - localStorage will still work
    } finally {
      isSyncing.value = false
    }
  }

  /**
   * Debounced save function (1 second delay)
   * Will only execute after user stops changing theme for 1 second
   */
  const debouncedSave = useDebounceFn((theme) => {
    saveThemeToBackend(theme)
  }, 1000)

  /**
   * Set theme with instant UI update and debounced backend sync
   */
  const setTheme = (theme) => {
    // Track this as a local change
    lastLocalChangeAt.value = Date.now()
    hasPendingSync.value = true

    // 1. Instant UI update (localStorage)
    colorMode.preference = theme

    // 2. Update meta theme color
    nextTick(() => {
      nuxtApp.$updateMetaThemeColor?.()
    })

    // 3. Debounced backend sync (only if authenticated)
    if (user.value) {
      debouncedSave(theme)
    }
  }

  /**
   * Load theme preference from user settings
   * Only loads if there are no pending local changes
   */
  const loadThemePreference = () => {
    // Don't overwrite local changes with backend data
    if (hasPendingSync.value) {
      console.log('Skipping theme load - pending local changes')
      return
    }

    // Only load if backend has a theme AND it's different from current
    if (user.value?.user_settings?.theme && user.value.user_settings.theme !== colorMode.preference) {
      colorMode.preference = user.value.user_settings.theme
    }
    // If no user settings, colorMode will use its default behavior (localStorage)
  }

  /**
   * Force immediate sync (useful for critical scenarios)
   */
  const forceSyncNow = async () => {
    if (user.value && colorMode.preference) {
      await saveThemeToBackend(colorMode.preference)
    }
  }

  // Load theme preference on initialization
  onMounted(() => {
    loadThemePreference()
  })

  // Watch for user changes and reload theme preference
  // Only reload if no pending local changes
  watch(
    user,
    () => {
      if (user.value) {
        loadThemePreference()
      }
    },
    { deep: true }
  )

  return {
    colorMode,
    setTheme,
    loadThemePreference,
    forceSyncNow,
    isSyncing,
    lastSyncedAt,
    syncError,
    hasPendingSync,
  }
}
