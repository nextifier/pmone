<script setup>
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Button } from "@/components/ui/button";
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button variant="outline">Open</Button>
    </PopoverTrigger>
    <PopoverContent class="w-64">
      <p class="text-sm tracking-tight">Anchored floating panel.</p>
    </PopoverContent>
  </Popover>
</template>
