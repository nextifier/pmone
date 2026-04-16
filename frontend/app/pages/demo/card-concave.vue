<script setup lang="ts">
definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Card Concave" });
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">Card Concave</h1>
      <p class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg">
        Card with true transparent concave rounding where a protruding element (like a logo) meets
        the card body. Uses SVG filters for smooth concave corners - no images, no box-shadow hacks.
      </p>
    </div>

    <!-- Basic: Top Left -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Basic - Top Left</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Default position: protruding element at top-left with concave rounding at the junction.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <CardConcave body-class="px-4 pt-10 pb-4">
          <template #protrusion>
            <div
              class="bg-primary text-primary-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
            >
              AB
            </div>
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">Air Minum Biru</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Retail & Wholesales</p>
        </CardConcave>

        <CardConcave body-class="px-4 pt-10 pb-4">
          <template #protrusion>
            <div
              class="bg-destructive text-destructive-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
            >
              BG
            </div>
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">Burger Bangor</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Food & Beverage</p>
        </CardConcave>

        <CardConcave body-class="px-4 pt-10 pb-4">
          <template #protrusion>
            <div
              class="bg-info text-info-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
            >
              CC
            </div>
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">Cheezy Coin</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Entertainment</p>
        </CardConcave>
      </div>
    </section>

    <!-- Positions -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Positions</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Protruding element can be positioned at top-left, top-center, or top-right.
      </p>
      <div class="grid gap-6 sm:grid-cols-3">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            top-left (default)
          </span>
          <CardConcave position="top-left" body-class="px-4 pt-10 pb-4">
            <template #protrusion>
              <div
                class="bg-primary text-primary-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
              >
                TL
              </div>
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Top Left</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Default protrusion position
            </p>
          </CardConcave>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            top-center
          </span>
          <CardConcave position="top-center" body-class="px-4 pt-10 pb-4 text-center">
            <template #protrusion>
              <div
                class="bg-warning text-warning-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
              >
                TC
              </div>
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Top Center</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Centered protrusion
            </p>
          </CardConcave>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            top-right
          </span>
          <CardConcave position="top-right" body-class="px-4 pt-10 pb-4 text-right">
            <template #protrusion>
              <div
                class="bg-destructive text-destructive-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
              >
                TR
              </div>
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Top Right</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Right-aligned protrusion
            </p>
          </CardConcave>
        </div>
      </div>
    </section>

    <!-- No Border variant -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Without Border</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Use <code class="bg-muted rounded px-1 py-0.5 text-xs">:bordered="false"</code> to remove
        the border and shadow. Best on colored backgrounds.
      </p>
      <div
        class="grid gap-6 rounded-xl bg-gradient-to-r from-blue-100 via-purple-100 to-pink-100 p-6 sm:grid-cols-3 dark:from-blue-950 dark:via-purple-950 dark:to-pink-950"
      >
        <CardConcave :bordered="false" body-class="px-4 pt-10 pb-4">
          <template #protrusion>
            <div
              class="bg-primary text-primary-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
            >
              A
            </div>
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">No Border</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            Card on colored background
          </p>
        </CardConcave>
        <CardConcave :bordered="false" position="top-center" body-class="px-4 pt-10 pb-4 text-center">
          <template #protrusion>
            <div
              class="bg-warning text-warning-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
            >
              B
            </div>
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">No Border</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Center position</p>
        </CardConcave>
        <CardConcave :bordered="false" position="top-right" body-class="px-4 pt-10 pb-4 text-right">
          <template #protrusion>
            <div
              class="bg-destructive text-destructive-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
            >
              C
            </div>
          </template>
          <h3 class="text-sm font-medium tracking-tight sm:text-base">No Border</h3>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Right position</p>
        </CardConcave>
      </div>
    </section>

    <!-- Custom Sizes -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Custom Sizes</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Adjust <code class="bg-muted rounded px-1 py-0.5 text-xs">size</code>,
        <code class="bg-muted rounded px-1 py-0.5 text-xs">gap</code>,
        <code class="bg-muted rounded px-1 py-0.5 text-xs">radius</code>, and
        <code class="bg-muted rounded px-1 py-0.5 text-xs">blur-radius</code> for different looks.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Large protrusion (5rem)
          </span>
          <CardConcave size="5rem" offset="1.5rem" gap="4px" :blur-radius="7" body-class="px-4 pt-14 pb-4">
            <template #protrusion>
              <div
                class="bg-primary text-primary-foreground flex size-full items-center justify-center rounded-full text-lg font-semibold tracking-tighter"
              >
                LG
              </div>
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Large Logo</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              5rem protrusion with blur-radius 7
            </p>
          </CardConcave>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Small protrusion (2.5rem)
          </span>
          <CardConcave size="2.5rem" offset="1rem" gap="2px" :blur-radius="4" body-class="px-4 pt-7 pb-4">
            <template #protrusion>
              <div
                class="bg-destructive text-destructive-foreground flex size-full items-center justify-center rounded-full text-xs font-semibold tracking-tight"
              >
                SM
              </div>
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Small Logo</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              2.5rem protrusion with blur-radius 4
            </p>
          </CardConcave>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Large gap (8px) + large radius
          </span>
          <CardConcave gap="8px" radius="1rem" :blur-radius="8" body-class="px-4 pt-10 pb-4">
            <template #protrusion>
              <div
                class="bg-warning text-warning-foreground flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight"
              >
                GP
              </div>
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Wide Gap</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              8px gap, 1rem radius, blur-radius 8
            </p>
          </CardConcave>
        </div>
      </div>
    </section>

    <!-- Transparency Test: Various Backgrounds -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Transparency Test</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Cards on different backgrounds to prove the gap between card and protrusion is truly
        transparent - not faked with box-shadow or pseudo-elements.
      </p>

      <!-- Solid color backgrounds -->
      <div class="mb-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl bg-red-100 p-6 dark:bg-red-950">
          <span class="text-muted-foreground mb-3 block text-xs tracking-tight sm:text-sm">
            Red background
          </span>
          <CardConcave body-class="px-4 pt-10 pb-4">
            <template #protrusion>
              <div
                class="bg-card flex size-full items-center justify-center rounded-full border text-sm font-semibold tracking-tight"
              >
                AB
              </div>
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">True Transparency</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Red shows through the gap
            </p>
          </CardConcave>
        </div>

        <div class="rounded-xl bg-blue-100 p-6 dark:bg-blue-950">
          <span class="text-muted-foreground mb-3 block text-xs tracking-tight sm:text-sm">
            Blue background
          </span>
          <CardConcave body-class="px-4 pt-10 pb-4" position="top-center">
            <template #protrusion>
              <div
                class="bg-card flex size-full items-center justify-center rounded-full border text-sm font-semibold tracking-tight"
              >
                CD
              </div>
            </template>
            <h3 class="text-center text-sm font-medium tracking-tight sm:text-base">
              True Transparency
            </h3>
            <p class="text-muted-foreground text-center text-xs tracking-tight sm:text-sm">
              Blue shows through the gap
            </p>
          </CardConcave>
        </div>

        <div class="rounded-xl bg-green-100 p-6 dark:bg-green-950">
          <span class="text-muted-foreground mb-3 block text-xs tracking-tight sm:text-sm">
            Green background
          </span>
          <CardConcave body-class="px-4 pt-10 pb-4" position="top-right">
            <template #protrusion>
              <div
                class="bg-card flex size-full items-center justify-center rounded-full border text-sm font-semibold tracking-tight"
              >
                EF
              </div>
            </template>
            <h3 class="text-right text-sm font-medium tracking-tight sm:text-base">
              True Transparency
            </h3>
            <p class="text-muted-foreground text-right text-xs tracking-tight sm:text-sm">
              Green shows through the gap
            </p>
          </CardConcave>
        </div>
      </div>

      <!-- Gradient background -->
      <div class="mb-6">
        <span class="text-muted-foreground mb-3 block text-xs tracking-tight sm:text-sm">
          Gradient background
        </span>
        <div
          class="grid gap-6 rounded-xl bg-gradient-to-r from-purple-200 via-pink-200 to-orange-200 p-6 sm:grid-cols-3 dark:from-purple-950 dark:via-pink-950 dark:to-orange-950"
        >
          <CardConcave body-class="px-4 pt-10 pb-4">
            <template #protrusion>
              <div
                class="bg-card flex size-full items-center justify-center rounded-full border text-sm font-semibold tracking-tight"
              >
                A
              </div>
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Gradient Test</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Gradient visible in gap
            </p>
          </CardConcave>
          <CardConcave body-class="px-4 pt-10 pb-4" position="top-center">
            <template #protrusion>
              <div
                class="bg-card flex size-full items-center justify-center rounded-full border text-sm font-semibold tracking-tight"
              >
                B
              </div>
            </template>
            <h3 class="text-center text-sm font-medium tracking-tight sm:text-base">
              Gradient Test
            </h3>
            <p class="text-muted-foreground text-center text-xs tracking-tight sm:text-sm">
              Center position
            </p>
          </CardConcave>
          <CardConcave body-class="px-4 pt-10 pb-4" position="top-right">
            <template #protrusion>
              <div
                class="bg-card flex size-full items-center justify-center rounded-full border text-sm font-semibold tracking-tight"
              >
                C
              </div>
            </template>
            <h3 class="text-right text-sm font-medium tracking-tight sm:text-base">
              Gradient Test
            </h3>
            <p class="text-muted-foreground text-right text-xs tracking-tight sm:text-sm">
              Right position
            </p>
          </CardConcave>
        </div>
      </div>

      <!-- Checkerboard transparency test -->
      <div>
        <span class="text-muted-foreground mb-3 block text-xs tracking-tight sm:text-sm">
          Checkerboard (like image editors)
        </span>
        <div
          class="grid gap-6 rounded-xl p-6 sm:grid-cols-3"
          style="
            background-image: repeating-conic-gradient(
              hsl(var(--muted)) 0% 25%,
              hsl(var(--muted-foreground) / 0.15) 0% 50%
            );
            background-size: 16px 16px;
          "
        >
          <CardConcave body-class="px-4 pt-10 pb-4">
            <template #protrusion>
              <div
                class="bg-card flex size-full items-center justify-center rounded-full border text-sm font-semibold tracking-tight"
              >
                A
              </div>
            </template>
            <h3 class="text-sm font-medium tracking-tight sm:text-base">Checkerboard</h3>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Pattern visible in gap
            </p>
          </CardConcave>
          <CardConcave body-class="px-4 pt-10 pb-4" position="top-center">
            <template #protrusion>
              <div
                class="bg-card flex size-full items-center justify-center rounded-full border text-sm font-semibold tracking-tight"
              >
                B
              </div>
            </template>
            <h3 class="text-center text-sm font-medium tracking-tight sm:text-base">
              Checkerboard
            </h3>
            <p class="text-muted-foreground text-center text-xs tracking-tight sm:text-sm">
              Center position
            </p>
          </CardConcave>
          <CardConcave body-class="px-4 pt-10 pb-4" position="top-right">
            <template #protrusion>
              <div
                class="bg-card flex size-full items-center justify-center rounded-full border text-sm font-semibold tracking-tight"
              >
                C
              </div>
            </template>
            <h3 class="text-right text-sm font-medium tracking-tight sm:text-base">
              Checkerboard
            </h3>
            <p class="text-muted-foreground text-right text-xs tracking-tight sm:text-sm">
              Right position
            </p>
          </CardConcave>
        </div>
      </div>
    </section>

    <!-- Brand Card Simulation -->
    <section>
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Brand Card Simulation</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Simulating the BrandCard use case with logo, name, category, booth info, and actions.
      </p>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <CardConcave
          v-for="brand in [
            { initials: 'AB', name: 'Air Minum Biru', category: '', booth: '-', color: 'bg-primary' },
            { initials: 'BG', name: 'Burger Bangor', category: 'Food & Beverage', booth: 'A-03', color: 'bg-destructive' },
            { initials: 'CC', name: 'Cheezy Coin', category: '', booth: '-', color: 'bg-warning' },
            { initials: 'DJ', name: 'DJ Juice', category: 'Food & Beverage', booth: '-', color: 'bg-info' },
            { initials: 'FA', name: 'Farmakita', category: '', booth: '-', color: 'bg-success' },
            { initials: 'CB', name: 'Cafe Bombom', category: 'Food & Beverage', booth: 'F-06', color: 'bg-primary' },
          ]"
          :key="brand.initials"
          body-class="px-4 pt-10 pb-4"
        >
          <template #protrusion>
            <div
              class="flex size-full items-center justify-center rounded-full text-sm font-semibold tracking-tight text-white"
              :class="brand.color"
            >
              {{ brand.initials }}
            </div>
          </template>
          <div class="flex flex-col gap-y-3">
            <div>
              <h3 class="text-sm font-medium tracking-tight sm:text-base">{{ brand.name }}</h3>
              <p
                v-if="brand.category"
                class="text-muted-foreground text-xs tracking-tight sm:text-sm"
              >
                {{ brand.category }}
              </p>
            </div>
            <div class="flex items-center gap-x-2">
              <span class="text-muted-foreground text-xs tracking-tight">Booth</span>
              <span class="text-xs font-medium tracking-tight sm:text-sm">{{ brand.booth }}</span>
            </div>
            <div
              class="text-muted-foreground flex items-center justify-between border-t pt-3 text-xs tracking-tight"
            >
              <span>Created 3 days ago</span>
              <span class="text-foreground cursor-pointer">View</span>
            </div>
          </div>
        </CardConcave>
      </div>
    </section>
  </div>
</template>
