<script setup>
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { Button } from "@/components/ui/button";

const sides = ["top", "right", "bottom", "left"];
</script>

<template>
  <TooltipProvider>
    <div class="flex flex-wrap items-center gap-3">
      <Tooltip v-for="side in sides" :key="side">
        <TooltipTrigger as-child>
          <Button variant="outline">{{ side }}</Button>
        </TooltipTrigger>
        <TooltipContent :side="side">{{ side }} tooltip</TooltipContent>
      </Tooltip>
    </div>
  </TooltipProvider>
</template>
