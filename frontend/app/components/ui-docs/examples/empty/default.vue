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
      <EmptyMedia variant="icon">
        <Icon name="lucide:inbox" class="size-6" />
      </EmptyMedia>
      <EmptyTitle>No messages</EmptyTitle>
      <EmptyDescription>You are all caught up. New messages will land here.</EmptyDescription>
    </EmptyHeader>
    <EmptyContent>
      <Button>Compose message</Button>
    </EmptyContent>
  </Empty>
</template>
