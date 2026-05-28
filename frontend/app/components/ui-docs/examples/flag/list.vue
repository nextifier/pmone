<script setup>
import { Flag } from "@/components/ui/flag";

const countries = [
  { code: "us", name: "United States" },
  { code: "gb", name: "United Kingdom" },
  { code: "jp", name: "Japan" },
  { code: "id", name: "Indonesia" },
  { code: "de", name: "Germany" },
];
</script>

<template>
  <ul class="flex flex-col gap-2 text-sm tracking-tight">
    <li v-for="c in countries" :key="c.code" class="flex items-center gap-2">
      <Flag :country="c.code" :country-name="c.name" />
      <span>{{ c.name }}</span>
    </li>
  </ul>
</template>
