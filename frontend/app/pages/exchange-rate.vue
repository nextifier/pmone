<template>
  <div class="container space-y-6 pt-4 pb-16">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:money-exchange-02" class="size-5 sm:size-6" />
      <h1 class="page-title">Exchange Rates</h1>
    </div>

    <div class="rounded-2xl border p-4 sm:p-5">
      <div class="mb-4 flex items-center gap-2">
        <Icon name="hugeicons:calculator-01" class="size-5" />
        <span class="text-sm font-medium tracking-tight">Currency Converter</span>
      </div>

      <div class="flex flex-col gap-3 lg:flex-row">
        <div class="flex grow items-center gap-2">
          <label
            class="text-muted-foreground w-16 text-right text-sm font-medium tracking-tight lg:w-auto lg:text-left"
            >Amount</label
          >
          <Input
            v-model="calculatorAmount"
            type="number"
            placeholder="Enter amount"
            @input="calculateConversion"
          />
        </div>

        <div class="flex grow items-center gap-2">
          <label
            class="text-muted-foreground w-16 text-right text-sm font-medium tracking-tight lg:w-auto lg:text-left"
            >From</label
          >
          <Popover v-model:open="fromOpen">
            <PopoverTrigger as-child>
              <button
                class="border-border data-[placeholder]:text-muted-foreground flex h-9 w-full items-center gap-1.5 rounded-md border bg-transparent px-3 text-sm tracking-tight shadow-xs"
              >
                <template v-if="calculatorFrom">
                  <FlagComponent :country="getCurrencyCountry(calculatorFrom)" />
                  <span class="font-medium">{{ calculatorFrom }}</span>
                  <span class="text-muted-foreground truncate text-sm">{{
                    getCurrencyName(calculatorFrom)
                  }}</span>
                </template>
                <span v-else class="text-muted-foreground">Select currency</span>
                <Icon
                  name="hugeicons:unfold-more"
                  class="text-muted-foreground ml-auto size-3.5 shrink-0"
                />
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
                      <span class="text-muted-foreground flex-1 truncate text-sm">{{
                        currency.name
                      }}</span>
                      <Icon
                        v-if="calculatorFrom === currency.code"
                        name="hugeicons:tick-02"
                        class="text-primary size-4 shrink-0"
                      />
                    </CommandItem>
                  </CommandGroup>
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>
        </div>

        <button
          @click="swapCurrencies"
          class="hover:bg-border bg-muted flex size-9 shrink-0 items-center justify-center self-end rounded-full active:scale-98 lg:self-end"
        >
          <Icon
            name="hugeicons:arrow-left-right"
            class="size-4 -scale-x-100 rotate-90 transition lg:scale-x-100 lg:rotate-0"
          />
        </button>

        <div class="flex grow items-center gap-2">
          <label
            class="text-muted-foreground w-16 text-right text-sm font-medium tracking-tight lg:w-auto lg:text-left"
            >To</label
          >
          <Popover v-model:open="toOpen">
            <PopoverTrigger as-child>
              <button
                class="border-border data-[placeholder]:text-muted-foreground flex h-9 w-full items-center gap-1.5 rounded-md border bg-transparent px-3 text-sm tracking-tight shadow-xs"
              >
                <template v-if="calculatorTo">
                  <FlagComponent :country="getCurrencyCountry(calculatorTo)" />
                  <span class="font-medium">{{ calculatorTo }}</span>
                  <span class="text-muted-foreground truncate text-sm">{{
                    getCurrencyName(calculatorTo)
                  }}</span>
                </template>
                <span v-else class="text-muted-foreground">Select currency</span>
                <Icon
                  name="hugeicons:unfold-more"
                  class="text-muted-foreground ml-auto size-3.5 shrink-0"
                />
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
                      <span class="text-muted-foreground flex-1 truncate text-sm">{{
                        currency.name
                      }}</span>
                      <Icon
                        v-if="calculatorTo === currency.code"
                        name="hugeicons:tick-02"
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

      <div class="bg-muted/50 mt-4 flex h-24 items-center justify-center rounded-xl text-center">
        <div v-if="conversionResult !== null" class="flex flex-col gap-y-1">
          <div class="text-foreground text-2xl font-semibold tracking-tighter">
            {{ calculatorFrom }} {{ formatNumber(calculatorAmount) }} = {{ calculatorTo }}
            {{ formatNumber(conversionResult) }}
          </div>
          <div
            v-if="conversionRate && calculatorAmount > 1"
            class="text-muted-foreground mt-1 text-sm tracking-tight"
          >
            {{ calculatorFrom }} 1 = {{ calculatorTo }} {{ formatNumber(conversionRate, 6) }}
          </div>
        </div>
      </div>
    </div>

    <div class="mt-10 flex flex-wrap items-center gap-3">
      <div class="relative min-w-48 flex-1">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <Input
          v-model="searchQuery"
          placeholder="Search currencies (e.g. IDR, Rupiah, USD)..."
          class="pl-10"
        />
      </div>

      <Popover v-model:open="baseOpen">
        <PopoverTrigger as-child>
          <button
            class="border-border hover:bg-muted flex h-9 items-center gap-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
          >
            <span class="text-muted-foreground text-sm">Base:</span>
            <FlagComponent
              v-if="getCurrencyCountry(baseCurrency)"
              :country="getCurrencyCountry(baseCurrency)"
            />
            <span class="font-medium">{{ baseCurrency }}</span>
            <Icon name="hugeicons:unfold-more" class="text-muted-foreground size-3.5" />
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
                  <span class="text-muted-foreground flex-1 truncate text-sm">{{
                    currency.name
                  }}</span>
                  <Icon
                    v-if="baseCurrency === currency.code"
                    name="hugeicons:tick-02"
                    class="text-primary size-4 shrink-0"
                  />
                </CommandItem>
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>

      <button
        type="button"
        class="flex h-9 cursor-pointer items-center gap-1.5"
        @click="
          invertRates = !invertRates;
          storeValue(STORAGE_KEY_INVERT, invertRates);
        "
      >
        <span
          role="switch"
          :aria-checked="invertRates"
          class="inline-flex h-[1.15rem] w-8 shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all"
          :class="invertRates ? 'bg-primary' : 'bg-input'"
        >
          <span
            class="pointer-events-none block size-4 rounded-full ring-0 transition-transform"
            :class="
              invertRates
                ? 'bg-primary-foreground translate-x-[calc(100%-2px)]'
                : 'dark:bg-foreground bg-background translate-x-0'
            "
          />
        </span>
        <span class="text-muted-foreground text-sm tracking-tight">Invert</span>
      </button>

      <button
        @click="fetchRates"
        :disabled="loading"
        class="border-border hover:bg-muted flex h-9 items-center gap-x-1 rounded-md border px-2.5 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <Icon
          name="hugeicons:reload"
          class="size-4 shrink-0"
          :class="{ 'animate-spin': loading }"
        />
        <span class="hidden sm:inline">Refresh</span>
      </button>

      <div
        v-if="meta?.fetched_at"
        class="text-muted-foreground flex items-center gap-1.5 text-sm tracking-tight"
      >
        <Icon name="hugeicons:clock-01" class="size-4 shrink-0" />
        <span>Last updated {{ formatRelativeTime(meta.fetched_at) }}</span>
        <span v-if="meta?.is_stale" class="text-warning-foreground">(stale)</span>
      </div>
    </div>

    <div v-if="loading" class="space-y-3">
      <Skeleton v-for="i in 8" :key="i" class="h-14 w-full rounded-lg" />
    </div>

    <div
      v-else-if="error"
      class="border-destructive/50 bg-destructive/10 rounded-lg border p-6 text-center"
    >
      <Icon name="hugeicons:alert-circle" class="text-destructive mx-auto mb-2 size-8" />
      <p class="text-destructive text-sm font-medium tracking-tight">
        Failed to load exchange rates
      </p>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">{{ error }}</p>
      <button
        @click="fetchRates"
        class="border-border hover:bg-muted mt-4 inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
      >
        <Icon name="hugeicons:reload" class="size-3.5" />
        <span>Retry</span>
      </button>
    </div>

    <div v-else class="mt-8">
      <div v-if="!searchQuery && favoriteCurrencies.length > 0" class="mb-6">
        <div class="text-muted-foreground mb-3 text-sm font-medium uppercase">Favorites</div>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-2">
          <div
            v-for="rate in favoriteCurrencies"
            :key="rate.code"
            class="hover:border-primary/30 group flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
            @click="selectCurrency(rate.code)"
          >
            <FlagComponent :country="rate.country" :country-name="rate.name" />
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <span class="text-sm font-medium tracking-tight">{{ rate.code }}</span>
                <span class="text-muted-foreground truncate text-sm">{{ rate.name }}</span>
              </div>
              <div class="text-muted-foreground text-sm tracking-tight tabular-nums">
                <template v-if="invertRates">
                  {{ rate.code }} 1 =
                  <span class="text-primary font-medium"
                    >{{ baseCurrency }} {{ formatNumber(1 / rate.rate) }}</span
                  >
                </template>
                <template v-else>
                  {{ baseCurrency }} 1 =
                  <span class="text-primary font-medium"
                    >{{ rate.code }} {{ formatNumber(rate.rate) }}</span
                  >
                </template>
              </div>
            </div>
            <button
              @click.stop="toggleFavorite(rate.code)"
              class="text-amber-400 opacity-60 transition-opacity hover:opacity-100"
            >
              <Icon name="hugeicons:star" class="size-4 fill-current" />
            </button>
          </div>
        </div>
      </div>

      <div v-if="!searchQuery && popularRates.length > 0" class="mb-6">
        <div class="text-muted-foreground mb-3 text-sm font-medium uppercase">
          Popular Currencies
        </div>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-2">
          <div
            v-for="rate in popularRates"
            :key="rate.code"
            class="hover:border-primary/30 group flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
            @click="selectCurrency(rate.code)"
          >
            <FlagComponent v-tippy="rate.name" :country="rate.country" :country-name="rate.name" />
            <div class="min-w-0 flex-1 gap-y-1.5 tracking-tight">
              <div class="flex items-center gap-1.5">
                <span v-tippy="rate.name" class="text-sm font-medium tracking-tight">{{
                  rate.code
                }}</span>
              </div>
              <div class="text-muted-foreground text-sm tracking-tight tabular-nums">
                <template v-if="invertRates">
                  {{ rate.code }} 1 =
                  <span class="text-primary font-medium"
                    >{{ baseCurrency }} {{ formatNumber(1 / rate.rate) }}</span
                  >
                </template>
                <template v-else>
                  {{ baseCurrency }} 1 =
                  <span class="text-primary font-medium"
                    >{{ rate.code }} {{ formatNumber(rate.rate) }}</span
                  >
                </template>
              </div>
            </div>
            <button
              @click.stop="toggleFavorite(rate.code)"
              class="opacity-0 transition-opacity group-hover:opacity-60 hover:!opacity-100"
              :class="{ 'text-amber-400 !opacity-60': isFavorite(rate.code) }"
            >
              <Icon
                name="hugeicons:star"
                class="size-4"
                :class="{ 'fill-current text-amber-400': isFavorite(rate.code) }"
              />
            </button>
          </div>
        </div>
      </div>

      <div>
        <div class="text-muted-foreground mb-3 text-sm font-medium uppercase">
          {{ searchQuery ? `Search Results (${filteredRates.length})` : "All Currencies" }}
        </div>

        <div
          v-if="filteredRates.length === 0"
          class="bg-muted/50 rounded-lg border p-8 text-center"
        >
          <Icon name="hugeicons:search-remove" class="text-muted-foreground mx-auto mb-2 size-8" />
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
            <FlagComponent :country="rate.country" :country-name="rate.name" />
            <div class="min-w-14">
              <span class="text-sm font-medium tracking-tight">{{ rate.code }}</span>
            </div>
            <div class="text-muted-foreground min-w-0 flex-1 truncate text-sm tracking-tight">
              {{ rate.name }}
            </div>
            <button
              @click.stop="toggleFavorite(rate.code)"
              class="opacity-0 transition-opacity group-hover:opacity-60 hover:!opacity-100"
              :class="{ 'text-amber-400 !opacity-60': isFavorite(rate.code) }"
            >
              <Icon
                name="hugeicons:star"
                class="size-3.5"
                :class="{ 'fill-current text-amber-400': isFavorite(rate.code) }"
              />
            </button>
            <div class="text-sm tracking-tight whitespace-nowrap tabular-nums">
              <template v-if="invertRates">
                <span class="text-muted-foreground">{{ rate.code }} 1 = </span>
                <span class="text-primary font-medium">
                  {{ baseCurrency }} {{ formatNumber(1 / rate.rate) }}</span
                >
              </template>
              <template v-else>
                <span class="text-muted-foreground">{{ baseCurrency }} 1 = </span>
                <span class="text-primary font-medium">
                  {{ rate.code }} {{ formatNumber(rate.rate) }}</span
                >
              </template>
            </div>
          </div>
        </div>
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
  layout: "default",
});

