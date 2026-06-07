<template>
  <div
    ref="rootEl"
    :class="
      presenting
        ? 'bg-background fixed inset-0 z-50 flex flex-col overflow-hidden'
        : 'h-screen-offset relative flex flex-col overflow-hidden'
    "
  >
    <!-- Toolbar: out of flow (so the stage/wheel uses the full height) and right-aligned
         to the same `.container` as the app header, so it lines up with the avatar at every breakpoint. -->
    <div v-show="!presenting" class="pointer-events-none absolute inset-x-0 top-3 z-30">
      <div class="container flex justify-end">
        <ButtonGroup class="pointer-events-auto">
          <Button variant="outline" size="sm" @click="entriesOpen = true">
            <Icon name="hugeicons:user-multiple-02" />
            <span>Entries</span>
            <span v-if="allOptions.length" class="text-muted-foreground">{{ allOptions.length }}</span>
          </Button>
          <Button
            v-if="state.winners.length"
            variant="outline"
            size="sm"
            v-tippy="'Winners'"
            @click="historyOpen = true"
          >
            <Icon name="hugeicons:champion" />
            <span class="text-muted-foreground">{{ state.winners.length }}</span>
          </Button>
          <Button variant="outline" size="iconSm" v-tippy="'Settings'" @click="settingsOpen = true">
            <Icon name="hugeicons:settings-02" />
          </Button>
          <Button variant="outline" size="iconSm" v-tippy="'Fullscreen'" @click="toggleFullscreen">
            <Icon name="hugeicons:full-screen" />
          </Button>
        </ButtonGroup>
      </div>
    </div>

    <!-- Stage -->
    <main
      class="relative mx-auto flex w-full max-w-5xl min-h-0 flex-1 flex-col items-center justify-center gap-2 px-4 pb-6"
    >
      <Button
        v-if="presenting"
        variant="ghost"
        size="icon"
        class="absolute top-4 right-4 z-20 rounded-full"
        @click="toggleFullscreen"
      >
        <Icon name="hugeicons:full-screen" class="size-4" />
      </Button>

      <!-- Empty state -->
      <div v-if="allOptions.length < 2" class="flex flex-col items-center gap-4 text-center">
        <div class="bg-muted grid size-12 place-items-center rounded-full">
          <Icon name="hugeicons:dice" class="size-6" />
        </div>
        <div class="space-y-1">
          <p class="text-primary text-lg font-medium tracking-tighter">Add entries to start</p>
          <p class="text-muted-foreground text-sm tracking-tight">Paste your names or items, then spin to pick a winner.</p>
        </div>
        <Button @click="entriesOpen = true">
          <Icon name="hugeicons:add-01" />
          Add entries
        </Button>
      </div>

      <!-- All entries drawn (no-repeat exhausted) -->
      <div v-else-if="pool.length < 1" class="flex flex-col items-center gap-4 text-center">
        <div class="bg-muted grid size-12 place-items-center rounded-full">
          <Icon name="hugeicons:champion" class="size-6" />
        </div>
        <div class="space-y-1">
          <p class="text-primary text-lg font-medium tracking-tighter">All entries drawn</p>
          <p class="text-muted-foreground text-sm tracking-tight">{{ state.winners.length }} winners picked. Reset to start over.</p>
        </div>
        <Button @click="reset">
          <Icon name="hugeicons:reload" />
          Reset
        </Button>
      </div>

      <!-- Draw stage -->
      <template v-else>
        <h1
          v-if="state.prize"
          class="text-primary shrink-0 text-center text-2xl font-semibold tracking-tighter text-balance sm:text-4xl"
        >
          {{ state.prize }}
        </h1>

        <div class="relative flex w-full min-h-0 flex-1 items-center justify-center">
          <Transition name="stage" mode="out-in">
            <!-- WHEEL -->
            <div
              v-if="effectiveMode === 'wheel'"
              key="wheel"
              class="relative aspect-square h-full max-w-full select-none"
            >
              <div
                class="pointer-events-none absolute top-1/2 -right-1 z-20 -translate-y-1/2"
                style="filter: drop-shadow(0 3px 4px rgb(0 0 0 / 0.3))"
              >
                <svg width="40" height="34" viewBox="0 0 40 34" fill="none">
                  <path d="M2 17 L28 4 A14 14 0 1 1 28 30 Z" fill="white" />
                  <circle cx="26" cy="17" r="5" class="fill-primary" />
                </svg>
              </div>
              <svg
                ref="wheelEl"
                viewBox="0 0 100 100"
                class="size-full cursor-grab touch-none drop-shadow-xl active:cursor-grabbing"
                :style="{ willChange: 'transform', transformOrigin: 'center' }"
              >
                <g v-for="(opt, i) in wheelView" :key="opt.label + i">
                  <path :d="segPath(i)" :fill="segColor(i)" stroke="white" stroke-width="0.4" />
                  <text
                    v-if="showLabels"
                    :transform="`rotate(${labelAngle(i)} 50 50)`"
                    x="94"
                    y="50"
                    text-anchor="end"
                    dominant-baseline="central"
                    :font-size="fontSize"
                    :fill="readableText(segColor(i))"
                    class="pointer-events-none font-bold tracking-tighter"
                    style="font-family: inherit"
                  >
                    {{ truncate(opt.label) }}
                  </text>
                </g>
                <circle cx="50" cy="50" r="49.4" fill="none" stroke="rgba(0,0,0,0.1)" stroke-width="0.6" />
                <circle cx="50" cy="50" r="7" fill="white" stroke="rgba(0,0,0,0.08)" stroke-width="0.5" />
              </svg>
              <button
                type="button"
                :disabled="busy || pool.length < 1"
                class="bg-card text-primary absolute top-1/2 left-1/2 z-10 grid size-[14%] -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border-2 border-white shadow-lg transition active:scale-95 disabled:opacity-60"
                @click="spin"
              >
                <span
                  v-if="state.settings.centerText"
                  class="px-1 text-center text-[10px] leading-tight font-semibold tracking-tight sm:text-xs"
                  >{{ state.settings.centerText }}</span
                >
                <Icon v-else name="hugeicons:play" class="size-[45%] translate-x-[5%]" />
              </button>
            </div>

            <!-- SLOT MACHINE -->
            <div
              v-else
              key="list"
              ref="listViewportEl"
              class="relative isolate mx-auto h-full w-full max-w-2xl overflow-hidden rounded-3xl"
            >
              <div ref="stripEl" class="relative z-10 flex flex-col will-change-transform">
                <div v-for="(opt, k) in reel" :key="k" class="flex h-24 items-center justify-center px-6 text-center">
                  <span
                    class="from-foreground to-foreground/55 line-clamp-1 bg-linear-to-r bg-clip-text text-4xl leading-[1.15]! font-semibold tracking-tighter text-transparent sm:text-7xl"
                    >{{ opt.label }}</span
                  >
                </div>
              </div>
              <div :class="['pointer-events-none absolute inset-x-0 top-1/2 z-30 mx-auto w-full max-w-xl -translate-y-1/2', justLanded && 'lk-landed']">
                <div class="via-primary absolute inset-x-0 -top-12 h-0.5 w-full bg-linear-to-r from-transparent to-transparent blur-xs" />
                <div class="via-primary absolute inset-x-0 -top-12 h-px w-full bg-linear-to-r from-transparent to-transparent" />
                <div class="via-primary absolute inset-x-0 top-12 h-0.5 w-full bg-linear-to-r from-transparent to-transparent blur-xs" />
                <div class="via-primary absolute inset-x-0 top-12 h-px w-full bg-linear-to-r from-transparent to-transparent" />
              </div>
              <div class="from-background via-background/0 to-background pointer-events-none absolute inset-0 z-20 bg-linear-to-b" />
              <div class="pointer-events-none absolute inset-x-0 top-1/2 z-20 flex -translate-y-1/2 items-center justify-between">
                <svg class="text-primary size-10 -rotate-90 sm:size-14" viewBox="0 0 24 24" fill="currentColor"><path d="M2 8C2 4 6 2 12 2C18 2 22 4 22 8C22 14 14 22 12 22C10 22 2 14 2 8Z" /></svg>
                <svg class="text-primary size-10 rotate-90 sm:size-14" viewBox="0 0 24 24" fill="currentColor"><path d="M2 8C2 4 6 2 12 2C18 2 22 4 22 8C22 14 14 22 12 22C10 22 2 14 2 8Z" /></svg>
              </div>
            </div>
          </Transition>

          <!-- Countdown overlay -->
          <Transition name="cd">
            <div v-if="countdownValue" class="bg-background/30 absolute inset-0 z-30 grid place-items-center backdrop-blur-[2px]">
              <span class="text-primary text-[22vw] leading-none font-semibold tracking-tighter sm:text-[160px]">{{ countdownValue }}</span>
            </div>
          </Transition>
        </div>

        <!-- Controls + inline winner (reserved height so the stage never reflows on reveal) -->
        <div
          :class="[
            'flex shrink-0 flex-col items-center justify-center gap-2',
            presenting ? 'min-h-44' : 'min-h-30',
          ]"
        >
          <Transition name="win" mode="out-in" @after-enter="focusSpinAgain">
            <div v-if="hasWinner" key="win" role="status" aria-live="polite" class="flex flex-col items-center gap-3">
              <template v-if="currentWinners.length === 1">
                <p v-if="!state.settings.winnerMessage" class="text-muted-foreground text-sm tracking-tight">Winner</p>
                <p
                  :class="[
                    'text-primary font-semibold tracking-tighter',
                    presenting ? 'text-5xl sm:text-7xl' : 'text-2xl sm:text-4xl',
                  ]"
                >
                  {{ state.settings.winnerMessage || currentWinners[0].name }}
                </p>
                <p
                  v-if="state.settings.winnerMessage"
                  :class="['text-primary font-medium tracking-tighter', presenting ? 'text-2xl sm:text-3xl' : 'text-lg']"
                >
                  {{ currentWinners[0].name }}
                </p>
              </template>
              <template v-else>
                <p class="text-muted-foreground text-sm tracking-tight">{{ currentWinners.length }} winners</p>
                <div class="flex max-h-[26vh] max-w-3xl flex-wrap items-center justify-center gap-x-4 gap-y-1 overflow-y-auto">
                  <span
                    v-for="(w, i) in currentWinners"
                    :key="i"
                    :class="[
                      'text-primary font-semibold tracking-tighter',
                      presenting ? 'text-3xl sm:text-5xl' : 'text-xl sm:text-2xl',
                    ]"
                    >{{ w.name }}</span
                  >
                </div>
              </template>
              <div class="flex flex-wrap items-center justify-center gap-2">
                <Button v-if="pool.length >= 1" ref="spinAgainBtn" :disabled="busy" @click="spin">
                  <Icon name="hugeicons:reload" />
                  Spin again
                </Button>
                <p v-else class="text-muted-foreground self-center text-sm tracking-tight">All entries drawn</p>
                <Button variant="outline" v-tippy="'Discard as no-show and draw replacements'" :disabled="busy" @click="redraw">
                  Redraw
                </Button>
                <Button variant="ghost" @click="reset">Reset</Button>
              </div>
            </div>
            <div v-else key="spin" class="flex flex-col items-center gap-2">
              <Button
                size="lg"
                class="h-14 min-w-56 rounded-full px-10 text-xl tracking-tighter sm:text-2xl"
                :disabled="busy || pool.length < 1"
                @click="spin"
              >
                {{ spinLabel }}
              </Button>
              <p v-if="!presenting" class="text-muted-foreground text-xs tracking-tight sm:text-sm">Press Space to spin</p>
            </div>
          </Transition>
        </div>
      </template>
    </main>

    <!-- Entries dialog -->
    <DialogResponsive v-model:open="entriesOpen" dialog-max-width="32rem">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h3 class="text-primary text-lg font-semibold tracking-tighter">Entries</h3>
          <p class="page-description">Set the prize and the names or items to draw from.</p>
        </div>
        <div class="mt-4 space-y-4">
          <div class="space-y-2">
            <Label for="lk-prize">Prize <span class="text-muted-foreground font-normal">(optional)</span></Label>
            <Input id="lk-prize" v-model="state.prize" placeholder="e.g. iPhone 16 Pro" />
          </div>
          <div class="space-y-2">
            <Label for="lk-entries" class="flex items-baseline gap-1.5">
              <span>Entries</span>
              <span class="text-muted-foreground text-xs font-normal">
                {{ allOptions.length }} item{{ allOptions.length === 1 ? "" : "s" }}
              </span>
            </Label>
            <Textarea
              id="lk-entries"
              v-model="entriesText"
              class="max-h-72 min-h-56 text-sm"
              placeholder="Paste or type one entry per line&#10;Alice&#10;Bob&#10;Charlie"
              @paste="onEntriesPaste"
              @blur="dedupeEntriesText"
            />
            <p class="text-muted-foreground text-xs tracking-tight">Duplicate entries are removed automatically.</p>
          </div>
        </div>
        <div class="mt-5 flex items-center justify-between gap-2">
          <Button
            variant="outline-destructive"
            size="sm"
            class="active:scale-98"
            :disabled="!state.entries.length && !state.prize"
            @click="requestClear"
          >
            <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
            <span>Clear entries & prize</span>
          </Button>
          <Button variant="outline" @click="entriesOpen = false">Done</Button>
        </div>
      </div>
    </DialogResponsive>

    <!-- Settings dialog -->
    <DialogResponsive v-model:open="settingsOpen" dialog-max-width="32rem">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h3 class="text-primary text-lg font-semibold tracking-tighter">Settings</h3>
          <p class="page-description">Customize how the draw looks and behaves.</p>
        </div>
        <div class="mt-4 space-y-5">
          <div class="space-y-1">
            <div v-for="t in toggles" :key="t.key" class="flex items-center justify-between gap-4 py-1">
              <Label :for="`lk-${t.key}`" class="cursor-pointer font-normal">{{ t.label }}</Label>
              <Switch :id="`lk-${t.key}`" v-model="state.settings[t.key]" />
            </div>
          </div>
          <div class="space-y-2">
            <Label class="flex items-center justify-between font-normal">
              <span>Volume</span>
              <span class="text-muted-foreground text-xs">{{ Math.round((state.settings.volume ?? 0.8) * 100) }}%</span>
            </Label>
            <Slider
              :model-value="[Math.round((state.settings.volume ?? 0.8) * 100)]"
              :min="0"
              :max="100"
              :step="5"
              @update:model-value="(v) => (state.settings.volume = (Array.isArray(v) ? v[0] : v) / 100)"
            />
          </div>
          <div class="grid grid-cols-2 gap-x-2 gap-y-4">
            <div class="space-y-2">
              <Label for="lk-celebration">Celebration</Label>
              <Select v-model="state.settings.confettiStyle">
                <SelectTrigger id="lk-celebration" class="w-full">
                  <SelectValue placeholder="Style" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="confetti">Confetti</SelectItem>
                  <SelectItem value="fireworks">Fireworks</SelectItem>
                  <SelectItem value="pride">Side cannons</SelectItem>
                  <SelectItem value="none">None</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label for="lk-spin">Spin seconds</Label>
              <InputNumber id="lk-spin" v-model="state.settings.spinDuration" :min="1" :max="20" />
            </div>
            <div class="space-y-2">
              <Label for="lk-winnercount">Winners per draw</Label>
              <InputNumber id="lk-winnercount" v-model="state.settings.winnerCount" :min="1" :max="50" />
            </div>
            <div class="space-y-2">
              <Label for="lk-threshold">Slot machine above</Label>
              <InputNumber id="lk-threshold" v-model="state.settings.threshold" :min="2" :max="60" />
            </div>
            <p class="text-muted-foreground col-span-2 -mt-2 text-xs">
              Entries above this number use the slot machine view; this many or fewer spin the wheel (a wheel with too
              many slots gets unreadable).
            </p>
            <div class="space-y-2">
              <Label for="lk-center">Wheel center text</Label>
              <Input id="lk-center" v-model="state.settings.centerText" maxlength="14" placeholder="SPIN" />
            </div>
            <div class="space-y-2">
              <Label for="lk-winnermsg">Winner message</Label>
              <Input id="lk-winnermsg" v-model="state.settings.winnerMessage" maxlength="80" placeholder="e.g. Congratulations!" />
            </div>
          </div>
        </div>
        <div class="mt-6 flex items-center justify-between gap-2">
          <Button variant="outline-destructive" size="sm" class="active:scale-98" @click="requestClearAll">
            <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
            <span>Clear all settings and data</span>
          </Button>
          <Button variant="outline" @click="settingsOpen = false">Done</Button>
        </div>
      </div>
    </DialogResponsive>

    <!-- Clear confirm -->
    <DialogResponsive v-model:open="clearConfirmOpen">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="text-primary text-lg font-semibold tracking-tighter">Clear entries & prize?</div>
        <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
          This removes all entries and the prize from this browser. This can't be undone.
        </p>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" @click="clearConfirmOpen = false">Cancel</Button>
          <Button variant="destructive" @click="clearEntries">Clear</Button>
        </div>
      </div>
    </DialogResponsive>

    <!-- Force winner (secret: type f then w) -->
    <DialogResponsive v-model:open="forceWinnerOpen" dialog-max-width="28rem">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h3 class="text-primary text-lg font-semibold tracking-tighter">Force winner</h3>
          <p class="page-description">Pick who wins the next spin. The result still animates normally.</p>
        </div>
        <div class="mt-4 space-y-2">
          <Input v-model="forceQuery" placeholder="Search entries..." />
          <div class="max-h-64 space-y-1 overflow-y-auto">
            <button
              type="button"
              class="hover:bg-muted flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm tracking-tight transition-colors"
              :class="!forcedWinnerLabel ? 'bg-muted font-medium' : ''"
              @click="setForced(null)"
            >
              <span>Random (off)</span>
              <Icon v-if="!forcedWinnerLabel" name="hugeicons:tick-02" class="text-primary size-4" />
            </button>
            <button
              v-for="o in forceMatches"
              :key="o.label"
              type="button"
              class="hover:bg-muted flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm tracking-tight transition-colors"
              :class="forcedWinnerLabel === o.label ? 'bg-muted font-medium' : ''"
              @click="setForced(o.label)"
            >
              <span class="line-clamp-1">{{ o.label }}</span>
              <Icon
                v-if="forcedWinnerLabel === o.label"
                name="hugeicons:tick-02"
                class="text-primary size-4 shrink-0"
              />
            </button>
            <p v-if="!pool.length" class="text-muted-foreground px-3 py-2 text-sm tracking-tight">No entries to pick from.</p>
            <p v-else-if="!forceMatches.length" class="text-muted-foreground px-3 py-2 text-sm tracking-tight">No matches.</p>
            <p
              v-else-if="pool.length > forceMatches.length"
              class="text-muted-foreground px-3 py-2 text-xs tracking-tight"
            >
              Showing {{ forceMatches.length }} of {{ pool.length }}. Type to narrow down.
            </p>
          </div>
        </div>
      </div>
    </DialogResponsive>

    <!-- Winner history -->
    <DialogResponsive v-model:open="historyOpen" dialog-max-width="28rem">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="flex items-start justify-between gap-2">
          <div class="space-y-1">
            <h3 class="text-primary text-lg font-semibold tracking-tighter">Winners</h3>
            <p class="page-description">{{ state.winners.length }} drawn this session.</p>
          </div>
          <div v-if="state.winners.length" class="flex items-center gap-1">
            <Button variant="outline" size="iconSm" v-tippy="'Download CSV'" @click="downloadWinners">
              <Icon name="hugeicons:download-01" />
            </Button>
            <ButtonCopy :text="winnersCopyText" />
          </div>
        </div>
        <ol class="mt-4 max-h-72 space-y-1 overflow-y-auto">
          <li
            v-for="(w, i) in winnersDisplay"
            :key="i"
            class="flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm tracking-tight"
          >
            <span class="text-muted-foreground w-6 shrink-0 text-right">{{ winnersDisplay.length - i }}</span>
            <span class="line-clamp-1 flex-1">{{ w.name }}</span>
            <span v-if="w.prize" class="text-muted-foreground line-clamp-1 shrink-0 text-xs">{{ w.prize }}</span>
          </li>
        </ol>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline-destructive" @click="reset(); historyOpen = false">Clear</Button>
          <Button variant="outline" @click="historyOpen = false">Done</Button>
        </div>
      </div>
    </DialogResponsive>

    <!-- Clear all confirm -->
    <DialogResponsive v-model:open="clearAllConfirmOpen">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="text-primary text-lg font-semibold tracking-tighter">Clear all settings and data?</div>
        <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
          This removes the entries, prize, winner history, and all settings from this browser and resets everything to
          defaults. This can't be undone.
        </p>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" @click="clearAllConfirmOpen = false">Cancel</Button>
          <Button variant="destructive" @click="clearAll">Clear everything</Button>
        </div>
      </div>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { gsap } from "gsap";

