<script setup>
import { ref } from "vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";

const accepted = ref(false);
</script>

<template>
  <div class="flex items-center gap-2">
    <Checkbox id="terms" v-model="accepted" />
    <Label for="terms">Accept terms and conditions</Label>
  </div>
</template>
