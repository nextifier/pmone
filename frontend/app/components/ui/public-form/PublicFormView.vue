<template>
  <div
    class="relative min-h-dvh"
    :class="[
      embed ? 'py-4' : 'pt-6 pb-12 sm:pt-8 sm:pb-16',
      centerTerminal && 'flex flex-col justify-center',
    ]"
  >
    <!-- Page chrome belongs to the host app, so it is a slot and is dropped
         entirely when embedded in someone else's iframe. -->
    <div
      v-if="!embed && $slots.chrome"
      class="border-border bg-background/95 supports-backdrop-filter:bg-background/90 fixed top-4 right-3 z-10 flex items-center gap-x-1 rounded-full border p-1 backdrop-blur-sm"
    >
      <slot name="chrome" :locales="formLocales" />
    </div>

    <div class="container sm:max-w-xl">
      <!-- Only forms that dedupe by fingerprint land here: the answer needs the
           browser, so the form is held back rather than shown and then retracted.
           Every other form renders straight from SSR with no placeholder. -->
      <div v-if="isCheckingDuplicate" class="space-y-8">
        <div class="space-y-2.5">
          <Skeleton class="h-8 w-3/5" />
          <Skeleton class="h-4 w-4/5" />
        </div>
        <div class="space-y-6">
          <div v-for="i in 3" :key="i" class="space-y-2">
            <Skeleton class="h-4 w-32" />
            <Skeleton class="h-11 w-full" />
          </div>
          <Skeleton class="h-11 w-full rounded-lg" />
        </div>
      </div>

      <!-- Terminal states (closed / load error / success / already responded)
           share one card. Result draws its own checkmark when no icon is given. -->
      <Result
        v-else-if="statusCard"
        ref="terminalRef"
        tabindex="-1"
        class="outline-none"
        :size="embed ? 'sm' : 'default'"
        title-as="h1"
        v-bind="statusCard"
      />

      <div v-else-if="form" class="relative">
        <!-- BlurImage sets inheritAttrs:false and binds $attrs to the inner <img>,
             so a class passed to it lands on the image. Spacing needs a wrapper.
             On phones the cover goes full-bleed: `-mx-4` cancels the container's
             px-4 and `-mt-6` cancels the page's pt-6, so it meets all three
             edges. From `sm` the page is a centred card again and it sits inside
             the padding like everything else. -->
        <div v-if="form.cover_image?.xl && !embed" class="-mx-4 -mt-6 mb-5 sm:mx-0 sm:mt-0 sm:mb-6">
          <BlurImage
            :src="form.cover_image.xl"
            :lqip="form.cover_image.lqip || ''"
            :srcset="coverSrcset"
            sizes="(min-width: 640px) 544px, 100vw"
            :alt="form.title"
            :width="1500"
            :height="500"
            loading="eager"
            fetchpriority="high"
            image-class="aspect-[3/1] w-full object-cover"
          />
        </div>

        <div class="space-y-2">
          <h1 class="text-2xl font-semibold tracking-tighter text-balance sm:text-3xl">
            {{ form.title }}
          </h1>

          <!-- Description and its toggle are one group, so the gap between them
               is set here rather than inherited from the header's space-y: the
               clamp deliberately ends mid-line, and 8px leaves the half-faded
               line looking like it collides with the button. -->
          <div v-if="form.description">
            <div
              :id="descId"
              class="pf-desc scroll-fade-b"
              :class="{ 'is-expanded': descExpanded, 'is-faded': descFaded }"
              :style="descStyle"
            >
              <div ref="descContent" class="format-html" v-html="form.description" />
            </div>

            <button
              v-if="descOverflows || descExpanded"
              type="button"
              class="pf-desc-toggle text-muted-foreground hover:text-foreground mt-3 inline-flex cursor-pointer items-center gap-1 text-sm font-medium tracking-tight transition-colors"
              :class="{ 'is-expanded': descExpanded }"
              :aria-expanded="descExpanded"
              :aria-controls="descId"
              @click="descExpanded = !descExpanded"
            >
              <span>{{ descExpanded ? t("forms.showLess") : t("forms.showMore") }}</span>
              <ChevronDown class="pf-desc-chevron size-4" aria-hidden="true" />
            </button>
          </div>
        </div>

        <div class="bg-border my-6 h-px sm:my-8" />

        <form @submit.prevent="handleSubmit" class="space-y-7">
          <div
            v-if="formErrors._general"
            class="bg-destructive/10 text-destructive rounded-lg px-4 py-3 text-sm tracking-tight"
          >
            {{ formErrors._general }}
          </div>

          <!-- Honeypot: invisible to humans, bots tend to fill it -->
          <div class="absolute top-auto -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
            <label for="hp_website">Website</label>
            <input
              id="hp_website"
              v-model="honeypotWebsite"
              type="text"
              name="website"
              tabindex="-1"
              autocomplete="off"
            />
          </div>

          <div v-if="form.settings?.require_email" class="space-y-2.5">
            <Label for="respondent_email" required class="text-base leading-snug">
              {{ t("forms.emailLabel") }}
            </Label>
            <Input
              id="respondent_email"
              v-model="respondentEmail"
              type="email"
              autocomplete="email"
              placeholder="your@email.com"
              :class="{ 'border-destructive': formErrors.respondent_email }"
            />
            <FieldError
              :errors="formErrors.respondent_email ? [formErrors.respondent_email] : []"
            />
          </div>

          <CustomFieldRenderer
            v-for="(field, index) in sortedFields"
            :key="field.ulid"
            :data-field-error="formErrors[`responses.${field.ulid}`] ? field.ulid : undefined"
            :field="field"
            :is-first="index === 0"
            :locale="locale"
            label-size="lg"
            :model-value="responses[field.ulid]"
            :error="firstFieldError(formErrors, field.ulid)"
            :upload-handler="uploadHandlers.uploadHandler"
            :revert-handler="uploadHandlers.revertHandler"
            @update:model-value="responses[field.ulid] = $event"
            @uploading="handleUploading"
          />

          <Button
            type="submit"
            :disabled="submitting || uploadsInProgress > 0"
            class="w-full"
            size="lg"
          >
            <Spinner v-if="submitting" class="size-4" />
            <span>{{ submitLabel }}</span>
          </Button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, useId, watch } from "vue";