defineOptions({
  name: "exchange-rate",
});

usePageMeta(null, { title: "Exchange Rates" });

const config = useRuntimeConfig();

const STORAGE_KEY_BASE = "exchange_rate_base_currency";
const STORAGE_KEY_FAVORITES = "exchange_rate_favorites";
const STORAGE_KEY_FROM = "exchange_rate_from";
const STORAGE_KEY_TO = "exchange_rate_to";
const STORAGE_KEY_INVERT = "exchange_rate_invert";

const loading = ref(true);
const error = ref(null);
const rates = ref([]);
const meta = ref(null);
const ratesCount = ref(0);
const searchQuery = ref("");
const baseOpen = ref(false);
const fromOpen = ref(false);
const toOpen = ref(false);
const baseCurrency = ref("USD");
const favorites = ref([]);
const calculatorAmount = ref(1);
const calculatorFrom = ref("USD");
const calculatorTo = ref("IDR");
const conversionResult = ref(null);
const conversionRate = ref(null);
const invertRates = ref(false);

function getStoredValue(key, fallback) {
  try {
    const stored = localStorage.getItem(key);
    return stored === null ? fallback : JSON.parse(stored);
  } catch {
    return fallback;
  }
}

function storeValue(key, value) {
  if (import.meta.server) return;
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {}
}

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

