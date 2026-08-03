<template>
  <div class="flex flex-col gap-y-0.5 overflow-hidden">
    <NuxtLink
      :to="`/forms/${form.slug}`"
      class="text-sm font-medium tracking-tight hover:underline"
    >
      {{ form.title }}
    </NuxtLink>

    <div class="flex items-center gap-x-0.5 overflow-hidden">
      <a
        :href="pmoneUrl"
        target="_blank"
        rel="noopener noreferrer"
        v-tippy="'Open public form'"
        class="text-muted-foreground min-w-0 truncate text-xs tracking-tight hover:underline"
      >
        {{ stripScheme(pmoneUrl) }}
      </a>

      <ButtonCopy :text="pmoneUrl" />

      <FormShareDialog :form="form">
        <template #trigger="{ open }">
          <button
            @click="open()"
            v-tippy="'Share & QR code'"
            aria-label="Share form"
            class="text-muted-foreground hover:text-foreground -ml-1 flex size-7 shrink-0 items-center justify-center rounded-lg"
          >
            <Icon name="hugeicons:qr-code-01" class="size-4 shrink-0" />
          </button>
        </template>
      </FormShareDialog>
    </div>

    <!-- Forms that belong to a project are also served from that project's event
         website. Each row carries its own share dialog, so a QR always encodes
         the link it sits under. -->
    <div v-if="eventUrl" class="flex items-center gap-x-0.5 overflow-hidden">
      <a
        :href="eventUrl"
        target="_blank"
        rel="noopener noreferrer"
        v-tippy="'Open on event website'"
        class="text-muted-foreground min-w-0 truncate text-xs tracking-tight hover:underline"
      >
        {{ stripScheme(eventUrl) }}
      </a>

      <ButtonCopy :text="eventUrl" />

      <FormShareDialog :form="form" target="event">
        <template #trigger="{ open }">
          <button
            @click="open()"
            v-tippy="'Share & QR code (event website)'"
            aria-label="Share event website form"
            class="text-muted-foreground hover:text-foreground -ml-1 flex size-7 shrink-0 items-center justify-center rounded-lg"
          >
            <Icon name="hugeicons:qr-code-01" class="size-4 shrink-0" />
          </button>
        </template>
      </FormShareDialog>
    </div>
  </div>
</template>

<script setup>
import FormShareDialog from "@/components/form-builder/FormShareDialog.vue";

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
});

const { pmoneUrl, eventUrl } = useFormPublicUrls(() => props.form);

const stripScheme = (url) => url.replace(/^https?:\/\//, "");
</script>
