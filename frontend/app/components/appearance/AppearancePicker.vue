<script setup lang="ts">
import { computed } from "vue";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { DARK_ITEM, DARK_MENU } from "@/lib/appearance/customizer-classes";

interface PickerOption {
  value: string;
  label: string;
  swatch?: string;
  swatchDark?: string;
  family?: string;
  description?: string;
}

const props = withDefaults(
  defineProps<{
    label: string;
    modelValue: string;
    options: PickerOption[];
    /** How each option is previewed on the trigger. */
    variant?: "swatch" | "font" | "radius" | "plain";
    disabled?: boolean;
    /** Force full-width at every breakpoint (embedded/popover layouts). */
    fluid?: boolean;
  }>(),
  { variant: "plain", disabled: false, fluid: false },
);

// In the customizer the trigger is a fixed 144px chip (mobile → horizontal
// scroll) that becomes full-width on desktop. `fluid` forces full-width always.
const triggerClass = computed(() =>
  props.fluid
    ? "w-full rounded-lg px-2.5 py-2"
    : "w-36 shrink-0 rounded-xl p-3 md:w-full md:rounded-lg md:px-2.5 md:py-2",
);

const emit = defineEmits<{ (e: "update:modelValue", value: string): void }>();

// Mobile opens the dropdown UPWARD (the customizer sits at the bottom of the
// screen) and centered; desktop opens to the RIGHT, floating over the preview.
const isMobile = useIsMobile();

const current = computed(
  () => props.options.find(o => o.value === props.modelValue) ?? props.options[0],
);

const fontFamily = computed(() =>
  props.variant === "font" && current.value?.family && current.value.family !== "inherit"
    ? current.value.family
    : undefined,
);
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child :disabled="disabled">
      <button
        type="button"
        :disabled="disabled"
        :class="[
          triggerClass,
          'relative touch-manipulation text-left ring-1 ring-foreground/10 outline-none select-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-foreground/50 disabled:pointer-events-none disabled:opacity-50 data-[state=open]:bg-muted',
        ]"
      >
        <div class="flex min-w-0 flex-col justify-start pr-5">
          <span class="text-muted-foreground text-xs tracking-tight">{{ label }}</span>
          <span
            class="text-foreground truncate text-sm font-medium tracking-tight"
            :style="fontFamily ? { fontFamily } : undefined"
          >{{ current?.label }}</span>
        </div>

        <!-- Indicator, absolutely positioned (right-4 mobile / right-2.5 desktop) -->
        <span
          v-if="variant === 'swatch'"
          class="pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 rounded-full ring-1 ring-foreground/15 select-none md:right-2.5"
          :style="{ backgroundColor: current?.swatchDark || current?.swatch }"
        />
        <span
          v-else-if="variant === 'font'"
          class="text-foreground pointer-events-none absolute top-1/2 right-4 flex size-4 -translate-y-1/2 items-center justify-center text-base leading-none select-none md:right-2.5"
          :style="fontFamily ? { fontFamily } : undefined"
        >Aa</span>
        <span
          v-else-if="variant === 'radius'"
          class="text-foreground pointer-events-none absolute top-1/2 right-4 flex size-4 -translate-y-1/2 rotate-90 items-center justify-center select-none md:right-2.5"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path
              fill="none"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 20v-5C4 8.925 8.925 4 15 4h5"
            />
          </svg>
        </span>
        <Icon
          v-else
          name="lucide:chevrons-up-down"
          class="text-muted-foreground pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 md:right-2.5"
        />
      </button>
    </DropdownMenuTrigger>

    <DropdownMenuContent
      :side="isMobile ? 'top' : 'right'"
      :align="isMobile ? 'center' : 'start'"
      :side-offset="12"
      :class="[DARK_MENU, 'z-50 w-56 max-w-[calc(100vw-2rem)] min-w-52 [scrollbar-width:none]']"
    >
      <DropdownMenuRadioGroup
        :model-value="modelValue"
        @update:model-value="(v) => emit('update:modelValue', String(v))"
      >
        <DropdownMenuRadioItem
          v-for="o in options"
          :key="o.value"
          :value="o.value"
          :class="[DARK_ITEM, 'gap-2']"
        >
          <span class="flex min-w-0 flex-col gap-0.5">
            <span
              class="truncate"
              :style="variant === 'font' && o.family && o.family !== 'inherit' ? { fontFamily: o.family } : undefined"
            >{{ o.label }}</span>
            <span
              v-if="o.description"
              class="text-xs leading-snug font-normal tracking-tight whitespace-normal text-neutral-400"
            >{{ o.description }}</span>
          </span>
        </DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
