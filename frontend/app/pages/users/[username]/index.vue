<template>
  <div class="flex flex-col gap-y-6">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="rounded-lg border p-4">
        <div class="text-muted-foreground flex items-center gap-x-1.5 text-sm tracking-tight">
          <Icon name="hugeicons:login-03" class="size-4 shrink-0" />
          <span>Last login</span>
        </div>
        <div class="mt-2 text-sm font-medium tracking-tight">
          <span v-if="security?.last_login_at" v-tippy="$dayjs(security.last_login_at).format('MMMM D, YYYY [at] h:mm A')">
            {{ $dayjs(security.last_login_at).fromNow() }}
          </span>
          <span v-else class="text-muted-foreground">Never</span>
        </div>
        <div v-if="security?.last_login_ip" class="text-muted-foreground mt-0.5 text-sm tracking-tight">
          {{ security.last_login_device?.label }} · {{ security.last_login_ip }}
        </div>
      </div>

      <div class="rounded-lg border p-4">
        <div class="text-muted-foreground flex items-center gap-x-1.5 text-sm tracking-tight">
          <Icon name="hugeicons:radar-01" class="size-4 shrink-0" />
          <span>Status</span>
        </div>
        <div class="mt-2 flex items-center gap-x-1.5 text-sm font-medium tracking-tight">
          <template v-if="user.is_online">
            <span class="size-2 rounded-full bg-green-500" />
            <span class="text-green-600 dark:text-green-500">Online</span>
          </template>
          <span v-else-if="user.last_seen" v-tippy="$dayjs(user.last_seen).format('MMMM D, YYYY [at] h:mm A')">
            Seen {{ $dayjs(user.last_seen).fromNow() }}
          </span>
          <span v-else class="text-muted-foreground">Never seen</span>
        </div>
      </div>

      <div class="rounded-lg border p-4">
        <div class="text-muted-foreground flex items-center gap-x-1.5 text-sm tracking-tight">
          <Icon name="hugeicons:shield-01" class="size-4 shrink-0" />
          <span>Two-factor</span>
        </div>
        <div class="mt-2 text-sm font-medium tracking-tight">
          <span v-if="security?.two_factor_confirmed" class="text-green-600 dark:text-green-500">Enabled</span>
          <span v-else class="text-muted-foreground">Disabled</span>
        </div>
      </div>

      <div class="rounded-lg border p-4">
        <div class="text-muted-foreground flex items-center gap-x-1.5 text-sm tracking-tight">
          <Icon name="hugeicons:laptop" class="size-4 shrink-0" />
          <span>Active sessions</span>
        </div>
        <div class="mt-2 text-sm font-medium tracking-tight">
          {{ security?.sessions_count ?? "-" }}
          <span v-if="security?.active_tokens_count" class="text-muted-foreground">
            · {{ security.active_tokens_count }} token(s)
          </span>
        </div>
      </div>
    </div>

    <div
      v-if="user.suspended_at"
      class="border-destructive/30 bg-destructive/5 flex items-start gap-x-2.5 rounded-lg border p-4"
    >
      <Icon name="hugeicons:alert-02" class="text-destructive mt-0.5 size-4 shrink-0" />
      <div class="text-sm tracking-tight">
        <span class="text-destructive font-medium">Suspended</span>
        <span v-if="user.suspension_reason" class="text-body"> · {{ user.suspension_reason }}</span>
      </div>
    </div>

    <UserNotes v-if="canManageNotes" :username="user.username" />
  </div>
</template>

<script setup>
import UserNotes from "@/components/user/UserNotes.vue";

const props = defineProps({
  user: { type: Object, required: true },
});

const { $dayjs } = useNuxtApp();
const { hasPermission } = usePermission();
const client = useSanctumClient();

usePageMeta(null, {
  title: computed(() => `${props.user?.name || "User"} · Overview`),
});

const canViewSecurity = computed(() => hasPermission("users.view_security"));
const canManageNotes = computed(() => hasPermission("users.manage_notes"));

const security = ref(null);

async function fetchSecurity() {
  if (!canViewSecurity.value) return;
  try {
    const res = await client(`/api/users/${props.user.username}/security`);
    security.value = res.data || null;
  } catch (err) {
    console.error("Error loading security overview:", err);
  }
}

onMounted(fetchSecurity);
</script>
