<template>
  <Popover>
    <PopoverTrigger as-child>
      <button class="rounded-full">
        <AuthUserAvatar :user="user" class="size-8" :showIndicator="true" />
      </button>
    </PopoverTrigger>
    <PopoverContent class="flex w-56 flex-col gap-y-1 rounded-lg px-0 py-1" align="end">
      <NuxtLink to="/settings/profile" class="px-1.5 py-1">
        <AuthUserInfo :user="user" :showVerifyIcon="true" :showRoleIcon="true" />
      </NuxtLink>

      <span class="border-border my-0 h-px w-full border-t"></span>

      <PopoverClose v-for="(item, index) in items" :key="index" class="px-1">
        <NuxtLink
          :to="item.path"
          class="hover:bg-muted text-foreground flex w-full items-center gap-x-2 rounded-md px-2 py-1.5"
        >
          <Icon :name="item.iconName" class="size-4.5 shrink-0" />
          <span class="text-sm tracking-tight">{{ item.label }}</span>
        </NuxtLink>
      </PopoverClose>

      <span class="border-border my-0 h-px w-full border-t"></span>

      <PopoverClose class="px-1">
        <button
          @click="logout"
          class="hover:bg-muted text-foreground flex w-full items-center gap-x-2 rounded-md px-2 py-1.5"
        >
          <Icon name="hugeicons:logout-05" class="size-4 shrink-0" />
          <span class="text-sm tracking-tight">Log out</span>
        </button>
      </PopoverClose>
    </PopoverContent>
  </Popover>
</template>

<script setup>
import { PopoverClose } from "reka-ui";
const { user, logout } = useSanctumAuth();

const items = [
  {
    label: "Go to home page",
    path: "/",
    iconName: "hugeicons:home-01",
  },
  {
    label: "Dashboard",
    path: "/dashboard",
    iconName: "hugeicons:dashboard-circle",
  },
  {
    label: "Account",
    path: "/settings/profile",
    iconName: "hugeicons:user",
  },
];
</script>
