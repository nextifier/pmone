<script setup>
import { ref } from "vue";
import { TableSwitch } from "@/components/ui/table-switch";

const enabled = ref(true);
</script>

<template>
  <TableSwitch v-model="enabled" item-id="row-1" status-key="published" />
</template>