definePageMeta({
  layout: "default",
});

usePageMeta?.(null, { title: "Lucky Draw" });

const PALETTE = [
  "#6366F1", "#EC4899", "#F59E0B", "#10B981", "#3B82F6",
  "#8B5CF6", "#EF4444", "#14B8A6", "#F97316", "#06B6D4",
];
const STORAGE_KEY = "pmone-lucky-draw";
const ROW_H = 96; // h-24 fallback; the live row height is measured at spin time
const DEFAULT_SETTINGS = {
  sound: true,
  volume: 0.8,
  confettiStyle: "confetti",
  countdown: false,
  idleSpin: false,
  tickSound: false,
  noRepeat: true,
  winnerCount: 1,
  spinDuration: 6,
  threshold: 12,
  centerText: "",
  winnerMessage: "",
};

const toggles = [
  { key: "sound", label: "Sound effects" },
  { key: "countdown", label: "Countdown before spin" },
  { key: "tickSound", label: "Tick sound" },
  { key: "noRepeat", label: "No repeat winners" },
  { key: "idleSpin", label: "Idle auto-spin (wheel)" },
];

const rootEl = ref(null);
const wheelEl = ref(null);
const listViewportEl = ref(null);
const stripEl = ref(null);
const spinAgainBtn = ref(null);

