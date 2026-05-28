<script setup>
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
</script>

<template>
  <div class="flex w-full flex-col gap-6">
    <Tabs default-value="account" variant="pill">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger value="account">Account</TabsTrigger>
        <TabsTrigger value="password">Password</TabsTrigger>
        <TabsTrigger value="notifications">Notifications</TabsTrigger>
      </TabsList>
      <TabsContent value="account" class="mt-3 text-sm tracking-tight">Pill variant.</TabsContent>
      <TabsContent value="password" class="mt-3 text-sm tracking-tight">Password panel.</TabsContent>
      <TabsContent value="notifications" class="mt-3 text-sm tracking-tight">Notifications panel.</TabsContent>
    </Tabs>

    <Tabs default-value="account" variant="segmented">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger value="account">Account</TabsTrigger>
        <TabsTrigger value="password">Password</TabsTrigger>
        <TabsTrigger value="notifications">Notifications</TabsTrigger>
      </TabsList>
      <TabsContent value="account" class="mt-3 text-sm tracking-tight">Segmented variant.</TabsContent>
      <TabsContent value="password" class="mt-3 text-sm tracking-tight">Password panel.</TabsContent>
      <TabsContent value="notifications" class="mt-3 text-sm tracking-tight">Notifications panel.</TabsContent>
    </Tabs>

    <Tabs default-value="account" variant="underline">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger value="account">Account</TabsTrigger>
        <TabsTrigger value="password">Password</TabsTrigger>
        <TabsTrigger value="notifications">Notifications</TabsTrigger>
      </TabsList>
      <TabsContent value="account" class="mt-3 text-sm tracking-tight">Underline variant.</TabsContent>
      <TabsContent value="password" class="mt-3 text-sm tracking-tight">Password panel.</TabsContent>
      <TabsContent value="notifications" class="mt-3 text-sm tracking-tight">Notifications panel.</TabsContent>
    </Tabs>
  </div>
</template>
