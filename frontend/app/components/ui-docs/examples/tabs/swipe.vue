<script setup>
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
</script>

<template>
  <Tabs default-value="a" variant="pill" swipe class="w-full">
    <TabsList>
      <TabsIndicator />
      <TabsTrigger value="a">Tab A</TabsTrigger>
      <TabsTrigger value="b">Tab B</TabsTrigger>
      <TabsTrigger value="c">Tab C</TabsTrigger>
    </TabsList>
    <TabsContent value="a" class="mt-3 text-sm tracking-tight">
      On mobile, swipe left or right in this area to change tabs.
    </TabsContent>
    <TabsContent value="b" class="mt-3 text-sm tracking-tight">Second tab.</TabsContent>
    <TabsContent value="c" class="mt-3 text-sm tracking-tight">Third tab.</TabsContent>
  </Tabs>
</template>
