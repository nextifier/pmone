<script setup>
import { ref } from "vue";
import { Time } from "@internationalized/date";
import { TimePicker } from "@/components/ui/time-picker";

const time = ref(new Time(8, 0));
</script>

<template>
  <ClientOnly>
    <div class="flex flex-col gap-4 sm:flex-row sm:gap-6">
      <div class="flex flex-col gap-1.5">
        <span class="text-muted-foreground text-sm tracking-tight">step minute 15</span>
        <TimePicker v-model="time" :step="{ minute: 15 }" />
      </div>
      <div class="flex flex-col gap-1.5">
        <span class="text-muted-foreground text-sm tracking-tight">step hour 2, minute 30</span>
        <TimePicker v-model="time" :step="{ minute: 30, hour: 2 }" />
      </div>
    </div>
  </ClientOnly>
</template>
