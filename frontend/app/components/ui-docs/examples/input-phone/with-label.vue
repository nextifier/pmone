<script setup>
import { ref } from "vue";
import { InputPhone } from "@/components/ui/input-phone";
import { Label } from "@/components/ui/label";

const phone = ref("");
</script>

<template>
  <div class="grid w-full max-w-xs items-center gap-1.5">
    <Label>Phone number</Label>
    <InputPhone v-model="phone" />
  </div>
</template>
