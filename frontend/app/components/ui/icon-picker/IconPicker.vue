<script setup lang="ts">
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { refDebounced, useLocalStorage } from "@vueuse/core";
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  shallowRef,
  triggerRef,
  watch,
} from "vue";

interface Props {
  modelValue?: string | null;
  prefix?: string;
  placeholder?: string;
  disabled?: boolean;
  popular?: string[];
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  prefix: "hugeicons,lucide",
  placeholder: "Pick an icon",
  disabled: false,
  popular: undefined,
});

const emit = defineEmits<{
  "update:modelValue": [value: string | null];
}>();

interface IconBatch {
  prefix: string;
  width?: number;
  height?: number;
  icons?: Record<string, { body: string; width?: number; height?: number }>;
}

interface SearchResponse {
  icons?: string[];
  total?: number;
}

const DEFAULT_POPULAR = [
  "hugeicons:notification-02",
  "hugeicons:megaphone-01",
  "hugeicons:calendar-04",
  "hugeicons:alert-circle",
  "hugeicons:information-circle",
  "hugeicons:checkmark-circle-02",
  "hugeicons:settings-01",
  "hugeicons:user",
  "hugeicons:user-group",
  "hugeicons:mail-01",
  "hugeicons:message-01",
  "hugeicons:search-01",
  "hugeicons:home-02",
  "hugeicons:dashboard-square-01",
  "hugeicons:tag-01",
  "hugeicons:star",
  "hugeicons:heart",
  "hugeicons:edit-02",
  "hugeicons:delete-01",
  "hugeicons:download-01",
  "hugeicons:upload-01",
  "hugeicons:link-04",
  "hugeicons:share-08",
  "hugeicons:gift",
];

const ICON_NAME_RE = /^[a-z0-9_-]+:[a-z0-9_-]+$/i;
const ICON_NAME_EXTRACT_RE = /([a-z0-9_-]+:[a-z0-9_-]+)/i;

// Module-level shared caches (across all IconPicker instances)
const SEARCH_CACHE_MAX = 50;
const ICON_CACHE_MAX = 500;
const searchCache = new Map<string, string[]>();
const sharedIconCache = shallowRef(new Map<string, string>());

function setLRU<K, V>(map: Map<K, V>, key: K, value: V, max: number) {
  if (map.has(key)) map.delete(key);
  map.set(key, value);
  while (map.size > max) {
    const oldest = map.keys().next().value;
    if (oldest === undefined) break;
    map.delete(oldest);
  }
}

// Per-instance state
let requestToken = 0;
let currentAbort: AbortController | null = null;

const open = ref(false);
const query = ref("");
const debouncedQuery = refDebounced(query, 250);
const results = ref<string[]>([]);
const loading = ref(false);
const filter = useLocalStorage<"all" | "hugeicons" | "lucide">(
  "pmone:icon-picker:filter",
  "all"
);
const focusedIndex = ref(-1);
const searchRef = ref<{ $el: HTMLInputElement } | null>(null);
const gridRef = ref<HTMLElement | null>(null);
const scrollAreaRef = ref<{ $el: HTMLElement } | null>(null);

const recent = useLocalStorage<string[]>("pmone:icon-picker:recent", []);
const iconCache = sharedIconCache;

const popularIcons = computed(() => props.popular ?? DEFAULT_POPULAR);

const activePrefixes = computed(() => {
  if (filter.value === "all") return props.prefix;
  return filter.value;
});

const cellsToShow = computed<string[]>(() => {
  if (query.value && results.value.length) return results.value;
  if (!query.value && recent.value.length) return recent.value;
  if (!query.value) return popularIcons.value;
  return [];
});

const showPopular = computed(
  () => !query.value && recent.value.length === 0 && popularIcons.value.length > 0
);

const showRecent = computed(() => !query.value && recent.value.length > 0);

const cachedTriggerSvg = computed(() => {
  if (!props.modelValue) return null;
  return iconCache.value.get(props.modelValue) || null;
});

const isInvalidFormat = computed(() => {
  if (!props.modelValue) return false;
  return !ICON_NAME_RE.test(props.modelValue);
});

