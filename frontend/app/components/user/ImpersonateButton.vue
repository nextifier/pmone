<template>
  <Button
    v-if="canImpersonate"
    v-tippy="'View the app as this user'"
    variant="outline"
    size="sm"
    :disabled="pending"
    @click="impersonate"
  >
    <Spinner v-if="pending" class="size-4 shrink-0" />
    <Icon v-else name="hugeicons:user-switch" class="size-4 shrink-0" />
    <span>Impersonate</span>
  </Button>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { toast } from "vue-sonner";

const props = defineProps({
  target: { type: Object, required: true },
});

const { isMaster, user } = usePermission();
const { refreshIdentity } = useSanctumAuth();
const client = useSanctumClient();

const pending = ref(false);

// Master-only, in code (never a grantable permission). Cannot impersonate self
// or another master account.
const canImpersonate = computed(() => {
  if (!isMaster.value) return false;
  if (!props.target?.id || props.target.id === user.value?.id) return false;
  return !(props.target.roles || []).includes("master");
});

async function impersonate() {
  pending.value = true;
  try {
    await client(`/api/users/${props.target.username}/impersonate`, { method: "POST" });
    await refreshIdentity();
    // Full navigation so every composable re-reads the impersonated identity.
    window.location.assign("/dashboard");
  } catch (error) {
    toast.error("Failed to impersonate user", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
    pending.value = false;
  }
}
</script>
