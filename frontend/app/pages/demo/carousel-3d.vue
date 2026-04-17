<script setup lang="ts">
import { Carousel3d } from "@/components/ui/carousel-3d";

definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Carousel 3D" });

const UNSPLASH_IDS = [
  "1540968221243-29f5d70540bf",
  "1596135187959-562c650d98bc",
  "1628944682084-831f35256163",
  "1590013330451-3946e83e0392",
  "1590421959604-741d0eec0a2e",
  "1572613000712-eadc57acbecd",
  "1570097192570-4b49a6736f9f",
  "1620789550663-2b10e0080354",
  "1617775623669-20bff4ffaa5c",
  "1548600916-dc8492f8e845",
  "1573824969595-a76d4365a2e6",
  "1633936929709-59991b5fdd72",
];

interface JellyfishItem {
  src: string;
  alt: string;
}

const images: JellyfishItem[] = UNSPLASH_IDS.map((id, i) => ({
  src: `https://images.unsplash.com/photo-${id}?w=280`,
  alt: `Jellyfish ${i + 1}`,
}));

interface BrandItem {
  src?: string;
  initials: string;
  name: string;
  color: string;
}

const brands: BrandItem[] = [
  { initials: "AB", name: "Air Minum Biru", color: "bg-primary" },
  { initials: "BG", name: "Burger Bangor", color: "bg-destructive" },
  { initials: "CC", name: "Cheezy Coin", color: "bg-warning" },
  { initials: "DJ", name: "DJ Juice", color: "bg-info" },
  { initials: "FA", name: "Farmakita", color: "bg-success" },
  { initials: "CB", name: "Cafe Bombom", color: "bg-primary" },
  { initials: "GS", name: "Gelato Sore", color: "bg-destructive" },
  { initials: "HR", name: "Hotel Rasa", color: "bg-info" },
];

const animatedToggle = ref(true);
const clickedItem = ref<string | null>(null);

