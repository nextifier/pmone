<script setup>
import { ref } from "vue";
import { Input } from "@/components/ui/input";
import {
  Field,
  FieldLabel,
  FieldGroup,
  FieldLegend,
  FieldSet,
  FieldDescription,
} from "@/components/ui/field";

const firstName = ref("");
const lastName = ref("");
</script>

<template>
  <FieldSet class="max-w-md">
    <FieldLegend>Personal information</FieldLegend>
    <FieldDescription>Match the spelling on your government ID.</FieldDescription>
    <FieldGroup>
      <Field>
        <FieldLabel for="first">First name</FieldLabel>
        <Input id="first" v-model="firstName" />
      </Field>
      <Field>
        <FieldLabel for="last">Last name</FieldLabel>
        <Input id="last" v-model="lastName" />
      </Field>
    </FieldGroup>
  </FieldSet>
</template>
