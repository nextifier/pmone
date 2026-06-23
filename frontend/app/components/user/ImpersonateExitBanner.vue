<template>
  <div
    v-if="user?.impersonating"
    class="bg-warning/10 text-warning-foreground border-warning/20 sticky top-0 z-50 flex items-center justify-center gap-x-3 border-b px-4 py-2 text-sm tracking-tight"
  >
    <Icon name="hugeicons:user-switch" class="size-4 shrink-0" />
    <span>
      Viewing as <span class="font-medium">{{ user.name }}</span>
    </span>
    <button
      :disabled="pending"
      class="border-warning/30 hover:bg-warning/15 inline-flex items-center gap-x-1 rounded-md border px-2 py-0.5 text-sm font-medium tracking-tight active:scale-98 disabled:opacity-50"
      @click="leave"
    >
      <Spinner v-if="pending" class="size-3.5" />
      <Icon v-else name="lucide:log-out" class="size-3.5 shrink-0" />
      <span>Exit</span>
    </button>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const { user, refreshIdentity } = useSanctumAuth();
const client = useSanctumClient();
const pending = ref(false);

async function leave() {
  pending.value = true;
  try {
    await client("/api/users/impersonate/leave", { method: "POST" });
    await refreshIdentity();
    window.location.assign("/users");
  } catch (error) {
    toast.error("Failed to exit impersonation", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
    pending.value = false;
  }
}
</script>
