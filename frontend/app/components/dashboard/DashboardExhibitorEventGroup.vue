<template>
  <div class="space-y-5">
    <!-- Event header (rendered once per event) -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-5">
      <img
        v-if="posterUrl"
        :src="posterUrl"
        :alt="event.title"
        :width="event.poster_image?.width || undefined"
        :height="event.poster_image?.height || undefined"
        class="border-border w-full rounded-xl border object-contain sm:max-w-xs"
      />
      <div
        v-else
        class="bg-muted text-muted-foreground flex aspect-video w-full items-center justify-center rounded-xl sm:max-w-xs"
      >
        <Icon name="hugeicons:calendar-03" class="size-8" />
      </div>

      <div class="min-w-0 flex-1 space-y-2">
        <h2 class="text-xl font-semibold tracking-tighter sm:text-2xl">{{ event.title }}</h2>
        <div class="text-muted-foreground flex flex-col gap-1 text-sm tracking-tight sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-2 sm:gap-y-1">
          <span v-if="event.date_label" class="flex items-center gap-1.5">
            <Icon name="hugeicons:calendar-03" class="size-4 shrink-0" />
            {{ event.date_label }}
          </span>
          <span
            v-if="event.date_label && event.location"
            class="text-muted-foreground/40 hidden sm:inline"
            >·</span
          >
          <span v-if="event.location" class="flex items-center gap-1.5">
            <Icon name="hugeicons:location-01" class="size-4 shrink-0" />
            {{ event.location }}
          </span>
        </div>

        <!-- Event-level deadlines -->
        <div v-if="deadlines.length" class="flex flex-col gap-1.5 pt-1 sm:flex-row sm:flex-wrap sm:gap-x-4">
          <div
            v-for="dl in deadlines"
            :key="dl.label"
            class="flex items-center gap-1.5 text-sm tracking-tight"
            :class="dl.urgent ? 'text-warning-foreground' : 'text-muted-foreground'"
          >
            <Icon :name="dl.icon" class="size-4 shrink-0" />
            <span>{{ dl.label }}: {{ dl.date }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Brand cards -->
    <div class="space-y-4">
      <DashboardExhibitorBrandCard
        v-for="(be, index) in brandEvents"
        :key="be.brand_event_id"
        :be="be"
        :dashboard="dashboard"
        :collapsible="brandEvents.length > 1"
        :default-open="index === 0"
        :ref="(el) => setCardRef(be.brand_event_id, el)"
        @refresh="$emit('refresh')"
      />
    </div>
  </div>
</template>

<script setup>
const { t, locale } = useI18n();

const props = defineProps({
  event: { type: Object, required: true },
  brandEvents: { type: Array, required: true },
  dashboard: { type: Object, required: true },
});

defineEmits(["refresh"]);

const cardRefs = {};
function setCardRef(beId, el) {
  cardRefs[beId] = el;
}

// Open the given brand card + scroll to the requested section.
function openAndScroll(beId, sectionKey) {
  cardRefs[beId]?.openAndScroll(sectionKey);
}

defineExpose({ openAndScroll });

const posterUrl = computed(() => {
  const poster = props.event.poster_image;
  if (!poster) return null;
  const lg = poster.lg;
  return (typeof lg === "object" ? lg?.url : lg) || poster.md || poster.url || null;
});

const dateLocale = computed(() => (locale.value === "zh" ? "zh-CN" : "en-US"));

function formatDeadline(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString(dateLocale.value, {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function isUrgent(dateStr) {
  if (!dateStr) return false;
  const daysLeft = Math.ceil((new Date(dateStr) - new Date()) / (1000 * 60 * 60 * 24));
  return daysLeft > 0 && daysLeft <= 7;
}

const deadlines = computed(() => {
  const first = props.brandEvents[0] || {};
  const items = [];
  if (first.promotion_post_deadline) {
    items.push({
      label: t("ed.eventCard.promotion"),
      date: formatDeadline(first.promotion_post_deadline),
      icon: "hugeicons:image-02",
      urgent: isUrgent(first.promotion_post_deadline),
    });
  }
  if (first.order_form_deadline) {
    items.push({
      label: t("ed.eventCard.order"),
      date: formatDeadline(first.order_form_deadline),
      icon: "hugeicons:shopping-cart-01",
      urgent: isUrgent(first.order_form_deadline),
    });
  }
  return items;
});
</script>