watch([debouncedQuery, filter], async ([q]) => {
  currentAbort?.abort();

  if (!q || q.length < 2) {
    loading.value = false;
    results.value = [];
    focusedIndex.value = -1;
    return;
  }

  const cacheKey = `${q}::${activePrefixes.value}`;
  const cached = searchCache.get(cacheKey);
  if (cached) {
    loading.value = false;
    results.value = cached;
    focusedIndex.value = cached.length > 0 ? 0 : -1;
    await preloadIcons(cached);
    return;
  }

  const myToken = ++requestToken;
  const controller = new AbortController();
  currentAbort = controller;
  loading.value = true;
  focusedIndex.value = -1;
  try {
    const data = await $fetch<SearchResponse>("https://api.iconify.design/search", {
      params: { query: q, limit: 120, prefixes: activePrefixes.value },
      signal: controller.signal,
    });
    if (myToken !== requestToken) return;
    const icons = Array.isArray(data?.icons) ? data.icons : [];
    setLRU(searchCache, cacheKey, icons, SEARCH_CACHE_MAX);
    results.value = icons;
    focusedIndex.value = icons.length > 0 ? 0 : -1;
    await preloadIcons(icons);
  } catch (err: unknown) {
    if (err instanceof Error && err.name === "AbortError") return;
    if (myToken !== requestToken) return;
    results.value = [];
    focusedIndex.value = -1;
  } finally {
    if (myToken === requestToken) {
      loading.value = false;
    }
  }
});

watch(query, (q) => {
  if (!q || q.length < 2) return;
  const cacheKey = `${q}::${activePrefixes.value}`;
  if (searchCache.has(cacheKey)) return;
  loading.value = true;
});

// Reset scroll position when results change
watch(results, (newResults) => {
  if (newResults.length === 0) return;
  nextTick(() => {
    const root = scrollAreaRef.value?.$el;
    const viewport =
      root?.querySelector?.("[data-reka-scroll-area-viewport]") ||
      root?.querySelector?.("[data-radix-scroll-area-viewport]") ||
      (root?.firstElementChild as HTMLElement | null);
    if (viewport) (viewport as HTMLElement).scrollTop = 0;
  });
});

watch(open, (isOpen) => {
  if (isOpen) {
    void preloadIcons(cellsToShow.value);
    nextTick(() => {
      const el = searchRef.value?.$el;
      if (el?.tagName === "INPUT") {
        el.focus();
      } else {
        el?.querySelector?.("input")?.focus();
      }
    });
  } else {
    currentAbort?.abort();
    query.value = "";
    results.value = [];
    loading.value = false;
    focusedIndex.value = -1;
  }
});

onMounted(() => {
  void preloadIcons(popularIcons.value);
  if (props.modelValue) {
    void preloadIcons([props.modelValue]);
  }
});

onBeforeUnmount(() => {
  currentAbort?.abort();
});

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue && !iconCache.value.has(newValue)) {
      void preloadIcons([newValue]);
    }
  }
);

