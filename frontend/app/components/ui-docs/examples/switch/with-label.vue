<script setup>
import { ref } from "vue";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";

const wifi = ref(true);
</script>

<template>
  <div class="flex items-center gap-2">
    <Switch id="wifi" v-model="wifi" />
    <Label for="wifi">Wi-Fi</Label>
  </div>
</template>
