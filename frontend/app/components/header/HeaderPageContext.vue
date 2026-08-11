<template>
  <div class="flex min-w-0 grow items-center gap-x-2 overflow-hidden sm:gap-x-2.5">
    <HeaderBackButton :destination="context.back" force-destination />

    <div class="no-scrollbar scroll-fade-x flex min-w-0 shrink grow items-center gap-x-1 overflow-auto">
      <NuxtLink
        v-if="context.to"
        :to="context.to"
        class="shrink-0 truncate text-sm font-medium tracking-tight decoration-dotted decoration-2 underline-offset-4 hover:underline"
      >
        {{ context.label }}
      </NuxtLink>
      <span v-else class="shrink-0 truncate text-sm font-medium tracking-tight">
        {{ context.label }}
      </span>

      <!--
        The record's name arrives with a fetch, so it is client-only — exactly
        how HeaderBreadcrumb handles the project/event chain. Rendering it on the
        server too would mean an empty string there and a name here, which is a
        hydration mismatch, and reka's `useId` counter drifts off the back of one
        of those and mismatches every id below it.
      -->
      <ClientOnly>
        <template v-if="pageTitle">
          <Icon
            name="hugeicons:arrow-right-01"
            class="text-muted-foreground size-3.5 shrink-0"
          />
          <span class="text-muted-foreground truncate text-sm font-medium tracking-tight">
            {{ pageTitle }}
          </span>
        </template>
      </ClientOnly>
    </div>
  </div>
</template>

<script setup>
/**
 * The lightweight sibling of HeaderBreadcrumb, for pages outside the
 * project/event/brand hierarchy that still want the back control and their
 * context in the app header rather than repeated inside the page body.
 *
 * A page opts in with `definePageMeta({ headerContext: { back, label, to } })`
 * and, if it has a record to name, `useHeaderPageTitle(...)`.
 */
defineProps({
  /** `{ back, label, to? }` from the route's `headerContext` meta. */
  context: { type: Object, required: true },
});

const pageTitle = useState("header-page-title", () => null);
</script>