import { useDebounceFn, useResizeObserver } from "@vueuse/core";
import { ChevronDown } from "@lucide/vue";
import {
  availableFormLocales,
  buildCoverSrcset,
  duplicateModeFor,
  firstFieldError,
  generateHoneypotToken,
  isClosedError,
  loadVisitorId,
  mapValidationErrors,
  sortFormFields,
} from "./core";
import { BlurImage } from "../blur-image";
import { Button } from "../button";
import {
  prefillValueFor as coercePrefill,
  CustomFieldRenderer,
  defaultValueFor,
  supportsPrefill,
} from "../custom-field";
import { FieldError } from "../field";
import { Input } from "../input";
import { Label } from "../label";
import { Result } from "../result";
import { Skeleton } from "../skeleton";
import { Spinner } from "../spinner";

/**
 * The whole public form surface, shared verbatim between pmone.id/f/{slug} and
 * every event website's /f/{slug}. Everything host-specific is injected: the
 * page owns fetching (its meta title needs the payload), the endpoints, the
 * upload transport, and the chrome slot. Nothing in here may hardcode a URL.
 */
const props = defineProps({
  /** Payload from the public form endpoint, or null while unavailable. */
  form: { type: Object, default: null },
  /** useFetch error, kept raw so the 403/404 split stays in one place. */
  fetchError: { type: Object, default: null },
  /** Absolute or same-origin URLs: { check, submit }. */
  endpoints: { type: Object, required: true },
  /** { uploadHandler, revertHandler } from lib/uploadHandlers. */
  uploadHandlers: { type: Object, required: true },
  /** Picks which translation of each field label is rendered. */
  locale: { type: String, default: "en" },
  embed: { type: Boolean, default: false },
});

const { t } = useI18n();
const route = useRoute();

const responses = ref({});
const respondentEmail = ref("");
const formErrors = ref({});
const submitting = ref(false);
const submitted = ref(false);
const successMessage = ref("");
const alreadySubmitted = ref(false);
const duplicateCheckDone = ref(false);
const uploadsInProgress = ref(0);
const visitorId = ref(null);
const honeypotWebsite = ref("");
const honeypotToken = ref("");

const sortedFields = computed(() => sortFormFields(props.form?.fields));
const coverSrcset = computed(() => buildCoverSrcset(props.form?.cover_image));

