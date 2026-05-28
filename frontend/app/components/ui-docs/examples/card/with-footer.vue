<script setup>
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
  CardFooter,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
</script>

<template>
  <Card class="w-full max-w-sm">
    <CardHeader>
      <CardTitle>Upgrade plan</CardTitle>
      <CardDescription>Unlock unlimited projects and priority support.</CardDescription>
    </CardHeader>
    <CardContent>
      <p class="text-sm tracking-tight">
        Pro is $19 per month. Cancel anytime from your billing settings.
      </p>
    </CardContent>
    <CardFooter class="flex gap-x-2">
      <Button variant="outline" class="flex-1">Compare plans</Button>
      <Button class="flex-1">Upgrade</Button>
    </CardFooter>
  </Card>
</template>
