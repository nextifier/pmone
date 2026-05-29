<script setup>
import { ref } from "vue";
import { LocationCombobox } from "@/components/ui/location-combobox";

const value = ref("ny");
const cities = [
  { value: "ny", label: "New York" },
  { value: "lon", label: "London" },
  { value: "tok", label: "Tokyo" },
  { value: "sg", label: "Singapore" },
  { value: "jkt", label: "Jakarta" },
];
</script>

<template>
  <LocationCombobox
    v-model="value"
    :options="cities"
    disabled
    placeholder="Select city"
    class="w-64"
  />
</template>
