<script setup>
import { ref } from "vue";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel, FieldError } from "@/components/ui/field";

const value = ref("");
</script>

<template>
  <Field data-invalid="true" class="max-w-sm">
    <FieldLabel for="username">Username</FieldLabel>
    <Input id="username" v-model="value" aria-invalid="true" placeholder="username" />
    <FieldError>Username is required.</FieldError>
  </Field>
</template>
