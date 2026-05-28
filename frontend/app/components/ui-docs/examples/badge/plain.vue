<script setup>
import { Badge } from "@/components/ui/badge";
</script>

<template>
  <Badge variant="success" plain>Active</Badge>
  <Badge variant="warning" plain>Pending</Badge>
  <Badge variant="destructive" plain>Failed</Badge>
  <Badge variant="info" plain with-icon>Synced</Badge>
</template>