function normalizeWinners(arr) {
  if (!Array.isArray(arr)) return [];
  return arr.map((w) => (typeof w === "string" ? { name: w, prize: "" } : { name: w?.name || "", prize: w?.prize || "" })).filter((w) => w.name);
}
function makeState(overrides = {}) {
  return {
    prize: "",
    entries: [],
    winners: [],
    ...overrides,
    settings: { ...DEFAULT_SETTINGS, ...(overrides.settings || {}) },
  };
}
const state = ref(makeState());

const presenting = ref(false);
const entriesOpen = ref(false);
const settingsOpen = ref(false);
const forceWinnerOpen = ref(false);
const historyOpen = ref(false);
const clearConfirmOpen = ref(false);
const clearAllConfirmOpen = ref(false);

const spinning = ref(false);
const countdownValue = ref(null);
const busy = computed(() => spinning.value || countdownValue.value !== null);

const currentWinners = ref([]);
const hasWinner = computed(() => currentWinners.value.length > 0);
const forcedWinnerLabel = ref(null);
const forceQuery = ref("");
const justLanded = ref(false);
const pool = ref([]);
const reel = ref([]);
// Snapshot of the pool the wheel is drawn from. It stays put while a winner is
// shown (the live pool may drop the winner via no-repeat) so the wheel never
// re-renders/jumps after a spin; it is re-synced when the next spin starts.
const wheelView = ref([]);
const winnersDisplay = computed(() => [...(state.value.winners || [])].reverse());
const winnersCopyText = computed(() =>
  (state.value.winners || []).map((w) => (w.prize ? `${w.name} - ${w.prize}` : w.name)).join("\n")
);
const forceMatches = computed(() => {
  const q = forceQuery.value.trim().toLowerCase();
  const base = q ? pool.value.filter((o) => o.label.toLowerCase().includes(q)) : pool.value;
  return base.slice(0, 100);
});

