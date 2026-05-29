<script setup>
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
</script>

<template>
  <Tabs default-value="account" variant="pill" class="w-full">
    <TabsList>
      <TabsIndicator />
      <TabsTrigger value="account">Account</TabsTrigger>
      <TabsTrigger value="password">Password</TabsTrigger>
      <TabsTrigger value="billing" disabled>Billing</TabsTrigger>
    </TabsList>
    <TabsContent value="account" class="mt-3 text-sm tracking-tight">Account panel.</TabsContent>
    <TabsContent value="password" class="mt-3 text-sm tracking-tight">Password panel.</TabsContent>
    <TabsContent value="billing" class="mt-3 text-sm tracking-tight">Billing panel.</TabsContent>
  </Tabs>
</template>
