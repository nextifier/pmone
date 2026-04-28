<script setup lang="ts">
import { computed } from "vue";

type LocaleValue = string | null | undefined;
type Translations = Record<string, LocaleValue>;

const props = withDefaults(
  defineProps<{
    modelValue: Translations | null | undefined;
    locales?: string[];
    defaultLocale?: string;
    requiredLocale?: string;
  }>(),
  {
    modelValue: () => ({}),
    locales: () => ["en", "id"],
    defaultLocale: "en",
    requiredLocale: "en",
  }
);

const emit = defineEmits<{
  "update:modelValue": [value: Translations];
}>();

const localeLabels: Record<string, string> = {
  en: "English",
  id: "Indonesia",
};

const activeLocale = ref(props.defaultLocale);

const value = computed<Translations>(() => props.modelValue ?? {});

function getLocale(locale: string): LocaleValue {
  return value.value?.[locale] ?? "";
}

function setLocale(locale: string, next: LocaleValue) {
  emit("update:modelValue", {
    ...(value.value ?? {}),
    [locale]: next,
  });
}

function hasContent(locale: string): boolean {
  const v = value.value?.[locale];
  if (v == null) return false;
  if (typeof v === "string") return v.trim().length > 0;
  return true;
}
</script>

<template>
  <div>
    <Tabs v-model="activeLocale" :default-value="defaultLocale">
      <TabsList class="bg-muted/50 mb-2 h-8 gap-1">
        <TabsTrigger
          v-for="locale in locales"
          :key="locale"
          :value="locale"
          class="data-[state=active]:bg-background data-[state=active]:text-foreground h-7 gap-1.5 rounded-md text-xs tracking-tight"
        >
          <span class="inline-flex items-center gap-1.5">
            <span>{{ localeLabels[locale] ?? locale.toUpperCase() }}</span>
            <span
              v-if="locale === requiredLocale"
              class="text-muted-foreground text-[10px]"
            >
              *
            </span>
            <span
              v-if="hasContent(locale)"
              class="bg-primary inline-block size-1.5 rounded-full"
            />
          </span>
        </TabsTrigger>
      </TabsList>

      <TabsContent
        v-for="locale in locales"
        :key="locale"
        :value="locale"
        class="mt-0 outline-none"
      >
        <slot
          :locale="locale"
          :model-value="getLocale(locale)"
          :update="(next: LocaleValue) => setLocale(locale, next)"
          :is-required="locale === requiredLocale"
          :is-default="locale === defaultLocale"
          :placeholder="
            locale !== defaultLocale && !hasContent(locale)
              ? `Falls back to ${localeLabels[defaultLocale] ?? defaultLocale}`
              : ''
          "
        />
      </TabsContent>
    </Tabs>
  </div>
</template>
