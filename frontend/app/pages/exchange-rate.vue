<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:dollar-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Exchange Rates</h1>
      </div>

      <div class="ml-auto flex shrink-0 items-center gap-2">
        <!-- Base Currency Select (searchable) -->
        <Popover v-model:open="baseOpen">
          <PopoverTrigger as-child>
            <button
              class="border-border hover:bg-muted flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-sm tracking-tight active:scale-98"
            >
              <span class="text-muted-foreground text-xs">Base:</span>
              <FlagComponent
                v-if="getCurrencyCountry(baseCurrency)"
                :country="getCurrencyCountry(baseCurrency)"
                class="size-4"
              />
              <span class="font-medium">{{ baseCurrency }}</span>
              <Icon name="lucide:chevrons-up-down" class="text-muted-foreground size-3.5" />
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-[280px] p-0" align="end">
            <Command>
              <CommandInput placeholder="Search currency..." />
              <CommandEmpty>No currency found.</CommandEmpty>
              <CommandList>
                <CommandGroup>
                  <CommandItem
                    v-for="currency in currencies"
                    :key="currency.code"
                    :value="`${currency.code} ${currency.name}`"
                    class="gap-2"
                    @select="
                      () => {
                        baseCurrency = currency.code;
                        handleBaseCurrencyChange(currency.code);
                        baseOpen = false;
                      }
                    "
                  >
                    <FlagComponent :country="currency.country" :country-name="currency.name" />
                    <span class="font-medium">{{ currency.code }}</span>
                    <span class="text-muted-foreground flex-1 truncate text-xs">{{ currency.name }}</span>
                    <Icon
                      v-if="baseCurrency === currency.code"
                      name="lucide:check"
                      class="text-primary size-4 shrink-0"
                    />
                  </CommandItem>
                </CommandGroup>
              </CommandList>
            </Command>
          </PopoverContent>
        </Popover>

        <!-- Refresh Button -->
        <button
          @click="fetchRates"
          :disabled="loading"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Icon name="lucide:refresh-cw" class="size-4 shrink-0" :class="{ 'animate-spin': loading }" />
          <span class="hidden sm:inline">Refresh</span>
        </button>
      </div>
    </div>

    <!-- Last Updated Info -->
    <div v-if="meta?.fetched_at" class="text-muted-foreground flex items-center gap-1.5 text-xs tracking-tight">
      <Icon name="lucide:clock" class="size-3.5" />
      <span>Last updated {{ formatRelativeTime(meta.fetched_at) }}</span>
      <span v-if="meta?.is_stale" class="text-amber-500">(stale)</span>
    </div>

    <!-- Calculator Card -->
    <div class="rounded-lg border p-4 sm:p-5">
      <div class="mb-4 flex items-center gap-2">
        <Icon name="lucide:calculator" class="text-muted-foreground size-4" />
        <span class="text-sm font-medium tracking-tight">Currency Converter</span>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <!-- Amount Input -->
        <div class="flex-1 space-y-1.5">
          <label class="text-muted-foreground text-xs font-medium tracking-tight">Amount</label>
          <Input
            v-model="calculatorAmount"
            type="number"
            placeholder="Enter amount"
            @input="calculateConversion"
          />
        </div>

        <!-- From Currency (searchable) -->
        <div class="flex-1 space-y-1.5">
          <label class="text-muted-foreground text-xs font-medium tracking-tight">From</label>
          <Popover v-model:open="fromOpen">
            <PopoverTrigger as-child>
              <button
                class="border-border data-[placeholder]:text-muted-foreground flex h-9 w-full items-center gap-1.5 rounded-md border bg-transparent px-3 text-sm tracking-tight shadow-xs"
              >
                <template v-if="calculatorFrom">
                  <FlagComponent
                    :country="getCurrencyCountry(calculatorFrom)"
                    class="size-4 shrink-0"
                  />
                  <span class="font-medium">{{ calculatorFrom }}</span>
                  <span class="text-muted-foreground truncate text-xs">{{ getCurrencyName(calculatorFrom) }}</span>
                </template>
                <span v-else class="text-muted-foreground">Select currency</span>
                <Icon name="lucide:chevrons-up-down" class="text-muted-foreground ml-auto size-3.5 shrink-0" />
              </button>
            </PopoverTrigger>
            <PopoverContent class="w-[280px] p-0">
              <Command>
                <CommandInput placeholder="Search currency..." />
                <CommandEmpty>No currency found.</CommandEmpty>
                <CommandList>
                  <CommandGroup>
                    <CommandItem
                      v-for="currency in currencies"
                      :key="currency.code"
                      :value="`${currency.code} ${currency.name}`"
                      class="gap-2"
                      @select="
                        () => {
                          calculatorFrom = currency.code;
                          calculateConversion();
                          fromOpen = false;
                        }
                      "
                    >
                      <FlagComponent :country="currency.country" :country-name="currency.name" />
                      <span class="font-medium">{{ currency.code }}</span>
                      <span class="text-muted-foreground flex-1 truncate text-xs">{{ currency.name }}</span>
                      <Icon
                        v-if="calculatorFrom === currency.code"
                        name="lucide:check"
                        class="text-primary size-4 shrink-0"
                      />
                    </CommandItem>
                  </CommandGroup>
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>
        </div>

        <!-- Swap Button -->
        <button
          @click="swapCurrencies"
          class="border-border hover:bg-muted flex size-9 shrink-0 items-center justify-center self-end rounded-md border active:scale-98"
        >
          <Icon name="lucide:arrow-right-left" class="size-4" />
        </button>

        <!-- To Currency (searchable) -->
        <div class="flex-1 space-y-1.5">
          <label class="text-muted-foreground text-xs font-medium tracking-tight">To</label>
          <Popover v-model:open="toOpen">
            <PopoverTrigger as-child>
              <button
                class="border-border data-[placeholder]:text-muted-foreground flex h-9 w-full items-center gap-1.5 rounded-md border bg-transparent px-3 text-sm tracking-tight shadow-xs"
              >
                <template v-if="calculatorTo">
                  <FlagComponent
                    :country="getCurrencyCountry(calculatorTo)"
                    class="size-4 shrink-0"
                  />
                  <span class="font-medium">{{ calculatorTo }}</span>
                  <span class="text-muted-foreground truncate text-xs">{{ getCurrencyName(calculatorTo) }}</span>
                </template>
                <span v-else class="text-muted-foreground">Select currency</span>
                <Icon name="lucide:chevrons-up-down" class="text-muted-foreground ml-auto size-3.5 shrink-0" />
              </button>
            </PopoverTrigger>
            <PopoverContent class="w-[280px] p-0">
              <Command>
                <CommandInput placeholder="Search currency..." />
                <CommandEmpty>No currency found.</CommandEmpty>
                <CommandList>
                  <CommandGroup>
                    <CommandItem
                      v-for="currency in currencies"
                      :key="currency.code"
                      :value="`${currency.code} ${currency.name}`"
                      class="gap-2"
                      @select="
                        () => {
                          calculatorTo = currency.code;
                          calculateConversion();
                          toOpen = false;
                        }
                      "
                    >
                      <FlagComponent :country="currency.country" :country-name="currency.name" />
                      <span class="font-medium">{{ currency.code }}</span>
                      <span class="text-muted-foreground flex-1 truncate text-xs">{{ currency.name }}</span>
                      <Icon
                        v-if="calculatorTo === currency.code"
                        name="lucide:check"
                        class="text-primary size-4 shrink-0"
                      />
                    </CommandItem>
                  </CommandGroup>
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>
        </div>
      </div>

      <!-- Conversion Result -->
      <div v-if="conversionResult !== null" class="bg-muted/50 mt-4 rounded-md p-3 text-center">
        <div class="text-muted-foreground text-xs tracking-tight">
          {{ formatNumber(calculatorAmount) }} {{ calculatorFrom }} =
        </div>
        <div class="text-primary mt-0.5 text-xl font-semibold tracking-tight">
          {{ formatNumber(conversionResult) }} {{ calculatorTo }}
        </div>
        <div v-if="conversionRate" class="text-muted-foreground mt-1 text-xs tracking-tight">
          1 {{ calculatorFrom }} = {{ formatNumber(conversionRate, 6) }} {{ calculatorTo }}
        </div>
      </div>
    </div>

    <!-- Search -->
    <div class="relative">
      <Icon name="lucide:search" class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
      <Input v-model="searchQuery" placeholder="Search currencies (e.g. IDR, Rupiah, USD)..." class="pl-10" />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="space-y-3">
      <Skeleton v-for="i in 8" :key="i" class="h-14 w-full rounded-lg" />
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="rounded-lg border border-destructive/50 bg-destructive/10 p-6 text-center">
      <Icon name="lucide:alert-circle" class="text-destructive mx-auto mb-2 size-8" />
      <p class="text-destructive text-sm font-medium tracking-tight">Failed to load exchange rates</p>
      <p class="text-muted-foreground mt-1 text-xs tracking-tight">{{ error }}</p>
      <button
        @click="fetchRates"
        class="border-border hover:bg-muted mt-4 inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
      >
        <Icon name="lucide:refresh-cw" class="size-3.5" />
        <span>Retry</span>
      </button>
    </div>

    <!-- Rates Content -->
    <div v-else>
      <!-- Favorites Section -->
      <div v-if="!searchQuery && favoriteCurrencies.length > 0" class="mb-6">
        <div class="text-muted-foreground mb-3 text-xs font-medium uppercase tracking-wider">
          Favorites
        </div>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="rate in favoriteCurrencies"
            :key="rate.code"
            class="hover:border-primary/30 group flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
            @click="selectCurrency(rate.code)"
          >
            <FlagComponent :country="rate.country" :country-name="rate.name" class="size-7 rounded-sm" />
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <span class="text-sm font-medium tracking-tight">{{ rate.code }}</span>
                <span class="text-muted-foreground truncate text-xs">{{ rate.name }}</span>
              </div>
              <div class="text-primary text-sm font-medium tabular-nums tracking-tight">
                {{ baseCurrency }} {{ formatNumber(rate.rate) }}
              </div>
            </div>
            <button
              @click.stop="toggleFavorite(rate.code)"
              class="text-amber-400 opacity-60 transition-opacity hover:opacity-100"
            >
              <Icon name="lucide:star" class="size-4 fill-current" />
            </button>
          </div>
        </div>
      </div>

      <!-- Popular Section -->
      <div v-if="!searchQuery && popularRates.length > 0" class="mb-6">
        <div class="text-muted-foreground mb-3 text-xs font-medium uppercase tracking-wider">
          Popular Currencies
        </div>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="rate in popularRates"
            :key="rate.code"
            class="hover:border-primary/30 group flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
            @click="selectCurrency(rate.code)"
          >
            <FlagComponent :country="rate.country" :country-name="rate.name" class="size-7 rounded-sm" />
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <span class="text-sm font-medium tracking-tight">{{ rate.code }}</span>
                <span class="text-muted-foreground truncate text-xs">{{ rate.name }}</span>
              </div>
              <div class="text-primary text-sm font-medium tabular-nums tracking-tight">
                {{ baseCurrency }} {{ formatNumber(rate.rate) }}
              </div>
            </div>
            <button
              @click.stop="toggleFavorite(rate.code)"
              class="opacity-0 transition-opacity group-hover:opacity-60 hover:!opacity-100"
              :class="{ '!opacity-60 text-amber-400': isFavorite(rate.code) }"
            >
              <Icon
                name="lucide:star"
                class="size-4"
                :class="{ 'fill-current text-amber-400': isFavorite(rate.code) }"
              />
            </button>
          </div>
        </div>
      </div>

      <!-- All Currencies -->
      <div>
        <div class="text-muted-foreground mb-3 text-xs font-medium uppercase tracking-wider">
          {{ searchQuery ? `Search Results (${filteredRates.length})` : 'All Currencies' }}
        </div>

        <div v-if="filteredRates.length === 0" class="bg-muted/50 rounded-lg border p-8 text-center">
          <Icon name="lucide:search-x" class="text-muted-foreground mx-auto mb-2 size-8" />
          <p class="text-muted-foreground text-sm tracking-tight">
            No currencies found for "{{ searchQuery }}"
          </p>
        </div>

        <div v-else class="divide-y rounded-lg border">
          <div
            v-for="rate in filteredRates"
            :key="rate.code"
            class="hover:bg-muted/50 group flex cursor-pointer items-center gap-3 px-3 py-2.5 transition-colors"
            @click="selectCurrency(rate.code)"
          >
            <FlagComponent :country="rate.country" :country-name="rate.name" class="size-6 shrink-0 rounded-sm" />
            <div class="min-w-14">
              <span class="text-sm font-medium tracking-tight">{{ rate.code }}</span>
            </div>
            <div class="text-muted-foreground min-w-0 flex-1 truncate text-sm tracking-tight">
              {{ rate.name }}
            </div>
            <button
              @click.stop="toggleFavorite(rate.code)"
              class="opacity-0 transition-opacity group-hover:opacity-60 hover:!opacity-100"
              :class="{ '!opacity-60 text-amber-400': isFavorite(rate.code) }"
            >
              <Icon
                name="lucide:star"
                class="size-3.5"
                :class="{ 'fill-current text-amber-400': isFavorite(rate.code) }"
              />
            </button>
            <div class="text-primary text-sm font-medium tabular-nums tracking-tight">
              <span class="text-muted-foreground font-normal">{{ baseCurrency }}</span>
              {{ formatNumber(rate.rate) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Count Info -->
      <div v-if="ratesCount > 0" class="text-muted-foreground mt-4 text-center text-xs tracking-tight">
        Showing {{ filteredRates.length }} of {{ ratesCount }} currencies
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

defineOptions({
  name: "exchange-rate",
});

usePageMeta("exchange-rate");

const config = useRuntimeConfig();

// LocalStorage keys
const STORAGE_KEY_BASE = "exchange_rate_base_currency";
const STORAGE_KEY_FAVORITES = "exchange_rate_favorites";
const STORAGE_KEY_FROM = "exchange_rate_from";
const STORAGE_KEY_TO = "exchange_rate_to";

// State
const loading = ref(true);
const error = ref(null);
const rates = ref([]);
const meta = ref(null);
const ratesCount = ref(0);
const searchQuery = ref("");

// Popover open states
const baseOpen = ref(false);
const fromOpen = ref(false);
const toOpen = ref(false);

// Initialize with SSR-safe defaults (localStorage read deferred to onMounted)
const baseCurrency = ref("USD");
const favorites = ref([]);
const calculatorAmount = ref(1);
const calculatorFrom = ref("USD");
const calculatorTo = ref("IDR");
const conversionResult = ref(null);
const conversionRate = ref(null);

// Helper to get stored value (client-only)
function getStoredValue(key, fallback) {
  try {
    const stored = localStorage.getItem(key);
    if (stored === null) return fallback;
    return JSON.parse(stored);
  } catch {
    return fallback;
  }
}

// Helper to store value (client-only)
function storeValue(key, value) {
  if (import.meta.server) return;
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // ignore
  }
}

// Currency metadata map (built from rates)
const currencyMap = computed(() => {
  const map = {};
  for (const r of rates.value) {
    map[r.code] = r;
  }
  return map;
});

function getCurrencyCountry(code) {
  return currencyMap.value[code]?.country || code.slice(0, 2).toLowerCase();
}

function getCurrencyName(code) {
  return currencyMap.value[code]?.name || code;
}

// Computed
const currencies = computed(() => {
  return rates.value.map((r) => ({
    code: r.code,
    name: r.name,
    country: r.country,
  }));
});

const popularRates = computed(() => {
  return rates.value.filter((r) => r.is_popular && !favorites.value.includes(r.code));
});

const favoriteCurrencies = computed(() => {
  return rates.value.filter((r) => favorites.value.includes(r.code));
});

const filteredRates = computed(() => {
  if (!searchQuery.value) {
    return rates.value.filter((r) => !r.is_popular && !favorites.value.includes(r.code));
  }
  const query = searchQuery.value.toLowerCase();
  return rates.value.filter(
    (r) => r.code.toLowerCase().includes(query) || r.name.toLowerCase().includes(query)
  );
});

// Favorites
function isFavorite(code) {
  return favorites.value.includes(code);
}

function toggleFavorite(code) {
  const idx = favorites.value.indexOf(code);
  if (idx >= 0) {
    favorites.value.splice(idx, 1);
  } else {
    favorites.value.push(code);
  }
  storeValue(STORAGE_KEY_FAVORITES, favorites.value);
}

// Base currency change - refetch with new base
function handleBaseCurrencyChange(value) {
  storeValue(STORAGE_KEY_BASE, value);
  fetchRates();
}

// Methods
const fetchRates = async () => {
  loading.value = true;
  error.value = null;

  try {
    const params = new URLSearchParams();
    if (baseCurrency.value && baseCurrency.value !== "USD") {
      params.append("base", baseCurrency.value);
    }
    const qs = params.toString();
    const response = await $fetch(`${config.public.apiUrl}/api/exchange-rates${qs ? `?${qs}` : ""}`);
    rates.value = response.data.rates;
    ratesCount.value = response.data.rates_count;
    meta.value = response.meta;

    calculateConversion();
  } catch (e) {
    error.value = e.message || "An error occurred while loading data";
  } finally {
    loading.value = false;
  }
};

const calculateConversion = () => {
  if (!calculatorAmount.value || !calculatorFrom.value || !calculatorTo.value) {
    conversionResult.value = null;
    conversionRate.value = null;
    return;
  }

  const fromRate = rates.value.find((r) => r.code === calculatorFrom.value)?.rate;
  const toRate = rates.value.find((r) => r.code === calculatorTo.value)?.rate;

  if (fromRate && toRate) {
    const inBase = calculatorAmount.value / fromRate;
    conversionResult.value = inBase * toRate;
    conversionRate.value = toRate / fromRate;
  }

  // Persist selections
  storeValue(STORAGE_KEY_FROM, calculatorFrom.value);
  storeValue(STORAGE_KEY_TO, calculatorTo.value);
};

const swapCurrencies = () => {
  const temp = calculatorFrom.value;
  calculatorFrom.value = calculatorTo.value;
  calculatorTo.value = temp;
  calculateConversion();
};

const selectCurrency = (code) => {
  calculatorTo.value = code;
  calculateConversion();
  window.scrollTo({ top: 0, behavior: "smooth" });
};

const formatNumber = (num, decimals) => {
  if (num === null || num === undefined) return "-";

  // Auto-detect appropriate decimal places if not specified
  if (decimals === undefined) {
    if (num === 0) decimals = 2;
    else if (Math.abs(num) >= 1000) decimals = 2;
    else if (Math.abs(num) >= 1) decimals = 4;
    else if (Math.abs(num) >= 0.01) decimals = 6;
    else decimals = 8;
  }

  return new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 0,
    maximumFractionDigits: decimals,
  }).format(num);
};

const formatRelativeTime = (dateStr) => {
  if (!dateStr) return "-";
  const now = new Date();
  const date = new Date(dateStr);
  const diffMs = now - date;
  const diffMin = Math.floor(diffMs / 60000);

  if (diffMin < 1) return "just now";
  if (diffMin < 60) return `${diffMin}m ago`;
  const diffHours = Math.floor(diffMin / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
};

// Hydrate from localStorage then fetch
onMounted(() => {
  baseCurrency.value = getStoredValue(STORAGE_KEY_BASE, "USD");
  favorites.value = getStoredValue(STORAGE_KEY_FAVORITES, []);
  calculatorFrom.value = getStoredValue(STORAGE_KEY_FROM, "USD");
  calculatorTo.value = getStoredValue(STORAGE_KEY_TO, "IDR");
  fetchRates();
});
</script>
