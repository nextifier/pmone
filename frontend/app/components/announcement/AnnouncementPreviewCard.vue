<template>
  <div class="bg-card border-border flex w-full items-start gap-3 rounded-lg border p-3">
    <div v-if="imageUrl" class="size-10 shrink-0 overflow-hidden rounded-md">
      <img :src="imageUrl" :alt="announcement.title" class="size-full object-cover" loading="lazy" />
    </div>
    <div
      v-else-if="announcement.icon"
      :class="['flex size-10 shrink-0 items-center justify-center rounded-md', typeBgClass]"
    >
      <Icon :name="announcement.icon" :class="['size-5', typeIconClass]" />
    </div>

    <div class="flex min-w-0 flex-1 flex-col gap-1.5">
      <p class="text-foreground text-sm font-medium tracking-tight text-pretty sm:text-base">
        {{ announcement.title || placeholderTitle }}
      </p>
      <p
        v-if="announcement.description"
        class="text-muted-foreground text-xs tracking-tight text-pretty sm:text-sm"
      >
        {{ announcement.description }}
      </p>
      <div
        v-if="visibleActions.length"
        class="mt-1.5 flex flex-wrap items-center gap-2"
      >
        <span v-for="(cta, idx) in visibleActions" :key="idx" :class="ctaClass(cta.style)">
          <Icon v-if="cta.icon" :name="cta.icon" class="size-3.5" />
          {{ cta.label }}
        </span>
      </div>
    </div>

    <!--
      Inert on purpose. This is a picture of the dashboard card, not the card:
      `tabindex="-1"` keeps it out of the tab order so the preview never steals
      a stop from the form being previewed.
    -->
    <Button
      v-if="announcement.is_dismissible"
      variant="ghost"
      size="iconSm"
      aria-label="Dismiss announcement"
      tabindex="-1"
      class="text-muted-foreground/70 -mt-1 -mr-1 ml-auto size-7 shrink-0"
    >
      <Icon name="hugeicons:cancel-01" class="size-3.5" />
    </Button>
  </div>
</template>

<script setup>
/**
 * How an announcement lands on a user's dashboard. Extracted out of
 * `announcements/[id]/show.vue`, where it was inline markup, so the create and
 * edit forms can show the same thing live while it is being written.
 *
 * Takes a loose object rather than a saved record: the create form feeds it a
 * draft that has no id and no uploaded image yet.
 */
import { Button } from "@/components/ui/button";

const props = defineProps({
  announcement: { type: Object, default: () => ({}) },
  /** Shown while the title is still empty, so the card is never a blank box. */
  placeholderTitle: { type: String, default: "Untitled announcement" },
});

const TYPE_BG = {
  info: "bg-info/15",
  success: "bg-success/15",
  warning: "bg-warning/15",
  error: "bg-destructive/15",
  marketing: "bg-muted",
};

const TYPE_ICON = {
  info: "text-info",
  success: "text-success",
  warning: "text-warning",
  error: "text-destructive-foreground",
  marketing: "text-muted-foreground",
};

const typeBgClass = computed(() => TYPE_BG[props.announcement?.type] || TYPE_BG.info);
const typeIconClass = computed(() => TYPE_ICON[props.announcement?.type] || TYPE_ICON.info);

/**
 * A saved record carries `image.sm.url`; a draft being written carries whatever
 * the uploader put on it, which may be a plain URL string or nothing at all.
 */
const imageUrl = computed(() => {
  const image = props.announcement?.image;
  if (!image) return null;
  if (typeof image === "string") return image;
  return image.sm?.url || image.url || null;
});

/** A half-typed action with no label yet would render as an empty pill. */
const visibleActions = computed(() =>
  (props.announcement?.cta_actions || []).filter((cta) => String(cta?.label ?? "").trim())
);

function ctaClass(style) {
  if (style === "button-primary") {
    return "bg-primary text-primary-foreground inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium tracking-tight sm:text-sm";
  }
  if (style === "button-outline") {
    return "border-border inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium tracking-tight sm:text-sm";
  }
  return "text-foreground inline-flex items-center gap-1 text-xs font-medium tracking-tight sm:text-sm";
}
</script>
