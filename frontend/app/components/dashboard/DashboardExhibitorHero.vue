<template>
  <div
    :class="[
      'relative overflow-hidden rounded-xl border p-5 sm:p-6',
      allDone ? 'border-success/30 bg-success/5' : 'border-border bg-card',
    ]"
  >
    <div class="flex items-start gap-4">
      <div
        :class="[
          'flex size-10 shrink-0 items-center justify-center rounded-full',
          allDone
            ? 'bg-success/10 text-success-foreground'
            : isUrgent
              ? 'bg-warning/10 text-warning-foreground'
              : 'bg-muted text-foreground',
        ]"
      >
        <Icon :name="currentAction.icon" class="size-5" />
      </div>
      <div class="min-w-0 flex-1 space-y-3">
        <div>
          <h2 class="text-base font-medium tracking-tighter sm:text-lg">{{ currentAction.title }}</h2>
          <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
            {{ currentAction.description }}
          </p>
          <div
            v-if="currentAction.deadline"
            class="text-muted-foreground mt-1.5 flex items-center gap-1.5 text-xs tracking-tight sm:text-sm"
          >
            <Icon name="hugeicons:clock-01" class="size-3.5" />
            <span>{{ currentAction.deadline }}</span>
          </div>
        </div>
        <Button
          v-if="currentAction.action"
          size="sm"
          :variant="allDone ? 'outline' : 'default'"
          class="w-full sm:w-auto"
          @click="$emit('action', currentAction.actionKey)"
        >
          {{ currentAction.action }}
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";

const props = defineProps({
  profileComplete: { type: Boolean, default: false },
  brandEvents: { type: Array, default: () => [] },
});

defineEmits(["action"]);

const isUrgent = computed(() => {
  return currentAction.value.urgent || false;
});

const allDone = computed(() => {
  return currentAction.value.actionKey === "all_done";
});

const currentAction = computed(() => {
  // 1. Profile incomplete
  if (!props.profileComplete) {
    return {
      icon: "hugeicons:user-edit-01",
      title: "Complete your profile",
      description: "Fill in your name, phone, job title, and company to continue.",
      action: "Complete Profile",
      actionKey: "profile",
    };
  }

  // Check across all brand events
  const bes = props.brandEvents || [];

  // 2. Event rules not agreed
  const rulesNeeded = bes.find((be) => be.event_rules?.length > 0 && !be.event_rules_agreed);
  if (rulesNeeded) {
    return {
      icon: "hugeicons:file-validation",
      title: "Review event rules",
      description: `Please agree to the rules for ${rulesNeeded.event.title} to proceed.`,
      action: "Review Rules",
      actionKey: `rules:${rulesNeeded.brand_event_id}`,
    };
  }

  // 3. Documents needing re-agreement
  const reagreement = bes.find(
    (be) =>
      be.event_rules?.some((r) => r.needs_reagreement) ||
      be.documents?.some((d) => d.status === "needs_reagreement")
  );
  if (reagreement) {
    return {
      icon: "hugeicons:alert-02",
      title: "Document updated, please review",
      description: `Some documents for ${reagreement.event.title} have been updated and need your attention.`,
      action: "Review",
      actionKey: `docs:${reagreement.brand_event_id}`,
      urgent: true,
    };
  }

  // 4. Required documents not submitted
  const docsNeeded = bes.find(
    (be) => be.documents_total > 0 && be.documents_completed < be.documents_total
  );
  if (docsNeeded) {
    const remaining = docsNeeded.documents_total - docsNeeded.documents_completed;
    return {
      icon: "hugeicons:file-01",
      title: `${remaining} document${remaining > 1 ? "s" : ""} to submit`,
      description: `Complete your documents for ${docsNeeded.event.title}.`,
      action: "Submit Documents",
      actionKey: `docs:${docsNeeded.brand_event_id}`,
    };
  }

  // 5. Order period open with deadline
  const orderOpen = bes.find((be) => {
    if (!be.order_form_deadline) return false;
    const deadline = new Date(be.order_form_deadline);
    return deadline > new Date();
  });
  if (orderOpen) {
    const deadline = new Date(orderOpen.order_form_deadline);
    const daysLeft = Math.ceil((deadline - new Date()) / (1000 * 60 * 60 * 24));
    return {
      icon: "hugeicons:shopping-cart-01",
      title: "Order form is open",
      description: `Submit your order for ${orderOpen.event.title}.`,
      action: "Order Now",
      actionKey: `order:${orderOpen.brand_event_id}`,
      deadline: daysLeft <= 7 ? `Closes in ${daysLeft} day${daysLeft > 1 ? "s" : ""}` : null,
      urgent: daysLeft <= 3,
    };
  }

  // 6. All done
  return {
    icon: "hugeicons:checkmark-circle-02",
    title: "You're all set!",
    description: "All steps are complete. Check back for updates or new deadlines.",
    actionKey: "all_done",
  };
});
</script>
