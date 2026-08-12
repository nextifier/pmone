<template>
  <!--
    No bottom padding here. The Editor tab pins its preview with `position:
    sticky`, and sticky stops sticking once its containing block runs out - so
    any space between the panel and the end of the document is space the pinned
    preview gets shoved up by, right under the header. Each tab pads its own
    bottom instead. See `preview-panel/context.ts`.
  -->
  <div class="flex flex-col sm:container">
    <template v-if="initialLoading">
      <div class="flex flex-col items-start gap-y-4 pt-4">
        <Skeleton class="h-8 w-24 rounded-lg" />
        <div class="flex w-full flex-wrap items-center gap-x-3 gap-y-2">
          <Skeleton class="h-7 w-56 max-w-full" />
          <Skeleton class="h-[22px] w-24 rounded-full" />
          <Skeleton class="h-5 w-20" />
        </div>
        <div class="mt-4 flex w-full gap-x-6 border-b pb-2">
          <Skeleton v-for="i in 4" :key="i" class="h-5 w-16" />
        </div>
        <div class="w-full space-y-4 pt-4">
          <Skeleton class="h-40 w-full max-w-2xl rounded-xl" />
          <Skeleton class="h-9 w-full max-w-2xl" />
          <Skeleton class="h-24 w-full max-w-2xl rounded-lg" />
        </div>
      </div>
    </template>

    <template v-else-if="error">
      <div class="flex items-center justify-center py-20">
        <div class="flex flex-col items-center gap-y-4 text-center">
          <div
            class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
          >
            <div class="translate-y-1.5 -rotate-6">
              <Icon name="hugeicons:resize-field-rectangle" />
            </div>
            <div><Icon name="hugeicons:search-remove" /></div>
            <div class="translate-y-1.5 rotate-6"><Icon name="hugeicons:file-not-found" /></div>
          </div>
          <div class="space-y-1">
            <h3 class="text-lg font-semibold tracking-tighter text-balance">{{ error }}</h3>
            <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
              The form may have been moved to trash or you may not have access to it.
            </p>
          </div>
          <Button to="/forms">
            <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
            <span>Back to forms</span>
          </Button>
        </div>
      </div>
    </template>

    <template v-else-if="form">
      <!-- Header info -->
      <!-- No in-page back button: it lives in the app header now, which is the
           only chrome that survives once a sticky-panel route scrolls. -->
      <div class="flex flex-col items-start gap-y-4 pt-4">
        <div class="flex w-full flex-wrap items-center gap-x-3 gap-y-2">
          <h1 class="text-xl font-semibold tracking-tighter text-balance">{{ form.title }}</h1>
          <Badge :variant="statusBadge.variant" :icon="statusBadge.icon">
            {{ form.status.charAt(0).toUpperCase() + form.status.slice(1) }}
          </Badge>
          <span
            v-if="form.responses_count !== undefined"
            class="text-muted-foreground text-sm tracking-tight tabular-nums"
          >
            {{ form.responses_count }} {{ form.responses_count === 1 ? "response" : "responses" }}
          </span>
          <!--
            Every action here needs a live public URL, so a draft shows none of
            them - the Editor tab previews it instead.

            Labelled, not four bare icons: a globe and two arrows next to each
            other are a guessing game. The two frequent actions stay visible and
            the rest move into the overflow, which is also what keeps the group
            inside a phone's width.
          -->
          <ButtonGroup v-if="form.status === 'published'" class="ml-auto">
            <Button variant="outline" size="sm" @click="copy(publicFormUrl)">
              <span class="t-icon-swap-slot grid">
                <Transition name="t-iswap" mode="out-in">
                  <Icon
                    :key="copied ? 'done' : 'copy'"
                    :name="copied ? 'hugeicons:tick-02' : 'hugeicons:copy-01'"
                    :class="['size-4 shrink-0', copied && 'text-success-foreground']"
                  />
                </Transition>
              </span>
              <Transition name="t-tswap" mode="out-in">
                <span :key="copied ? 'done' : 'copy'" class="inline-block whitespace-nowrap">
                  {{ copied ? "Copied" : "Copy link" }}
                </span>
              </Transition>
            </Button>

            <Button variant="outline" size="sm" :to="publicFormUrl">
              <Icon name="hugeicons:arrow-up-right-01" class="size-4 shrink-0" />
              <span>View form</span>
            </Button>

            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" size="sm" aria-label="More form actions">
                  <Icon name="hugeicons:arrow-down-01" class="size-4 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-52">
                <DropdownMenuItem @select="shareDialogOpen = true">
                  <Icon name="hugeicons:share-03" class="size-4 shrink-0" />
                  <span>Share &amp; embed</span>
                </DropdownMenuItem>
                <DropdownMenuItem v-if="eventUrl" as-child>
                  <a :href="eventUrl" target="_blank" rel="noopener noreferrer">
                    <Icon name="hugeicons:globe-02" class="size-4 shrink-0" />
                    <span>Open on event website</span>
                  </a>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </ButtonGroup>
        </div>
      </div>

      <FormShareDialog v-model:open="shareDialogOpen" :form="form" />

      <!-- Not sticky: it would hover over the pinned preview beside it, and
           the preview would have to give up a tab-bar of height to clear it. -->
      <TabNav :tabs="formTabs" class="mt-4" :sticky="false" />

      <div class="pt-6">
        <NuxtPage :form="form" @refresh="refreshForm" />
      </div>
    </template>
  </div>
</template>

<script setup>
import FormShareDialog from "@/components/form-builder/FormShareDialog.vue";
import { useClipboard } from "@vueuse/core";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ButtonGroup } from "@/components/ui/button-group";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Skeleton } from "@/components/ui/skeleton";
import { TabNav } from "@/components/ui/tabs";
import { formStatusBadge } from "@/lib/formBuilderStatus";

const { copy, copied } = useClipboard();

definePageMeta({
  layout: "app",
  middleware: ["sanctum:auth", "permission"],
  permissions: ["forms.read"],
  // Back control + section name in the app header instead of in the page body.
  // Static, so AppHeader can render it during SSR (see HeaderPageContext).
  headerContext: { back: "/forms", label: "Form Builder", to: "/forms" },
});

const route = useRoute();

const {
  data: formResponse,
  pending: initialLoading,
  error: formError,
  refresh: refreshForm,
} = await useLazySanctumFetch(() => `/api/forms/${route.params.slug}`, {
  key: `form-detail-${route.params.slug}`,
});

const form = computed(() => formResponse.value?.data || null);

const error = computed(() => {
  if (!formError.value) return null;
  const err = formError.value;
  if (err.statusCode === 404) return "Form not found";
  if (err.statusCode === 403) return "You do not have permission to view this form";
  return err.message || "Failed to load form";
});

usePageMeta(null, {
  title: computed(() => form.value?.title || "Form"),
});

const statusBadge = computed(() => formStatusBadge(form.value?.status));


const formBase = computed(() => `/forms/${route.params.slug}`);

// Editor is what respondents see (cover, title, description, layout, fields);
// Settings is how the form behaves. Splitting them is what makes room for the
// live preview beside the Editor.
const formTabs = computed(() => [
  { label: "Editor", to: formBase.value, exact: true },
  { label: "Settings", to: `${formBase.value}/settings` },
  { label: "Responses", to: `${formBase.value}/responses` },
  { label: "Analytics", to: `${formBase.value}/analytics` },
]);

const { pmoneUrl: publicFormUrl, eventUrl } = useFormPublicUrls(form);

const shareDialogOpen = ref(false);

useHeaderPageTitle(() => form.value?.title);
</script>
