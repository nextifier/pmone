<script setup>
import { ref } from "vue";
import { MultiSelect } from "@/components/ui/multi-select";

const selected = ref([{ value: "admin", label: "Admin", fixed: true }]);
const roles = [
  { value: "admin", label: "Admin", fixed: true },
  { value: "editor", label: "Editor" },
  { value: "viewer", label: "Viewer" },
  { value: "billing", label: "Billing" },
];
</script>

<template>
  <MultiSelect v-model="selected" :options="roles" placeholder="Pick roles" class="w-full max-w-sm" />
</template>
