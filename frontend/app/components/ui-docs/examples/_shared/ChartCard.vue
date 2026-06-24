<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const props = defineProps({
  title: {
    type: String,
    default: "",
  },
  description: {
    type: String,
    default: "",
  },
  // default = left-aligned header (bar/area); center = centered header (pie/radar/radial).
  variant: {
    type: String,
    default: "default",
  },
  // md (bar/area) or xs (pie/radar/radial).
  size: {
    type: String,
    default: "md",
  },
});

const isCenter = computed(() => props.variant === "center");
const cardClass = computed(() => (props.size === "xs" ? "w-full max-w-xs" : "w-full max-w-md"));
</script>

<template>
  <Card :class="cardClass">
    <CardHeader :class="isCenter ? 'items-center pb-0 text-center' : ''">
      <CardTitle :class="isCenter ? 'tracking-tight' : 'flex items-center justify-between gap-2 tracking-tight'">
        <span>{{ title }}</span>
        <slot name="trend" />
      </CardTitle>
      <CardDescription class="tracking-tight">{{ description }}</CardDescription>
    </CardHeader>
    <CardContent :class="isCenter ? 'flex-1 pb-0' : ''">
      <slot />
    </CardContent>
  </Card>
</template>
