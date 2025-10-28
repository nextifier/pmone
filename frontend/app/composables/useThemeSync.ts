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
  const lastSyncedAt = ref<Date | null>(null)
  const syncError = ref<string | null>(null)

  /**
   * Save theme to backend user settings
   */
  const saveThemeToBackend = async (theme: string) => {
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
  const debouncedSave = useDebounceFn((theme: string) => {
    saveThemeToBackend(theme)
  }, 1000)

  /**
   * Set theme with instant UI update and debounced backend sync
   */
  const setTheme = (theme: string) => {
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
   */
  const loadThemePreference = () => {
    if (user.value?.user_settings?.theme) {
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
  }
}