function onImageClick(item: JellyfishItem, index: number): void {
  clickedItem.value = `Clicked ${item.alt} (index ${index})`;
  setTimeout(() => {
    clickedItem.value = null;
  }, 2000);
}
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">Carousel 3D</h1>
      <p class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg">
        Pure-CSS 3D rotating ring of cards. Each item is laid out on a circular path with
        perspective depth, auto-rotating around the Y axis. Flexible via props, scoped slot,
        generics, and built-in accessibility controls.
      </p>
    </div>

    <!-- Basic -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Basic</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Default settings: 12 images, 32s rotation, lateral fade edges, responsive card width
        <code class="bg-muted rounded px-1 py-0.5 text-xs">clamp(10em, 35vw, 17.5em)</code>, 7/10
        aspect ratio, auto-pause when scrolled off-screen.
      </p>
      <Carousel3d :items="images" aria-label="Jellyfish image carousel" />
    </section>

    <!-- Animated Toggle (v-model support) -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">v-model Animated Toggle</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Use <code class="bg-muted rounded px-1 py-0.5 text-xs">v-model:animated</code> for two-way
        binding. External button OR click on the carousel body itself toggle the same state.
      </p>
      <div class="mb-4 flex justify-center">
        <Button variant="outline" size="sm" @click="animatedToggle = !animatedToggle">
          External: {{ animatedToggle ? "Pause" : "Play" }}
        </Button>
      </div>
      <Carousel3d v-model:animated="animatedToggle" :items="images" />
    </section>

    <!-- Click Handler -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Click Handler</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Listen to <code class="bg-muted rounded px-1 py-0.5 text-xs">@item-click</code> for
        interactivity. Enable <code class="bg-muted rounded px-1 py-0.5 text-xs">:pause-on-hover</code>
        so users can comfortably aim at a card before clicking.
      </p>
      <div class="mb-2 text-center">
        <span
          v-if="clickedItem"
          class="bg-primary text-primary-foreground inline-block rounded-full px-3 py-1 text-sm tracking-tight"
        >
          {{ clickedItem }}
        </span>
        <span v-else class="text-muted-foreground text-sm tracking-tight">
          Click a card to trigger the handler.
        </span>
      </div>
      <Carousel3d
        :items="images"
        :pause-on-hover="true"
        hover-scale="1.1"
        class="cursor-pointer"
        @item-click="onImageClick"
      />
    </section>

    <!-- Click-to-Toggle + Accessibility -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Click-to-Toggle + A11y</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Clicking anywhere on the carousel toggles play/pause. Component renders with
        <code class="bg-muted rounded px-1 py-0.5 text-xs">role="button"</code> (or
        <code class="bg-muted rounded px-1 py-0.5 text-xs">region</code> for single/empty),
        <code class="bg-muted rounded px-1 py-0.5 text-xs">aria-pressed</code>, and supports
        Enter/Space keyboard toggle.
      </p>
      <Carousel3d :items="images" aria-label="Jellyfish gallery" />
    </section>

    <!-- Edge Cases: Item Count -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Edge Cases: Item Count</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Component handles edge counts gracefully. 1 item: static center. 2 items: arbitrary spacing
        (tan formula undefined at 180°). 3+ items: proper ring.
      </p>
      <div class="grid gap-6 lg:grid-cols-3">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            1 item (static)
          </span>
          <Carousel3d
            :items="[images[0]!]"
            card-width="10em"
            class="min-h-[18em]"
            perspective="25em"
          />
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            2 items (facing pair)
          </span>
          <Carousel3d
            :items="images.slice(0, 2)"
            card-width="10em"
            class="min-h-[18em]"
            perspective="25em"
          />
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            3 items (triangle ring)
          </span>
          <Carousel3d
            :items="images.slice(0, 3)"
            card-width="10em"
            class="min-h-[18em]"
            perspective="25em"
          />
        </div>
      </div>
    </section>

    <!-- Custom Duration -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Custom Duration</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Adjust rotation speed with <code class="bg-muted rounded px-1 py-0.5 text-xs">duration</code
        >. Values: <code class="bg-muted rounded px-1 py-0.5 text-xs">16s</code> (fast),
        <code class="bg-muted rounded px-1 py-0.5 text-xs">32s</code> (default),
        <code class="bg-muted rounded px-1 py-0.5 text-xs">64s</code> (slow).
      </p>
      <div class="grid gap-6 lg:grid-cols-3">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            16s (fast)
          </span>
          <Carousel3d
            :items="images"
            duration="16s"
            card-width="10em"
            class="min-h-[18em]"
            perspective="25em"
          />
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            32s (default)
          </span>
          <Carousel3d
            :items="images"
            duration="32s"
            card-width="10em"
            class="min-h-[18em]"
            perspective="25em"
          />
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            64s (slow)
          </span>
          <Carousel3d
            :items="images"
            duration="64s"
            card-width="10em"
            class="min-h-[18em]"
            perspective="25em"
          />
        </div>
      </div>
    </section>

    <!-- Reverse + Tilt -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Reverse + Tilt</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:reverse</code> flips rotation direction.
        <code class="bg-muted rounded px-1 py-0.5 text-xs">tilt</code> rotates the ring on X axis
        for extra depth.
      </p>
      <div class="grid gap-6 lg:grid-cols-2">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Reverse + tilt -10deg
          </span>
          <Carousel3d
            :items="images"
            :reverse="true"
            tilt="-10deg"
            card-width="12em"
            class="min-h-[20em]"
            perspective="30em"
          />
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Forward + tilt 10deg
          </span>
          <Carousel3d
            :items="images"
            tilt="10deg"
            card-width="12em"
            class="min-h-[20em]"
            perspective="30em"
          />
        </div>
      </div>
    </section>

    <!-- Initial Rotation -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Initial Rotation</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Use <code class="bg-muted rounded px-1 py-0.5 text-xs">initial-rotation</code> to offset the
        starting angle.
      </p>
      <div class="grid gap-6 lg:grid-cols-2">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            initial-rotation="0deg"
          </span>
          <Carousel3d
            :items="images"
            :animated="false"
            initial-rotation="0deg"
            card-width="12em"
            class="min-h-[20em]"
            perspective="30em"
          />
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            initial-rotation="45deg"
          </span>
          <Carousel3d
            :items="images"
            :animated="false"
            initial-rotation="45deg"
            card-width="12em"
            class="min-h-[20em]"
            perspective="30em"
          />
        </div>
      </div>
    </section>

    <!-- Custom Card Dimensions -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Custom Card Dimensions</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Adjust <code class="bg-muted rounded px-1 py-0.5 text-xs">card-width</code> and
        <code class="bg-muted rounded px-1 py-0.5 text-xs">card-aspect</code> to match your content.
      </p>
      <div class="grid gap-6 lg:grid-cols-2">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Small square cards (8em, 1/1)
          </span>
          <Carousel3d
            :items="images"
            card-width="8em"
            card-aspect="1/1"
            class="min-h-[14em]"
            perspective="22em"
          />
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            Wide landscape cards (14em, 16/9)
          </span>
          <Carousel3d
            :items="images"
            card-width="14em"
            card-aspect="16/9"
            class="min-h-[16em]"
            perspective="30em"
          />
        </div>
      </div>
    </section>

    <!-- Ground Shadow + No Fade -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Ground Shadow + No Fade</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:show-shadow="true"</code> adds soft
        ground shadow. <code class="bg-muted rounded px-1 py-0.5 text-xs">:fade-edges="false"</code>
        removes lateral mask fade.
      </p>
      <Carousel3d :items="images" :show-shadow="true" :fade-edges="false" />
    </section>

    <!-- Custom Slot Content (Generic Type) -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Custom Slot + Generic Type</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        The <code class="bg-muted rounded px-1 py-0.5 text-xs">#item</code> slot is fully typed via
        TypeScript generics. Here the <code class="bg-muted rounded px-1 py-0.5 text-xs">item</code>
        parameter is inferred as <code class="bg-muted rounded px-1 py-0.5 text-xs">BrandItem</code>
        with typed <code class="bg-muted rounded px-1 py-0.5 text-xs">initials</code> and
        <code class="bg-muted rounded px-1 py-0.5 text-xs">name</code> fields.
      </p>
      <Carousel3d
        :items="brands"
        card-width="11em"
        card-aspect="1/1"
        card-radius="999px"
        class="min-h-[18em]"
        perspective="28em"
      >
        <template #item="{ item }">
          <div
            :class="[
              item.color,
              'relative flex size-full flex-col items-center justify-center gap-y-2 text-white',
            ]"
          >
            <span class="text-3xl font-semibold tracking-tighter">
              {{ item.initials }}
            </span>
            <span class="px-3 text-center text-xs font-medium tracking-tight sm:text-sm">
              {{ item.name }}
            </span>
          </div>
        </template>
      </Carousel3d>
    </section>

    <!-- Custom cardClass -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Custom Card Class</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Use <code class="bg-muted rounded px-1 py-0.5 text-xs">card-class</code> to apply styling to
        every card wrapper.
      </p>
      <Carousel3d
        :items="images"
        card-class="ring-2 ring-primary/70 ring-offset-2 ring-offset-background shadow-xl"
      />
    </section>

    <!-- Combined Showcase -->
    <section
      class="-mx-4 rounded-2xl bg-gradient-to-br from-indigo-200 via-pink-200 to-orange-200 p-4 dark:from-indigo-950 dark:via-pink-950 dark:to-orange-950"
    >
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Combined Showcase</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        All features together: tilt, ground shadow, hover scale, click-to-toggle, slow rotation,
        custom card class, gradient background.
      </p>
      <Carousel3d
        :items="images"
        duration="48s"
        tilt="-8deg"
        hover-scale="1.1"
        :show-shadow="true"
        card-class="shadow-2xl"
        aria-label="Featured jellyfish showcase"
      />
    </section>
  </div>
</template>
