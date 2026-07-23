<script setup lang="ts">
/**
 * ColorPicker — a swatch trigger opening a popover with a saturation/lightness
 * area, hue slider, optional alpha slider, and hex + HSL value fields. Ports
 * the official Reka UI color-picker example onto the project's popover/input
 * tokens.
 *
 * v-model is a hex string. With `alpha` enabled the emitted hex becomes
 * 8-digit ("#rrggbbaa") once alpha drops below 1; leave it off when the
 * consumer validates against a strict 6-digit pattern.
 *
 * State is kept as a reka `Color` object (not the hex string) so the hue and
 * saturation channels survive positions where hex alone would lose them (pure
 * black, pure white, zero saturation).
 */
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import {
  ColorAreaArea,
  ColorAreaRoot,
  ColorAreaThumb,
  ColorFieldInput,
  ColorFieldRoot,
  ColorSliderRoot,
  ColorSliderThumb,
  ColorSliderTrack,
  ColorSwatch,
  colorToString,
  isValidColor,
  normalizeColor,
  type Color,
} from "reka-ui";
import type { HTMLAttributes } from "vue";
import { computed, ref, watch } from "vue";

const props = withDefaults(
  defineProps<{
    /** Applied to the trigger button — PopoverRoot renders no element of its own. */
    class?: HTMLAttributes["class"];
    modelValue?: string | null;
    /** Adds an alpha slider; the emitted hex turns 8-digit while alpha < 1. */
    alpha?: boolean;
    disabled?: boolean;
    id?: string;
    /** Trigger label shown while no color has been picked. */
    placeholder?: string;
    align?: "start" | "center" | "end";
  }>(),
  {
    modelValue: "",
    alpha: false,
    disabled: false,
    placeholder: "Pick a color",
    align: "start",
  }
);

const emit = defineEmits<{ "update:modelValue": [value: string] }>();
const isOpen = defineModel<boolean>("open", { default: false });

const DEFAULT_COLOR = "#3b82f6";

function parseHex(value: string | null | undefined): Color | null {
  return value && isValidColor(value) ? normalizeColor(value) : null;
}

function toHex(value: Color): string {
  return colorToString(props.alpha ? value : { ...value, alpha: 1 }, "hex").toLowerCase();
}

const color = ref<Color>(parseHex(props.modelValue) ?? normalizeColor(DEFAULT_COLOR));

watch(
  () => props.modelValue,
  (value) => {
    const parsed = parseHex(value);
    if (parsed && toHex(parsed) !== toHex(color.value)) {
      color.value = parsed;
    }
  }
);

function handleColorUpdate(value: Color) {
  color.value = value;
  emit("update:modelValue", toHex(value));
}

function handleHexUpdate(value: string) {
  if (isValidColor(value)) {
    handleColorUpdate(normalizeColor(value));
  }
}

/**
 * The trigger swatch previews the working color even before anything is
 * picked, so the button and the popover never disagree; the label is what
 * distinguishes "not answered yet" from a real value.
 */
const currentHex = computed(() => toHex(color.value));
const selectedHex = computed(() => {
  const parsed = parseHex(props.modelValue);
  return parsed ? toHex(parsed) : "";
});

const HSL_CHANNELS = ["hue", "saturation", "lightness"] as const;

const thumbClass =
  "block size-4 cursor-pointer rounded-full border-2 border-white bg-white shadow-md ring-1 ring-black/20 transition-transform outline-none hover:scale-110 focus-visible:ring-2 focus-visible:ring-black/40";

const fieldClass =
  "cn-input h-8 w-full min-w-0 px-2 text-center text-xs tracking-tight outline-none sm:text-sm";
</script>

