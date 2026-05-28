<script setup>
import { Button } from "@/components/ui/button";
</script>

<template>
  <Button to="/dashboard">Internal link</Button>
  <Button to="https://example.com" variant="outline">External link</Button>
</template>
