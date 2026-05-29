<script setup>
import {
  Item,
  ItemContent,
  ItemDescription,
  ItemMedia,
  ItemTitle,
} from "@/components/ui/item";
</script>

<template>
  <div class="flex w-full max-w-md flex-col gap-3">
    <Item variant="default">
      <ItemMedia variant="icon">
        <Icon name="lucide:square" class="size-5" />
      </ItemMedia>
      <ItemContent>
        <ItemTitle>Default</ItemTitle>
        <ItemDescription>The standard row style.</ItemDescription>
      </ItemContent>
    </Item>
    <Item variant="outline">
      <ItemMedia variant="icon">
        <Icon name="lucide:square-dashed" class="size-5" />
      </ItemMedia>
      <ItemContent>
        <ItemTitle>Outline</ItemTitle>
        <ItemDescription>Bordered for emphasis.</ItemDescription>
      </ItemContent>
    </Item>
    <Item variant="muted">
      <ItemMedia variant="icon">
        <Icon name="lucide:square-asterisk" class="size-5" />
      </ItemMedia>
      <ItemContent>
        <ItemTitle>Muted</ItemTitle>
        <ItemDescription>Subtle filled background.</ItemDescription>
      </ItemContent>
    </Item>
  </div>
</template>
