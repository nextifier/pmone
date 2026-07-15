<template>
  <div
    v-if="user?.impersonating"
    class="text-foreground border-border bg-background sticky inset-x-0 top-0 z-50 flex items-center justify-center gap-x-2 border-b px-4 py-2 text-sm tracking-tight"
  >
    <Icon name="hugeicons:user-switch" class="size-4.5 shrink-0" />
    <span>
      Viewing as <span class="font-medium">{{ user.name }}</span>
    </span>
    <Button size="sm" variant="secondary" :disabled="pending" @click="leave">
      <Spinner v-if="pending" class="size-4" />
      <Icon v-else name="hugeicons:logout-05" class="size-4 shrink-0" />
      <span>Exit</span>
    </Button>
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
