<script setup>
import { ref } from "vue";
import { LocationCombobox } from "@/components/ui/location-combobox";

const value = ref("");
const countries = [
  { value: "us", label: "United States" },
  { value: "gb", label: "United Kingdom" },
  { value: "jp", label: "Japan" },
  { value: "id", label: "Indonesia" },
  { value: "de", label: "Germany" },
  { value: "fr", label: "France" },
  { value: "es", label: "Spain" },
];
</script>

<template>
  <LocationCombobox
    v-model="value"
    :options="countries"
    :pinned="['us', 'gb']"
    placeholder="Select country"
    class="w-64"
  />
</template>
