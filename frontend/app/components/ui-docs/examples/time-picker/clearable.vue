<script setup>
import { ref } from "vue";
import { Time } from "@internationalized/date";
import { TimePicker } from "@/components/ui/time-picker";

const time = ref(new Time(11, 45));
</script>

<template>
  <ClientOnly>
    <TimePicker v-model="time" clearable />
  </ClientOnly>
</template>