/* ----- Description clamp ----- */
/**
 * The description is author HTML of any length sitting between the title and the
 * first question, so a long one is clamped and opened by an explicit toggle.
 *
 * SSR renders the clamp but never the mask or the toggle. The server cannot
 * measure, and the alternative - render unclamped, then clamp on hydration -
 * yanks the whole form upward in front of the reader. A clamp that turns out to
 * be unnecessary is invisible (the content already fit inside it), so the only
 * post-hydration change is a mask fading in and a toggle appearing under it.
 */
const descId = useId();
const descContent = ref(null);
const descExpanded = ref(false);
const descOverflows = ref(false);
const descHeight = ref(0);
const descClamp = ref(0);

const descFaded = computed(() => descOverflows.value && !descExpanded.value);

/** Nominal clamp. Mirrors the `--pf-desc-max` fallback the CSS uses during SSR. */
const DESC_CLAMP_REM = 14;

/**
 * Where inside a line the cut lands, as a fraction of the line box. Has to clear
 * the half-leading above the glyphs - land at 0.2 and the "visible" sliver is
 * blank space, which reads exactly like the paragraph having ended.
 */
const DESC_CUT_INTO_LINE = 0.6;

/** Text-bearing blocks. The cut is aligned to these, not to wrappers. */
const DESC_BLOCKS = "p, li, h1, h2, h3, h4, h5, h6, blockquote, pre, figcaption, td, th";

/**
 * A clamp is only legible as a clamp if it slices a line in half. Left at a round
 * number it lands wherever it lands - most often in the margin between two
 * paragraphs, where the gradient has nothing but whitespace to fade and the
 * description reads as simply having ended.
 *
 * So the nominal clamp is snapped onto the nearest line box below it: the reader
 * gets a half-shown line under the gradient, which is the one unambiguous "keep
 * going" cue a clamp can give.
 */
const resolveDescClamp = (content) => {
  const rootSize = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
  const nominal = DESC_CLAMP_REM * rootSize;

  // Children lay out at full height regardless of the clamp - `max-height` plus
  // `overflow: hidden` clips, it does not reflow - so these rects are honest in
  // either state.
  const contentTop = content.getBoundingClientRect().top;
  let target = null;

  for (const el of content.querySelectorAll(DESC_BLOCKS)) {
    const rect = el.getBoundingClientRect();
    const top = rect.top - contentTop;
    const bottom = rect.bottom - contentTop;

    // The block the cut already falls inside wins outright. Otherwise remember
    // the nearest one starting below it, for when the cut is in a margin.
    if (top < nominal && nominal < bottom) {
      target = { el, top };
      break;
    }
    if (top >= nominal && (!target || top < target.top)) {
      target = { el, top };
    }
  }

  if (!target) return nominal;

  const lineHeight =
    parseFloat(getComputedStyle(target.el).lineHeight) ||
    target.el.getBoundingClientRect().height ||
    rootSize * 1.5;

  // Which line of that block the cut is in - 0 when the cut sits above it.
  const line = Math.max(0, Math.floor((nominal - target.top) / lineHeight));

  return Math.round(target.top + (line + DESC_CUT_INTO_LINE) * lineHeight);
};

// Both lengths are measured rather than guessed: max-height interpolates between
// two lengths, so exact endpoints keep the easing honest instead of spending most
// of the curve on empty space. The 8px of slack on the open end keeps
// `overflow: hidden` from shaving a focus ring off a link on the last line.
const descStyle = computed(() => ({
  "--pf-desc-max": descClamp.value ? `${descClamp.value}px` : undefined,
  maxHeight: descExpanded.value ? `${descHeight.value + 8}px` : undefined,
}));

const measureDescription = () => {
  const content = descContent.value;
  if (!content) return;

  descHeight.value = Math.ceil(content.getBoundingClientRect().height);
  descClamp.value = resolveDescClamp(content);
  descOverflows.value = descHeight.value - descClamp.value > 1;
};

// One observer is enough. The content's border box changes on every reflow that
// matters - viewport width, a late web font, an image inside the description
// finishing, a locale switch - and it does NOT fire while max-height animates,
// so there is no observer loop to guard against.
useResizeObserver(descContent, measureDescription);

// Handed to the chrome slot so the host can scope its language switcher to the
// languages this particular form actually has.
const formLocales = computed(() => availableFormLocales(props.form));

const submitLabel = computed(() => {
  if (uploadsInProgress.value > 0) return t("forms.uploadingFiles");
  return submitting.value ? t("forms.submitting") : t("forms.submit");
});

const handleUploading = (active) => {
  uploadsInProgress.value = Math.max(0, uploadsInProgress.value + (active ? 1 : -1));
};

