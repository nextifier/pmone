<template>
  <DropdownMenu v-if="options.length > 1">
    <DropdownMenuTrigger
      class="text-foreground hover:bg-muted relative flex size-8 items-center justify-center rounded-lg"
      aria-label="Switch language"
    >
      <Icon name="lucide:languages" class="size-4.5 shrink-0" />
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end">
      <DropdownMenuItem
        v-for="loc in options"
        :key="loc.code"
        class="cursor-pointer"
        :lang="loc.language || loc.code"
        :aria-current="locale === loc.code ? 'true' : undefined"
        @select="setLocale(loc.code)"
      >
        <div class="flex w-full items-center justify-between gap-2">
          <span class="text-sm">{{ loc.name }}</span>
          <Icon v-if="locale === loc.code" name="lucide:check" class="size-4" />
        </div>
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup>
/**
 * `setLocale` rather than a NuxtLink to `switchLocalePath()`. The link form only
 * works under a prefixed routing strategy: under `no_prefix` (pmone)
 * switchLocalePath returns the path you are already on, so the router treated
 * every choice as a same-route no-op and the language never actually changed.
 * setLocale covers both strategies - it navigates where a prefix is involved and
 * writes the cookie where one is not.
 */
const props = defineProps({
  /**
   * Restrict the menu to these locale codes. Use it wherever only some of the
   * app's locales are actually translated - a public form only carries the
   * languages its fields were authored in. Omit to offer every configured
   * locale. With fewer than two options left there is nothing to switch
   * between, so the trigger hides itself rather than opening a one-item menu.
   */
  codes: { type: Array, default: null },
});

const { locale, locales, setLocale } = useI18n();

const options = computed(() =>
  props.codes ? locales.value.filter((loc) => props.codes.includes(loc.code)) : locales.value
);
</script>