<template>
  <Popover v-model:open="isOpen">
    <PopoverTrigger as-child>
      <button
        :id="id"
        type="button"
        :disabled="disabled"
        :class="
          cn(
            'cn-input hover:bg-muted/50 data-[state=open]:bg-muted/50 inline-flex min-w-36 cursor-pointer items-center gap-x-2 text-sm tracking-tight disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
            props.class
          )
        "
      >
        <ColorSwatch :color="currentHex" class="swatch border-border/60 size-5 shrink-0 rounded-sm border" />
        <span v-if="selectedHex" class="font-mono">{{ selectedHex }}</span>
        <span v-else class="text-muted-foreground">{{ placeholder }}</span>
      </button>
    </PopoverTrigger>

    <PopoverContent
      data-slot="color-picker-content"
      class="w-70 p-4"
      :align="align"
      :collision-padding="8"
    >
      <div class="flex flex-col gap-4">
        <div class="flex items-center gap-x-3">
          <ColorSwatch :color="currentHex" class="swatch border-border/60 size-8 shrink-0 rounded-md border" />
          <div class="flex min-w-0 flex-col">
            <span class="text-sm font-medium tracking-tight">Color</span>
            <code class="text-muted-foreground font-mono text-xs">{{ currentHex }}</code>
          </div>
        </div>

        <ColorAreaRoot
          v-slot="{ style }"
          :model-value="color"
          color-space="hsl"
          x-channel="saturation"
          y-channel="lightness"
          class="relative"
          @update:color="handleColorUpdate"
        >
          <ColorAreaArea
            :style="style"
            class="relative h-35 w-full cursor-crosshair touch-none overflow-hidden rounded-md outline-none"
          >
            <ColorAreaThumb :class="thumbClass" />
          </ColorAreaArea>
        </ColorAreaRoot>

        <div class="flex flex-col gap-y-2">
          <span class="text-muted-foreground text-xs font-medium tracking-tight">Hue</span>
          <ColorSliderRoot
            :model-value="color"
            channel="hue"
            color-space="hsl"
            class="relative flex h-4 w-full touch-none items-center select-none"
            @update:color="handleColorUpdate"
          >
            <ColorSliderTrack class="relative h-2 flex-1 rounded-full" />
            <ColorSliderThumb :class="thumbClass" />
          </ColorSliderRoot>
        </div>

        <div v-if="alpha" class="flex flex-col gap-y-2">
          <span class="text-muted-foreground text-xs font-medium tracking-tight">Alpha</span>
          <ColorSliderRoot
            :model-value="color"
            channel="alpha"
            color-space="hsl"
            class="relative flex h-4 w-full touch-none items-center select-none"
            @update:color="handleColorUpdate"
          >
            <ColorSliderTrack class="relative h-2 flex-1 rounded-full" />
            <ColorSliderThumb :class="thumbClass" />
          </ColorSliderRoot>
        </div>

        <div class="flex flex-col gap-y-2">
          <span class="text-muted-foreground text-xs font-medium tracking-tight">Values</span>
          <div class="flex gap-x-2">
            <ColorFieldRoot
              :model-value="currentHex"
              class="flex-[2]"
              @update:model-value="handleHexUpdate"
            >
              <ColorFieldInput :class="cn(fieldClass, 'text-left font-mono')" placeholder="#000000" />
            </ColorFieldRoot>
            <ColorFieldRoot
              v-for="channel in HSL_CHANNELS"
              :key="channel"
              :model-value="color"
              :channel="channel"
              color-space="hsl"
              class="flex-1"
              @update:color="handleColorUpdate"
            >
              <ColorFieldInput
                :class="fieldClass"
                :placeholder="channel.charAt(0).toUpperCase()"
                :aria-label="channel"
              />
            </ColorFieldRoot>
          </div>
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>

<style scoped>
/* The color rides as the top background layer over a checker, so a
   translucent value shows the checker through it while an opaque one hides it
   completely — no wrapper element, hence no checker bleeding past the border.
   Same layering reka uses for the alpha slider track. */
.swatch {
  background-image:
    linear-gradient(
      var(--reka-color-swatch-color, transparent),
      var(--reka-color-swatch-color, transparent)
    ),
    linear-gradient(45deg, var(--color-muted-foreground) 25%, transparent 25%),
    linear-gradient(-45deg, var(--color-muted-foreground) 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, var(--color-muted-foreground) 75%),
    linear-gradient(-45deg, transparent 75%, var(--color-muted-foreground) 75%);
  background-size:
    100% 100%,
    6px 6px,
    6px 6px,
    6px 6px,
    6px 6px;
  background-position:
    0 0,
    0 0,
    0 3px,
    3px -3px,
    -3px 0;
  background-clip: padding-box;
}
</style>
