<script setup>
import {
  HoverCard,
  HoverCardContent,
  HoverCardTrigger,
} from "@/components/ui/hover-card";
import { Button } from "@/components/ui/button";
</script>

<template>
  <HoverCard>
    <HoverCardTrigger as-child>
      <Button variant="link">@nextifier</Button>
    </HoverCardTrigger>
    <HoverCardContent class="w-72">
      <div class="space-y-2">
        <h4 class="text-sm font-semibold tracking-tight">@nextifier</h4>
        <p class="text-muted-foreground text-sm tracking-tight">
          Building UI libraries since the early reka-ui days.
        </p>
        <p class="text-muted-foreground text-xs">Joined March 2024</p>
      </div>
    </HoverCardContent>
  </HoverCard>
</template>
