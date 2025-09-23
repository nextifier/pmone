<template>
  <SwitchRoot
    v-model:model-value="isDarkMode"
    class="text-primary hover:bg-muted relative flex size-8 rounded-lg"
    aria-label="Toggle Dark Mode"
  >
    <SwitchThumb class="absolute inset-0 flex items-center justify-center">
      <ClientOnly>
        <template #default>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="size-4.5 transition duration-500 ease-out"
            :class="{
              'rotate-180': isDarkMode,
            }"
          >
            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
            <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
            <path d="M12 3l0 18"></path>
            <path d="M12 9l4.65 -4.65"></path>
            <path d="M12 14.3l7.37 -7.37"></path>
            <path d="M12 19.6l8.85 -8.85"></path>
          </svg>
        </template>
        <template #placeholder>
          <div
            class="size-4 animate-[spin_1s_linear_infinite] rounded-full border border-current border-r-transparent"
          ></div>
        </template>
      </ClientOnly>
    </SwitchThumb>
  </SwitchRoot>
</template>

<script setup>
import { SwitchRoot, SwitchThumb } from "reka-ui";

const colorMode = useColorMode();
const nuxtApp = useNuxtApp();

// Try to get user from sanctum auth, but don't fail if not available
const { user } = useSanctumAuth();
const sanctumFetch = useSanctumClient();

// Load theme preference from user settings or fallback to current colorMode
const loadThemePreference = () => {
  if (user.value?.user_settings?.theme) {
    colorMode.preference = user.value.user_settings.theme;
  }
  // If no user settings, colorMode will use its default behavior (localStorage)
};

// Save theme to user settings when authenticated
const saveThemeToUserSettings = async (theme) => {
  if (!user.value) return; // Skip if not authenticated

  try {
    const currentSettings = user.value.user_settings || {};
    const updatedSettings = {
      ...currentSettings,
      theme: theme
    };

    await sanctumFetch('/api/user/profile/settings', {
      method: 'PATCH',
      body: {
        settings: updatedSettings
      }
    });

    // Update local user data
    if (user.value) {
      user.value.user_settings = updatedSettings;
    }
  } catch (error) {
    console.error('Failed to save theme to user settings:', error);
    // Fail silently - localStorage will still work
  }
};

const isDarkMode = computed({
  get: () => colorMode.value === "dark",
  set: (value) => {
    const newTheme = value ? "dark" : "light";
    colorMode.preference = newTheme;

    nextTick(() => {
      nuxtApp.$updateMetaThemeColor();
    });

    // Save to user settings if authenticated
    saveThemeToUserSettings(newTheme);
  },
});

// Load theme preference when component mounts
onMounted(() => {
  loadThemePreference();
});

// Watch for user changes and reload theme preference
watch(user, () => {
  if (user.value) {
    loadThemePreference();
  }
}, { deep: true });

defineShortcuts({
  meta_d: {
    handler: () => {
      isDarkMode.value = !isDarkMode.value;
    },
  },
});
</script>
