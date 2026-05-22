<script setup lang="ts">
import { Button } from "@/components/ui/button";
import type { LightboxItem } from "@/components/ui/lightbox";
import { Lightbox } from "@/components/ui/lightbox";

definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Lightbox" });

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

const images = UNSPLASH_IDS.map(
  (id, i): LightboxItem => ({
    sm: `https://images.unsplash.com/photo-${id}?w=400&q=80`,
    md: `https://images.unsplash.com/photo-${id}?w=800&q=80`,
    lg: `https://images.unsplash.com/photo-${id}?w=1600&q=85`,
    xl: `https://images.unsplash.com/photo-${id}?w=2400&q=90`,
    name: `Underwater Photo ${i + 1}`,
    caption: `Jellyfish capture #${i + 1} - shot in deep ocean.`,
  })
);

const limitedImages = images.slice(0, 6);
const singleImage = [images[0]!];

const mixedItems: LightboxItem[] = [
  images[0]!,
  images[1]!,
  {
    type: "video",
    src: "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4",
    poster: `https://images.unsplash.com/photo-${UNSPLASH_IDS[2]}?w=1600&q=85`,
    name: "Big Buck Bunny",
    caption: "Sample MP4 video. Auto-pause saat slide berubah.",
  },
  images[3]!,
  {
    type: "video",
    src: "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4",
    poster: `https://images.unsplash.com/photo-${UNSPLASH_IDS[4]}?w=1600&q=85`,
    name: "Elephants Dream",
    caption: "Video kedua untuk test multi-video navigation.",
  },
  images[5]!,
];

const controlledOpen = ref(false);
const controlledIndex = ref(0);

function openAtIndex(i: number) {
  controlledIndex.value = i;
  controlledOpen.value = true;
}

