<script setup>
import { ref } from "vue";
import { Time } from "@internationalized/date";
import { TimePicker } from "@/components/ui/time-picker";

const time = ref(new Time(14, 0));
</script>

<template>
  <ClientOnly>
    <TimePicker v-model="time" :hour-cycle="24" />
  </ClientOnly>
</template>