/**
 * One shared Result for every terminal state. A closed or missing form is
 * painted neutral on purpose: it is not the visitor's fault, so it should not
 * read as a failure they caused.
 */
const statusCard = computed(() => {
  if (props.fetchError) {
    const closed = isClosedError(props.fetchError);

    return {
      status: "error",
      variant: "muted",
      icon: "lucide:alert-circle",
      title: closed ? t("forms.closedTitle") : t("forms.notFoundTitle"),
      // The 403 body carries the owner's own "form closed" wording. A 404 body
      // does not: Laravel answers a missing form with the raw model name.
      description: closed
        ? props.fetchError.data?.message || t("forms.closedMessage")
        : t("forms.notFoundMessage"),
    };
  }

  if (submitted.value) {
    return {
      status: "success",
      variant: "soft",
      title: t("forms.successTitle"),
      description: successMessage.value || t("forms.successMessage"),
    };
  }

  if (alreadySubmitted.value && props.form) {
    return {
      status: "info",
      variant: "soft",
      icon: "lucide:check-circle-2",
      title: t("forms.alreadyTitle"),
      description: t("forms.alreadyMessage"),
    };
  }

  return null;
});

const duplicateMode = computed(() => duplicateModeFor(props.form?.settings));

const isCheckingDuplicate = computed(
  () => duplicateMode.value.checksFingerprint && !duplicateCheckDone.value
);

/**
 * A terminal card is the whole page - there is no form under it to anchor the
 * eye - so it centres in the viewport instead of hanging off the top of a
 * min-h-dvh box. Embedded stays top-aligned: a host sizing its iframe to content
 * should not be handed a screenful of centred whitespace.
 */
const centerTerminal = computed(
  () => !props.embed && !isCheckingDuplicate.value && !!statusCard.value
);

// Submitting tears the <form> out from under the focused button, dropping focus
// to <body>. role="status" reads the outcome out loud; this is what keeps the
// keyboard caret somewhere sensible.
const terminalRef = ref(null);

watch(statusCard, async (card) => {
  if (!card) return;
  await nextTick();
  terminalRef.value?.$el?.focus?.();
});

const runDuplicateCheck = async (email = null) => {
  const params = new URLSearchParams();
  if (email) params.append("email", email);
  if (visitorId.value) params.append("fingerprint", visitorId.value);
  if (![...params].length) return;

  try {
    const result = await $fetch(`${props.endpoints.check}?${params}`);
    if (result.already_submitted) {
      alreadySubmitted.value = true;
    }
  } catch {
    // A failed pre-check is not fatal - submit still rejects duplicates with 409.
  }
};

// Debounced rather than per-keystroke, which used to fire one request per
// character typed after the "@". Watching the value (not blur) covers autofill.
const checkEmailForDuplicate = useDebounceFn(() => {
  if (duplicateMode.value.checksEmail && respondentEmail.value.includes("@")) {
    runDuplicateCheck(respondentEmail.value);
  }
}, 500);

watch(respondentEmail, checkEmailForDuplicate);

onMounted(async () => {
  // Ahead of the observer's first (next-frame) callback, so the mask and the
  // toggle are already correct in the first painted frame after hydration.
  measureDescription();

  honeypotToken.value = generateHoneypotToken();
  visitorId.value = await loadVisitorId();

  if (duplicateMode.value.checksFingerprint) {
    await runDuplicateCheck();
  }
  duplicateCheckDone.value = true;
});

// Prefill a field from URL query params (?{ulid}=value or ?{param_key}=value).
// The view owns query extraction; per-type coercion lives in custom-field/core.
const prefillValueFor = (field) => {
  if (!supportsPrefill(field.type)) return undefined;

  const paramKey = field.settings?.param_key;
  const raw = route.query[field.ulid] ?? (paramKey ? route.query[paramKey] : undefined);

  return coercePrefill(field, raw);
};

watch(
  sortedFields,
  (fields) => {
    for (const field of fields) {
      if (field.type !== "section" && responses.value[field.ulid] === undefined) {
        responses.value[field.ulid] = prefillValueFor(field) ?? defaultValueFor(field);
      }
    }
  },
  { immediate: true }
);

// Scroll the first invalid field into view after server-side validation
const scrollToFirstError = async () => {
  await nextTick();
  const target = document.querySelector("[data-field-error]") || document.querySelector("form");
  target?.scrollIntoView({ behavior: "smooth", block: "center" });
};

