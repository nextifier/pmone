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

function validateUsername(value) {
  if (!value) {
    return "Username is required.";
  }
  if (value.length < 3) {
    return "Must be at least 3 characters.";
  }
  return true;
}

function onSubmit(values) {
  alert(JSON.stringify(values, null, 2));
}
</script>

<template>
  <Form :initial-values="{ username: '' }" @submit="onSubmit" class="w-full max-w-sm space-y-4">
    <FormField v-slot="{ componentField }" name="username" :rules="validateUsername">
      <FormItem>
        <FormLabel>Username</FormLabel>
        <FormControl>
          <Input v-bind="componentField" placeholder="shadcn" />
        </FormControl>
        <FormDescription>Public display name.</FormDescription>
        <FormMessage />
      </FormItem>
    </FormField>
    <Button type="submit">Submit</Button>
  </Form>
</template>
