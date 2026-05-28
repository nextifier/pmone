<script setup>
import { ref } from "vue";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel, FieldDescription } from "@/components/ui/field";

const email = ref("");
</script>

<template>
  <Field class="max-w-sm">
    <FieldLabel for="email">Email</FieldLabel>
    <Input id="email" v-model="email" type="email" placeholder="you@example.com" />
    <FieldDescription>We use this to send important account updates.</FieldDescription>
  </Field>
</template>