const entriesText = computed({
  get: () => (state.value.entries || []).join("\n"),
  set: (v) => {
    state.value.entries = String(v).split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
  },
});

// Entries are de-duplicated automatically (case-insensitive, first occurrence wins).
const allOptions = computed(() => {
  const seen = new Set();
  const out = [];
  for (const label of state.value.entries || []) {
    const key = label.toLowerCase();
    if (seen.has(key)) {
      continue;
    }
    seen.add(key);
    out.push({ label });
  }
  return out;
});
// Strip duplicates from the raw textarea so the typed/pasted list matches the
// (already de-duplicated) draw pool. Runs on paste and on blur. Case-insensitive,
// first occurrence wins. (The draw itself dedups live via `allOptions` regardless.)
function dedupeEntriesText() {
  const seen = new Set();
  const cleaned = [];
  for (const label of state.value.entries || []) {
    const key = label.toLowerCase();
    if (seen.has(key)) {
      continue;
    }
    seen.add(key);
    cleaned.push(label);
  }
  if (cleaned.length !== (state.value.entries || []).length) {
    state.value.entries = cleaned;
  }
}
// Paste fires before the textarea's input updates the model, so defer the clean.
function onEntriesPaste() {
  setTimeout(dedupeEntriesText, 0);
}
const threshold = computed(() => Number(state.value.settings.threshold) || 12);
const spinDuration = computed(() => Number(state.value.settings.spinDuration) || 6);
const effectiveMode = computed(() => (pool.value.length > threshold.value ? "list" : "wheel"));
const spinLabel = computed(() => {
  if (busy.value) return "Spinning...";
  const n = Math.min(Number(state.value.settings.winnerCount) || 1, pool.value.length);
  return n > 1 ? `Draw ${n} winners` : "Spin";
});

