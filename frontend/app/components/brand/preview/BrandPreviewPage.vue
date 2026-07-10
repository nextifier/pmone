<template>
  <!--
    Faithful replica of the public brand detail page (narrow / mobile layout).
    Source of truth: ~/Frontend/pmone-events/layers/base/app/pages/brands/[slug].vue
    Kept in sync manually; this is an indicative preview only.
  -->
  <div class="space-y-8">
    <!-- Identity (centered, like the detail page's left rail on mobile) -->
    <div class="flex flex-col items-center text-center">
      <Avatar
        :model="{ name: preview.brand_name || 'Brand', profile_image: imageModel }"
        size="xl"
        class="size-28 sm:size-32"
        rounded="rounded-full"
        :colorful="false"
        :gradient-frame="hasInstagram"
      />

      <!-- Name -->
      <h1 class="text-foreground mt-6 text-4xl leading-[0.95] font-semibold tracking-tighter text-balance">
        {{ preview.brand_name || "Brand name" }}
      </h1>

      <!-- Company -->
      <p v-if="preview.company_name" class="text-muted-foreground mt-2 text-base tracking-tight">
        {{ preview.company_name }}
      </p>

      <!-- Social links -->
      <div v-if="preview.links?.length" class="mt-6 flex flex-wrap justify-center gap-x-4 gap-y-2">
        <a
          v-for="link in preview.links"
          :key="link.label"
          :href="link.url || undefined"
          target="_blank"
          rel="noopener"
          class="text-foreground hover:bg-muted flex size-9 items-center justify-center rounded-xl transition active:scale-98"
          :aria-label="link.label"
          v-tippy="link.label"
        >
          <Icon :name="linkIcon(link.label)" class="size-4.5" />
        </a>
      </div>

      <!-- Facts grid (booth + categories + custom fields) - same GridFill as the live page -->
      <GridFill
        v-if="facts.length"
        :count="facts.length"
        :cols="2"
        min-col-width="150px"
        rounded="xl"
        class="mt-8 w-full text-left"
      >
        <div
          v-for="fact in facts"
          :key="fact.label"
          class="flex flex-col gap-1 px-4 py-4"
        >
          <span class="text-muted-foreground text-sm tracking-tight">{{ fact.label }}</span>
          <span class="text-foreground text-lg leading-tight font-medium tracking-tighter text-balance">
            {{ fact.value }}
          </span>
        </div>
      </GridFill>
    </div>

    <!-- Description (left-aligned, like the detail page's right column) -->
    <div
      v-if="preview.description_html"
      v-html="preview.description_html"
      class="text-foreground text-lg font-medium tracking-tight text-pretty [&_a]:underline [&_p:not(:first-child)]:mt-4"
    />

    <!-- Promotions placeholder (managed per event, not on this page) -->
    <div
      class="border-border text-muted-foreground flex flex-col items-center gap-2 rounded-xl border border-dashed py-10 text-center"
    >
      <Icon name="hugeicons:image-02" class="size-7" />
      <p class="text-sm tracking-tight">Promotion posts appear here on the live page.</p>
    </div>
  </div>
</template>

<script setup>
import { Avatar } from "@/components/ui/avatar";
import { GridFill } from "@/components/ui/grid-fill";
import { toImageModel } from "./previewImage";

const linkIconMap = {
  website: "hugeicons:globe-02",
  instagram: "hugeicons:instagram",
  facebook: "hugeicons:facebook-01",
  tiktok: "hugeicons:tiktok",
  x: "hugeicons:new-twitter",
  twitter: "hugeicons:new-twitter",
  linkedin: "hugeicons:linkedin-01",
  youtube: "hugeicons:youtube",
  threads: "hugeicons:threads",
};

const props = defineProps({
  preview: { type: Object, default: () => ({}) },
});

const imageModel = computed(() => toImageModel(props.preview.profile_image_url));

const hasInstagram = computed(() =>
  (props.preview.links || []).some((link) => link.label?.toLowerCase() === "instagram")
);

function linkIcon(label) {
  return linkIconMap[label?.toLowerCase()] || "hugeicons:link-01";
}

const facts = computed(() => {
  const items = [];
  if (props.preview.booth_number) {
    items.push({ label: "Booth", value: props.preview.booth_number });
  }
  if (props.preview.business_categories?.length) {
    items.push({ label: "Categories", value: props.preview.business_categories.join(", ") });
  }
  // custom_fields is a pre-formatted [{ label, value }] array from the form.
  for (const field of props.preview.custom_fields || []) {
    items.push({ label: field.label, value: field.value });
  }
  return items;
});
</script>
