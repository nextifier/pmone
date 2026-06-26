<template>
  <main
    class="bg-background text-foreground grid min-h-dvh place-items-center overflow-hidden px-6 py-12"
  >
    <div class="t-stagger flex w-full max-w-md flex-col items-center text-center">
      <div class="t-stagger-line mb-2" style="--err-i: 0">
        <Stack aria-hidden="true">
          <template #layer-1>
            <Icon :name="state.icons[0]" class="text-foreground size-7 shrink-0" />
          </template>
          <template #layer-2>
            <Icon :name="state.icons[1]" class="text-muted-foreground size-6 shrink-0" />
          </template>
          <template #layer-3>
            <Icon :name="state.icons[2]" class="text-muted-foreground size-6 shrink-0" />
          </template>
        </Stack>
      </div>

      <span
        v-if="statusCode"
        class="t-stagger-line text-muted-foreground mb-2 text-xs font-medium tracking-tight tabular-nums sm:text-sm"
        style="--err-i: 1"
      >
        Error {{ statusCode }}
      </span>

      <h1
        class="t-stagger-line text-primary text-2xl font-semibold tracking-tighter text-balance wrap-break-word sm:text-3xl md:text-4xl"
        style="--err-i: 2"
      >
        {{ state.title }}
      </h1>

      <p
        v-if="state.description"
        class="t-stagger-line text-muted-foreground mt-3 max-w-md text-sm tracking-tight text-balance wrap-break-word sm:text-base"
        style="--err-i: 3"
      >
        {{ state.description }}
      </p>

      <div class="t-stagger-line mt-8" style="--err-i: 4">
        <Button @click="handleError">
          <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" aria-hidden="true" />
          <span>Back to home</span>
        </Button>
      </div>

      <pre
        v-if="isDev && error?.stack && statusCode >= 500"
        class="text-muted-foreground border-border bg-muted/40 mt-8 max-h-64 w-full max-w-full overflow-auto rounded-xl border px-4 py-3 text-left text-xs leading-relaxed whitespace-pre-wrap break-words"
      >{{ error.stack }}</pre>
    </div>
  </main>
</template>

<script setup>
const props = defineProps({
  error: { type: Object, default: () => ({}) },
});

const isDev = import.meta.dev;

const statusCode = computed(() => Number(props.error?.statusCode) || 500);

/* Each status fills all three Stack layers: [front, middle, back]. The front
   (layer-1) carries the primary icon; the two behind reinforce the theme. */
const STATES = {
  404: {
    icons: ["hugeicons:search-01", "hugeicons:location-01", "hugeicons:link-01"],
    title: "Page not found",
    description:
      "We couldn’t find that page. It may have moved, been renamed, or maybe it never existed.",
  },
  403: {
    icons: ["hugeicons:square-lock-02", "hugeicons:cancel-circle", "hugeicons:alert-circle"],
    title: "Access denied",
    description: "You don’t have permission to view this page.",
  },
  409: {
    icons: ["hugeicons:mail-01", "hugeicons:alert-circle", "hugeicons:checkmark-circle-02"],
    title: "Email not verified",
    description: "Please verify your email address to continue.",
  },
  410: {
    icons: ["hugeicons:delete-02", "hugeicons:cancel-circle", "hugeicons:file-01"],
    title: "No longer available",
    description: "This page is gone and won’t be coming back.",
  },
  429: {
    icons: ["hugeicons:clock-01", "hugeicons:reload", "hugeicons:alert-circle"],
    title: "Too many requests",
    description:
      "You’ve made too many requests in a short time. Please wait a moment and try again.",
  },
  500: {
    icons: ["hugeicons:alert-02", "hugeicons:alert-circle", "hugeicons:information-circle"],
    title: "Something went wrong",
    description: "An unexpected error occurred on our end. Please try again shortly.",
  },
};

const state = computed(() => {
  const base = STATES[statusCode.value] || STATES[500];

  if (statusCode.value === 403 && props.error?.statusMessage) {
    return {
      ...base,
      description: props.error.statusMessage.replace(/^forbidden:\s*/i, ""),
    };
  }

  return base;
});

if (import.meta.server) {
  const event = useRequestEvent();
  if (event) {
    setResponseStatus(event, statusCode.value);
  }
}

useSeoMeta({
  title: () => state.value.title,
  robots: "noindex, nofollow",
});

const handleError = () => clearError({ redirect: "/" });
</script>

<style scoped>
/* Texts-reveal (transitions.dev 18): staggered blurred rise on first paint.
   Implemented as an auto-playing keyframe (not a JS .is-shown toggle) so the
   content stays visible without JS and during SSR. Motion values match the
   skill's tokens; --err-ease reuses the global --panel-ease. */
.t-stagger {
  --err-dur: 500ms;
  --err-distance: 12px;
  --err-stagger: 40ms;
  --err-blur: 3px;
  --err-ease: var(--panel-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.t-stagger-line {
  opacity: 0;
  animation: err-reveal var(--err-dur) var(--err-ease) both;
  animation-delay: calc(var(--err-stagger) * var(--err-i, 0));
  will-change: transform, opacity, filter;
}

@keyframes err-reveal {
  from {
    opacity: 0;
    transform: translateY(var(--err-distance));
    filter: blur(var(--err-blur));
  }
  to {
    opacity: 1;
    transform: translateY(0);
    filter: blur(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .t-stagger-line {
    animation: none;
    opacity: 1;
  }
}
</style>
