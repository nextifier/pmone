<script setup lang="ts">
import { computed } from "vue";
import { toast } from "vue-sonner";
import AppearancePicker from "@/components/appearance/AppearancePicker.vue";
import { Button } from "@/components/ui/button";
import {
  BASE_COLOR_OPTIONS,
  CHART_COLOR_OPTIONS,
  RADII,
  THEME_OPTIONS,
} from "@/lib/appearance";
import { STYLES } from "@/lib/appearance/styles";
import { FONTS, FONT_HEADING_OPTIONS } from "@/lib/fonts";

withDefaults(defineProps<{ showColorMode?: boolean }>(), { showColorMode: true });

const {
  colorMode,
  setColorMode,
  style: currentStyle,
  appearance,
  setStyle,
  setBaseColor,
  setTheme,
  setChartColor,
  setRadius,
  setFont,
  setFontHeading,
  reset,
  syncError,
} = useAppearance();

// ---- Option lists -----------------------------------------------------------
const styleOptions = STYLES.map(s => ({ value: s.name, label: s.title, description: s.description }));
const radiusOptions = RADII.map(r => ({ value: r.name, label: r.title }));
const fontOptions = [
  { value: "default", label: "Default (MinusOne)", family: "" },
  ...FONTS.map(f => ({ value: f.value, label: f.name, family: f.fontFamily })),
];
const headingOptions = FONT_HEADING_OPTIONS.map(f => ({
  value: f.value,
  label: f.value === "inherit" ? "Inherit (body font)" : f.name,
  family: f.fontFamily,
}));

// ---- Current values (fall back to defaults when not customized) -------------
const baseColor = computed(() => appearance.value?.baseColor ?? "neutral");
const theme = computed(() => appearance.value?.theme ?? "neutral");
const chartColor = computed(() => appearance.value?.chartColor ?? "neutral");
const radius = computed(() => appearance.value?.radius ?? "default");
const font = computed(() => appearance.value?.font ?? "default");
const fontHeading = computed(() => appearance.value?.fontHeading ?? "inherit");

const colorModes = [
  { value: "light", label: "Light", icon: "lucide:sun" },
  { value: "dark", label: "Dark", icon: "lucide:moon" },
  { value: "system", label: "System", icon: "lucide:monitor" },
];

watch(syncError, (error) => {
  if (error) {
    toast.error(error);
  }
});
</script>

<template>
  <div class="space-y-3">
    <!-- Color mode (optional — hidden in the header where ColorModeToggle sits) -->
    <div v-if="showColorMode" class="space-y-1.5">
      <span class="text-muted-foreground px-1 text-xs tracking-tight">Color mode</span>
      <div class="bg-muted/60 ring-border grid grid-cols-3 gap-1 rounded-xl p-1 ring-1">
        <button
          v-for="m in colorModes"
          :key="m.value"
          type="button"
          :aria-pressed="colorMode.preference === m.value"
          class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium tracking-tight transition-colors"
          :class="colorMode.preference === m.value
            ? 'bg-background text-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground'"
          @click="setColorMode(m.value)"
        >
          <Icon :name="m.icon" class="size-4" />
          <span class="hidden sm:inline">{{ m.label }}</span>
        </button>
      </div>
    </div>

    <AppearancePicker
      label="Style"
      variant="plain"
      :model-value="currentStyle"
      :options="styleOptions"
      @update:model-value="setStyle"
    />
    <AppearancePicker
      label="Base Color"
      variant="swatch"
      :model-value="baseColor"
      :options="BASE_COLOR_OPTIONS"
      @update:model-value="setBaseColor"
    />
    <AppearancePicker
      label="Theme"
      variant="swatch"
      :model-value="theme"
      :options="THEME_OPTIONS"
      @update:model-value="setTheme"
    />
    <AppearancePicker
      label="Chart Color"
      variant="swatch"
      :model-value="chartColor"
      :options="CHART_COLOR_OPTIONS"
      @update:model-value="setChartColor"
    />
    <AppearancePicker
      label="Heading"
      variant="font"
      :model-value="fontHeading"
      :options="headingOptions"
      @update:model-value="setFontHeading"
    />
    <AppearancePicker
      label="Font"
      variant="font"
      :model-value="font"
      :options="fontOptions"
      @update:model-value="setFont"
    />
    <AppearancePicker
      label="Radius"
      variant="plain"
      :model-value="radius"
      :options="radiusOptions"
      @update:model-value="setRadius"
    />

    <div class="pt-1">
      <Button variant="ghost" size="sm" class="text-muted-foreground w-full" @click="reset">
        <Icon name="lucide:rotate-ccw" class="size-3.5" />
        Reset to default
      </Button>
    </div>
  </div>
</template>