function rebuildPool() {
  const drawn = state.value.settings.noRepeat ? new Set((state.value.winners || []).map((w) => w.name)) : null;
  const opts = drawn ? allOptions.value.filter((o) => !drawn.has(o.label)) : allOptions.value;
  pool.value = opts.map((o, i) => ({ label: o.label, color: PALETTE[i % PALETTE.length] }));
}

// Rebuild the live pool whenever the entries change (keep winner history).
watch(
  allOptions,
  () => {
    rebuildPool();
    idleReel();
    currentWinners.value = [];
  },
  { immediate: true }
);
watch(() => state.value.settings.noRepeat, () => {
  rebuildPool();
  idleReel();
});

// ---- persistence ----
let loaded = false;
const persist = useDebounceFn(() => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state.value));
  } catch (e) {
    /* ignore quota */
  }
}, 400);

onMounted(() => {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : null;
    if (parsed && typeof parsed === "object") {
      // Accept both the new single-draw shape and the legacy multi-draw shape.
      const src = Array.isArray(parsed.draws)
        ? parsed.draws.find((d) => d.id === parsed.activeId) || parsed.draws[0]
        : parsed;
      if (src && typeof src === "object") {
        state.value = makeState({
          prize: src.prize || "",
          entries: Array.isArray(src.entries) ? src.entries : [],
          winners: normalizeWinners(src.winners),
          settings: src.settings || {},
        });
      }
    }
  } catch (e) {
    /* ignore corrupt storage */
  }
  loaded = true;

  document.addEventListener("fullscreenchange", onFullscreenChange);
  setupGsap();
});

// Persist on change. entries/winners are always reassigned (new array) and prize
// is a primitive, so a shallow reference watch catches them without deep-traversing
// the (potentially huge) entries array. Settings are mutated in place, so deep.
const onPersist = () => {
  if (loaded) persist();
};
watch([() => state.value.entries, () => state.value.winners, () => state.value.prize], onPersist);
watch(() => state.value.settings, onPersist, { deep: true });

onBeforeUnmount(() => {
  stopSound();
  idleTween?.kill();
  draggable?.kill();
  if (stripEl.value) {
    gsap.killTweensOf(stripEl.value);
  }
  if (wheelEl.value) {
    gsap.killTweensOf(wheelEl.value);
  }
  audioCtx?.close?.();
  document.removeEventListener("fullscreenchange", onFullscreenChange);
});

// ---- clear / entry tools ----
function requestClear() {
  // Close the entries dialog first, then open the confirm once it has finished
  // animating out (stacked DialogResponsive instances fight over the body scroll lock).
  entriesOpen.value = false;
  setTimeout(() => {
    clearConfirmOpen.value = true;
  }, 250);
}
function clearEntries() {
  state.value.prize = "";
  state.value.entries = [];
  state.value.winners = [];
  clearConfirmOpen.value = false;
}

function requestClearAll() {
  settingsOpen.value = false;
  setTimeout(() => {
    clearAllConfirmOpen.value = true;
  }, 250);
}
function clearAll() {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch (e) {
    /* ignore */
  }
  state.value = makeState();
  forcedWinnerLabel.value = null;
  currentWinners.value = [];
  rebuildPool();
  idleReel();
  clearAllConfirmOpen.value = false;
}

function setForced(label) {
  forcedWinnerLabel.value = label;
  forceWinnerOpen.value = false;
}

// ---- export ----
function downloadWinners() {
  const rows = [["Name", "Prize"], ...(state.value.winners || []).map((w) => [w.name, w.prize || ""])];
  const csv = rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(",")).join("\n");
  try {
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "lucky-draw-winners.csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  } catch (e) {
    /* ignore */
  }
}

// ---- wheel geometry ----
const N = computed(() => wheelView.value.length);
const seg = computed(() => 360 / Math.max(N.value, 1));
const fontSize = computed(() => Math.max(2.6, Math.min(4.8, 42 / Math.max(N.value, 1))));
const showLabels = computed(() => fontSize.value >= 2.4 && N.value <= 30);

function point(angleDeg, r) {
  const a = (angleDeg * Math.PI) / 180;
  return { x: 50 + r * Math.sin(a), y: 50 - r * Math.cos(a) };
}
function segPath(i) {
  const a0 = i * seg.value;
  const a1 = (i + 1) * seg.value;
  const p0 = point(a0, 49.4);
  const p1 = point(a1, 49.4);
  if (N.value === 1) return "M50 0.6 A49.4 49.4 0 1 1 49.99 0.6 Z";
  const large = seg.value > 180 ? 1 : 0;
  return `M50 50 L${p0.x.toFixed(3)} ${p0.y.toFixed(3)} A49.4 49.4 0 ${large} 1 ${p1.x.toFixed(3)} ${p1.y.toFixed(3)} Z`;
}
function segColor(i) {
  return wheelView.value[i]?.color || PALETTE[i % PALETTE.length];
}
// Radial label: the text sits on the 3 o'clock radius (ending near the rim) and
// is rotated to its segment. The pointer is on the RIGHT, so the winning segment
// always rotates back to angle 0 -> its label is horizontal and readable.
function labelAngle(i) {
  return i * seg.value + seg.value / 2 - 90;
}
function truncate(label) {
  // Radial run from near the rim (x=94) to just outside the hub (~x=56) ≈ 38 units.
  const max = Math.max(6, Math.floor(38 / (fontSize.value * 0.52)));
  const s = String(label ?? "");
  return s.length > max ? `${s.slice(0, max - 1)}…` : s;
}
function readableText(hex) {
  const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || "").trim());
  if (!m) return "#ffffff";
  const n = parseInt(m[1], 16);
  const lum = (0.299 * ((n >> 16) & 255) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
  return lum > 0.6 ? "#1f2937" : "#ffffff";
}
function indexAtPointer(rotation) {
  // Pointer is at 90 deg (right); find the segment occupying that angle.
  const phi = ((((90 - rotation) % 360) + 360) % 360) / seg.value;
  return Math.floor(phi) % Math.max(N.value, 1);
}
function syncWheelView() {
  wheelView.value = pool.value.map((o) => ({ label: o.label, color: o.color }));
}

