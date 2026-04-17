<script setup lang="ts">
import type { Position } from "@/components/ui/card-notch";

definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Card Notch" });

const brands = [
  { name: "Air Minum Biru", category: "Retail & Wholesales", booth: "-" },
  { name: "Burger Bangor", category: "Food & Beverage", booth: "A-03" },
  { name: "Cheezy Coin", category: "Entertainment", booth: "-" },
  { name: "DJ Juice", category: "Food & Beverage", booth: "-" },
  { name: "Farmakita", category: "Health & Wellness", booth: "-" },
  { name: "Cafe Bombom", category: "Food & Beverage", booth: "F-06" },
];

const buttonClicks = ref(0);
const onCardClick = () => {
  buttonClicks.value++;
};

const positions: { value: Position; label: string; bodyClass: string }[] = [
  { value: "top-left", label: "top-left", bodyClass: "p-5 pt-20" },
  { value: "top-center", label: "top-center", bodyClass: "p-5 pt-20 text-center" },
  { value: "top-right", label: "top-right", bodyClass: "p-5 pt-20 text-right" },
  { value: "bottom-left", label: "bottom-left", bodyClass: "p-5 pb-20" },
  { value: "bottom-center", label: "bottom-center", bodyClass: "p-5 pb-20 text-center" },
  { value: "bottom-right", label: "bottom-right (default)", bodyClass: "p-5 pb-20 text-right" },
];
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">Card Notch</h1>
      <p class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg">
        Card with a true transparent notched cutout where a circular element meets the card body.
        Uses SVG clip-path for crisp, pixel-perfect corners - no images, no hacks.
      </p>
    </div>

    <!-- Basic -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Basic</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Default position: notch element at bottom-right with a cutout at the junction.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <CardNotch v-for="item in brands.slice(0, 3)" :key="item.name" body-class="p-5 pb-20">
          <template #notch>
            <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">{{ item.name }}</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ item.category }}</p>
        </CardNotch>
      </div>
    </section>

    <!-- Plain Card (no notch) -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Plain Card (No Notch)</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Without <code class="bg-muted rounded px-1 py-0.5 text-xs">#notch</code> slot and without
        <code class="bg-muted rounded px-1 py-0.5 text-xs">notched</code> prop, CardNotch falls
        back to a plain rounded card - reusable as a regular card component.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <CardNotch v-for="item in brands.slice(0, 3)" :key="item.name" body-class="p-5">
          <h3 class="text-sm font-medium tracking-tight sm:text-base">{{ item.name }}</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ item.category }}</p>
        </CardNotch>
      </div>
    </section>

    <!-- As NuxtLink -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">As NuxtLink</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Pass <code class="bg-muted rounded px-1 py-0.5 text-xs">to</code> or
        <code class="bg-muted rounded px-1 py-0.5 text-xs">href</code> to render the card as a
        NuxtLink. Hover state + focus ring auto-enable. External URLs (<code
          class="bg-muted rounded px-1 py-0.5 text-xs"
          >https://</code
        >) auto-apply <code class="bg-muted rounded px-1 py-0.5 text-xs">target="_blank"</code> +
        <code class="bg-muted rounded px-1 py-0.5 text-xs">rel="noopener noreferrer"</code>.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <CardNotch to="/demo/card-notch" body-class="p-5 pb-20">
          <template #notch>
            <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">Internal Route</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            to="/demo/card-notch"
          </p>
        </CardNotch>
        <CardNotch href="https://nuxt.com" body-class="p-5 pb-20">
          <template #notch>
            <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">External Link</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            href="https://nuxt.com"
          </p>
        </CardNotch>
        <CardNotch as="button" body-class="p-5 pb-20" @click="onCardClick">
          <template #notch>
            <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">As Button</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            as="button" clicked {{ buttonClicks }}x
          </p>
        </CardNotch>
      </div>
    </section>

    <!-- Auto Padding -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Auto Padding</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Use <code class="bg-muted rounded px-1 py-0.5 text-xs">auto-pad</code> to let the card
        reserve space for the notch automatically - no manual
        <code class="bg-muted rounded px-1 py-0.5 text-xs">pb-*</code> in body-class needed.
      </p>
      <div class="grid gap-6 sm:grid-cols-2">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Manual padding (body-class="p-5 pb-20")
          </span>
          <CardNotch body-class="p-5 pb-20">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Manual</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Consumer computes notch clearance
            </p>
          </CardNotch>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Auto (auto-pad + body-class="p-5")
          </span>
          <CardNotch auto-pad body-class="p-5">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Auto</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Card reserves space for notch
            </p>
          </CardNotch>
        </div>
      </div>

      <div class="mt-6 grid gap-6 sm:grid-cols-3">
        <div v-for="np in ['0px', '0.75rem', '1.5rem']" :key="np">
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            notch-padding="{{ np }}"
          </span>
          <CardNotch auto-pad :notch-padding="np" body-class="p-5">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Padding {{ np }}</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Breathing room above notch
            </p>
          </CardNotch>
        </div>
      </div>
    </section>

    <!-- Positions -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Positions</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Notch element supports six positions: top-left, top-center, top-right, bottom-left,
        bottom-center, and bottom-right.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="pos in positions" :key="pos.value">
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            {{ pos.label }}
          </span>
          <CardNotch :position="pos.value" :body-class="pos.bodyClass">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">{{ pos.value }}</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Notch position
            </p>
          </CardNotch>
        </div>
      </div>
    </section>

    <!-- No Border variant -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Without Border</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Use <code class="bg-muted rounded px-1 py-0.5 text-xs">:bordered="false"</code> to remove
        the border. Best on colored backgrounds.
      </p>
      <div
        class="grid gap-6 rounded-xl bg-gradient-to-r from-blue-100 via-purple-100 to-pink-100 p-6 sm:grid-cols-3 dark:from-blue-950 dark:via-purple-950 dark:to-pink-950"
      >
        <CardNotch v-for="i in 3" :key="i" :bordered="false" body-class="p-5 pb-20">
          <template #notch>
            <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">No Border</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            Card on colored background
          </p>
        </CardNotch>
      </div>
    </section>

    <!-- Custom Sizes -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Custom Sizes</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Adjust <code class="bg-muted rounded px-1 py-0.5 text-xs">size</code>,
        <code class="bg-muted rounded px-1 py-0.5 text-xs">gap</code>, and
        <code class="bg-muted rounded px-1 py-0.5 text-xs">radius</code> for different looks.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Large notch (5rem)
          </span>
          <CardNotch size="5rem" gap="4px" body-class="p-5 pb-28">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-7" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Large Logo</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              5rem notch, 4px gap
            </p>
          </CardNotch>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Small notch (2.5rem)
          </span>
          <CardNotch size="2.5rem" gap="2px" body-class="p-5 pb-14">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-4" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Small Logo</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              2.5rem notch, 2px gap
            </p>
          </CardNotch>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Wide gap + smaller radius
          </span>
          <CardNotch gap="12px" radius="1rem" body-class="p-5 pb-20">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Wide Gap</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              12px gap, 1rem radius
            </p>
          </CardNotch>
        </div>
      </div>
    </section>

    <!-- Muted (no border) variant -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Muted Card (No Border)</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Use <code class="bg-muted rounded px-1 py-0.5 text-xs">:bordered="false"</code> with
        <code class="bg-muted rounded px-1 py-0.5 text-xs">card-bg</code> for subtle muted cards.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <CardNotch
          v-for="item in brands.slice(0, 3)"
          :key="item.name"
          :bordered="false"
          card-bg="color-mix(in oklab, var(--color-muted) 70%, transparent)"
          body-class="p-5 pb-20"
        >
          <template #notch>
            <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">{{ item.name }}</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ item.category }}</p>
        </CardNotch>
      </div>
    </section>

    <!-- Border customization -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Border Width & Color</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Customize border appearance via
        <code class="bg-muted rounded px-1 py-0.5 text-xs">border-width</code> and
        <code class="bg-muted rounded px-1 py-0.5 text-xs">border-color</code>.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Thicker border (2px)
          </span>
          <CardNotch border-width="2px" body-class="p-5 pb-20">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Thicker Border</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              border-width="2px"
            </p>
          </CardNotch>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Destructive color
          </span>
          <CardNotch border-color="var(--color-destructive)" body-class="p-5 pb-20">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Destructive</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              border-color="var(--color-destructive)"
            </p>
          </CardNotch>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Thick + colored
          </span>
          <CardNotch border-width="3px" border-color="var(--color-info)" body-class="p-5 pb-20">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Combined</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">3px info color</p>
          </CardNotch>
        </div>
      </div>
    </section>

    <!-- Transparency Test -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Transparency Test</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Cards on different backgrounds to prove the gap between card and notch is truly
        transparent - not faked with box-shadow or pseudo-elements.
      </p>

      <div class="mb-6 grid gap-6 sm:grid-cols-3">
        <div
          v-for="bg in [
            { cls: 'bg-red-100 dark:bg-red-950', label: 'Red' },
            { cls: 'bg-blue-100 dark:bg-blue-950', label: 'Blue' },
            { cls: 'bg-green-100 dark:bg-green-950', label: 'Green' },
          ]"
          :key="bg.label"
          :class="['rounded-xl p-6', bg.cls]"
        >
          <span class="text-muted-foreground mb-3 block text-xs tracking-tight sm:text-sm">
            {{ bg.label }} background
          </span>
          <CardNotch :bordered="false" body-class="p-5 pb-20">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">True Transparency</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              {{ bg.label }} shows through
            </p>
          </CardNotch>
        </div>
      </div>

      <div class="mb-6">
        <span class="text-muted-foreground mb-3 block text-xs tracking-tight sm:text-sm">
          Gradient background
        </span>
        <div
          class="grid gap-6 rounded-xl bg-gradient-to-r from-purple-200 via-pink-200 to-orange-200 p-6 sm:grid-cols-3 dark:from-purple-950 dark:via-pink-950 dark:to-orange-950"
        >
          <CardNotch v-for="i in 3" :key="i" :bordered="false" body-class="p-5 pb-20">
            <template #notch>
              <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Gradient Test</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Gradient visible in gap
            </p>
          </CardNotch>
        </div>
      </div>
    </section>

    <!-- Brand Card Simulation -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Brand Card Simulation</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Simulating the BrandCard use case with logo, name, category, and metadata.
        <code class="bg-muted rounded px-1 py-0.5 text-xs">auto-pad</code> reserves notch space.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <CardNotch
          v-for="brand in brands"
          :key="brand.name"
          auto-pad
          body-class="flex flex-col gap-3 p-5"
        >
          <template #notch>
            <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
          </template>
          <div>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">{{ brand.name }}</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              {{ brand.category }}
            </p>
          </div>
          <div class="flex items-center gap-x-2">
            <span class="text-muted-foreground text-xs tracking-tight">Booth</span>
            <span class="text-xs font-medium tracking-tight sm:text-sm">{{ brand.booth }}</span>
          </div>
          <div class="mt-auto flex items-end justify-between pt-3">
            <span class="text-muted-foreground text-xs tracking-tight">Created 3 days ago</span>
          </div>
        </CardNotch>
      </div>
    </section>

    <!-- Brand List Skeleton -->
    <section
      class="-mx-4 rounded-2xl bg-gradient-to-br from-blue-200 via-pink-200 to-orange-200 p-4 dark:from-blue-950 dark:via-pink-950 dark:to-orange-950"
    >
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Brand List Skeleton</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Skeleton loading state. Uses <code class="bg-muted rounded px-1 py-0.5 text-xs">:notched</code>
        to enable the cutout without a slot. Gradient background proves true gap transparency.
      </p>
      <div
        class="grid grid-cols-2 gap-x-2 gap-y-4 sm:grid-cols-[repeat(auto-fit,minmax(280px,1fr))] sm:gap-x-4"
      >
        <CardNotch
          v-for="index in 16"
          :key="index"
          notched
          auto-pad
          :bordered="false"
          body-class="flex flex-col gap-4 p-4 sm:p-5"
        >
          <div class="flex flex-col items-center gap-x-2 gap-y-3 sm:flex-row">
            <span class="bg-muted block size-20 shrink-0 rounded-full sm:size-18" />
            <div class="flex w-full flex-col items-center gap-y-2 sm:items-start">
              <span class="bg-muted block h-4 w-full rounded-md" />
              <span class="bg-muted block h-3 w-3/4 rounded-md" />
            </div>
          </div>

          <div class="flex items-start justify-center gap-2 sm:justify-between">
            <div
              class="flex flex-col items-center justify-center gap-1.5 rounded-xl border p-2.5 sm:h-full sm:rounded-2xl"
            >
              <span class="bg-muted block h-2 w-10 rounded-md" />
              <span class="bg-muted block h-3 w-14 rounded-md" />
            </div>

            <div
              class="relative isolate flex flex-col items-center justify-end -space-y-13 sm:flex-row sm:space-y-0 sm:-space-x-5"
            >
              <span
                v-for="i in 2"
                :key="i"
                class="bg-muted flex size-16 items-center justify-center overflow-hidden rounded-2xl border-2 nth-2:scale-90 sm:odd:-rotate-12 sm:even:rotate-12 sm:nth-2:scale-100"
                :style="`z-index: ${2 - i}`"
              />
            </div>
          </div>

          <div class="mt-auto flex items-end justify-between pt-8 sm:pt-4">
            <span class="bg-muted block h-3 w-16 rounded-md sm:w-28" />
          </div>
        </CardNotch>
      </div>
    </section>
  </div>
</template>
