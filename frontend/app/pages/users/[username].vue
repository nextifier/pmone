<template>
  <div class="mx-auto pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <NuxtLink
      to="/users"
      class="text-muted-foreground hover:text-foreground mb-4 inline-flex items-center gap-x-1.5 text-sm tracking-tight"
    >
      <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
      <span>Back to Users</span>
    </NuxtLink>

    <template v-if="initialLoading">
      <div class="flex items-center gap-x-4">
        <Skeleton class="size-14 rounded-full" />
        <div class="space-y-2">
          <Skeleton class="h-5 w-40" />
          <Skeleton class="h-4 w-28" />
        </div>
      </div>
    </template>

    <template v-else-if="error">
      <div class="flex flex-col items-center gap-y-4 py-20 text-center">
        <h3 class="text-lg font-semibold tracking-tighter">{{ error }}</h3>
        <NuxtLink
          to="/users"
          class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
          <span>Back to Users</span>
        </NuxtLink>
      </div>
    </template>

    <template v-else-if="user">
      <!-- Header -->
      <div class="flex flex-col gap-y-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-x-4">
          <div class="relative shrink-0">
            <Avatar :model="user" class="size-14 text-lg" rounded="rounded-full" />
            <span
              v-if="user.is_online"
              v-tippy="'Online'"
              class="border-background absolute right-0 bottom-0 size-3.5 rounded-full border-2 bg-green-500"
            />
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-x-1.5">
              <h1 class="truncate text-xl font-semibold tracking-tighter">{{ user.name }}</h1>
              <Icon
                v-if="user.email_verified_at"
                v-tippy="'Verified'"
                name="material-symbols:verified"
                class="text-info size-4.5 shrink-0"
              />
            </div>
            <div class="text-muted-foreground flex flex-wrap items-center gap-x-2 text-sm tracking-tight">
              <span class="truncate">{{ user.email }}</span>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
              <Badge v-for="role in user.roles || []" :key="role" variant="outline" class="capitalize">
                {{ role }}
              </Badge>
              <Badge v-if="user.suspended_at" variant="destructive">Suspended</Badge>
              <Badge v-else-if="user.status !== 'active'" variant="outline" class="capitalize">
                {{ user.status }}
              </Badge>
            </div>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
          <ImpersonateButton :target="user" />
          <a
            v-if="user.phone"
            v-tippy="'WhatsApp'"
            :href="whatsappLink"
            target="_blank"
            rel="noopener noreferrer"
            class="border-border hover:bg-muted text-success-foreground inline-flex size-9 items-center justify-center rounded-md border"
          >
            <Icon name="hugeicons:whatsapp" class="size-4" />
          </a>
          <NuxtLink
            v-tippy="'Edit profile'"
            :to="`/${user.username}/edit`"
            class="border-border hover:bg-muted inline-flex size-9 items-center justify-center rounded-md border"
          >
            <Icon name="lucide:pencil-line" class="size-4" />
          </NuxtLink>
        </div>
      </div>

      <div class="mt-6">
        <TabNav :tabs="userTabs" />
      </div>

      <div class="pt-6">
        <NuxtPage :user="user" @refresh="refreshUser" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { TabNav } from "@/components/ui/tab-nav";
import { Skeleton } from "@/components/ui/skeleton";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import ImpersonateButton from "@/components/user/ImpersonateButton.vue";

definePageMeta({
  layout: "app",
  middleware: ["sanctum:auth", "permission"],
  permissions: ["users.read"],
});

const route = useRoute();
const { hasPermission } = usePermission();

const {
  data: userResponse,
  pending: initialLoading,
  error: userError,
  refresh: refreshUser,
} = await useLazySanctumFetch(() => `/api/users/${route.params.username}`, {
  key: `admin-user-${route.params.username}`,
});

const user = computed(() => userResponse.value?.data || null);

const error = computed(() => {
  if (!userError.value) return null;
  const err = userError.value;
  if (err.statusCode === 404) return "User not found";
  if (err.statusCode === 403) return "You do not have permission to view this user";
  return err.message || "Failed to load user";
});

usePageMeta(null, {
  title: computed(() => user.value?.name || "User"),
});

const whatsappLink = computed(() => {
  if (!user.value?.phone) return null;
  return `https://wa.me/${user.value.phone.replace(/\D/g, "")}`;
});

const base = computed(() => `/users/${route.params.username}`);
const canViewSecurity = computed(() => hasPermission("users.view_security"));

const userTabs = computed(() => {
  const tabs = [
    { label: "Overview", icon: "hugeicons:dashboard-circle", to: base.value, exact: true },
  ];
  if (hasPermission("users.manage_sessions")) {
    tabs.push({ label: "Sessions & Devices", icon: "hugeicons:laptop", to: `${base.value}/sessions` });
  }
  if (canViewSecurity.value) {
    tabs.push({ label: "Login History", icon: "hugeicons:login-03", to: `${base.value}/login-history` });
    tabs.push({ label: "Security", icon: "hugeicons:shield-01", to: `${base.value}/security` });
  }
  return tabs;
});

provide("adminUser", user);
</script>
