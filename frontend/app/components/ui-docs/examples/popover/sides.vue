<script setup>
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Button } from "@/components/ui/button";

const sides = ["top", "right", "bottom", "left"];
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <Popover v-for="side in sides" :key="side">
      <PopoverTrigger as-child>
        <Button variant="outline">{{ side }}</Button>
      </PopoverTrigger>
      <PopoverContent :side="side" class="w-48">
        <p class="text-sm tracking-tight">Opens on the {{ side }}.</p>
      </PopoverContent>
    </Popover>
  </div>
</template>
