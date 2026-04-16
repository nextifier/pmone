<script setup lang="ts">
definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Grid Fill" });

const items = Array.from({ length: 15 }, (_, i) => ({
  id: i + 1,
  name: `Item ${i + 1}`,
}));

const itemCounts = [7, 12, 15, 20];
const selectedCount = ref(15);
const selectedBreakpoint = ref<"xs" | "sm" | "md" | "lg" | "xl">("sm");
const selectedCols = ref(2);
const selectedMinColWidth = ref("180px");

const breakpoints = ["xs", "sm", "md", "lg", "xl"] as const;
const colOptions = [1, 2, 3, 4, 5, 6];
const minColWidths = ["120px", "150px", "180px", "220px", "280px"];
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">Grid Fill</h1>
      <p class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg">
        Responsive grid that auto-detects column count and fills remaining cells in the last row
        with a diagonal pattern. Supports configurable breakpoints, custom filler slots, and
        flexible column sizing.
      </p>
    </div>

    <!-- Basic -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Basic</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Default configuration: <code class="bg-muted rounded px-1 py-0.5 text-xs">cols=2</code>,
        <code class="bg-muted rounded px-1 py-0.5 text-xs">min-col-width="180px"</code>,
        <code class="bg-muted rounded px-1 py-0.5 text-xs">breakpoint="sm"</code>. The last row is
        filled with diagonal pattern.
      </p>
      <GridFill :count="7">
        <div
          v-for="i in 7"
          :key="i"
          class="flex aspect-square flex-col items-center justify-center gap-y-1"
        >
          <div
            class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter"
          >
            {{ i }}
          </div>
          <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">Item {{ i }}</span>
        </div>
      </GridFill>
    </section>

    <!-- Custom Filler Slot -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Custom Filler Slot</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Use the
        <code class="bg-muted rounded px-1 py-0.5 text-xs">#filler</code> slot to customize filler
        content. The first filler shows "and many more", the rest use the default pattern.
      </p>
      <GridFill :count="10" filler-class="bg-pattern-diagonal aspect-square">
        <div
          v-for="i in 10"
          :key="i"
          class="flex aspect-square flex-col items-center justify-center gap-y-1"
        >
          <div
            class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter"
          >
            {{ i }}
          </div>
          <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">Brand {{ i }}</span>
        </div>
        <template #filler="{ index }">
          <div
            v-if="index === 1"
            class="flex aspect-square flex-col items-center justify-center p-4 text-center"
          >
            <span class="text-muted-foreground text-sm tracking-tight sm:text-base"
              >and many more</span
            >
          </div>
          <div v-else class="bg-pattern-diagonal aspect-square" />
        </template>
      </GridFill>
    </section>

    <!-- Breakpoints -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Breakpoints</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        The
        <code class="bg-muted rounded px-1 py-0.5 text-xs">breakpoint</code> prop controls when
        auto-fit kicks in. Below that breakpoint, the grid uses fixed
        <code class="bg-muted rounded px-1 py-0.5 text-xs">cols</code>.
      </p>
      <div class="mb-4 flex flex-wrap gap-2">
        <button
          v-for="bp in breakpoints"
          :key="bp"
          class="rounded-lg border px-3 py-1.5 text-xs font-medium tracking-tight transition sm:text-sm"
          :class="
            selectedBreakpoint === bp
              ? 'bg-primary text-primary-foreground'
              : 'bg-background hover:bg-muted'
          "
          @click="selectedBreakpoint = bp"
        >
          {{ bp }}
        </button>
      </div>
      <GridFill :count="8" :breakpoint="selectedBreakpoint">
        <div
          v-for="i in 8"
          :key="i"
          class="flex aspect-square flex-col items-center justify-center gap-y-1"
        >
          <div
            class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter"
          >
            {{ i }}
          </div>
        </div>
      </GridFill>
    </section>

    <!-- Interactive Playground -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Playground</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Adjust all props interactively to see how the grid responds.
      </p>

      <div class="bg-muted/50 mb-4 flex flex-wrap items-center gap-4 rounded-xl border p-4">
        <div class="flex flex-col gap-1">
          <label class="text-muted-foreground text-xs tracking-tight sm:text-sm">Items</label>
          <div class="flex gap-1">
            <button
              v-for="c in itemCounts"
              :key="c"
              class="rounded-md border px-2.5 py-1 text-xs font-medium tracking-tight transition sm:text-sm"
              :class="
                selectedCount === c
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-background hover:bg-muted'
              "
              @click="selectedCount = c"
            >
              {{ c }}
            </button>
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-muted-foreground text-xs tracking-tight sm:text-sm"
            >Cols (mobile)</label
          >
          <div class="flex gap-1">
            <button
              v-for="c in colOptions"
              :key="c"
              class="rounded-md border px-2.5 py-1 text-xs font-medium tracking-tight transition sm:text-sm"
              :class="
                selectedCols === c
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-background hover:bg-muted'
              "
              @click="selectedCols = c"
            >
              {{ c }}
            </button>
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-muted-foreground text-xs tracking-tight sm:text-sm"
            >Min Col Width</label
          >
          <div class="flex gap-1">
            <button
              v-for="w in minColWidths"
              :key="w"
              class="rounded-md border px-2.5 py-1 text-xs font-medium tracking-tight transition sm:text-sm"
              :class="
                selectedMinColWidth === w
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-background hover:bg-muted'
              "
              @click="selectedMinColWidth = w"
            >
              {{ w }}
            </button>
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-muted-foreground text-xs tracking-tight sm:text-sm">Breakpoint</label>
          <div class="flex gap-1">
            <button
              v-for="bp in breakpoints"
              :key="bp"
              class="rounded-md border px-2.5 py-1 text-xs font-medium tracking-tight transition sm:text-sm"
              :class="
                selectedBreakpoint === bp
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-background hover:bg-muted'
              "
              @click="selectedBreakpoint = bp"
            >
              {{ bp }}
            </button>
          </div>
        </div>
      </div>

      <div class="bg-muted/50 mb-4 rounded-lg border px-4 py-3">
        <code class="text-xs tracking-tight sm:text-sm">
          &lt;GridFill :count="{{ selectedCount }}" :cols="{{ selectedCols }}" min-col-width="{{
            selectedMinColWidth
          }}" breakpoint="{{ selectedBreakpoint }}" /&gt;
        </code>
      </div>

      <GridFill
        :count="selectedCount"
        :cols="selectedCols"
        :min-col-width="selectedMinColWidth"
        :breakpoint="selectedBreakpoint"
      >
        <div
          v-for="i in selectedCount"
          :key="i"
          class="flex aspect-square flex-col items-center justify-center gap-y-1"
        >
          <div
            class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter"
          >
            {{ i }}
          </div>
          <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">Item {{ i }}</span>
        </div>
      </GridFill>
    </section>

    <!-- Rounded -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Rounded</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Use the
        <code class="bg-muted rounded px-1 py-0.5 text-xs">rounded</code> prop to apply consistent
        rounded corners. Accepts: sm, md, lg, xl, 2xl, 3xl.
      </p>
      <div class="grid gap-6 sm:grid-cols-2">
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            rounded-lg
          </span>
          <GridFill :count="5" rounded="lg">
            <div
              v-for="i in 5"
              :key="i"
              class="flex aspect-square flex-col items-center justify-center gap-y-1"
            >
              <div
                class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter"
              >
                {{ i }}
              </div>
              <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Item {{ i }}
              </span>
            </div>
          </GridFill>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            rounded-xl
          </span>
          <GridFill :count="5" rounded="xl">
            <div
              v-for="i in 5"
              :key="i"
              class="flex aspect-square flex-col items-center justify-center gap-y-1"
            >
              <div
                class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter"
              >
                {{ i }}
              </div>
              <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Item {{ i }}
              </span>
            </div>
          </GridFill>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            rounded-2xl
          </span>
          <GridFill :count="5" rounded="2xl">
            <div
              v-for="i in 5"
              :key="i"
              class="flex aspect-square flex-col items-center justify-center gap-y-1"
            >
              <div
                class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter"
              >
                {{ i }}
              </div>
              <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Item {{ i }}
              </span>
            </div>
          </GridFill>
        </div>
        <div>
          <span class="text-muted-foreground mb-2 block text-xs tracking-tight sm:text-sm">
            no rounding (default)
          </span>
          <GridFill :count="5">
            <div
              v-for="i in 5"
              :key="i"
              class="flex aspect-square flex-col items-center justify-center gap-y-1"
            >
              <div
                class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter"
              >
                {{ i }}
              </div>
              <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Item {{ i }}
              </span>
            </div>
          </GridFill>
        </div>
      </div>
    </section>

    <!-- Non-square Items -->
    <section class="mb-12">
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Non-square Items</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        GridFill doesn't enforce
        <code class="bg-muted rounded px-1 py-0.5 text-xs">aspect-square</code>. Items and fillers
        can have any dimensions.
      </p>
      <GridFill
        :count="5"
        min-col-width="280px"
        :cols="1"
        filler-class="bg-pattern-diagonal min-h-32"
      >
        <div v-for="i in 5" :key="i" class="flex min-h-32 items-center gap-4 p-5">
          <div
            class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-lg text-sm font-medium tracking-tighter"
          >
            {{ i }}
          </div>
          <div>
            <p class="text-sm font-medium tracking-tight sm:text-base">Card item {{ i }}</p>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Non-square layout with flexible height
            </p>
          </div>
        </div>
      </GridFill>
    </section>

    <!-- Fixed Columns -->
    <section>
      <h2 class="mb-1.5 text-xl font-medium tracking-tighter">Fixed Columns</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight sm:text-base">
        Set
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:min-col-width="false"</code> to disable
        auto-fit and use fixed column count.
      </p>
      <GridFill :count="5" :min-col-width="false" :cols="4">
        <div
          v-for="i in 5"
          :key="i"
          class="flex aspect-square flex-col items-center justify-center gap-y-1"
        >
          <div
            class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter"
          >
            {{ i }}
          </div>
          <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">Item {{ i }}</span>
        </div>
      </GridFill>
    </section>
  </div>
</template>
