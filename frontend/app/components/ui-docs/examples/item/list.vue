<script setup>
import {
  Item,
  ItemContent,
  ItemDescription,
  ItemGroup,
  ItemMedia,
  ItemSeparator,
  ItemTitle,
} from "@/components/ui/item";

const items = [
  { icon: "lucide:user", title: "Profile", description: "Manage your personal info." },
  { icon: "lucide:bell", title: "Notifications", description: "Email and push preferences." },
  { icon: "lucide:lock", title: "Security", description: "Password and 2FA." },
];
</script>

<template>
  <ItemGroup class="w-full max-w-md">
    <template v-for="(item, index) in items" :key="item.title">
      <Item>
        <ItemMedia variant="icon">
          <Icon :name="item.icon" class="size-5" />
        </ItemMedia>
        <ItemContent>
          <ItemTitle>{{ item.title }}</ItemTitle>
          <ItemDescription>{{ item.description }}</ItemDescription>
        </ItemContent>
      </Item>
      <ItemSeparator v-if="index < items.length - 1" />
    </template>
  </ItemGroup>
</template>
