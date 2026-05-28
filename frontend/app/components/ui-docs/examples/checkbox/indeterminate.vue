<script setup>
import { ref } from "vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";

const parent = ref("indeterminate");
</script>

<template>
  <div class="flex items-center gap-2">
    <Checkbox id="select-all" v-model="parent" />
    <Label for="select-all">Select all (2 of 5)</Label>
  </div>
</template>
