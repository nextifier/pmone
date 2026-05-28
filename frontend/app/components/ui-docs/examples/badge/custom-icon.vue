<script setup>
import { Badge } from "@/components/ui/badge";
</script>

<template>
  <Badge variant="success" icon="hugeicons:checkmark-circle-02">Published</Badge>
  <Badge variant="warning" icon="hugeicons:clock-01">Scheduled</Badge>
  <Badge variant="destructive" icon="hugeicons:cancel-circle">Cancelled</Badge>
  <Badge variant="muted" icon="hugeicons:archive-01">Archived</Badge>
</template>
