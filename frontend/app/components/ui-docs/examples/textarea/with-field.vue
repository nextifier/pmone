<script setup>
import { ref } from "vue";
import { Textarea } from "@/components/ui/textarea";
import { Field, FieldDescription, FieldLabel } from "@/components/ui/field";

const bio = ref("");
</script>

<template>
  <Field class="max-w-sm">
    <FieldLabel for="bio">Bio</FieldLabel>
    <Textarea id="bio" v-model="bio" :rows="4" />
    <FieldDescription>A short summary about you. Max 280 characters.</FieldDescription>
  </Field>
</template>
