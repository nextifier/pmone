<script lang="ts" setup>
/**
 * TimeRangePicker — segmented 24-hour time range input.
 *
 * SSR caveat: when `hourCycle=12`, the AM/PM literal uses a narrow no-break
 * space ( ) from Intl formatter which can differ between Node ICU and
 * browser ICU, causing hydration mismatch. Wrap usage in <ClientOnly> when
 * hourCycle=12.
 *
 * Range validation: reka-ui auto-flags the field invalid when `start > end`
 * (no opt-out — wrap-around schedules are not supported by reka-ui's internal
 * validator). To allow custom availability checks, pass `is-time-unavailable`.
 */
import type { TimeRangeFieldRootEmits, TimeRangeFieldRootProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { TimeRangeFieldRoot, useForwardPropsEmits } from "reka-ui";
import { computed } from "vue";
import { cn } from "@/lib/utils";
import TimeRangePickerInput from "./TimeRangePickerInput.vue";

const props = withDefaults(
  defineProps<TimeRangeFieldRootProps & {
    class?: HTMLAttributes["class"];
    clearable?: boolean;
  }>(),
  { hourCycle: 24, locale: "en-US", clearable: false },
);

const emits = defineEmits<TimeRangeFieldRootEmits>();

const delegatedProps = reactiveOmit(props, "class", "clearable");
const forwarded = useForwardPropsEmits(delegatedProps, emits);

const hasValue = computed(
  () => props.modelValue?.start != null || props.modelValue?.end != null,
);

const showClear = computed(
  () => props.clearable && hasValue.value && !props.disabled && !props.readonly,
);

function clear() {
  emits("update:modelValue", { start: undefined, end: undefined });
}
</script>

<template>
  <TimeRangeFieldRoot
    v-slot="{ segments }"
    data-slot="time-range-picker"
    :class="
      cn(
        'border-border flex h-9 w-full min-w-0 items-center rounded-md border bg-transparent px-3 py-1 text-sm tracking-tight shadow-xs transition-[color,box-shadow]',
        'focus-within:border-ring focus-within:ring-ring focus-within:ring-[1px]',
        'data-[invalid]:border-destructive data-[invalid]:ring-destructive/20',
        'data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50',
        props.class,
      )
    "
    v-bind="forwarded"
  >
    <TimeRangePickerInput
      v-for="(item, idx) in segments.start"
      :key="`start-${item.part}-${idx}`"
      :part="item.part"
      type="start"
    >
      {{ item.value }}
    </TimeRangePickerInput>
    <span aria-hidden="true" class="text-muted-foreground px-1 tracking-tight">–</span>
    <TimeRangePickerInput
      v-for="(item, idx) in segments.end"
      :key="`end-${item.part}-${idx}`"
      :part="item.part"
      type="end"
    >
      {{ item.value }}
    </TimeRangePickerInput>
    <button
      v-if="showClear"
      type="button"
      aria-label="Clear time range"
      class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring ml-auto rounded-sm p-0.5 transition-colors focus-visible:ring-1 focus-visible:outline-none"
      @mousedown.prevent
      @click="clear"
    >
      <Icon name="lucide:x" class="size-3.5" aria-hidden="true" />
    </button>
  </TimeRangeFieldRoot>
</template>
