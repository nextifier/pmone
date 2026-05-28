<script setup>
import { ref } from "vue";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel, FieldDescription } from "@/components/ui/field";

const email = ref("");
</script>

<template>
  <Field class="max-w-xs">
    <FieldLabel for="email">Email</FieldLabel>
    <Input id="email" v-model="email" type="email" placeholder="you@example.com" />
    <FieldDescription>We will send a confirmation to this address.</FieldDescription>
  </Field>
</template>
