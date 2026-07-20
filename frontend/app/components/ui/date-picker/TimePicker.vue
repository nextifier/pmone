<script lang="ts" setup>
/**
 * TimePicker — segmented 24-hour time input.
 *
 * SSR caveat: when `hourCycle=12`, the AM/PM literal uses a narrow no-break
 * space ( ) from Intl formatter which can differ between Node ICU and
 * browser ICU, causing hydration mismatch. Wrap usage in <ClientOnly> when
 * hourCycle=12.
 */
import type { TimeFieldRootEmits, TimeFieldRootProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { TimeFieldRoot, useForwardPropsEmits } from "reka-ui";
import { computed } from "vue";
import { cn } from "@/lib/utils";
import TimePickerInput from "./TimePickerInput.vue";

const props = withDefaults(
  defineProps<TimeFieldRootProps & {
    class?: HTMLAttributes["class"];
    clearable?: boolean;
    showCaret?: boolean;
  }>(),
  { hourCycle: 24, locale: "en-US", clearable: false, showCaret: false },
);

const emits = defineEmits<TimeFieldRootEmits>();

const delegatedProps = reactiveOmit(props, "class", "clearable", "showCaret");
const forwarded = useForwardPropsEmits(delegatedProps, emits);

const showClear = computed(
  () => props.clearable && props.modelValue != null && !props.disabled && !props.readonly,
);

function clear() {
  emits("update:modelValue", undefined);
}
</script>

<template>
  <TimeFieldRoot
    v-slot="{ segments }"
    data-slot="time-picker"
    :class="
      cn(
        'flex h-9 w-full min-w-0 items-center gap-0.5 text-sm tracking-tight',
        'data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50',
        props.class,
      )
    "
    v-bind="forwarded"
  >
    <template v-for="(item, idx) in segments" :key="`${item.part}-${idx}`">
      <TimePickerInput
        v-if="item.part !== 'literal' || item.value.trim()"
        :part="item.part"
        :show-caret="showCaret"
      >
        {{ item.value }}
      </TimePickerInput>
    </template>
    <button
      v-if="showClear"
      type="button"
      aria-label="Clear time"
      class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring ml-auto rounded-sm p-0.5 transition-colors focus-visible:ring-1 focus-visible:outline-none"
      @mousedown.prevent
      @click="clear"
    >
      <Icon name="lucide:x" class="size-3.5" aria-hidden="true" />
    </button>
  </TimeFieldRoot>
</template>
