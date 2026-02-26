<template>
  <div class="mx-auto max-w-sm space-y-6 pt-4 pb-16">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:colors" class="size-5 sm:size-6" />
      <h1 class="page-title">{{ $t('settings.appearance') }}</h1>
    </div>

    <div class="mt-8 space-y-6">
      <!-- Theme Selection -->
      <div class="space-y-4">
        <div>
          <h3 class="font-semibold tracking-tight">{{ $t('settings.theme') }}</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            {{ $t('settings.chooseTheme') }}
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
              {{ $t('settings.light') }}
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
              {{ $t('settings.dark') }}
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
              {{ $t('settings.system') }}
            </div>
          </label>
        </div>
      </div>

      <!-- Loading/Success Feedback -->
      <!-- <div v-if="isSyncing" class="text-muted-foreground flex items-center gap-x-1.5 text-sm">
        <LoadingSpinner class="size-4" />
        <span>Saving preferences..</span>
      </div>

      <div
        v-if="lastUpdated"
        class="flex items-center gap-x-1.5 text-sm tracking-tight text-green-700 dark:text-green-500"
      >
        <Icon name="lucide:check" class="size-4" />
        <span>Preferences saved</span>
      </div> -->
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

usePageMeta(null, { title: "Appearance" });

const { t } = useI18n();

const { colorMode, setTheme, isSyncing, lastSyncedAt, syncError } = useThemeSync();

// Reactive state
const selectedTheme = ref("system");
const lastUpdated = ref(false);

// Load current theme preference from user settings
const loadThemePreference = () => {
  selectedTheme.value = colorMode.preference || "system";
};

// Update theme preference
const updateTheme = () => {
  // Use the centralized setTheme function with debounced sync
  setTheme(selectedTheme.value);

  // Show temporary success feedback
  lastUpdated.value = true;
  setTimeout(() => {
    lastUpdated.value = false;
  }, 2000);
};

// Watch for sync errors
watch(syncError, (error) => {
  if (error) {
    toast.error(error);
  }
});

// Load theme preference when component mounts
onMounted(() => {
  loadThemePreference();
});
</script>
