<script setup>
import { ref } from "vue";
import { InputPassword } from "@/components/ui/input-password";
import { Label } from "@/components/ui/label";

const value = ref("");
</script>

<template>
  <div class="grid w-full max-w-xs items-center gap-1.5">
    <Label for="password">Password</Label>
    <InputPassword id="password" v-model="value" placeholder="Password" />
  </div>
</template>