async function preloadIcons(iconNames: string[]) {
  const byPrefix = new Map<string, string[]>();
  for (const full of iconNames) {
    if (!full || iconCache.value.has(full)) continue;
    const colonIdx = full.indexOf(":");
    if (colonIdx === -1) continue;
    const prefix = full.slice(0, colonIdx);
    const name = full.slice(colonIdx + 1);
    if (!byPrefix.has(prefix)) byPrefix.set(prefix, []);
    byPrefix.get(prefix)!.push(name);
  }

  if (byPrefix.size === 0) return;

  await Promise.all(
    Array.from(byPrefix.entries()).map(async ([prefix, names]) => {
      try {
        const data = await $fetch<IconBatch>(`https://api.iconify.design/${prefix}.json`, {
          params: { icons: names.join(",") },
        });
        const dw = data.width || 24;
        const dh = data.height || 24;
        for (const name in data.icons || {}) {
          const icon = data.icons![name];
          const w = icon.width || dw;
          const h = icon.height || dh;
          const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="100%" height="100%">${icon.body}</svg>`;
          setLRU(iconCache.value, `${prefix}:${name}`, svg, ICON_CACHE_MAX);
        }
      } catch {
        // silently ignore — empty cells will show fallback
      }
    })
  );

  triggerRef(iconCache);
}

function pick(name: string) {
  emit("update:modelValue", name);
  recent.value = [name, ...recent.value.filter((n) => n !== name)].slice(0, 12);
  open.value = false;
}

function clear() {
  emit("update:modelValue", null);
  open.value = false;
}

function clearRecent() {
  recent.value = [];
}

function removeRecent(name: string) {
  recent.value = recent.value.filter((n) => n !== name);
}

function handlePaste(e: ClipboardEvent) {
  const text = e.clipboardData?.getData("text") || "";
  const match = text.match(ICON_NAME_EXTRACT_RE);
  if (match && match[1]) {
    e.preventDefault();
    pick(match[1]);
  }
}

function handleKeyDown(e: KeyboardEvent) {
  const list = cellsToShow.value;
  if (!list.length) return;

  const cols = 6;
  const fromInput = e.target instanceof HTMLInputElement;

  if (e.key === "ArrowDown") {
    e.preventDefault();
    const next = focusedIndex.value < 0 ? 0 : focusedIndex.value + cols;
    focusedIndex.value = next >= list.length ? 0 : next;
  } else if (e.key === "ArrowUp") {
    e.preventDefault();
    const next = focusedIndex.value - cols;
    focusedIndex.value = next < 0 ? list.length - 1 : next;
  } else if (e.key === "ArrowRight" && !fromInput) {
    e.preventDefault();
    const next = focusedIndex.value < 0 ? 0 : focusedIndex.value + 1;
    focusedIndex.value = next >= list.length ? 0 : next;
  } else if (e.key === "ArrowLeft" && !fromInput) {
    e.preventDefault();
    const next = focusedIndex.value - 1;
    focusedIndex.value = next < 0 ? list.length - 1 : next;
  } else if (e.key === "Enter") {
    if (focusedIndex.value < 0 || focusedIndex.value >= list.length) return;
    e.preventDefault();
    pick(list[focusedIndex.value]!);
  }
}

function cellClass(name: string, idx: number) {
  const isSelected = name === props.modelValue;
  const isFocused = idx === focusedIndex.value;
  return cn(
    "hover:bg-muted aspect-square flex items-center justify-center rounded-md transition-colors text-foreground",
    isSelected && !isFocused && "bg-muted ring-1 ring-primary/40",
    isFocused && "bg-muted ring-1 ring-ring",
    isSelected && isFocused && "ring-primary"
  );
}

watch(focusedIndex, (idx) => {
  if (idx < 0) return;
  nextTick(() => {
    const cells = gridRef.value?.querySelectorAll<HTMLElement>("[data-icon-cell]");
    cells?.[idx]?.scrollIntoView({ block: "nearest" });
  });
});
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <button
        type="button"
        :disabled="disabled"
        :aria-label="modelValue || placeholder"
        :class="
          cn(
            'border-border bg-transparent dark:bg-background hover:bg-muted flex h-9 w-full items-center gap-x-2 rounded-md border px-3 text-sm tracking-tight shadow-xs transition-colors',
            'focus-visible:border-ring focus-visible:ring-ring outline-none focus-visible:ring-[1px]',
            'disabled:pointer-events-none disabled:opacity-50',
            !modelValue && 'text-muted-foreground'
          )
        "
      >
        <Icon
          v-if="modelValue && isInvalidFormat"
          name="hugeicons:alert-circle"
          class="text-warning size-4 shrink-0"
        />
        <span
          v-else-if="modelValue && cachedTriggerSvg"
          class="text-foreground size-4 shrink-0"
          v-html="cachedTriggerSvg"
        />
        <Icon
          v-else-if="modelValue"
          :name="modelValue"
          class="text-foreground size-4 shrink-0"
        />
        <Icon v-else name="hugeicons:dashboard-square-01" class="size-4 shrink-0" />
        <span class="truncate">{{ modelValue || placeholder }}</span>
        <Icon
          name="lucide:chevron-down"
          class="text-muted-foreground ml-auto size-3.5 shrink-0"
        />
      </button>
    </PopoverTrigger>

    <PopoverContent
      class="w-[min(20rem,calc(100vw-2rem))] p-0"
      align="start"
      @keydown="handleKeyDown"
    >
      <div class="border-border space-y-2 border-b p-2">
        <Input
          ref="searchRef"
          v-model="query"
          placeholder="Search icons or paste prefix:name"
          class="h-8"
          @paste="handlePaste"
        />

        <div class="flex gap-1">
          <button
            v-for="opt in [
              { value: 'all' as const, label: 'All' },
              { value: 'hugeicons' as const, label: 'Hugeicons' },
              { value: 'lucide' as const, label: 'Lucide' },
            ]"
            :key="opt.value"
            type="button"
            :class="
              cn(
                'rounded-md px-2 py-0.5 text-sm font-medium tracking-tight transition-colors',
                filter === opt.value
                  ? 'bg-foreground text-background'
                  : 'text-muted-foreground hover:bg-muted'
              )
            "
            @click="filter = opt.value"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <ScrollArea ref="scrollAreaRef" class="h-[280px]">
        <!-- Loading skeleton -->
        <div v-if="loading" class="grid grid-cols-6 gap-1 p-2">
          <Skeleton v-for="i in 24" :key="i" class="aspect-square rounded-md" />
        </div>

        <!-- Search results -->
        <div v-else-if="query && results.length">
          <div class="text-muted-foreground px-3 pt-2.5 text-sm tracking-tight">
            {{ results.length }} {{ results.length === 1 ? "result" : "results" }}
          </div>
          <div ref="gridRef" class="grid grid-cols-6 gap-1 p-2" role="listbox">
            <button
              v-for="(name, idx) in results"
              :key="name"
              type="button"
              data-icon-cell
              :title="name"
              :aria-label="name"
              :aria-selected="name === modelValue"
              :class="cellClass(name, idx)"
              @click="pick(name)"
            >
              <span v-if="iconCache.get(name)" class="block size-6" v-html="iconCache.get(name)" />
              <span v-else class="bg-muted/40 block size-6 animate-pulse rounded" />
            </button>
          </div>
        </div>

        <!-- No results -->
        <div
          v-else-if="query && !loading && !results.length"
          class="text-muted-foreground p-6 text-center text-sm tracking-tight"
        >
          <Icon
            name="hugeicons:search-remove"
            class="text-muted-foreground/50 mx-auto mb-2 size-8"
          />
          <p>No icons found</p>
          <p class="text-muted-foreground/70 mt-1 text-sm">Try a different keyword</p>
        </div>

        <!-- Recent -->
        <div v-else-if="showRecent">
          <div
            class="text-muted-foreground flex items-center justify-between px-3 pt-2.5 text-sm tracking-tight"
          >
            <span>Recent</span>
            <button type="button" class="hover:text-foreground tracking-tight" @click="clearRecent">
              Clear
            </button>
          </div>
          <div ref="gridRef" class="grid grid-cols-6 gap-1 p-2" role="listbox">
            <div
              v-for="(name, idx) in recent"
              :key="name"
              :class="['group/recent relative', focusedIndex === idx && 'z-10']"
            >
              <button
                type="button"
                data-icon-cell
                :title="name"
                :aria-label="name"
                :aria-selected="name === modelValue"
                :class="cellClass(name, idx)"
                class="w-full"
                @click="pick(name)"
              >
                <span
                  v-if="iconCache.get(name)"
                  class="block size-6"
                  v-html="iconCache.get(name)"
                />
                <span v-else class="bg-muted/40 block size-6 animate-pulse rounded" />
              </button>
              <button
                type="button"
                aria-label="Remove from recent"
                class="bg-card border-border text-muted-foreground hover:text-destructive absolute -top-1 -right-1 hidden size-4 items-center justify-center rounded-full border shadow-sm group-hover/recent:flex"
                @click.stop="removeRecent(name)"
              >
                <Icon name="lucide:x" class="size-2.5" />
              </button>
            </div>
          </div>
        </div>

        <!-- Popular (initial state, no recent) -->
        <div v-else-if="showPopular">
          <div class="text-muted-foreground px-3 pt-2.5 text-sm tracking-tight">Popular</div>
          <div ref="gridRef" class="grid grid-cols-6 gap-1 p-2" role="listbox">
            <button
              v-for="(name, idx) in popularIcons"
              :key="name"
              type="button"
              data-icon-cell
              :title="name"
              :aria-label="name"
              :aria-selected="name === modelValue"
              :class="cellClass(name, idx)"
              @click="pick(name)"
            >
              <span v-if="iconCache.get(name)" class="block size-6" v-html="iconCache.get(name)" />
              <span v-else class="bg-muted/40 block size-6 animate-pulse rounded" />
            </button>
          </div>
        </div>

        <!-- True empty (shouldn't normally hit) -->
        <div v-else class="text-muted-foreground p-6 text-center text-sm tracking-tight">
          Type to search
        </div>
      </ScrollArea>

      <div
        v-if="modelValue"
        class="border-border flex items-center justify-between gap-2 border-t px-3 py-2"
      >
        <span class="text-muted-foreground truncate text-sm tracking-tight">
          {{ modelValue }}
        </span>
        <button
          type="button"
          class="text-muted-foreground hover:text-destructive text-sm tracking-tight"
          @click="clear"
        >
          Clear
        </button>
      </div>
    </PopoverContent>
  </Popover>
</template>
