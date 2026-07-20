<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import { AccordionContent, type AccordionContentProps } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<
  AccordionContentProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});
</script>

<template>
  <AccordionContent
    data-slot="accordion-content"
    v-bind="delegatedProps"
    class="cn-accordion-content overflow-hidden"
  >
    <div :class="cn('cn-accordion-content-inner [&_a]:underline [&_a]:underline-offset-3 [&_a]:hover:text-foreground [&_p:not(:last-child)]:mb-4', props.class)">
      <slot />
    </div>
  </AccordionContent>
</template>
