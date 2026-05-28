<script setup>
import { ref } from "vue";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel, FieldError } from "@/components/ui/field";

const value = ref("");
</script>

<template>
  <Field data-invalid="true" class="max-w-xs">
    <FieldLabel for="phone">Phone number</FieldLabel>
    <Input id="phone" v-model="value" aria-invalid="true" placeholder="+1 555 0100" />
    <FieldError>Phone number is required.</FieldError>
  </Field>
</template>
