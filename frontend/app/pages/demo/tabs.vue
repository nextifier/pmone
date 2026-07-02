<template>
  <div id="demo-tabs-page" class="container max-w-4xl py-10">
    <div class="mb-10">
      <h1 class="text-foreground text-3xl font-semibold tracking-tighter sm:text-4xl">
        Tabs — Variants Showcase
      </h1>
      <p class="text-muted-foreground mt-2 tracking-tight">
        Showcase variant + size dari
        <code class="bg-muted rounded px-1.5 py-0.5 text-sm">components/ui/tabs</code>.
      </p>
    </div>

    <div class="space-y-12">
      <!-- 1. Pill (default) -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">
          1. Variant: <code class="bg-muted rounded px-1.5 py-0.5 text-base">pill</code> (default)
        </h2>
        <p class="text-muted-foreground mb-4 text-sm">
          Rounded-full container dengan sliding pill indicator.
        </p>

        <Tabs default-value="overview" class="flex flex-col gap-4">
          <TabsList>
            <TabsIndicator />
            <TabsTrigger value="overview">Overview</TabsTrigger>
            <TabsTrigger value="features">Features</TabsTrigger>
            <TabsTrigger value="pricing">Pricing</TabsTrigger>
            <TabsTrigger value="reviews">Reviews</TabsTrigger>
          </TabsList>
          <TabsContent value="overview">
            <p class="text-muted-foreground tracking-tight">
              Pill variant cocok untuk top-level filter — paling generic.
            </p>
          </TabsContent>
          <TabsContent value="features">
            <p class="text-muted-foreground tracking-tight">Features content.</p>
          </TabsContent>
          <TabsContent value="pricing">
            <p class="text-muted-foreground tracking-tight">Pricing content.</p>
          </TabsContent>
          <TabsContent value="reviews">
            <p class="text-muted-foreground tracking-tight">Reviews content.</p>
          </TabsContent>
        </Tabs>
      </section>

      <!-- 2. Segmented — text -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">
          2. Variant: <code class="bg-muted rounded px-1.5 py-0.5 text-base">segmented</code>
        </h2>
        <p class="text-muted-foreground mb-4 text-sm">
          Squarer container dengan shadow + ring indicator. Cocok untuk tab toggle yang lebih solid.
        </p>

        <Tabs default-value="all" class="flex flex-col gap-4">
          <TabsList>
            <TabsIndicator />
            <TabsTrigger value="all">All</TabsTrigger>
            <TabsTrigger value="active">Active</TabsTrigger>
            <TabsTrigger value="archived">Archived</TabsTrigger>
          </TabsList>
          <TabsContent value="all">
            <p class="text-muted-foreground tracking-tight">All items.</p>
          </TabsContent>
          <TabsContent value="active">
            <p class="text-muted-foreground tracking-tight">Active items.</p>
          </TabsContent>
          <TabsContent value="archived">
            <p class="text-muted-foreground tracking-tight">Archived items.</p>
          </TabsContent>
        </Tabs>
      </section>

      <!-- 3. Segmented — multi-line text (Rundown style) -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">
          3. Segmented + multi-line trigger
          <span class="text-muted-foreground text-sm font-normal">(Rundown style)</span>
        </h2>
        <p class="text-muted-foreground mb-4 text-sm">
          Segmented dengan label dua baris untuk day picker. Override <code>h-auto</code> di
          trigger.
        </p>

        <div class="flex justify-center">
          <Tabs default-value="day1" variant="segmented">
            <TabsList>
              <TabsIndicator />
              <TabsTrigger
                v-for="day in days"
                :key="day.value"
                :value="day.value"
                class="h-auto py-1.5"
              >
                <span class="flex flex-col items-center gap-0.5 px-1">
                  <span class="text-base leading-tight font-semibold tracking-tighter">{{
                    day.label
                  }}</span>
                  <span class="text-xs leading-tight tracking-tight sm:text-sm">{{
                    day.dateLabel
                  }}</span>
                </span>
              </TabsTrigger>
            </TabsList>
          </Tabs>
        </div>
      </section>

      <!-- 4. Segmented — icon-only (BrandViewSwitcher style) -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">
          4. Segmented + icon-only trigger
          <span class="text-muted-foreground text-sm font-normal">(BrandViewSwitcher style)</span>
        </h2>
        <p class="text-muted-foreground mb-4 text-sm">Icon-only dengan padding 0 + size-8 fixed.</p>

        <Tabs v-model="viewMode" variant="segmented">
          <TabsList>
            <TabsIndicator />
            <TabsTrigger
              v-for="opt in viewOptions"
              :key="opt.value"
              :value="opt.value"
              :aria-label="opt.label"
              class="size-8 p-0"
            >
              <Icon :name="opt.icon" class="size-4 shrink-0" />
            </TabsTrigger>
          </TabsList>
        </Tabs>
        <p class="text-muted-foreground mt-2 text-sm">
          Selected: <span class="text-foreground font-mono">{{ viewMode }}</span>
        </p>
      </section>

      <!-- 5. Underline -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">
          5. Variant: <code class="bg-muted rounded px-1.5 py-0.5 text-base">underline</code>
        </h2>
        <p class="text-muted-foreground mb-4 text-sm">
          Bottom underline indicator. Cocok untuk page-level tab nav.
        </p>

        <Tabs default-value="tickets" variant="underline" class="flex flex-col gap-4">
          <TabsList>
            <TabsIndicator />
            <TabsTrigger
              v-for="tab in iconTabs"
              :key="tab.value"
              :value="tab.value"
              class="rounded-lg"
            >
              <Icon :name="tab.icon" class="size-4 shrink-0" />
              <span>{{ tab.label }}</span>
            </TabsTrigger>
          </TabsList>
          <TabsContent v-for="tab in iconTabs" :key="tab.value" :value="tab.value">
            <div class="border-border bg-muted/30 rounded-xl border p-6">
              <p class="text-muted-foreground tracking-tight">
                Content panel untuk <span class="text-foreground font-medium">{{ tab.label }}</span
                >.
              </p>
            </div>
          </TabsContent>
        </Tabs>
      </section>

      <!-- 6. Sizes -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">6. Sizes</h2>
        <p class="text-muted-foreground mb-4 text-sm">
          Tiga ukuran: <code>sm</code>, <code>md</code> (default), <code>lg</code>.
        </p>

        <div class="flex flex-col gap-4">
          <div v-for="s in ['sm', 'md', 'lg'] as const" :key="s" class="flex items-center gap-4">
            <span class="text-muted-foreground w-10 font-mono text-sm">{{ s }}</span>
            <Tabs default-value="one" :size="s">
              <TabsList>
                <TabsIndicator />
                <TabsTrigger value="one">One</TabsTrigger>
                <TabsTrigger value="two">Two</TabsTrigger>
                <TabsTrigger value="three">Three</TabsTrigger>
              </TabsList>
            </Tabs>
          </div>
        </div>
      </section>

      <!-- 7. Disabled trigger -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">
          7. Disabled trigger
        </h2>
        <p class="text-muted-foreground mb-4 text-sm">
          Trigger disabled tidak bisa diaktifkan dan punya opacity 50%.
        </p>

        <Tabs default-value="active1">
          <TabsList>
            <TabsIndicator />
            <TabsTrigger value="active1">Active</TabsTrigger>
            <TabsTrigger value="disabled1" disabled>Disabled</TabsTrigger>
            <TabsTrigger value="active2">Another</TabsTrigger>
          </TabsList>
        </Tabs>
      </section>

      <!-- 8. Many tabs (overflow) -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">
          8. Many tabs (overflow scroll)
        </h2>
        <p class="text-muted-foreground mb-4 text-sm">
          Underline variant dengan <code>overflow-x-auto</code> + <code>scroll-fade-x</code> +
          <code>shrink-0</code> di trigger.
        </p>

        <Tabs default-value="cat1" variant="underline">
          <TabsList>
            <TabsIndicator />
            <TabsTrigger
              v-for="cat in manyCategories"
              :key="cat"
              :value="cat"
              class="shrink-0 rounded-lg"
            >
              {{ cat }}
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </section>

      <!-- 9. Swipe -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">9. Swipe gesture</h2>
        <p class="text-muted-foreground mb-4 text-sm">
          Set <code class="bg-muted rounded px-1.5 py-0.5">swipe</code> agar tab bisa di-switch
          dengan swipe horizontal di area Tabs root. Default exclude: <code>[role='tablist']</code>,
          <code>[aria-roledescription='carousel']</code>, <code>.pswp</code>.
        </p>
        <p class="text-muted-foreground mb-4 text-sm">
          <span class="text-foreground font-medium">Tip:</span> coba di mobile / touch device, atau
          drag horizontal di area konten panel di bawah ini.
        </p>

        <Tabs default-value="tab1" variant="underline" swipe class="flex flex-col gap-4">
          <TabsList>
            <TabsIndicator />
            <TabsTrigger
              v-for="tab in swipeTabs"
              :key="tab.value"
              :value="tab.value"
              class="rounded-lg"
            >
              {{ tab.label }}
            </TabsTrigger>
          </TabsList>
          <TabsContent v-for="tab in swipeTabs" :key="tab.value" :value="tab.value">
            <div
              class="border-border bg-muted/30 flex min-h-40 flex-col items-center justify-center gap-2 rounded-xl border p-6"
            >
              <span class="text-foreground text-lg font-semibold tracking-tight">{{
                tab.label
              }}</span>
              <p class="text-muted-foreground text-sm">
                Swipe kiri/kanan di area ini untuk pindah tab.
              </p>
            </div>
          </TabsContent>
        </Tabs>
      </section>

      <!-- 10. Controlled with external buttons -->
      <section>
        <h2 class="text-foreground mb-3 text-xl font-semibold tracking-tighter">
          10. Controlled state (v-model)
        </h2>
        <p class="text-muted-foreground mb-4 text-sm">
          Tab aktif dikontrol dari luar via <code>v-model</code>.
        </p>

        <div class="flex flex-col gap-4">
          <div class="flex flex-wrap items-center gap-2">
            <button
              v-for="step in steps"
              :key="step"
              @click="controlled = step"
              class="border-border rounded-lg border px-3 py-1.5 text-sm transition"
              :class="controlled === step ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
            >
              Set "{{ step }}"
            </button>
          </div>

          <Tabs v-model="controlled" variant="segmented">
            <TabsList>
              <TabsIndicator />
              <TabsTrigger v-for="step in steps" :key="step" :value="step">
                {{ step }}
              </TabsTrigger>
            </TabsList>
          </Tabs>

          <p class="text-muted-foreground text-sm">
            Active: <span class="text-foreground font-mono">{{ controlled }}</span>
          </p>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  layout: "default",
});

