<script setup>
import { ref } from "vue";
import { MultiSelect } from "@/components/ui/multi-select";

const selected = ref([]);
const tags = [
  { value: "design", label: "Design" },
  { value: "eng", label: "Engineering" },
  { value: "marketing", label: "Marketing" },
  { value: "sales", label: "Sales" },
  { value: "support", label: "Support" },
];
</script>

<template>
  <MultiSelect v-model="selected" :options="tags" placeholder="Select tags" class="w-full max-w-sm" />
</template>
