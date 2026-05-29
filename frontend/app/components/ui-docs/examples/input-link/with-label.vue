<script setup>
import { ref } from "vue";
import { InputLink } from "@/components/ui/input-link";
import { Label } from "@/components/ui/label";

const url = ref("");
</script>

<template>
  <div class="grid w-full max-w-sm items-center gap-1.5">
    <Label>Website</Label>
    <InputLink v-model="url" label="Website" />
  </div>
</template>