useHead({
  title: "Tabs Demo",
});

const viewMode = ref("grid");
const viewOptions = [
  { value: "grid", label: "Grid", icon: "hugeicons:grid-view" },
  { value: "list", label: "List", icon: "hugeicons:menu-01" },
  { value: "compact", label: "Compact", icon: "hugeicons:menu-09" },
];

const days = [
  { value: "day1", label: "Day 1", dateLabel: "Mon, 22 Jul" },
  { value: "day2", label: "Day 2", dateLabel: "Tue, 23 Jul" },
  { value: "day3", label: "Day 3", dateLabel: "Wed, 24 Jul" },
];

const iconTabs = [
  { value: "tickets", label: "Tickets", icon: "hugeicons:ticket-01" },
  { value: "brands", label: "Brands", icon: "hugeicons:grid-view" },
  { value: "rundown", label: "Rundown", icon: "hugeicons:check-list" },
  { value: "about", label: "About", icon: "hugeicons:information-circle" },
];

const manyCategories = [
  "All",
  "Technology",
  "Design",
  "Business",
  "Marketing",
  "Engineering",
  "Product",
  "Operations",
  "Finance",
  "Legal",
  "HR",
];

const steps = ["Step 1", "Step 2", "Step 3", "Step 4"];
const controlled = ref("Step 1");

const swipeTabs = [
  { value: "tab1", label: "Tab One" },
  { value: "tab2", label: "Tab Two" },
  { value: "tab3", label: "Tab Three" },
  { value: "tab4", label: "Tab Four" },
];
</script>
