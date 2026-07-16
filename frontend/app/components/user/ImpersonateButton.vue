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

const props = defineProps({
  target: { type: Object, required: true },
});

const {
  canImpersonate: canImpersonateTarget,
  impersonate: startImpersonation,
  pending,
} = useImpersonate();

const canImpersonate = computed(() => canImpersonateTarget(props.target));

const impersonate = () => startImpersonation(props.target);
</script>
