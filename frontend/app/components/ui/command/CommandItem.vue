<script setup lang="ts">
import { cn } from "@/lib/utils";
import { Check } from "@lucide/vue";
import type { ComboboxItemEmits, ComboboxItemProps } from "reka-ui";
import { ComboboxItem, useForwardPropsEmits } from "reka-ui";
import { computed, type HTMLAttributes } from "vue";

const props = defineProps<ComboboxItemProps & { class?: HTMLAttributes["class"] }>();
const emits = defineEmits<ComboboxItemEmits>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ComboboxItem
    v-bind="forwarded"
    data-slot="command-item"
    :class="
      cn(
        // `cn-command-item` keys its highlight off `data-selected`, the attribute cmdk
        // sets in the React original. Reka marks the active option with
        // `data-highlighted`, so mirror the same three declarations here rather than
        // forking the shared stylesheet. Colours are identical in all 9 styles.
        // Same story for disabled: cmdk writes data-disabled=true, reka writes an
        // empty `data-disabled`, so the selector has to test presence instead.
        'cn-command-item group/command-item cursor-pointer data-highlighted:bg-muted data-highlighted:text-foreground data-highlighted:*:[svg]:text-foreground data-disabled:pointer-events-none data-disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
        props.class
      )
    "
  >
    <slot />

    <!-- Reka reports selection as `data-state="checked"` where cmdk uses
         `data-checked="true"`; the rest matches the React original. -->
    <Check
      class="cn-command-item-indicator ml-auto opacity-0 group-has-data-[slot=command-shortcut]/command-item:hidden group-data-[state=checked]/command-item:opacity-100"
    />
  </ComboboxItem>
</template>
