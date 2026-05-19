<template>
  <div class="flex flex-col gap-y-4">
    <div class="space-y-1">
      <h2 class="page-title">Payment Gateways</h2>
      <p class="page-description">
        Manage payment provider credentials per project. Each project can have its own Xendit
        account so settlements stay separated.
      </p>
    </div>

    <div v-if="canCreate" class="flex items-center justify-end">
      <Button size="sm" @click="manager?.openCreateDialog()">
        <Icon name="hugeicons:add-01" class="size-4" />
        Add Gateway
        <KbdGroup>
          <Kbd>N</Kbd>
        </KbdGroup>
      </Button>
    </div>

    <ProjectPaymentGatewaysManager ref="manager" :project-username="route.params.username" />
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";

definePageMeta({
  middleware: ["permission"],
  permissions: ["payment_gateways.read"],
});

const props = defineProps({
  project: Object,
});

const route = useRoute();
const manager = ref(null);

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("payment_gateways.create"));

usePageMeta(null, {
  title: computed(() => `Payment Gateways · ${props.project?.name || ""}`),
});

defineShortcuts({
  n: {
    handler: () => {
      if (canCreate.value) manager.value?.openCreateDialog();
    },
  },
});
</script>
