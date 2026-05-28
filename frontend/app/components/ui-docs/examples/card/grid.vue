<script setup>
import { Card, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";

const items = [
  { title: "Total revenue", value: "$45,231", description: "+20% from last month" },
  { title: "Subscriptions", value: "2,350", description: "+180 this week" },
  { title: "Sales", value: "12,234", description: "+19% from last month" },
  { title: "Active now", value: "573", description: "+201 since last hour" },
];
</script>

<template>
  <div class="grid w-full grid-cols-1 gap-x-2 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
    <Card v-for="item in items" :key="item.title">
      <CardHeader>
        <CardDescription>{{ item.title }}</CardDescription>
        <CardTitle class="text-3xl tracking-tighter">{{ item.value }}</CardTitle>
        <p class="text-muted-foreground text-sm tracking-tight">{{ item.description }}</p>
      </CardHeader>
    </Card>
  </div>
</template>
