<script setup>
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

function onSubmit(values) {
  alert(JSON.stringify(values, null, 2));
}
</script>

<template>
  <Form :initial-values="{ firstName: '', lastName: '', email: '' }" @submit="onSubmit" class="w-full max-w-sm space-y-4">
    <FormField v-slot="{ componentField }" name="firstName">
      <FormItem>
        <FormLabel>First name</FormLabel>
        <FormControl>
          <Input v-bind="componentField" placeholder="Jane" />
        </FormControl>
        <FormMessage />
      </FormItem>
    </FormField>
    <FormField v-slot="{ componentField }" name="lastName">
      <FormItem>
        <FormLabel>Last name</FormLabel>
        <FormControl>
          <Input v-bind="componentField" placeholder="Doe" />
        </FormControl>
        <FormMessage />
      </FormItem>
    </FormField>
    <FormField v-slot="{ componentField }" name="email">
      <FormItem>
        <FormLabel>Email</FormLabel>
        <FormControl>
          <Input v-bind="componentField" type="email" placeholder="jane@example.com" />
        </FormControl>
        <FormMessage />
      </FormItem>
    </FormField>
    <Button type="submit">Submit</Button>
  </Form>
</template>
