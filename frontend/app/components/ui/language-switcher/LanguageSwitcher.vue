<template>
  <DropdownMenu>
    <DropdownMenuTrigger
      class="text-foreground hover:bg-muted relative flex size-8 items-center justify-center rounded-lg"
      aria-label="Switch language"
    >
      <Icon name="lucide:languages" class="size-4.5 shrink-0" />
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end">
      <DropdownMenuItem
        v-for="loc in locales"
        :key="loc.code"
        as-child
        class="cursor-pointer"
      >
        <NuxtLink
          :to="switchLocalePath(loc.code)"
          :hreflang="loc.language || loc.code"
          :lang="loc.language || loc.code"
          :aria-current="locale === loc.code ? 'true' : undefined"
        >
          <div class="flex w-full items-center justify-between gap-2">
            <span class="text-sm">{{ loc.name }}</span>
            <Icon
              v-if="locale === loc.code"
              name="lucide:check"
              class="size-4"
            />
          </div>
        </NuxtLink>
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup>
const { locale, locales } = useI18n();
const switchLocalePath = useSwitchLocalePath();
</script>
