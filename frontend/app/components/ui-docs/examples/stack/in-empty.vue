<script setup>
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Button } from "@/components/ui/button";
</script>

<template>
  <Empty class="border-border w-full max-w-md border">
    <EmptyHeader>
      <EmptyMedia variant="stacked">
        <Icon name="hugeicons:layers-01" class="size-7" />
      </EmptyMedia>
      <EmptyTitle>It's empty here</EmptyTitle>
      <EmptyDescription>You are all caught up. New messages will land here.</EmptyDescription>
    </EmptyHeader>
    <EmptyContent>
      <Button>Compose message</Button>
    </EmptyContent>
  </Empty>
</template>
