<script setup>
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
</script>

<template>
  <div class="flex w-full flex-col gap-4">
    <Tabs default-value="a" size="sm">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger value="a">Small</TabsTrigger>
        <TabsTrigger value="b">Tabs</TabsTrigger>
        <TabsTrigger value="c">Demo</TabsTrigger>
      </TabsList>
      <TabsContent value="a" class="mt-3 text-sm tracking-tight">Size sm (h-7).</TabsContent>
      <TabsContent value="b" />
      <TabsContent value="c" />
    </Tabs>

    <Tabs default-value="a" size="md">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger value="a">Medium</TabsTrigger>
        <TabsTrigger value="b">Tabs</TabsTrigger>
        <TabsTrigger value="c">Demo</TabsTrigger>
      </TabsList>
      <TabsContent value="a" class="mt-3 text-sm tracking-tight">Size md (h-8), the default.</TabsContent>
      <TabsContent value="b" />
      <TabsContent value="c" />
    </Tabs>

    <Tabs default-value="a" size="lg">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger value="a">Large</TabsTrigger>
        <TabsTrigger value="b">Tabs</TabsTrigger>
        <TabsTrigger value="c">Demo</TabsTrigger>
      </TabsList>
      <TabsContent value="a" class="mt-3 text-sm tracking-tight">Size lg (h-10).</TabsContent>
      <TabsContent value="b" />
      <TabsContent value="c" />
    </Tabs>
  </div>
</template>
