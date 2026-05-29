<script setup>
import { ref } from "vue";
import { Time } from "@internationalized/date";
import { TimePicker } from "@/components/ui/time-picker";

const hour = ref(new Time(9, 0));
const minute = ref(new Time(9, 30));
const second = ref(new Time(9, 30, 15));
</script>

<template>
  <ClientOnly>
    <div class="flex flex-col gap-4 sm:flex-row sm:gap-6">
      <div class="flex flex-col gap-1.5">
        <span class="text-muted-foreground text-sm tracking-tight">granularity="hour"</span>
        <TimePicker v-model="hour" granularity="hour" />
      </div>
      <div class="flex flex-col gap-1.5">
        <span class="text-muted-foreground text-sm tracking-tight">granularity="minute"</span>
        <TimePicker v-model="minute" />
      </div>
      <div class="flex flex-col gap-1.5">
        <span class="text-muted-foreground text-sm tracking-tight">granularity="second"</span>
        <TimePicker v-model="second" granularity="second" />
      </div>
    </div>
  </ClientOnly>
</template>
