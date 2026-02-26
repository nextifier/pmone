<template>
  <DropdownMenu>
    <DropdownMenuTrigger
      class="text-primary hover:bg-muted relative flex size-8 items-center justify-center rounded-lg"
      aria-label="Switch Language"
    >
      <Icon name="lucide:languages" class="size-4.5 shrink-0" />
      <!-- <span class="text-sm font-medium tracking-wide uppercase">
          {{ currentLocale }}
        </span> -->
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end">
      <DropdownMenuItem
        v-for="loc in availableLocales"
        :key="loc.code"
        class="cursor-pointer"
        @click="switchLanguage(loc.code)"
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
const { locale, locales, setLocale } = useI18n();
const switchLocalePath = useSwitchLocalePath();
const router = useRouter();

const availableLocales = computed(() => locales.value);

async function switchLanguage(code) {
  await setLocale(code);
  const path = switchLocalePath(code);
  if (path) {
    await router.push(path);
  }
}

const currentLocale = computed({
  get: () => locale.value,
  set: async (value) => {
    // setLocale untuk update cookie dan state
    await setLocale(value);
    // navigasi ke URL yang sesuai dengan locale baru
    const path = switchLocalePath(value);
    if (path) {
      await router.push(path);
    }
  },
});
</script>
