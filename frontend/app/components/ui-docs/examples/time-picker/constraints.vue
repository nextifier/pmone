<script setup>
import { Time } from "@internationalized/date";
import { TimePicker } from "@/components/ui/time-picker";
</script>

<template>
  <ClientOnly>
    <div class="flex flex-col gap-4 sm:flex-row sm:gap-6">
      <div class="flex flex-col gap-1.5">
        <span class="text-muted-foreground text-sm tracking-tight">disabled</span>
        <TimePicker :model-value="new Time(10, 30)" disabled />
      </div>
      <div class="flex flex-col gap-1.5">
        <span class="text-muted-foreground text-sm tracking-tight">readonly</span>
        <TimePicker :model-value="new Time(15, 45)" readonly />
      </div>
    </div>
  </ClientOnly>
</template>
