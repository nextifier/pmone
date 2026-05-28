<script setup>
import { ref } from "vue";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel } from "@/components/ui/field";

const code = ref("");
</script>

<template>
  <Field orientation="horizontal" class="max-w-md">
    <FieldLabel for="code">Promo code</FieldLabel>
    <Input id="code" v-model="code" placeholder="WELCOME10" />
  </Field>
</template>
