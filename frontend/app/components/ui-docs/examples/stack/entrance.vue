<script setup>
import { ref } from "vue";
import { Stack } from "@/components/ui/stack";
import { Button } from "@/components/ui/button";

const replayKey = ref(0);
</script>

<template>
  <div class="flex flex-col items-center gap-4">
    <Stack :key="replayKey" class="text-muted-foreground">
      <Icon name="hugeicons:layers-01" class="size-7" />
    </Stack>
    <Button size="sm" variant="outline" @click="replayKey++">Replay entrance</Button>
  </div>
</template>