// ---- slot-machine reel ----
// A bounded, pool-independent reel (~43 rows) so the strip stays performant for
// thousands of entries AND does not re-render when the pool changes on reveal
// (which previously shifted the centred name away from the announced winner).
const REEL_LEAD = 36; // rows scrolled past before the winner row
const REEL_SPIN_LEAD = 150; // long, fast run during a real spin -> high-velocity blur
const REEL_TRAIL = 6; // rows after the winner (fill below the centre line)
function randomOpt() {
  return pool.value[Math.floor(Math.random() * pool.value.length)];
}
function idleReel() {
  syncWheelView(); // keep the idle wheel in sync with the current pool
  const n = pool.value.length;
  reel.value = n ? Array.from({ length: Math.min(Math.max(n, 8), 16) }, (_, k) => pool.value[k % n]) : [];
  // Park a name on the centre line (with rows above & below) so the idle reel
  // looks aligned to the markers instead of starting on a row boundary.
  nextTick(() => {
    if (!stripEl.value || !reel.value.length) return;
    const rowH = stripEl.value.children[0]?.getBoundingClientRect().height || ROW_H;
    const viewportH = listViewportEl.value?.clientHeight || 440;
    const c = Math.min(2, reel.value.length - 1);
    gsap.set(stripEl.value, { y: -(c * rowH) + (viewportH / 2 - rowH / 2) });
  });
}
function buildReel(winnerOpt, lead = REEL_LEAD) {
  const leadRows = Array.from({ length: lead }, randomOpt);
  const trail = Array.from({ length: REEL_TRAIL }, randomOpt);
  reel.value = [...leadRows, winnerOpt, ...trail];
  return lead; // index of the winner row within the reel
}

// ---- GSAP: wheel spin, drag inertia, idle, tick ----
let DraggableClass = null;
let draggable = null;
let idleTween = null;
let audioCtx = null;

async function setupGsap() {
  // Keep spins resilient: if the window loses focus (or lags) mid-spin, GSAP
  // catches up to real time instead of slow-mo crawling. No effect at 60fps.
  gsap.ticker.lagSmoothing(0);
  try {
    const [{ Draggable }, { InertiaPlugin }] = await Promise.all([import("gsap/Draggable"), import("gsap/InertiaPlugin")]);
    gsap.registerPlugin(Draggable, InertiaPlugin);
    DraggableClass = Draggable;
  } catch (e) {
    /* gsap plugins unavailable */
  }
  await nextTick();
  ensureDraggable();
  startIdle();
}

watch([effectiveMode, () => state.value.settings.idleSpin], async () => {
  idleReel();
  await nextTick();
  ensureDraggable();
  startIdle();
});

function ensureDraggable() {
  if (effectiveMode.value !== "wheel" || !wheelEl.value || !DraggableClass) {
    draggable?.kill();
    draggable = null;
    return;
  }
  if (draggable) return;
  draggable = DraggableClass.create(wheelEl.value, {
    type: "rotation",
    inertia: true,
    throwResistance: 180,
    maxDuration: 6,
    minDuration: 1.2,
    dragResistance: 0,
    onPressInit() {
      idleTween?.kill();
      idleTween = null;
      gsap.killTweensOf(wheelEl.value);
    },
    onDragStart() {
      syncWheelView(); // align the wheel with the live pool before this drag
      spinning.value = true;
      currentWinners.value = [];
      startSound();
    },
    onThrowUpdate() {
      if (!state.value.settings.tickSound) return;
      const idx = indexAtPointer(this.rotation);
      if (idx !== this._tickIdx) {
        this._tickIdx = idx;
        playTick();
      }
    },
    onThrowComplete() {
      spinning.value = false;
      revealWinners([indexAtPointer(this.rotation)]);
    },
    onDragEnd() {
      if (!this.tween || !this.tween.isActive()) {
        spinning.value = false;
        revealWinners([indexAtPointer(this.rotation)]);
      }
    },
  })[0];
}

function startIdle() {
  idleTween?.kill();
  idleTween = null;
  if (effectiveMode.value !== "wheel" || !state.value.settings.idleSpin || !wheelEl.value) return;
  idleTween = gsap.to(wheelEl.value, { rotation: "+=360", duration: 24, ease: "none", repeat: -1, transformOrigin: "50% 50%" });
  gsap.fromTo(idleTween, { timeScale: 0 }, { timeScale: 1, duration: 1.4, ease: "power1.in" });
}

function spinWheelTo(index) {
  return new Promise((resolve) => {
    if (!wheelEl.value) return resolve();
    idleTween?.kill();
    idleTween = null;
    draggable?.disable();
    const center = index * seg.value + seg.value / 2;
    // Land the winner's centre at the pointer (90 deg, right side).
    const base = (((90 - center) % 360) + 360) % 360;
    const current = Number(gsap.getProperty(wheelEl.value, "rotation")) || 0;
    const spins = 5 + Math.floor(Math.random() * 3);
    const forward = (((base - (current % 360)) % 360) + 360) % 360;
    const jitter = (Math.random() - 0.5) * seg.value * 0.55;
    let lastIdx = -1;
    gsap.to(wheelEl.value, {
      rotation: current + spins * 360 + forward + jitter,
      duration: spinDuration.value,
      ease: "power4.out",
      transformOrigin: "50% 50%",
      overwrite: true, // kill the idle/prior rotation tween so spins never stack
      onUpdate: () => {
        if (!state.value.settings.tickSound) return;
        const idx = indexAtPointer(Number(gsap.getProperty(wheelEl.value, "rotation")) || 0);
        if (idx !== lastIdx) {
          lastIdx = idx;
          playTick();
        }
      },
      onComplete: () => {
        draggable?.enable();
        resolve();
      },
    });
  });
}

