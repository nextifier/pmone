<script setup>
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";
</script>

<template>
  <Card class="w-full max-w-sm">
    <CardHeader>
      <CardTitle>Create project</CardTitle>
      <CardDescription>Deploy your new project in one click.</CardDescription>
    </CardHeader>
    <CardContent>
      <p class="text-muted-foreground text-sm tracking-tight">
        Pick a framework and a region. You can change either later from settings.
      </p>
    </CardContent>
  </Card>
</template>
