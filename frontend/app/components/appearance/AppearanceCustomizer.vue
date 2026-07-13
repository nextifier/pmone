<script setup lang="ts">
import { computed } from "vue";
import { toast } from "vue-sonner";
import AppearancePicker from "@/components/appearance/AppearancePicker.vue";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { DARK_ITEM as darkItem, DARK_MENU as darkMenu } from "@/lib/appearance/customizer-classes";
import {
  BASE_COLOR_OPTIONS,
  CHART_COLOR_OPTIONS,
  RADII,
  RADIUS_LOCKED_STYLES,
  THEME_OPTIONS,
} from "@/lib/appearance";
import { STYLES } from "@/lib/appearance/styles";
import { FONTS, FONT_HEADING_OPTIONS } from "@/lib/fonts";

// `embedded` renders a compact, page-themed, always-vertical panel for the
// header popover; the default renders the full forced-dark customizer card.
withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false });

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
  syncError,
} = useAppearance();

// Copy / Open / Shuffle actions + preset label (shared). The Reset + Open-Preset
// DIALOGS are mounted once in <AppearanceDialogs>, not here.
const { shuffle, copyPreset, copyLabel, openReset, openPreset } = useAppearanceActions();

// ---- Option lists -----------------------------------------------------------
const styleOptions = STYLES.map(s => ({ value: s.name, label: s.title, description: s.description }));
const radiusOptions = RADII.map(r => ({ value: r.name, label: r.title }));
const fontOptions = [
  { value: "default", label: "Default", family: "" },
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
const font = computed(() => appearance.value?.font ?? "default");
const fontHeading = computed(() => appearance.value?.fontHeading ?? "inherit");

// Some styles force square corners — mirror shadcn: show "None" + disable the picker.
const radiusLocked = computed(() =>
  (RADIUS_LOCKED_STYLES as readonly string[]).includes(currentStyle.value),
);
const radius = computed(() =>
  radiusLocked.value ? "none" : (appearance.value?.radius ?? "default"),
);

function toggleMode() {
  setColorMode(colorMode.value === "dark" ? "light" : "dark");
}

// Shared button chrome (adapts to the page theme via tokens). The forced-dark
// menu/item classes are shared via `@/lib/appearance/customizer-classes`.
const chromeBtn =
  "touch-manipulation ring-1 ring-foreground/10 transition-colors outline-none select-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-foreground/50";

watch(syncError, (error) => {
  if (error) {
    toast.error(error);
  }
});
</script>

<template>
  <!-- Forced-dark, translucent, blurred card (page) / compact page-themed panel
       (embedded). The card is always `dark` so it reads identically over a light
       OR dark preview — matches shadcn /create Customizer. -->
  <div
    :class="embedded
      ? 'flex w-full flex-col gap-3'
      : 'dark isolate z-10 flex max-h-full min-h-0 w-full flex-col self-start overflow-hidden rounded-2xl bg-card/90 text-sm text-card-foreground ring-1 ring-foreground/10 backdrop-blur-xl md:w-(--customizer-width)'"
  >
    <!-- Header (page, desktop only) — the Menu dropdown. -->
    <div
      v-if="!embedded"
      class="hidden items-center justify-between gap-2 border-b border-foreground/10 px-3 py-2.5 md:flex"
    >
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button
            type="button"
            :class="[chromeBtn, 'flex w-full items-center justify-between gap-2 rounded-lg px-1.75 py-1 data-[state=open]:bg-muted']"
          >
            <span class="text-foreground text-sm font-medium tracking-tight">Menu</span>
            <Icon name="lucide:menu" class="text-foreground size-5" />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="right" align="start" :align-offset="-8" :class="[darkMenu, 'w-52']">
          <DropdownMenuItem :class="darkItem" @click="openPreset">
            <Icon name="lucide:square-arrow-out-up-right" class="size-4" />
            Open Preset
          </DropdownMenuItem>
          <DropdownMenuItem :class="darkItem" @click="shuffle">
            <Icon name="lucide:dices" class="size-4" />
            Shuffle
          </DropdownMenuItem>
          <DropdownMenuItem :class="darkItem" @click="toggleMode">
            <Icon name="lucide:sun-moon" class="size-4" />
            Light / Dark
          </DropdownMenuItem>
          <DropdownMenuSeparator class="-mx-1.5 my-1.5 h-px bg-neutral-700" />
          <DropdownMenuItem :class="darkItem" @click="openReset">
            <Icon name="lucide:rotate-ccw" class="size-4" />
            Reset
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>

    <!-- Content — horizontal scroll (row) on mobile / vertical scroll (column) on
         desktop for the page; plain vertical stack for embedded. -->
    <div
      :class="embedded
        ? ''
        : 'min-h-0 flex-1 overflow-x-auto overflow-y-hidden px-3 py-3 md:overflow-x-hidden md:overflow-y-auto [scrollbar-width:none]'"
    >
      <div :class="embedded ? 'flex flex-col gap-3' : 'flex flex-row gap-2.5 md:flex-col md:gap-3'">
        <AppearancePicker
          label="Style"
          variant="plain"
          :fluid="embedded"
          :model-value="currentStyle"
          :options="styleOptions"
          @update:model-value="setStyle"
        />
        <div :class="embedded ? 'bg-border h-px' : 'hidden h-px bg-foreground/10 md:block'" />
        <AppearancePicker
          label="Base Color"
          variant="swatch"
          :fluid="embedded"
          :model-value="baseColor"
          :options="BASE_COLOR_OPTIONS"
          @update:model-value="setBaseColor"
        />
        <AppearancePicker
          label="Theme"
          variant="swatch"
          :fluid="embedded"
          :model-value="theme"
          :options="THEME_OPTIONS"
          @update:model-value="setTheme"
        />
        <AppearancePicker
          label="Chart Color"
          variant="swatch"
          :fluid="embedded"
          :model-value="chartColor"
          :options="CHART_COLOR_OPTIONS"
          @update:model-value="setChartColor"
        />
        <div :class="embedded ? 'bg-border h-px' : 'hidden h-px bg-foreground/10 md:block'" />
        <AppearancePicker
          label="Heading"
          variant="font"
          :fluid="embedded"
          :model-value="fontHeading"
          :options="headingOptions"
          @update:model-value="setFontHeading"
        />
        <AppearancePicker
          label="Font"
          variant="font"
          :fluid="embedded"
          :model-value="font"
          :options="fontOptions"
          @update:model-value="setFont"
        />
        <div :class="embedded ? 'bg-border h-px' : 'hidden h-px bg-foreground/10 md:block'" />
        <AppearancePicker
          label="Radius"
          variant="radius"
          :fluid="embedded"
          :model-value="radius"
          :options="radiusOptions"
          :disabled="radiusLocked"
          @update:model-value="setRadius"
        />
      </div>
    </div>

    <!-- Footer — buttons row (mobile) / column (desktop) for the page. -->
    <div
      :class="embedded
        ? 'flex min-w-0 items-center gap-2 pt-1'
        : 'flex min-w-0 items-center gap-2 border-t border-foreground/10 bg-muted/30 p-3 md:flex-col'"
    >
      <button
        type="button"
        v-tippy="copyLabel"
        :class="[chromeBtn, 'inline-flex h-9 min-w-0 flex-1 items-center justify-center rounded-lg px-2 text-sm font-medium', embedded ? '' : 'md:w-full md:flex-none']"
        @click="copyPreset"
      >
        <span class="block min-w-0 truncate">{{ copyLabel }}</span>
      </button>
      <button
        type="button"
        :class="[chromeBtn, 'inline-flex h-9 min-w-0 max-w-20 flex-1 items-center justify-center rounded-lg px-2 text-sm font-medium sm:max-w-none', embedded ? '' : 'md:w-full md:max-w-none md:flex-none']"
        @click="openPreset"
      >
        <span class="w-full truncate text-center">Open</span>
      </button>
      <button
        type="button"
        :class="[chromeBtn, 'inline-flex h-9 min-w-0 max-w-20 flex-1 items-center justify-center rounded-lg px-2 text-sm font-medium sm:max-w-none', embedded ? '' : 'md:w-full md:max-w-none md:flex-none']"
        @click="shuffle"
      >
        <span class="w-full truncate text-center">Shuffle</span>
      </button>

      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button
            type="button"
            aria-label="More actions"
            :class="[chromeBtn, 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg data-[state=open]:bg-muted', embedded ? '' : 'md:hidden']"
          >
            <Icon name="lucide:ellipsis" class="size-4" />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="top" align="end" :side-offset="8" :class="[darkMenu, 'w-48']">
          <DropdownMenuItem :class="darkItem" @click="toggleMode">
            <Icon name="lucide:sun-moon" class="size-4" />
            Light / Dark
          </DropdownMenuItem>
          <DropdownMenuSeparator class="-mx-1.5 my-1.5 h-px bg-neutral-700" />
          <DropdownMenuItem :class="darkItem" @click="openReset">
            <Icon name="lucide:rotate-ccw" class="size-4" />
            Reset
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </div>
</template>
