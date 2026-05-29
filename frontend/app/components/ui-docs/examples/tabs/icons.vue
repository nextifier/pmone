<script setup>
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
</script>

<template>
  <Tabs default-value="overview" variant="segmented" class="w-full">
    <TabsList>
      <TabsIndicator />
      <TabsTrigger value="overview">
        <Icon name="lucide:layout-dashboard" class="size-4" />
        Overview
      </TabsTrigger>
      <TabsTrigger value="analytics">
        <Icon name="lucide:chart-line" class="size-4" />
        Analytics
      </TabsTrigger>
      <TabsTrigger value="reports">
        <Icon name="lucide:file-text" class="size-4" />
        Reports
      </TabsTrigger>
    </TabsList>
    <TabsContent value="overview" class="mt-3 text-sm tracking-tight">Overview panel.</TabsContent>
    <TabsContent value="analytics" class="mt-3 text-sm tracking-tight">Analytics panel.</TabsContent>
    <TabsContent value="reports" class="mt-3 text-sm tracking-tight">Reports panel.</TabsContent>
  </Tabs>
</template>
