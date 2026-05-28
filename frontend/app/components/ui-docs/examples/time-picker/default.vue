<script setup>
import { ref } from "vue";
import { Time } from "@internationalized/date";
import { TimePicker } from "@/components/ui/time-picker";

const time = ref(new Time(9, 30));
</script>

<template>
  <ClientOnly>
    <TimePicker v-model="time" />
  </ClientOnly>
</template>
