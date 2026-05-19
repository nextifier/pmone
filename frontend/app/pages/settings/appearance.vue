<template>
  <div class="mx-auto max-w-sm space-y-6 pt-4 pb-16">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:colors" class="size-5 sm:size-6" />
      <h1 class="page-title">{{ $t("settings.appearance") }}</h1>
    </div>

    <div class="mt-8 space-y-6">
      <!-- Theme Selection -->
      <div class="space-y-4">
        <div>
          <h3 class="font-medium tracking-tight">{{ $t("settings.theme") }}</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            {{ $t("settings.chooseTheme") }}
          </p>
        </div>

        <RadioGroup
          v-model="selectedTheme"
          class="grid grid-cols-3 gap-3"
          @update:model-value="updateTheme"
        >
          <Label
            v-for="opt in themeOptions"
            :key="opt.value"
            :for="`theme-${opt.value}`"
            class="flex cursor-pointer flex-col items-center gap-y-2"
          >
            <RadioGroupItem :id="`theme-${opt.value}`" :value="opt.value" class="sr-only" />
            <div
              class="border-border aspect-64/54 w-full overflow-hidden rounded-xl border transition active:scale-98"
              :class="{
                'ring-primary ring-offset-background ring-2 ring-offset-2':
                  selectedTheme === opt.value,
                'hover:border-muted-foreground': selectedTheme !== opt.value,
              }"
            >
              <component :is="opt.thumbnail" class="h-full w-full object-cover" />
            </div>
            <span
              class="text-center text-sm font-medium"
              :class="{
                'text-primary': selectedTheme === opt.value,
                'text-muted-foreground/80': selectedTheme !== opt.value,
              }"
            >
              {{ opt.label }}
            </span>
          </Label>
        </RadioGroup>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  ColorModeThumbnailDark,
  ColorModeThumbnailLight,
  ColorModeThumbnailSystem,
} from "@/components/ui/color-mode-buttons";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
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

const themeOptions = computed(() => [
  { value: "light", label: t("settings.light"), thumbnail: ColorModeThumbnailLight },
  { value: "dark", label: t("settings.dark"), thumbnail: ColorModeThumbnailDark },
  { value: "system", label: t("settings.system"), thumbnail: ColorModeThumbnailSystem },
]);

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