const KEYBOARD_HINTS = [
  { keys: ["←", "→"], desc: "Navigate prev / next slide" },
  { keys: ["Home"], desc: "Jump ke slide pertama" },
  { keys: ["End"], desc: "Jump ke slide terakhir" },
  { keys: ["Esc"], desc: "Tutup lightbox" },
  { keys: ["Tab"], desc: "Cycle focus dalam controls" },
];
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">Lightbox</h1>
      <p class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg">
        Flexible image and video lightbox built dengan reka-ui Dialog + Embla Carousel. Mendukung
        touch swipe, keyboard nav, thumbnail strip, download, autoplay, dan video auto-pause. Hybrid
        API: drop-in grid, custom trigger slot, atau fully controlled state.
      </p>
    </div>

    <!-- 1. Basic Gallery -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">1. Basic gallery</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Default grid 3 sampai 6 kolom responsive. Klik thumbnail untuk membuka lightbox.
      </p>
      <Lightbox :items="images" alt="Underwater gallery" />
    </section>

    <!-- 2. Slot trigger custom UI -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">2. Custom trigger slot</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Pakai
        <code class="bg-muted rounded px-1 py-0.5 text-xs">#trigger</code> slot dengan scope
        <code class="bg-muted rounded px-1 py-0.5 text-xs">{ open, openAt }</code> untuk full
        control trigger UI.
      </p>
      <Lightbox :items="images.slice(0, 5)">
        <template #trigger="{ openAt }">
          <div class="grid grid-cols-3 gap-3 sm:grid-cols-5">
            <button
              v-for="(item, i) in images.slice(0, 5)"
              :key="i"
              type="button"
              class="border-border bg-card hover:border-primary/40 flex flex-col gap-2 rounded-xl border p-2 text-left transition"
              @click="openAt(i)"
            >
              <img
                :src="item.sm"
                :alt="item.name"
                class="aspect-square w-full rounded-lg object-cover"
                loading="lazy"
              />
              <span class="text-xs font-medium tracking-tight">{{ item.name }}</span>
            </button>
          </div>
        </template>
      </Lightbox>
    </section>

    <!-- 3. Limit + first spans large -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">3. Limit + first spans large</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:limit="4"</code> +
        <code class="bg-muted rounded px-1 py-0.5 text-xs">first-spans-large</code>. Item pertama
        mengisi 2x2, sisanya thumbnail kecil. Counter overlay menampilkan jumlah sisa.
      </p>
      <Lightbox
        :items="images"
        grid-class="grid grid-cols-4 gap-1 bg-muted"
        first-spans-large
        :limit="4"
        rounded="rounded-none"
      />
    </section>

    <!-- 4. Single trigger inside small frame -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">4. Single trigger</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Trigger custom hanya menampilkan 1 thumbnail dalam frame kecil
        <code class="bg-muted rounded px-1 py-0.5 text-xs">aspect-4/5 w-32</code> tapi tetap pakai
        full gallery di lightbox.
      </p>
      <div class="flex items-start gap-x-4">
        <Lightbox :items="images" thumbnail-key="md">
          <template #trigger="{ open }">
            <button
              type="button"
              class="bg-muted border-border relative block aspect-4/5 w-32 shrink-0 cursor-zoom-in overflow-hidden rounded-lg border"
              @click="open"
            >
              <img :src="images[0]!.md" :alt="images[0]!.name" class="size-full object-cover" />
              <span
                class="absolute right-1.5 bottom-1.5 rounded-full bg-black/55 px-2 py-0.5 text-xs font-medium tracking-tight text-white"
              >
                +{{ images.length - 1 }}
              </span>
            </button>
          </template>
        </Lightbox>
        <div class="text-muted-foreground space-y-1 text-sm tracking-tight">
          <p class="text-foreground font-medium">Standard Suite</p>
          <p>1 King Bed · 45 m² · Sea View</p>
          <p>{{ images.length }} foto tersedia di gallery.</p>
        </div>
      </div>
    </section>

    <!-- 5. Mixed images + video -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">5. Mixed images + video</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Array boleh berisi mix
        <code class="bg-muted rounded px-1 py-0.5 text-xs">{ type: "video", src }</code> dan image
        objects. Video auto-pause saat slide berubah atau lightbox ditutup. Thumbnail video pakai
        <code class="bg-muted rounded px-1 py-0.5 text-xs">poster</code> dengan play overlay icon.
      </p>
      <Lightbox :items="mixedItems" grid-class="grid grid-cols-3 gap-1 sm:grid-cols-6" />
    </section>

    <!-- 6. Programmatic control v-model -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">6. Programmatic control (v-model)</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Pakai
        <code class="bg-muted rounded px-1 py-0.5 text-xs">v-model:open</code> dan
        <code class="bg-muted rounded px-1 py-0.5 text-xs">v-model:index</code> untuk kontrol
        eksternal. Trigger slot kosong supaya tidak render grid bawaan.
      </p>
      <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
          <Button
            v-for="i in [0, 2, 4, 6, 8]"
            :key="i"
            variant="outline"
            size="sm"
            @click="openAtIndex(i)"
          >
            Buka di index {{ i }}
          </Button>
          <Button variant="default" size="sm" @click="controlledOpen = true">
            Buka di posisi aktif ({{ controlledIndex }})
          </Button>
        </div>
        <p class="text-muted-foreground text-sm tracking-tight">
          State: open = <span class="text-foreground font-mono">{{ controlledOpen }}</span
          >, index = <span class="text-foreground font-mono">{{ controlledIndex }}</span>
        </p>
        <Lightbox v-model:open="controlledOpen" v-model:index="controlledIndex" :items="images">
          <template #trigger>
            <div />
          </template>
        </Lightbox>
      </div>
    </section>

    <!-- 7. Autoplay slideshow -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">7. Autoplay slideshow</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:autoplay="3500"</code> auto-advance
        setiap 3.5 detik. Hover untuk pause. Slide tipe video akan otomatis pause autoplay.
      </p>
      <Lightbox
        :items="images.slice(0, 6)"
        :autoplay="3500"
        grid-class="grid grid-cols-3 gap-2 sm:grid-cols-6"
      />
    </section>

    <!-- 8. No thumbnails / no download -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">8. Minimal chrome</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:show-thumbnails="false"</code>,
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:show-download="false"</code>,
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:show-counter="false"</code>. Hanya
        viewport + close + nav buttons.
      </p>
      <Lightbox
        :items="images.slice(0, 4)"
        :show-thumbnails="false"
        :show-download="false"
        :show-counter="false"
        grid-class="grid grid-cols-2 gap-2 sm:grid-cols-4"
      />
    </section>

    <!-- 9. Custom caption slot -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">9. Custom caption slot</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Override
        <code class="bg-muted rounded px-1 py-0.5 text-xs">#caption</code> slot untuk full custom
        markup. Default caption diambil dari
        <code class="bg-muted rounded px-1 py-0.5 text-xs">item.caption</code> atau
        <code class="bg-muted rounded px-1 py-0.5 text-xs">item.name</code>.
      </p>
      <Lightbox :items="images.slice(0, 5)" grid-class="grid grid-cols-5 gap-2">
        <template #caption>
          <div class="mx-auto max-w-2xl text-center text-white">
            <p class="text-base font-semibold tracking-tight sm:text-lg">📸 Powered by Unsplash</p>
            <p class="text-sm tracking-tight text-white/70">
              Custom caption block dapat render text, button, atau metadata lain.
            </p>
          </div>
        </template>
      </Lightbox>
    </section>

    <!-- 10. Single item -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">10. Single item gallery</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Kalau items berisi 1 saja, nav buttons + thumbnails + counter otomatis hidden. Hanya
        viewport + close + download.
      </p>
      <Lightbox :items="singleImage" grid-class="grid grid-cols-1 max-w-sm" />
    </section>

    <!-- 11. Keyboard / a11y info -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Keyboard &amp; accessibility</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Saat lightbox aktif, shortcut berikut berlaku. Focus trap dan ARIA dialog handle by reka-ui
        Dialog. DialogTitle disediakan via VisuallyHidden untuk screen reader.
      </p>
      <div class="border-border divide-border bg-card divide-y rounded-xl border">
        <div
          v-for="hint in KEYBOARD_HINTS"
          :key="hint.desc"
          class="flex items-center justify-between gap-4 px-4 py-3"
        >
          <span class="text-sm tracking-tight sm:text-base">{{ hint.desc }}</span>
          <div class="flex items-center gap-1">
            <kbd
              v-for="key in hint.keys"
              :key="key"
              class="bg-muted border-border rounded border px-2 py-0.5 font-mono text-xs tracking-tight"
            >
              {{ key }}
            </kbd>
          </div>
        </div>
      </div>
    </section>

    <!-- 12. Custom counter format -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Custom counter format</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Override counter via prop
        <code class="bg-muted rounded px-1 py-0.5 text-xs">counterFormat</code>. Default
        <code class="bg-muted rounded px-1 py-0.5 text-xs">1 / 12</code>, contoh di bawah
        <code class="bg-muted rounded px-1 py-0.5 text-xs">Image 1 of 6</code>.
      </p>
      <Lightbox
        :items="limitedImages"
        :counter-format="(i, total) => `Image ${i + 1} of ${total}`"
        grid-class="grid grid-cols-3 gap-2 sm:grid-cols-6"
      />
    </section>

    <!-- 13. Zoom + fullscreen + share buttons -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Zoom, fullscreen, share</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Klik image untuk toggle zoom 1.6x. Tombol Fullscreen masuk ke fullscreen API
        browser. Tombol Share pakai Web Share API (mobile only / browser support).
      </p>
      <Lightbox
        :items="limitedImages"
        zoomable
        show-fullscreen
        show-share
        grid-class="grid grid-cols-3 gap-2 sm:grid-cols-6"
      />
    </section>

    <!-- 14. Pull-to-close + safe area -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Pull-to-close (mobile)</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Di mobile, swipe vertical (atas atau bawah) sejauh 120px untuk tutup lightbox.
        Backdrop fade saat drag. Default
        <code class="bg-muted rounded px-1 py-0.5 text-xs">swipeToClose</code> aktif.
      </p>
      <Lightbox
        :items="limitedImages"
        grid-class="grid grid-cols-3 gap-2 sm:grid-cols-6"
      />
    </section>

    <!-- 15. Last default -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Compact 6-image preview</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Drop-in style untuk gallery hotel detail (3 sampai 6 kolom).
      </p>
      <Lightbox
        :items="limitedImages"
        alt="Hotel gallery"
        grid-class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6"
      />
    </section>
  </div>
</template>
