<script setup>
import { Stack } from "@/components/ui/stack";
</script>

<template>
  <div class="flex flex-wrap items-center justify-center gap-6">
    <div class="flex flex-col items-center gap-1">
      <Stack size="2rem" class="text-muted-foreground">
        <Icon name="lucide:image" class="size-3.5" />
      </Stack>
      <span class="text-muted-foreground text-sm tracking-tight">2rem</span>
    </div>
    <div class="flex flex-col items-center gap-1">
      <Stack size="4rem" class="text-muted-foreground">
        <Icon name="lucide:image" class="size-7" />
      </Stack>
      <span class="text-muted-foreground text-sm tracking-tight">4rem</span>
    </div>
    <div class="flex flex-col items-center gap-1">
      <Stack size="3.5rem" width="5rem" aspect="4 / 3" class="text-muted-foreground">
        <Icon name="lucide:image" class="size-6" />
      </Stack>
      <span class="text-muted-foreground text-sm tracking-tight">5rem · 4/3</span>
    </div>
  </div>
</template>
