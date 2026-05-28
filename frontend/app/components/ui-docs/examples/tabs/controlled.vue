<script setup>
import { ref } from "vue";
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";

const active = ref("account");
</script>

<template>
  <div class="flex w-full flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <Button size="sm" variant="outline" @click="active = 'account'">Go to account</Button>
      <Button size="sm" variant="outline" @click="active = 'password'">Go to password</Button>
      <Button size="sm" variant="outline" @click="active = 'notifications'">Go to notifications</Button>
      <span class="text-muted-foreground text-sm tracking-tight">Active: {{ active }}</span>
    </div>

    <Tabs v-model="active" variant="underline">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger value="account">Account</TabsTrigger>
        <TabsTrigger value="password">Password</TabsTrigger>
        <TabsTrigger value="notifications">Notifications</TabsTrigger>
      </TabsList>
      <TabsContent value="account" class="mt-3 text-sm tracking-tight">Account panel.</TabsContent>
      <TabsContent value="password" class="mt-3 text-sm tracking-tight">Password panel.</TabsContent>
      <TabsContent value="notifications" class="mt-3 text-sm tracking-tight">Notifications panel.</TabsContent>
    </Tabs>
  </div>
</template>
