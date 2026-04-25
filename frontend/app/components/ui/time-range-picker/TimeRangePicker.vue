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
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { TimeRangeFieldRootEmits, TimeRangeFieldRootProps } from "reka-ui";
import { TimeRangeFieldRoot, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { computed } from "vue";
import TimeRangePickerInput from "./TimeRangePickerInput.vue";

const props = withDefaults(
  defineProps<
    TimeRangeFieldRootProps & {
      class?: HTMLAttributes["class"];
      clearable?: boolean;
      showCaret?: boolean;
    }
  >(),
  { hourCycle: 24, locale: "en-US", clearable: false, showCaret: false }
);

const emits = defineEmits<TimeRangeFieldRootEmits>();

const delegatedProps = reactiveOmit(props, "class", "clearable", "showCaret");
const forwarded = useForwardPropsEmits(delegatedProps, emits);

const hasValue = computed(() => props.modelValue?.start != null || props.modelValue?.end != null);

const showClear = computed(
  () => props.clearable && hasValue.value && !props.disabled && !props.readonly
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
        'flex h-9 w-full min-w-0 items-center gap-0.5 text-sm tracking-tight',
        'data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50',
        props.class
      )
    "
    v-bind="forwarded"
  >
    <template
      v-for="(item, idx) in segments.start"
      :key="`start-${item.part}-${idx}`"
    >
      <TimeRangePickerInput
        v-if="item.part !== 'literal' || item.value.trim()"
        :part="item.part"
        type="start"
        :show-caret="showCaret"
      >
        {{ item.value }}
      </TimeRangePickerInput>
    </template>
    <span aria-hidden="true" class="grid h-full w-4 place-items-center">
      <span class="bg-muted-foreground/60 block h-px w-2" />
    </span>
    <template
      v-for="(item, idx) in segments.end"
      :key="`end-${item.part}-${idx}`"
    >
      <TimeRangePickerInput
        v-if="item.part !== 'literal' || item.value.trim()"
        :part="item.part"
        type="end"
        :show-caret="showCaret"
      >
        {{ item.value }}
      </TimeRangePickerInput>
    </template>
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
