<script setup>
import {
  HoverCard,
  HoverCardContent,
  HoverCardTrigger,
} from "@/components/ui/hover-card";
import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";

const user = { name: "Olivia Martin" };
</script>

<template>
  <HoverCard>
    <HoverCardTrigger as-child>
      <Button variant="link">Olivia Martin</Button>
    </HoverCardTrigger>
    <HoverCardContent class="w-72">
      <div class="flex gap-3">
        <Avatar :model="user" class="size-10" />
        <div class="space-y-1">
          <h4 class="text-sm font-semibold tracking-tight">Olivia Martin</h4>
          <p class="text-muted-foreground text-sm tracking-tight">
            Product designer at Acme Inc. Loves clean systems.
          </p>
          <p class="text-muted-foreground text-xs">Joined March 2024</p>
        </div>
      </div>
    </HoverCardContent>
  </HoverCard>
</template>
