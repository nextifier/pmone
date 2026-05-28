<script setup>
import { ref } from "vue";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";

const active = ref("item-1");
</script>

<template>
  <div class="flex w-full max-w-md flex-col gap-3">
    <div class="flex items-center gap-2">
      <Button size="sm" variant="outline" @click="active = 'item-1'">Open 1</Button>
      <Button size="sm" variant="outline" @click="active = 'item-2'">Open 2</Button>
      <Button size="sm" variant="outline" @click="active = ''">Close</Button>
    </div>
    <Accordion v-model="active" type="single" collapsible>
      <AccordionItem value="item-1">
        <AccordionTrigger>Item one</AccordionTrigger>
        <AccordionContent>Controlled from the buttons above.</AccordionContent>
      </AccordionItem>
      <AccordionItem value="item-2">
        <AccordionTrigger>Item two</AccordionTrigger>
        <AccordionContent>Try toggling each.</AccordionContent>
      </AccordionItem>
    </Accordion>
  </div>
</template>
