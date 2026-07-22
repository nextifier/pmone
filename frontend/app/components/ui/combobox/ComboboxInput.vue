<script setup lang="ts">
import { cn } from "@/lib/utils";
import ComboboxClear from "./ComboboxClear.vue";
import ComboboxTrigger from "./ComboboxTrigger.vue";
import { InputGroup, InputGroupAddon, InputGroupButton } from "@/components/ui/input-group";
import { reactiveOmit } from "@vueuse/core";
import type { ComboboxInputEmits, ComboboxInputProps } from "reka-ui";
import { ComboboxInput, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

defineOptions({
  inheritAttrs: false,
});

const props = withDefaults(
  defineProps<
    ComboboxInputProps & {
      class?: HTMLAttributes["class"];
      /** Chevron button that opens the list. */
      showTrigger?: boolean;
      /** Button that clears the current value. Hides the chevron while shown. */
      showClear?: boolean;
    }
  >(),
  {
    showTrigger: true,
    showClear: false,
  }
);

const emits = defineEmits<ComboboxInputEmits>();

const delegatedProps = reactiveOmit(props, "class", "showTrigger", "showClear");

const forwarded = useForwardPropsEmits(delegatedProps, emits);

// `data-slot="input-group-control"` is what `cn-input-group` keys its border and focus
// ring off. Reka renders the native input itself (an `as-child` InputGroupInput would
// fight it over `v-model`), so the classes Input + InputGroupInput would have
// contributed are applied directly instead.
</script>

<template>
  <InputGroup :class="cn('cn-combobox-input w-auto', props.class)">
    <ComboboxInput
      data-slot="input-group-control"
      class="cn-input cn-input-group-input w-full min-w-0 flex-1 outline-none placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
      v-bind="{ ...forwarded, ...$attrs }"
    />
    <InputGroupAddon align="inline-end">
      <InputGroupButton
        v-if="showTrigger"
        variant="ghost"
        size="icon-xs"
        as-child
        :disabled="props.disabled"
        class="group-has-data-[slot=combobox-clear]/input-group:hidden"
      >
        <ComboboxTrigger />
      </InputGroupButton>
      <ComboboxClear v-if="showClear" :disabled="props.disabled" />
    </InputGroupAddon>
    <slot />
  </InputGroup>
</template>
