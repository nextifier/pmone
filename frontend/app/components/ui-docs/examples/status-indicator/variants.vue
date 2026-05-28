<script setup>
import { StatusIndicator } from "@/components/ui/status-indicator";
</script>

<template>
  <div class="flex flex-col gap-2 text-sm tracking-tight">
    <StatusIndicator status="Available" />
    <StatusIndicator status="Coming soon" />
    <StatusIndicator status="Maintenance" />
    <StatusIndicator status="Sold out" />
    <StatusIndicator status="Available" :show-status-label="false" />
  </div>
</template>
