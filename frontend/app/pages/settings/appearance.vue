<template>
  <div class="mx-auto max-w-sm space-y-6">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:colors" class="size-5 sm:size-6" />
      <h1 class="page-title">Appearance</h1>
    </div>

    <div class="mt-8 space-y-6">
      <!-- Theme Selection -->
      <div class="space-y-4">
        <div>
          <h3 class="font-semibold tracking-tight">Theme</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Choose your preferred theme for the interface.
          </p>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <!-- Light Mode -->
          <label class="cursor-pointer">
            <input
              v-model="selectedTheme"
              type="radio"
              value="light"
              class="sr-only"
              @change="updateTheme"
            />
            <div
              class="border-border aspect-64/54 w-full overflow-hidden rounded-xl border transition active:scale-98"
              :class="{
                'ring-primary ring-offset-background ring-2 ring-offset-2':
                  selectedTheme === 'light',
                'hover:border-muted-foreground': selectedTheme !== 'light',
              }"
            >
              <ColorModeThumbnailLight class="h-full w-full object-cover" />
            </div>
            <div
              class="mt-2 text-center text-sm font-medium"
              :class="{
                'text-primary': selectedTheme === 'light',
                'text-muted-foreground/80': selectedTheme !== 'light',
              }"
            >
              Light
            </div>
          </label>

          <!-- Dark Mode -->
          <label class="cursor-pointer">
            <input
              v-model="selectedTheme"
              type="radio"
              value="dark"
              class="sr-only"
              @change="updateTheme"
            />
            <div
              class="border-border aspect-64/54 w-full overflow-hidden rounded-xl border transition active:scale-98"
              :class="{
                'ring-primary ring-offset-background ring-2 ring-offset-2':
                  selectedTheme === 'dark',
                'hover:border-muted-foreground': selectedTheme !== 'dark',
              }"
            >
              <ColorModeThumbnailDark class="h-full w-full object-cover" />
            </div>
            <div
              class="mt-2 text-center text-sm font-medium"
              :class="{
                'text-primary': selectedTheme === 'dark',
                'text-muted-foreground/80': selectedTheme !== 'dark',
              }"
            >
              Dark
            </div>
          </label>

          <!-- System Mode -->
          <label class="cursor-pointer">
            <input
              v-model="selectedTheme"
              type="radio"
              value="system"
              class="sr-only"
              @change="updateTheme"
            />
            <div
              class="border-border aspect-64/54 w-full overflow-hidden rounded-xl border transition active:scale-98"
              :class="{
                'ring-primary ring-offset-background ring-2 ring-offset-2':
                  selectedTheme === 'system',
                'hover:border-muted-foreground': selectedTheme !== 'system',
              }"
            >
              <ColorModeThumbnailSystem class="h-full w-full object-cover" />
            </div>
            <div
              class="mt-2 text-center text-sm font-medium"
              :class="{
                'text-primary': selectedTheme === 'system',
                'text-muted-foreground/80': selectedTheme !== 'system',
              }"
            >
              System
            </div>
          </label>
        </div>
      </div>

      <!-- Loading/Success Feedback -->
      <div v-if="isUpdating" class="text-muted-foreground flex items-center gap-x-1.5 text-sm">
        <LoadingSpinner class="size-4" />
        <span>Saving preferences..</span>
      </div>

      <div
        v-if="lastUpdated"
        class="flex items-center gap-x-1.5 text-sm tracking-tight text-green-700 dark:text-green-500"
      >
        <Icon name="lucide:check" class="size-4" />
        <span>Preferences saved</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  ColorModeThumbnailDark,
  ColorModeThumbnailLight,
  ColorModeThumbnailSystem,
} from "#components";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("settingsAppearance");

const sanctumFetch = useSanctumClient();
const { user } = useSanctumAuth();
const colorMode = useColorMode();
const nuxtApp = useNuxtApp();

// Reactive state
const selectedTheme = ref("system");
const isUpdating = ref(false);
const lastUpdated = ref(false);

// Load current theme preference from user settings
const loadThemePreference = () => {
  if (user.value?.user_settings?.theme) {
    selectedTheme.value = user.value.user_settings.theme;
  } else {
    // If no user setting, use current color mode preference
    selectedTheme.value = colorMode.preference || "system";
  }
};

// Apply theme to color mode and update meta
const applyTheme = (theme) => {
  colorMode.preference = theme;
  // Update meta theme color to match new theme
  nextTick(() => {
    nuxtApp.$updateMetaThemeColor?.();
  });
};

// Update theme preference
const updateTheme = async () => {
  try {
    isUpdating.value = true;
    lastUpdated.value = false;

    // Get current user settings or initialize empty object
    const currentSettings = user.value?.user_settings || {};

    // Update theme in user settings
    const updatedSettings = {
      ...currentSettings,
      theme: selectedTheme.value,
    };

    // Save to backend
    const response = await sanctumFetch("/api/user/settings", {
      method: "PATCH",
      body: {
        settings: updatedSettings,
      },
    });

    // Apply theme immediately
    applyTheme(selectedTheme.value);

    // Update local user data if available
    if (user.value) {
      user.value.user_settings = updatedSettings;
    }

    // Show success feedback
    lastUpdated.value = true;
  } catch (error) {
    console.error("Failed to update theme:", error);
    toast.error("Failed to save theme preference. Please try again.");
  } finally {
    isUpdating.value = false;
  }
};

// Load theme preference when component mounts
onMounted(() => {
  loadThemePreference();
  applyTheme(selectedTheme.value);
});

// Watch for user changes and reload theme preference
watch(
  user,
  () => {
    loadThemePreference();
  },
  { deep: true }
);
</script>
