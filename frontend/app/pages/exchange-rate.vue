<template>
  <div class="my-8 md:my-12">
    <div class="container">
      <!-- Header -->
      <div class="mx-auto mb-8 max-w-3xl text-center">
        <h1 class="mb-3 text-3xl font-bold tracking-tight md:text-4xl">Exchange Rate</h1>
        <p class="text-muted-foreground">
          Kurs mata uang terkini dari berbagai negara. Data diperbarui secara berkala.
        </p>
        <p v-if="meta?.fetched_at" class="text-muted-foreground mt-2 text-sm">
          Terakhir diperbarui:
          {{ formatDate(meta.fetched_at) }}
        </p>
      </div>

      <!-- Calculator Card -->
      <Card class="mx-auto mb-8 max-w-2xl">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Icon name="lucide:calculator" class="size-5" />
            Kalkulator Konversi
          </CardTitle>
          <CardDescription>
            Konversi nilai mata uang dengan cepat dan mudah
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex flex-col gap-4 md:flex-row md:items-end">
            <!-- Amount Input -->
            <div class="flex-1 space-y-2">
              <label class="text-sm font-medium">Jumlah</label>
              <Input
                v-model="calculatorAmount"
                type="number"
                placeholder="Masukkan jumlah"
                class="text-lg"
                @input="calculateConversion"
              />
            </div>

            <!-- From Currency -->
            <div class="flex-1 space-y-2">
              <label class="text-sm font-medium">Dari</label>
              <Select v-model="calculatorFrom" @update:model-value="calculateConversion">
                <SelectTrigger>
                  <SelectValue placeholder="Pilih mata uang" />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem v-for="currency in currencies" :key="currency.code" :value="currency.code">
                      <div class="flex items-center gap-2">
                        <FlagComponent :country="currency.country" :country-name="currency.name" />
                        <span>{{ currency.code }}</span>
                        <span class="text-muted-foreground text-xs">{{ currency.name }}</span>
                      </div>
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
            </div>

            <!-- Swap Button -->
            <Button variant="outline" size="icon" class="shrink-0" @click="swapCurrencies">
              <Icon name="lucide:arrow-right-left" class="size-4" />
            </Button>

            <!-- To Currency -->
            <div class="flex-1 space-y-2">
              <label class="text-sm font-medium">Ke</label>
              <Select v-model="calculatorTo" @update:model-value="calculateConversion">
                <SelectTrigger>
                  <SelectValue placeholder="Pilih mata uang" />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem v-for="currency in currencies" :key="currency.code" :value="currency.code">
                      <div class="flex items-center gap-2">
                        <FlagComponent :country="currency.country" :country-name="currency.name" />
                        <span>{{ currency.code }}</span>
                        <span class="text-muted-foreground text-xs">{{ currency.name }}</span>
                      </div>
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
            </div>
          </div>

          <!-- Result -->
          <div v-if="conversionResult !== null" class="mt-6 rounded-lg border bg-muted/50 p-4">
            <div class="text-center">
              <div class="text-muted-foreground text-sm">
                {{ formatNumber(calculatorAmount) }} {{ calculatorFrom }} =
              </div>
              <div class="text-primary mt-1 text-2xl font-bold">
                {{ formatNumber(conversionResult) }} {{ calculatorTo }}
              </div>
              <div v-if="conversionRate" class="text-muted-foreground mt-2 text-xs">
                1 {{ calculatorFrom }} = {{ formatNumber(conversionRate, 6) }} {{ calculatorTo }}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Search & Filter -->
      <div class="mx-auto mb-6 max-w-3xl">
        <div class="relative">
          <Icon name="lucide:search" class="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
          <Input
            v-model="searchQuery"
            placeholder="Cari mata uang (contoh: IDR, Rupiah, USD)..."
            class="pl-10"
          />
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="mx-auto max-w-3xl space-y-4">
        <Skeleton v-for="i in 10" :key="i" class="h-16 w-full rounded-lg" />
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="mx-auto max-w-3xl text-center">
        <div class="rounded-lg border border-destructive/50 bg-destructive/10 p-6">
          <Icon name="lucide:alert-circle" class="text-destructive mx-auto mb-2 size-8" />
          <p class="text-destructive font-medium">Gagal memuat data</p>
          <p class="text-muted-foreground mt-1 text-sm">{{ error }}</p>
          <Button variant="outline" class="mt-4" @click="fetchRates">
            <Icon name="lucide:refresh-cw" class="mr-2 size-4" />
            Coba Lagi
          </Button>
        </div>
      </div>

      <!-- Rates Grid -->
      <div v-else class="mx-auto max-w-3xl">
        <!-- Popular Section -->
        <div v-if="!searchQuery && popularRates.length > 0" class="mb-8">
          <h2 class="text-muted-foreground mb-4 text-sm font-medium uppercase tracking-wider">Mata Uang Populer</h2>
          <div class="grid gap-3 sm:grid-cols-2">
            <div
              v-for="rate in popularRates"
              :key="rate.code"
              class="hover:border-primary/50 flex cursor-pointer items-center gap-3 rounded-lg border bg-card p-4 transition-colors"
              @click="selectCurrency(rate.code)"
            >
              <FlagComponent :country="rate.country" :country-name="rate.name" class="size-8 rounded" />
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <span class="font-semibold">{{ rate.code }}</span>
                  <span class="text-muted-foreground text-sm">{{ rate.name }}</span>
                </div>
                <div class="text-primary text-lg font-medium">
                  {{ formatNumber(rate.rate, 4) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- All Rates -->
        <div>
          <h2 class="text-muted-foreground mb-4 text-sm font-medium uppercase tracking-wider">
            {{ searchQuery ? `Hasil Pencarian (${filteredRates.length})` : 'Semua Mata Uang' }}
          </h2>
          <div v-if="filteredRates.length === 0" class="rounded-lg border bg-muted/50 p-8 text-center">
            <Icon name="lucide:search-x" class="text-muted-foreground mx-auto mb-2 size-8" />
            <p class="text-muted-foreground">Tidak ditemukan mata uang untuk "{{ searchQuery }}"</p>
          </div>
          <div v-else class="grid gap-2">
            <div
              v-for="rate in filteredRates"
              :key="rate.code"
              class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
              @click="selectCurrency(rate.code)"
            >
              <FlagComponent :country="rate.country" :country-name="rate.name" class="size-6 rounded-sm" />
              <div class="min-w-16">
                <span class="font-medium">{{ rate.code }}</span>
              </div>
              <div class="text-muted-foreground flex-1 truncate text-sm">
                {{ rate.name }}
              </div>
              <div class="text-primary font-medium tabular-nums">
                {{ formatNumber(rate.rate, 4) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination Info -->
        <div v-if="ratesCount > 0" class="text-muted-foreground mt-6 text-center text-sm">
          Menampilkan {{ filteredRates.length }} dari {{ ratesCount }} mata uang
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  layout: "default",
});

usePageMeta("exchange-rate");

const config = useRuntimeConfig();

// State
const loading = ref(true);
const error = ref(null);
const rates = ref([]);
const meta = ref(null);
const ratesCount = ref(0);
const searchQuery = ref("");

// Calculator state
const calculatorAmount = ref(1);
const calculatorFrom = ref("USD");
const calculatorTo = ref("IDR");
const conversionResult = ref(null);
const conversionRate = ref(null);

// Computed
const currencies = computed(() => {
  return rates.value.map(r => ({
    code: r.code,
    name: r.name,
    country: r.country,
  }));
});

const popularRates = computed(() => {
  return rates.value.filter(r => r.is_popular);
});

const filteredRates = computed(() => {
  if (!searchQuery.value) {
    return rates.value.filter(r => !r.is_popular);
  }
  const query = searchQuery.value.toLowerCase();
  return rates.value.filter(r =>
    r.code.toLowerCase().includes(query) ||
    r.name.toLowerCase().includes(query)
  );
});

// Methods
const fetchRates = async () => {
  loading.value = true;
  error.value = null;

  try {
    const response = await $fetch(`${config.public.apiUrl}/api/exchange-rates`);
    rates.value = response.data.rates;
    ratesCount.value = response.data.rates_count;
    meta.value = response.meta;

    // Initial calculation
    calculateConversion();
  } catch (e) {
    error.value = e.message || "Terjadi kesalahan saat memuat data";
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

  const fromRate = rates.value.find(r => r.code === calculatorFrom.value)?.rate;
  const toRate = rates.value.find(r => r.code === calculatorTo.value)?.rate;

  if (fromRate && toRate) {
    // Convert to base (USD) first, then to target
    const inBase = calculatorAmount.value / fromRate;
    conversionResult.value = inBase * toRate;
    conversionRate.value = toRate / fromRate;
  }
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
  // Scroll to calculator
  window.scrollTo({ top: 0, behavior: "smooth" });
};

const formatNumber = (num, decimals = 2) => {
  if (num === null || num === undefined) return "-";
  return new Intl.NumberFormat("id-ID", {
    minimumFractionDigits: 0,
    maximumFractionDigits: decimals,
  }).format(num);
};

const formatDate = (dateStr) => {
  if (!dateStr) return "-";
  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(dateStr));
};

// Fetch on mount
onMounted(() => {
  fetchRates();
});
</script>
