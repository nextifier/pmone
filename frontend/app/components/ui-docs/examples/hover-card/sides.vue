<script setup>
import {
  HoverCard,
  HoverCardContent,
  HoverCardTrigger,
} from "@/components/ui/hover-card";
import { Button } from "@/components/ui/button";

const sides = ["top", "right", "bottom", "left"];
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <HoverCard v-for="side in sides" :key="side">
      <HoverCardTrigger as-child>
        <Button variant="outline">{{ side }}</Button>
      </HoverCardTrigger>
      <HoverCardContent :side="side" class="w-48">
        <p class="text-sm tracking-tight">Opens on the {{ side }}.</p>
      </HoverCardContent>
    </HoverCard>
  </div>
</template>
