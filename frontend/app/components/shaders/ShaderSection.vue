<script setup>
import { AccordionItem, AccordionHeader, AccordionTrigger, AccordionContent } from "reka-ui";

/**
 * Flat collapsible section for the editor properties panel — a thin divider, a
 * compact uppercase header, and full-bleed content. No card chrome (no border
 * box, rounding, or nested padding), so the sidebar stays dense and clean.
 */
defineProps({
  value: { type: String, required: true },
  title: { type: String, required: true },
});
</script>

<template>
  <AccordionItem :value="value" class="border-border/50 border-b">
    <AccordionHeader class="flex">
      <AccordionTrigger
        class="text-muted-foreground hover:text-foreground flex flex-1 items-center justify-between py-2.5 text-xs font-medium tracking-tight uppercase outline-hidden transition-colors [&[data-state=open]>svg]:rotate-180"
      >
        {{ title }}
        <Icon name="hugeicons:arrow-down-01" class="size-3.5 shrink-0 transition-transform duration-200" />
      </AccordionTrigger>
    </AccordionHeader>
    <AccordionContent
      class="overflow-hidden data-[state=closed]:animate-accordion-up data-[state=open]:animate-accordion-down"
    >
      <div class="space-y-3 pb-3.5">
        <slot />
      </div>
    </AccordionContent>
  </AccordionItem>
</template>
