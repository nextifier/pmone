<script setup>
import {
  Item,
  ItemActions,
  ItemContent,
  ItemDescription,
  ItemMedia,
  ItemTitle,
} from "@/components/ui/item";
import { Button } from "@/components/ui/button";
</script>

<template>
  <Item class="w-full max-w-md">
    <ItemMedia variant="icon">
      <Icon name="lucide:bell" class="size-5" />
    </ItemMedia>
    <ItemContent>
      <ItemTitle>Push notifications</ItemTitle>
      <ItemDescription>Send notifications to device.</ItemDescription>
    </ItemContent>
    <ItemActions>
      <Button variant="outline" size="sm">Configure</Button>
    </ItemActions>
  </Item>
</template>