const currencies = computed(() =>
  rates.value.map((r) => ({ code: r.code, name: r.name, country: r.country }))
);

const popularRates = computed(() =>
  rates.value.filter((r) => r.is_popular && !favorites.value.includes(r.code))
);

const favoriteCurrencies = computed(() =>
  rates.value.filter((r) => favorites.value.includes(r.code))
);

const filteredRates = computed(() => {
  if (!searchQuery.value) {
    return rates.value.filter((r) => !r.is_popular && !favorites.value.includes(r.code));
  }
  const query = searchQuery.value.toLowerCase();
  return rates.value.filter(
    (r) => r.code.toLowerCase().includes(query) || r.name.toLowerCase().includes(query)
  );
});

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

function handleBaseCurrencyChange(value) {
  storeValue(STORAGE_KEY_BASE, value);
  fetchRates();
}

const fetchRates = async () => {
  loading.value = true;
  error.value = null;

  try {
    const params = new URLSearchParams();
    if (baseCurrency.value && baseCurrency.value !== "USD") {
      params.append("base", baseCurrency.value);
    }
    const qs = params.toString();
    const response = await $fetch(
      `${config.public.apiUrl}/api/exchange-rates${qs ? `?${qs}` : ""}`
    );
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
    conversionResult.value = (calculatorAmount.value / fromRate) * toRate;
    conversionRate.value = toRate / fromRate;
  }

  storeValue(STORAGE_KEY_FROM, calculatorFrom.value);
  storeValue(STORAGE_KEY_TO, calculatorTo.value);
};