function spinListTo(index) {
  return new Promise((resolve) => {
    const winnerOpt = pool.value[index];
    if (!winnerOpt) return resolve();
    const winRow = buildReel(winnerOpt, REEL_SPIN_LEAD); // winner row index in the reel
    const useSound = state.value.settings.sound;
    // The drum-roll runs for the whole spin; the completion chime fires only when
    // the tween finishes (revealWinners), so it never overlaps the roll. Duration
    // matches the drum length by default so the chime lands as the roll ends.
    const duration = Math.max(2, spinDuration.value);
    nextTick(() => {
      if (!stripEl.value) return resolve();
      // Measure the real rendered row height so the winner is exactly centred on
      // any screen size (h-24 is the fallback only).
      const rowH = stripEl.value.children[0]?.getBoundingClientRect().height || ROW_H;
      // Centre the winner row on the live viewport, recomputed at landing too so a
      // late layout reflow (e.g. webfonts loading) can never leave it off-centre.
      const centerY = () => (listViewportEl.value?.clientHeight || 440) / 2 - rowH / 2;
      gsap.set(stripEl.value, { y: centerY() }); // row 0 centred
      if (useSound) {
        ensureAudio();
        drumRoll.playbackRate = 1;
        drumRoll.volume = vol();
        try {
          drumRoll.currentTime = 0;
          drumRoll.play().catch(() => {});
        } catch (e) {
          /* autoplay blocked */
        }
      }
      let lastTickRow = -1;
      gsap.to(stripEl.value, {
        y: -(winRow * rowH) + centerY(),
        duration,
        ease: "power4.out",
        overwrite: true, // kill any prior tween of this strip so spins never stack
        onUpdate: () => {
          if (!state.value.settings.tickSound) return;
          const y = Number(gsap.getProperty(stripEl.value, "y")) || 0;
          const row = Math.round((centerY() - y) / rowH);
          if (row !== lastTickRow) {
            lastTickRow = row;
            playTick();
          }
        },
        onComplete: () => {
          gsap.set(stripEl.value, { y: -(winRow * rowH) + centerY() });
          resolve();
        },
      });
    });
  });
}

// ---- pick + orchestration ----
const reducedMotion = usePreferredReducedMotion();

function pickIndices(count) {
  const n = pool.value.length;
  const k = Math.max(1, Math.min(count, n));
  // Pick k distinct random indices via rejection sampling (O(k)); avoids
  // allocating + fully shuffling an n-element array for thousands of entries.
  const used = new Set();
  const picked = [];
  if (forcedWinnerLabel.value) {
    const fi = pool.value.findIndex((o) => o.label === forcedWinnerLabel.value);
    if (fi >= 0) {
      used.add(fi);
      picked.push(fi);
    }
  }
  while (picked.length < k) {
    const i = Math.floor(Math.random() * n);
    if (!used.has(i)) {
      used.add(i);
      picked.push(i);
    }
  }
  return picked;
}

function runCountdown() {
  return new Promise((resolve) => {
    if (!state.value.settings.countdown || reducedMotion.value === "reduce") return resolve();
    let n = 3;
    countdownValue.value = n;
    const step = () => {
      n -= 1;
      if (n <= 0) {
        countdownValue.value = null;
        resolve();
      } else {
        countdownValue.value = n;
        setTimeout(step, 800);
      }
    };
    setTimeout(step, 800);
  });
}

async function spin() {
  if (busy.value || pool.value.length < 1) return;
  currentWinners.value = [];
  const count = Math.max(1, Math.min(Number(state.value.settings.winnerCount) || 1, pool.value.length));
  const indices = pickIndices(count);
  const primary = indices[0];
  if (pool.value.length === 1 || reducedMotion.value === "reduce") {
    // No animation: still centre the winner row on the slot-machine reel.
    if (effectiveMode.value === "list") {
      const w = buildReel(pool.value[primary]);
      await nextTick();
      if (stripEl.value) {
        const rowH = stripEl.value.children[0]?.getBoundingClientRect().height || ROW_H;
        const viewportH = listViewportEl.value?.clientHeight || 440;
        gsap.set(stripEl.value, { y: -(w * rowH) + (viewportH / 2 - rowH / 2) });
      }
    }
    revealWinners(indices);
    return;
  }
  await runCountdown();
  spinning.value = true;
  try {
    if (effectiveMode.value === "wheel") {
      syncWheelView(); // refresh the wheel to the current pool, then spin to the winner
      startSound();
      await spinWheelTo(primary);
    } else {
      await spinListTo(primary);
    }
  } finally {
    spinning.value = false;
    revealWinners(indices);
  }
}

function revealWinners(indices) {
  const picked = indices.map((i) => pool.value[i]).filter(Boolean);
  if (!picked.length) return;
  const prize = state.value.prize || "";
  const entries = picked.map((o) => ({ name: o.label, prize }));
  currentWinners.value = entries;
  state.value.winners = [...(state.value.winners || []), ...entries];
  if (state.value.settings.noRepeat) {
    const set = new Set(picked);
    pool.value = pool.value.filter((o) => !set.has(o));
  }
  forcedWinnerLabel.value = null;
  // The spin lasts the whole drum-roll; stop it here (spin end) and play the
  // completion chime, so the chime never overlaps or pre-empts the roll.
  stopSound();
  playChime();
  fireConfetti();
  if (effectiveMode.value === "list") {
    justLanded.value = true;
    setTimeout(() => {
      justLanded.value = false;
    }, 1200);
  }
}

function redraw() {
  // Discard the current winners as no-shows: drop them from history, exclude
  // them from the pool, and draw replacements of the same size.
  if (busy.value || !currentWinners.value.length) return;
  const names = new Set(currentWinners.value.map((w) => w.name));
  const count = currentWinners.value.length;
  state.value.winners = (state.value.winners || []).slice(0, Math.max(0, state.value.winners.length - count));
  pool.value = pool.value.filter((o) => !names.has(o.label));
  currentWinners.value = [];
  nextTick(spin);
}