const handleSubmit = async () => {
  if (alreadySubmitted.value) return;

  submitting.value = true;
  formErrors.value = {};

  try {
    const result = await $fetch(props.endpoints.submit, {
      method: "POST",
      body: {
        responses: responses.value,
        respondent_email: respondentEmail.value || null,
        browser_fingerprint: visitorId.value,
        website: honeypotWebsite.value,
        _token_time: honeypotToken.value,
      },
    });

    if (result.redirect_url) {
      window.location.href = result.redirect_url;
      return;
    }

    successMessage.value = result.message || t("forms.successMessage");
    submitted.value = true;
  } catch (err) {
    if (err.status === 422 && err.data?.errors) {
      formErrors.value = mapValidationErrors(err.data.errors);
      scrollToFirstError();
    } else if (err.status === 409) {
      alreadySubmitted.value = true;
    } else {
      formErrors.value._general = err.data?.message || t("forms.submitFailed");
    }
  } finally {
    submitting.value = false;
  }
};
</script>

<style scoped>
/* Description clamp + reveal, on transitions.dev 21 (accordion expand) tokens.
   Plain CSS, no @apply: this file has no `@reference`, and everything it needs
   is already a global custom property.

   Why not the accordion's grid-template-rows 0fr -> 1fr: that trick expresses
   "nothing -> auto". This is "N lines -> auto", which has no fr equivalent, so
   max-height carries the tween and the rest of the snippet is unchanged.

   Why scroll-fade-b is kept but its animation killed: the utility drives its
   length from a scroll timeline, and a scroll timeline on an `overflow: hidden`
   box is inactive, so --scroll-fade-b falls back to the 0px initial value of its
   @property registration and no fade ever paints. `animation: none` states that
   outright and leaves a registered <length-percentage> we own - and can
   transition, which `mask-image` itself cannot. Only the length is ours; the
   mask plumbing stays the utility's.

   This works without !important because Vue injects scoped styles unlayered,
   and every Tailwind utility lives in @layer utilities. Unlayered wins. */
.pf-desc {
  /* SSR fallback only. Once mounted, JS overwrites --pf-desc-max inline with a
     px value nudged so the cut lands inside a line of text - see
     resolveDescClamp. Keep the two in step (DESC_CLAMP_REM). */
  --pf-desc-max: 14rem;
  --scroll-fade-b: 0px;
  animation: none;
  overflow: hidden;
  max-height: var(--pf-desc-max);
  /* Declared on the resting rule, so this is the collapse leg. */
  transition:
    max-height var(--acc-collapse) var(--acc-ease),
    --scroll-fade-b var(--acc-collapse) var(--acc-ease);
}

.pf-desc.is-expanded {
  transition:
    max-height var(--acc-expand) var(--acc-ease),
    --scroll-fade-b var(--acc-expand) var(--acc-ease);
}

/* Painted only once JS has confirmed the content really overflows, so a short
   description is never fringed with a fade that means nothing. Roughly one line
   tall on purpose: any deeper and it swallows the half-shown line the clamp
   works to expose, leaving the reader with no visible evidence of a cut. */
.pf-desc.is-faded {
  --scroll-fade-b: 2rem;
}

/* A block formatting context, so the child's own margins stay inside its border
   box - the measured height has to be the exact max-height target. format-html
   zeroes the first paragraph's margin but not a leading <ul>'s. */
.pf-desc > .format-html {
  display: flow-root;
}

.pf-desc-toggle {
  animation: pf-toggle-in var(--acc-expand) var(--acc-ease) both;
}

/* The keyframe and the `animation` shorthand that drives it must stay in this
   same scoped block: the SFC compiler renames them together. Never reference it
   from a Tailwind `animate-[...]` class - that string is emitted into the global
   stylesheet and never sees the rename. */
@keyframes pf-toggle-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

/* scaleY, never a `d:` path morph - CSS path interpolation is Chromium-only. */
.pf-desc-chevron {
  transition: transform var(--acc-chevron) var(--acc-ease);
}

.pf-desc-toggle.is-expanded .pf-desc-chevron {
  transform: scaleY(-1);
}

@media (prefers-reduced-motion: reduce) {
  .pf-desc,
  .pf-desc.is-expanded,
  .pf-desc-chevron {
    transition: none !important;
  }
  .pf-desc-toggle {
    animation: none !important;
  }
}
</style>
