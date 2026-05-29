<script setup>
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";

const months = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December",
];
</script>

<template>
  <Tabs default-value="January" variant="underline" class="w-full max-w-md">
    <TabsList>
      <TabsIndicator />
      <TabsTrigger v-for="m in months" :key="m" :value="m">{{ m }}</TabsTrigger>
    </TabsList>
    <TabsContent v-for="m in months" :key="m" :value="m" class="mt-3 text-sm tracking-tight">
      {{ m }} panel. Too many tabs scroll horizontally.
    </TabsContent>
  </Tabs>
</template>
