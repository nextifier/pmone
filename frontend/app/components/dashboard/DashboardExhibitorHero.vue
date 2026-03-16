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
        <div class="flex flex-wrap gap-2">
          <Button
            v-if="currentAction.action"
            size="sm"
            :variant="allDone ? 'outline' : 'default'"
            class="w-full sm:w-auto"
            @click="$emit('action', currentAction.actionKey)"
          >
            {{ currentAction.action }}
          </Button>
          <NuxtLink
            v-if="brandEventWithOrders"
            :to="`/brands/${brandEventWithOrders.brand.slug}/orders/${brandEventWithOrders.brand_event_id}`"
          >
            <Button size="sm" variant="outline" class="w-full sm:w-auto">
              <Icon name="hugeicons:shopping-cart-01" class="mr-1.5 size-4" />
              {{ $t("ed.hero.viewOrders") }}
            </Button>
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";

const { t } = useI18n();

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

const brandEventWithOrders = computed(() => {
  return (props.brandEvents || []).find((be) => be.orders_count > 0) || null;
});

const currentAction = computed(() => {
  // 1. Profile incomplete
  if (!props.profileComplete) {
    return {
      icon: "hugeicons:user-edit-01",
      title: t("ed.hero.completeProfile"),
      description: t("ed.hero.completeProfileDesc"),
      action: t("ed.hero.completeProfileCta"),
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
      title: t("ed.hero.reviewRules"),
      description: t("ed.hero.reviewRulesDesc", { event: rulesNeeded.event.title }),
      action: t("ed.hero.reviewRulesCta"),
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
      title: t("ed.hero.docUpdated"),
      description: t("ed.hero.docUpdatedDesc", { event: reagreement.event.title }),
      action: t("ed.hero.docUpdatedCta"),
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
      title: t("ed.hero.docsToSubmit", remaining),
      description: t("ed.hero.docsToSubmitDesc", { event: docsNeeded.event.title }),
      action: t("ed.hero.docsToSubmitCta"),
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
      title: t("ed.hero.orderOpen"),
      description: t("ed.hero.orderOpenDesc", { event: orderOpen.event.title }),
      action: t("ed.hero.orderOpenCta"),
      actionKey: `order:${orderOpen.brand_event_id}`,
      deadline: daysLeft <= 7 ? t("ed.hero.orderDeadline", daysLeft, { days: daysLeft }) : null,
      urgent: daysLeft <= 3,
    };
  }

  // 6. All done
  return {
    icon: "hugeicons:checkmark-circle-02",
    title: t("ed.hero.allDone"),
    description: t("ed.hero.allDoneDesc"),
    actionKey: "all_done",
  };
});
</script>
