<script setup>
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

function validateEmail(value) {
  if (!value) {
    return "Email is required.";
  }
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value)) {
    return "Enter a valid email address.";
  }
  return true;
}

function onSubmit(values) {
  alert(JSON.stringify(values, null, 2));
}
</script>

<template>
  <Form :initial-values="{ email: '' }" @submit="onSubmit" class="w-full max-w-sm space-y-4">
    <FormField v-slot="{ componentField }" name="email" :rules="validateEmail">
      <FormItem>
        <FormLabel>Email</FormLabel>
        <FormControl>
          <Input v-bind="componentField" placeholder="jane@example.com" />
        </FormControl>
        <FormDescription>Submit empty to see the error message.</FormDescription>
        <FormMessage />
      </FormItem>
    </FormField>
    <Button type="submit">Submit</Button>
  </Form>
</template>