function reset() {
  state.value.winners = [];
  forcedWinnerLabel.value = null;
  currentWinners.value = [];
  rebuildPool();
  idleReel(); // re-centres the strip on the centre line
  if (wheelEl.value) gsap.set(wheelEl.value, { rotation: 0 });
  nextTick(() => {
    ensureDraggable();
    startIdle();
  });
}

function focusSpinAgain() {
  if (!hasWinner.value) return;
  const el = spinAgainBtn.value?.$el || spinAgainBtn.value;
  el?.focus?.();
}

// ---- sound ----
let drumRoll = null;
let chime = null;
function vol() {
  const v = Number(state.value.settings.volume);
  return Number.isFinite(v) ? Math.max(0, Math.min(1, v)) : 0.8;
}
function ensureAudio() {
  if (!drumRoll) {
    drumRoll = new Audio("/sfx/drum-roll.mp3");
    chime = new Audio("/sfx/completed.mp3");
  }
}
function startSound() {
  if (!state.value.settings.sound) return;
  ensureAudio();
  try {
    drumRoll.volume = vol();
    drumRoll.playbackRate = 1;
    drumRoll.currentTime = 0;
    drumRoll.play().catch(() => {});
  } catch (e) {
    /* autoplay */
  }
}
function stopSound() {
  if (!drumRoll) return;
  drumRoll.pause();
  drumRoll.currentTime = 0;
}
function playChime() {
  if (!state.value.settings.sound) return;
  ensureAudio();
  try {
    chime.volume = vol();
    chime.currentTime = 0;
    chime.play().catch(() => {});
  } catch (e) {
    /* */
  }
}
function playTick() {
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    audioCtx = audioCtx || new Ctx();
    if (audioCtx.state === "suspended") audioCtx.resume();
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.type = "square";
    osc.frequency.value = 880;
    gain.gain.setValueAtTime(0.09 * vol(), audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.05);
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.onended = () => {
      osc.disconnect();
      gain.disconnect();
    };
    osc.start();
    osc.stop(audioCtx.currentTime + 0.05);
  } catch (e) {
    /* */
  }
}

// ---- confetti ----
function brandColors() {
  try {
    const probe = document.createElement("span");
    probe.style.color = "var(--primary)";
    probe.style.display = "none";
    document.body.appendChild(probe);
    const rgb = getComputedStyle(probe).color;
    document.body.removeChild(probe);
    const m = rgb.match(/\d+/g);
    if (m && m.length >= 3) {
      const hex = "#" + m.slice(0, 3).map((n) => Number(n).toString(16).padStart(2, "0")).join("");
      return [hex, ...PALETTE.slice(0, 5)];
    }
  } catch (e) {
    /* */
  }
  return PALETTE.slice(0, 6);
}

async function fireConfetti() {
  const style = state.value.settings.confettiStyle || "confetti";
  if (style === "none") return;
  const confetti = (await import("canvas-confetti")).default;
  const colors = brandColors();
  if (style === "fireworks") {
    for (let i = 0; i < 6; i++) {
      setTimeout(() => confetti({ particleCount: 50, spread: 360, startVelocity: 32, colors, origin: { x: Math.random(), y: Math.random() * 0.5 } }), i * 200);
    }
    return;
  }
  if (style === "pride") {
    for (let i = 0; i < 5; i++) {
      setTimeout(() => {
        confetti({ particleCount: 30, angle: 60, spread: 55, origin: { x: 0 }, colors });
        confetti({ particleCount: 30, angle: 120, spread: 55, origin: { x: 1 }, colors });
      }, i * 160);
    }
    return;
  }
  const defaults = { origin: { y: 0.7 }, colors };
  const fire = (ratio, opts) => confetti({ ...defaults, ...opts, particleCount: Math.floor(220 * ratio) });
  fire(0.25, { spread: 26, startVelocity: 55 });
  fire(0.2, { spread: 60 });
  fire(0.35, { spread: 100, decay: 0.91, scalar: 0.8 });
  fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
  fire(0.1, { spread: 120, startVelocity: 45 });
}

// ---- fullscreen ----
function onFullscreenChange() {
  presenting.value = !!document.fullscreenElement;
}
function toggleFullscreen() {
  if (document.fullscreenElement) document.exitFullscreen?.();
  else rootEl.value?.requestFullscreen?.();
}

// ---- keyboard shortcuts (page-local, auto-cleanup on unmount) ----
const noDialog = computed(
  () =>
    !entriesOpen.value &&
    !settingsOpen.value &&
    !forceWinnerOpen.value &&
    !historyOpen.value &&
    !clearConfirmOpen.value &&
    !clearAllConfirmOpen.value
);
defineShortcuts({
  " ": { whenever: [noDialog], handler: () => spin() },
  r: { whenever: [noDialog], handler: () => reset() },
  shift_f: { whenever: [noDialog], handler: () => toggleFullscreen() },
  // Secret: type "f" then "w" to open the force-winner picker.
  "f-w": {
    whenever: [noDialog],
    handler: () => {
      forceQuery.value = "";
      forceWinnerOpen.value = true;
    },
  },
});
</script>

<style scoped>
.cd-enter-active,
.cd-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.cd-enter-from,
.cd-leave-to {
  opacity: 0;
  transform: scale(1.3);
}

.stage-enter-active,
.stage-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.stage-enter-from,
.stage-leave-to {
  opacity: 0;
  transform: scale(0.96);
}

.win-enter-active {
  transition:
    opacity 0.35s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.35s cubic-bezier(0.22, 1, 0.36, 1),
    filter 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
.win-leave-active {
  transition: opacity 0.15s ease;
}
.win-enter-from {
  opacity: 0;
  transform: scale(0.96) translateY(6px);
  filter: blur(6px);
}
.win-leave-to {
  opacity: 0;
}

.lk-landed {
  animation: lk-flash 1.2s ease-out;
}
@keyframes lk-flash {
  0% {
    opacity: 0.35;
    transform: translateY(-50%) scaleY(1.6);
  }
  100% {
    opacity: 1;
    transform: translateY(-50%) scaleY(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .cd-enter-active,
  .cd-leave-active,
  .stage-enter-active,
  .stage-leave-active,
  .win-enter-active,
  .win-leave-active {
    transition: none;
  }
  .win-enter-from {
    opacity: 1;
    transform: none;
    filter: none;
  }
  .lk-landed {
    animation: none;
  }
}
</style>