const swapCurrencies = () => {
  [calculatorFrom.value, calculatorTo.value] = [calculatorTo.value, calculatorFrom.value];
  calculateConversion();
};

const selectCurrency = (code) => {
  calculatorTo.value = code;
  calculateConversion();
  window.scrollTo({ top: 0, behavior: "smooth" });
};

const formatNumber = (num, decimals) => {
  if (num === null || num === undefined) return "-";

  if (decimals === undefined) {
    const abs = Math.abs(num);
    if (abs === 0) decimals = 2;
    else if (abs >= 1000) decimals = 2;
    else if (abs >= 1) decimals = 4;
    else if (abs >= 0.01) decimals = 6;
    else decimals = 8;
  }

  return new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 0,
    maximumFractionDigits: decimals,
  }).format(num);
};

const formatRelativeTime = (dateStr) => {
  if (!dateStr) return "-";
  const diffMin = Math.floor((Date.now() - new Date(dateStr)) / 60000);

  if (diffMin < 1) return "just now";
  if (diffMin < 60) return `${diffMin}m ago`;
  const diffHours = Math.floor(diffMin / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  return `${Math.floor(diffHours / 24)}d ago`;
};

onMounted(() => {
  baseCurrency.value = getStoredValue(STORAGE_KEY_BASE, "USD");
  favorites.value = getStoredValue(STORAGE_KEY_FAVORITES, []);
  calculatorFrom.value = getStoredValue(STORAGE_KEY_FROM, "USD");
  calculatorTo.value = getStoredValue(STORAGE_KEY_TO, "IDR");
  invertRates.value = getStoredValue(STORAGE_KEY_INVERT, false);
  fetchRates();
});
</script>
