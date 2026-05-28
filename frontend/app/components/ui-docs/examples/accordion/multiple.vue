<script setup>
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
</script>

<template>
  <Accordion type="multiple" class="w-full max-w-md">
    <AccordionItem value="item-1">
      <AccordionTrigger>Section one</AccordionTrigger>
      <AccordionContent>Opens and closes independently.</AccordionContent>
    </AccordionItem>
    <AccordionItem value="item-2">
      <AccordionTrigger>Section two</AccordionTrigger>
      <AccordionContent>You can keep both panels open.</AccordionContent>
    </AccordionItem>
  </Accordion>
</template>
