<template>
  <div class="space-y-4">
    <!-- Order Period Info -->
    <div v-if="be.normal_order_opens_at || be.onsite_order_opens_at" class="space-y-2">
      <!-- Normal Order Period -->
      <div
        v-if="be.normal_order_opens_at"
        class="flex items-start gap-2 text-sm tracking-tight"
      >
        <Icon
          :name="normalStatus.icon"
          :class="['size-4.5 shrink-0 mt-0.5', normalStatus.color]"
        />
        <div>
          <p>
            <span class="font-medium">{{ $t("ed.order.normalLabel") }}</span>
            {{ formatDateTime(be.normal_order_opens_at) }}
            <template v-if="be.normal_order_closes_at">
              - {{ formatDateTime(be.normal_order_closes_at) }}
            </template>
          </p>
          <p v-if="normalStatus.label" :class="['text-sm', normalStatus.color]">
            {{ normalStatus.label }}
          </p>
        </div>
      </div>

      <!-- Onsite Order Period -->
      <div
        v-if="be.onsite_order_opens_at"
        class="flex items-start gap-2 text-sm tracking-tight"
      >
        <Icon
          :name="onsiteStatus.icon"
          :class="['size-4.5 shrink-0 mt-0.5', onsiteStatus.color]"
        />
        <div>
          <p>
            <span class="font-medium">{{ $t("ed.order.onsiteLabel") }}</span>
            {{ formatDateTime(be.onsite_order_opens_at) }}
            <template v-if="be.onsite_order_closes_at">
              - {{ formatDateTime(be.onsite_order_closes_at) }}
            </template>
          </p>
          <p class="text-muted-foreground text-sm">
            {{ $t("ed.order.surcharge", { rate: be.onsite_penalty_rate }) }}
          </p>
          <p v-if="onsiteStatus.label" :class="['text-sm', onsiteStatus.color]">
            {{ onsiteStatus.label }}
          </p>
        </div>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="flex flex-wrap items-center gap-2">
      <Button
        v-if="canOrder"
        :to="`/brands/${be.brand.slug}/order-form/${be.brand_event_id}`"
        size="sm"
      >
        <Icon name="hugeicons:shopping-cart-01" class="mr-1.5 size-4" />
        {{ be.orders_count > 0 ? $t("ed.order.newOrder") : $t("ed.order.openForm") }}
      </Button>
      <p v-else class="text-muted-foreground text-sm tracking-tight">
        {{ closedMessage }}
      </p>
      <Button
        v-if="be.orders_count > 0"
        :to="`/brands/${be.brand.slug}/orders/${be.brand_event_id}`"
        size="sm"
        variant="outline"
      >
        <Icon name="hugeicons:shopping-bag-01" class="mr-1.5 size-4" />
        {{ $t("ed.order.viewOrders") }}
      </Button>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";

const { t, locale } = useI18n();

const props = defineProps({
  be: { type: Object, required: true },
});

const dateLocale = computed(() => (locale.value === "zh" ? "zh-CN" : "en-US"));

function formatDateTime(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return (
    d.toLocaleDateString(dateLocale.value, { day: "numeric", month: "short", year: "numeric" }) +
    " " +
    String(d.getHours()).padStart(2, "0") +
    ":" +
    String(d.getMinutes()).padStart(2, "0")
  );
}

function getPeriodStatus(opensAt, closesAt) {
  if (!opensAt) return { icon: "hugeicons:circle", color: "text-muted-foreground", label: "", status: "none" };
  const now = new Date();
  const opens = new Date(opensAt);
  const closes = closesAt ? new Date(closesAt) : null;

  if (now < opens) {
    return { icon: "hugeicons:clock-01", color: "text-muted-foreground", label: t("ed.order.statusNotOpen"), status: "not_open" };
  }
  if (!closes || now <= closes) {
    return {
      icon: "hugeicons:checkmark-circle-02",
      color: "text-success-foreground",
      label: t("ed.order.statusOpen"),
      status: "open",
    };
  }
  return { icon: "hugeicons:cancel-circle", color: "text-muted-foreground", label: t("ed.order.statusClosed"), status: "closed" };
}

const normalStatus = computed(() =>
  getPeriodStatus(props.be.normal_order_opens_at, props.be.normal_order_closes_at),
);
const onsiteStatus = computed(() =>
  getPeriodStatus(props.be.onsite_order_opens_at, props.be.onsite_order_closes_at),
);

const canOrder = computed(() => {
  const be = props.be;
  // Legacy: no periods configured, always allow
  if (!be.normal_order_opens_at && !be.onsite_order_opens_at) return true;
  return normalStatus.value.status === "open" || onsiteStatus.value.status === "open";
});

const closedMessage = computed(() => {
  const be = props.be;
  if (!be.normal_order_opens_at && !be.onsite_order_opens_at) return "";
  const now = new Date();
  if (be.normal_order_opens_at && now < new Date(be.normal_order_opens_at)) {
    return t("ed.order.waitingMessage");
  }
  if (be.onsite_order_opens_at && now < new Date(be.onsite_order_opens_at)) {
    return t("ed.order.gapMessage");
  }
  return t("ed.order.closedMessage");
});
</script>
