<template>
  <section class="space-y-5">
    <!-- Event header, rendered once per event. The poster identifies the event
         at a glance; it is not the content, so it stays at thumbnail scale and
         the clock gets the width instead. -->
    <header class="border-border flex items-start gap-3 border-b pb-5 sm:gap-4">
      <!-- Fixed height, natural width: the poster keeps its own proportions
           rather than being letterboxed into a square. Tap opens it full size. -->
      <Lightbox
        v-if="posterItems.length"
        :items="posterItems"
        :show-thumbnails="false"
        :show-share="false"
      >
        <template #trigger="{ openAt }">
          <button
            type="button"
            class="group bg-muted relative shrink-0 cursor-zoom-in overflow-hidden rounded-lg border"
            :aria-label="$t('ed.eventCard.viewPoster')"
            @click="openAt(0)"
          >
            <img :src="posterUrl" :alt="posterAlt" class="h-20 w-auto max-w-24 sm:h-24 sm:max-w-28" />
            <span
              class="bg-foreground/0 group-hover:bg-foreground/20 absolute inset-0 flex items-center justify-center transition-colors"
            >
              <Icon
                name="lucide:zoom-in"
                class="text-background size-5 opacity-0 transition-opacity group-hover:opacity-100"
              />
            </span>
          </button>
        </template>
      </Lightbox>
      <div
        v-else
        class="border-border bg-muted text-muted-foreground flex h-20 w-20 shrink-0 items-center justify-center rounded-lg border sm:h-24 sm:w-24"
      >
        <Icon name="hugeicons:calendar-03" class="size-6" />
      </div>

      <div class="min-w-0 flex-1 space-y-1">
        <h2 class="text-xl font-semibold tracking-tighter text-balance sm:text-2xl">
          {{ event.title }}
        </h2>

        <div
          v-if="event.date_label || event.location"
          class="text-muted-foreground flex flex-col text-sm tracking-tight sm:flex-row sm:flex-wrap sm:items-center"
        >
          <span v-if="event.date_label">{{ event.date_label }}</span>
          <span
            v-if="event.date_label && event.location"
            class="text-muted-foreground/40 hidden px-1.5 sm:inline"
            >·</span
          >
          <span v-if="event.location">{{ event.location }}</span>
        </div>

        <!-- Event-level deadlines. Colour reports the state, the sentence says it
             too, so it survives a screen the exhibitor cannot see colour on. -->
        <div
          v-if="deadlines.length"
          class="flex flex-col gap-x-4 gap-y-1 pt-1.5 sm:flex-row sm:flex-wrap"
        >
          <div
            v-for="dl in deadlines"
            :key="dl.key"
            class="flex items-start gap-1.5 text-sm tracking-tight"
            :class="dl.tone"
          >
            <!-- Top-aligned, not centred: a longer translation wraps to two
                 lines and the icon has to stay with the first one. -->
            <Icon :name="dl.icon" class="mt-0.5 size-4 shrink-0" />
            <span>{{ dl.text }}</span>
          </div>
        </div>
      </div>
    </header>

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
  </section>
</template>

<script setup>
import { Lightbox } from "@/components/ui/lightbox";

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

// Spatie media URLs come keyed lqip/sm/md/lg/xl (+ url/alt/width/height).
const poster = computed(() => props.event.poster_image || null);
const posterUrl = computed(() => poster.value?.sm || poster.value?.md || poster.value?.url || null);
const posterAlt = computed(() => poster.value?.alt || props.event.title);

const posterItems = computed(() => {
  const p = poster.value;
  if (!posterUrl.value) return [];

  return [
    {
      sm: p.sm,
      md: p.md,
      lg: p.lg,
      xl: p.xl,
      url: p.url,
      alt: posterAlt.value,
      caption: props.event.title || undefined,
      downloadUrl: p.original || p.url,
    },
  ];
});

const dateLocale = computed(() => exhibitorDateLocale(locale.value));

function formatDeadline(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString(dateLocale.value, {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

// Whole calendar days from today, so a deadline at 09:00 tomorrow reads
// "tomorrow" rather than "in 0 days".
function daysUntil(dateStr) {
  const target = new Date(dateStr);
  const startOfTarget = new Date(target.getFullYear(), target.getMonth(), target.getDate());
  const now = new Date();
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  return Math.round((startOfTarget - startOfToday) / 86400000);
}

/**
 * A raw date is not actionable, so anything closing within a week says how long
 * is left instead. Past that, the date itself is the more useful fact.
 */
function deadlineState(dateStr) {
  if (new Date(dateStr) < new Date()) {
    return { tone: "text-destructive-foreground", key: "closed" };
  }

  const days = daysUntil(dateStr);

  if (days === 0) return { tone: "text-warning-foreground", key: "closesToday" };
  if (days === 1) return { tone: "text-warning-foreground", key: "closesTomorrow" };
  if (days <= 7) return { tone: "text-warning-foreground", key: "closesInDays", count: days };

  return { tone: "text-muted-foreground", key: "closes" };
}

// Deadlines belong to the event, not the booth, so any brand-event in this group
// carries the same pair.
const deadlines = computed(() => {
  const first = props.brandEvents[0] || {};

  return [
    { key: "promotion", date: first.promotion_post_deadline, icon: "hugeicons:image-02" },
    { key: "order", date: first.order_form_deadline, icon: "hugeicons:shopping-cart-01" },
  ]
    .filter((item) => item.date)
    .map((item) => {
      const state = deadlineState(item.date);

      return {
        key: item.key,
        icon: item.icon,
        tone: state.tone,
        text: t(`ed.eventCard.${state.key}`, {
          label: t(`ed.eventCard.${item.key}`),
          date: formatDeadline(item.date),
          count: state.count ?? 0,
        }),
      };
    });
});
</script>
