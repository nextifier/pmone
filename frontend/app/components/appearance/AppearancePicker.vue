<script setup lang="ts">
import { computed } from "vue";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

interface PickerOption {
  value: string;
  label: string;
  swatch?: string;
  family?: string;
  description?: string;
}

const props = withDefaults(
  defineProps<{
    label: string;
    modelValue: string;
    options: PickerOption[];
    /** How each option is previewed. */
    variant?: "swatch" | "font" | "plain";
  }>(),
  { variant: "plain" },
);

const emit = defineEmits<{ (e: "update:modelValue", value: string): void }>();

const current = computed(
  () => props.options.find(o => o.value === props.modelValue) ?? props.options[0],
);
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <button
        type="button"
        class="ring-border hover:bg-muted/60 focus-visible:ring-ring/50 relative flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left ring-1 outline-none transition-colors focus-visible:ring-2"
      >
        <span class="min-w-0 flex-1">
          <span class="text-muted-foreground block text-xs tracking-tight">{{ label }}</span>
          <span
            class="text-foreground block truncate text-sm font-medium tracking-tight"
            :style="variant === 'font' && current?.family ? { fontFamily: current.family } : undefined"
          >
            {{ current?.label }}
          </span>
        </span>
        <span
          v-if="variant === 'swatch'"
          class="border-border size-4 shrink-0 rounded-full border"
          :style="{ backgroundColor: current?.swatch }"
        />
        <span
          v-else-if="variant === 'font'"
          class="text-muted-foreground shrink-0 text-base leading-none"
          :style="current?.family && current.family !== 'inherit' ? { fontFamily: current.family } : undefined"
        >Aa</span>
        <Icon v-else name="lucide:chevrons-up-down" class="text-muted-foreground size-4 shrink-0" />
      </button>
    </DropdownMenuTrigger>

    <DropdownMenuContent
      align="start"
      class="max-h-[min(24rem,var(--reka-dropdown-menu-content-available-height))] w-(--reka-dropdown-menu-trigger-width) min-w-52 overflow-y-auto"
    >
      <DropdownMenuRadioGroup
        :model-value="modelValue"
        @update:model-value="(v) => emit('update:modelValue', String(v))"
      >
        <DropdownMenuRadioItem
          v-for="o in options"
          :key="o.value"
          :value="o.value"
          class="gap-2 pr-2 focus:bg-muted! focus:text-foreground! focus:**:text-foreground!"
        >
          <span
            v-if="variant === 'swatch'"
            class="border-border size-3.5 shrink-0 rounded-full border"
            :style="{ backgroundColor: o.swatch }"
          />
          <span class="flex min-w-0 flex-col gap-0.5">
            <span
              class="truncate"
              :style="variant === 'font' && o.family && o.family !== 'inherit' ? { fontFamily: o.family } : undefined"
            >{{ o.label }}</span>
            <span
              v-if="o.description"
              class="text-muted-foreground text-xs leading-snug font-normal tracking-tight whitespace-normal"
            >{{ o.description }}</span>
          </span>
        </DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
