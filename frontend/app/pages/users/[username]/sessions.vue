<template>
  <div class="flex flex-col gap-y-8">
    <!-- Sessions -->
    <section class="flex flex-col gap-y-3">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base font-semibold tracking-tight">Active sessions</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Devices currently signed in to this account.
          </p>
        </div>
        <DialogResponsive v-model:open="clearAllOpen">
          <template #trigger="{ open }">
            <Button v-if="sessions.length" variant="outline-destructive" size="sm" @click="open()">
              <Icon name="hugeicons:logout-03" class="size-4 shrink-0" />
              <span>Sign out everywhere</span>
            </Button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-foreground text-lg font-semibold tracking-tight">Sign out everywhere?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This revokes every session and API token for this user. They will need to sign in again.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  :disabled="clearing"
                  @click="clearAllOpen = false"
                >
                  Cancel
                </button>
                <button
                  class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="clearing"
                  @click="clearAll"
                >
                  <Spinner v-if="clearing" class="size-4 text-white" />
                  <span v-else>Sign out everywhere</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>
      </div>

      <div v-if="loading" class="text-muted-foreground text-sm tracking-tight">Loading sessions...</div>

      <Empty v-else-if="sessions.length === 0" class="border">
        <EmptyHeader>
          <EmptyMedia variant="icon"><Icon name="hugeicons:laptop" class="size-5" /></EmptyMedia>
          <EmptyTitle>No active sessions</EmptyTitle>
          <EmptyDescription>This user has no active browser sessions.</EmptyDescription>
        </EmptyHeader>
      </Empty>

      <ul v-else class="divide-y rounded-lg border">
        <li v-for="s in sessions" :key="s.id" class="flex items-center gap-x-3 px-4 py-3">
          <div class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
            <Icon :name="deviceIcon(s.device?.device_type)" class="size-4.5" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-x-1.5">
              <span class="truncate text-sm font-medium tracking-tight">{{ s.device?.label }}</span>
              <Badge v-if="s.is_current" variant="success">This device</Badge>
              <span v-else-if="s.is_online" class="size-2 shrink-0 rounded-full bg-green-500" />
            </div>
            <div class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-1.5 text-sm tracking-tight">
              <span>{{ s.ip_address || "Unknown IP" }}</span>
              <span>·</span>
              <span v-tippy="s.last_activity ? $dayjs(s.last_activity).format('MMMM D, YYYY [at] h:mm A') : ''">
                {{ s.last_activity_human || "-" }}
              </span>
            </div>
          </div>
          <Button
            v-if="!s.is_current"
            v-tippy="'Revoke session'"
            variant="ghost"
            size="iconSm"
            class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 shrink-0"
            @click="revokeSession(s)"
          >
            <Icon name="lucide:x" class="size-4" />
          </Button>
        </li>
      </ul>
    </section>

    <!-- API tokens -->
    <section class="flex flex-col gap-y-3">
      <div>
        <h2 class="text-base font-semibold tracking-tight">API tokens</h2>
        <p class="text-muted-foreground text-sm tracking-tight">
          Personal access tokens issued for this account.
        </p>
      </div>

      <div v-if="tokensLoading" class="text-muted-foreground text-sm tracking-tight">Loading tokens...</div>

      <Empty v-else-if="tokens.length === 0" class="border">
        <EmptyHeader>
          <EmptyMedia variant="icon"><Icon name="hugeicons:key-01" class="size-5" /></EmptyMedia>
          <EmptyTitle>No API tokens</EmptyTitle>
          <EmptyDescription>This user has no personal access tokens.</EmptyDescription>
        </EmptyHeader>
      </Empty>

      <ul v-else class="divide-y rounded-lg border">
        <li v-for="t in tokens" :key="t.id" class="flex items-center gap-x-3 px-4 py-3">
          <div class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
            <Icon name="hugeicons:key-01" class="size-4.5" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-x-1.5">
              <span class="truncate text-sm font-medium tracking-tight">{{ t.name }}</span>
              <Badge v-if="t.is_expired" variant="destructive">Expired</Badge>
            </div>
            <div class="text-muted-foreground mt-0.5 text-sm tracking-tight">
              <span v-if="t.last_used_at">Last used {{ t.last_used_human }}</span>
              <span v-else>Never used</span>
            </div>
          </div>
          <Button
            v-tippy="'Revoke token'"
            variant="ghost"
            size="iconSm"
            class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 shrink-0"
            @click="revokeToken(t)"
          >
            <Icon name="lucide:x" class="size-4" />
          </Button>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { DialogResponsive } from "@/components/ui/dialog-responsive";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle, EmptyDescription } from "@/components/ui/empty";
import { toast } from "vue-sonner";

const props = defineProps({
  user: { type: Object, required: true },
});

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();

usePageMeta(null, {
  title: computed(() => `${props.user?.name || "User"} · Sessions`),
});

const sessions = ref([]);
const tokens = ref([]);
const loading = ref(true);
const tokensLoading = ref(true);
const clearAllOpen = ref(false);
const clearing = ref(false);

function deviceIcon(type) {
  return {
    mobile: "hugeicons:smart-phone-01",
    tablet: "hugeicons:tablet-01",
    bot: "hugeicons:robotic",
    desktop: "hugeicons:laptop",
  }[type] || "hugeicons:laptop";
}

async function fetchSessions() {
  loading.value = true;
  try {
    const res = await client(`/api/users/${props.user.username}/sessions`);
    sessions.value = res.data || [];
  } catch (err) {
    console.error("Error loading sessions:", err);
  } finally {
    loading.value = false;
  }
}

async function fetchTokens() {
  tokensLoading.value = true;
  try {
    const res = await client(`/api/users/${props.user.username}/tokens`);
    tokens.value = res.data || [];
  } catch (err) {
    console.error("Error loading tokens:", err);
  } finally {
    tokensLoading.value = false;
  }
}

async function revokeSession(session) {
  const prev = sessions.value;
  sessions.value = sessions.value.filter((s) => s.id !== session.id);
  try {
    await client(`/api/users/${props.user.username}/sessions/${session.id}`, { method: "DELETE" });
    toast.success("Session revoked");
  } catch (err) {
    sessions.value = prev;
    toast.error("Failed to revoke session", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  }
}

async function revokeToken(token) {
  const prev = tokens.value;
  tokens.value = tokens.value.filter((t) => t.id !== token.id);
  try {
    await client(`/api/users/${props.user.username}/tokens/${token.id}`, { method: "DELETE" });
    toast.success("Token revoked");
  } catch (err) {
    tokens.value = prev;
    toast.error("Failed to revoke token", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  }
}

async function clearAll() {
  clearing.value = true;
  try {
    await client(`/api/users/${props.user.username}/sessions`, { method: "DELETE" });
    clearAllOpen.value = false;
    toast.success("Signed out everywhere");
    await Promise.all([fetchSessions(), fetchTokens()]);
  } catch (err) {
    toast.error("Failed to sign out everywhere", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    clearing.value = false;
  }
}

onMounted(() => {
  fetchSessions();
  fetchTokens();
});
</script>
