<script setup lang="ts">
import { Time } from "@internationalized/date";

definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Time Picker" });

const basicTime = ref<Time | null>(new Time(13, 30));
const emptyTime = ref<Time | null>(null);
const hourTime = ref<Time | null>(new Time(9, 0));
const secondTime = ref<Time | null>(new Time(14, 25, 30));
const stepTime = ref<Time | null>(new Time(8, 0));
const constrainedTime = ref<Time | null>(new Time(10, 0));
const twelveHourTime = ref<Time | null>(new Time(13, 30));
const clearableTime = ref<Time | null>(new Time(11, 45));

type TimeRangeValue = { start: Time | undefined; end: Time | undefined };

const basicRange = ref<TimeRangeValue>({ start: new Time(9, 0), end: new Time(17, 0) });
const emptyRange = ref<TimeRangeValue>({ start: undefined, end: undefined });
const stepRange = ref<TimeRangeValue>({ start: new Time(9, 0), end: new Time(11, 30) });
const constrainedRange = ref<TimeRangeValue>({ start: new Time(9, 0), end: new Time(12, 0) });
const clearableRange = ref<TimeRangeValue>({ start: new Time(9, 0), end: new Time(17, 0) });
const invalidRange = ref<TimeRangeValue>({ start: new Time(15, 0), end: new Time(9, 0) });
const validRange = ref<TimeRangeValue>({ start: new Time(9, 0), end: new Time(17, 0) });

const formatTime = (t: Time | null | undefined) => {
  if (!t) {
    return "—";
  }
  return t.toString();
};

const formatRange = (r: TimeRangeValue) => {
  return `${formatTime(r.start)} → ${formatTime(r.end)}`;
};
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">Time Picker</h1>
      <p class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg">
        Segmented time inputs powered by reka-ui. Each segment (hour, minute, second) is keyboard
        navigable with arrow keys and accepts numeric input. Default format is 24-hour.
      </p>
    </div>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimePicker - Basic</h2>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">With initial value</label>
          <TimePicker v-model="basicTime" />
          <span class="text-muted-foreground text-sm tracking-tight">
            Value: <code class="bg-muted rounded px-1 py-0.5">{{ formatTime(basicTime) }}</code>
          </span>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Empty (placeholder)</label>
          <TimePicker v-model="emptyTime" />
          <span class="text-muted-foreground text-sm tracking-tight">
            Value: <code class="bg-muted rounded px-1 py-0.5">{{ formatTime(emptyTime) }}</code>
          </span>
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimePicker - Granularity</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Use <code class="bg-muted rounded px-1 py-0.5 text-xs">granularity</code> to control which
        segments render. Defaults to <code class="bg-muted rounded px-1 py-0.5 text-xs">minute</code>.
      </p>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-3">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">granularity="hour"</label>
          <TimePicker v-model="hourTime" granularity="hour" />
          <span class="text-muted-foreground text-sm tracking-tight">
            <code class="bg-muted rounded px-1 py-0.5">{{ formatTime(hourTime) }}</code>
          </span>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">granularity="minute" (default)</label>
          <TimePicker v-model="basicTime" />
          <span class="text-muted-foreground text-sm tracking-tight">
            <code class="bg-muted rounded px-1 py-0.5">{{ formatTime(basicTime) }}</code>
          </span>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">granularity="second"</label>
          <TimePicker v-model="secondTime" granularity="second" />
          <span class="text-muted-foreground text-sm tracking-tight">
            <code class="bg-muted rounded px-1 py-0.5">{{ formatTime(secondTime) }}</code>
          </span>
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimePicker - Hour Cycle</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Default <code class="bg-muted rounded px-1 py-0.5 text-xs">hourCycle</code> is
        <code class="bg-muted rounded px-1 py-0.5 text-xs">24</code>. Set to
        <code class="bg-muted rounded px-1 py-0.5 text-xs">12</code> for AM/PM.
      </p>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">24-hour (default)</label>
          <TimePicker v-model="basicTime" />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">12-hour</label>
          <ClientOnly>
            <TimePicker v-model="twelveHourTime" :hour-cycle="12" />
            <template #fallback>
              <div class="border-border h-9 w-full rounded-md border bg-transparent" />
            </template>
          </ClientOnly>
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimePicker - Step</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Use <code class="bg-muted rounded px-1 py-0.5 text-xs">step</code> to set increment values
        per segment when using arrow keys.
      </p>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">step minute=15</label>
          <TimePicker v-model="stepTime" :step="{ minute: 15 }" />
          <span class="text-muted-foreground text-sm tracking-tight">Arrow up/down jumps by 15</span>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">step minute=30, hour=2</label>
          <TimePicker v-model="stepTime" :step="{ minute: 30, hour: 2 }" />
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimePicker - Constraints</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">minValue</code> /
        <code class="bg-muted rounded px-1 py-0.5 text-xs">maxValue</code> mark out-of-range times
        as invalid.
      </p>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Between 09:00 and 17:00</label>
          <TimePicker
            v-model="constrainedTime"
            :min-value="new Time(9, 0)"
            :max-value="new Time(17, 0)"
          />
          <span class="text-muted-foreground text-sm tracking-tight">
            Try setting to 08:00 or 18:00 to see invalid state
          </span>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Disabled</label>
          <TimePicker :model-value="new Time(10, 30)" disabled />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Readonly</label>
          <TimePicker :model-value="new Time(15, 45)" readonly />
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimePicker - Clearable</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Pass <code class="bg-muted rounded px-1 py-0.5 text-xs">clearable</code> to render an X
        button on the right when the value is set. Clicking it sets the value to
        <code class="bg-muted rounded px-1 py-0.5 text-xs">undefined</code>.
      </p>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Clearable (with value)</label>
          <TimePicker v-model="clearableTime" clearable />
          <span class="text-muted-foreground text-sm tracking-tight">
            Value: <code class="bg-muted rounded px-1 py-0.5">{{ formatTime(clearableTime) }}</code>
          </span>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Clearable (no X when empty)</label>
          <TimePicker v-model="emptyTime" clearable />
          <span class="text-muted-foreground text-sm tracking-tight">
            X button hidden when value is null
          </span>
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimeRangePicker - Basic</h2>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">With initial range</label>
          <TimeRangePicker v-model="basicRange" />
          <span class="text-muted-foreground text-sm tracking-tight">
            Value: <code class="bg-muted rounded px-1 py-0.5">{{ formatRange(basicRange) }}</code>
          </span>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Empty (placeholder)</label>
          <TimeRangePicker v-model="emptyRange" />
          <span class="text-muted-foreground text-sm tracking-tight">
            Value: <code class="bg-muted rounded px-1 py-0.5">{{ formatRange(emptyRange) }}</code>
          </span>
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimeRangePicker - Step & Constraints</h2>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">step minute=15</label>
          <TimeRangePicker v-model="stepRange" :step="{ minute: 15 }" />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Between 08:00 and 18:00</label>
          <TimeRangePicker
            v-model="constrainedRange"
            :min-value="new Time(8, 0)"
            :max-value="new Time(18, 0)"
          />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Disabled</label>
          <TimeRangePicker
            :model-value="{ start: new Time(9, 0), end: new Time(17, 0) }"
            disabled
          />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">12-hour cycle</label>
          <ClientOnly>
            <TimeRangePicker v-model="basicRange" :hour-cycle="12" />
            <template #fallback>
              <div class="border-border h-9 w-full rounded-md border bg-transparent" />
            </template>
          </ClientOnly>
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimeRangePicker - Granularity</h2>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-3">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">granularity="hour"</label>
          <TimeRangePicker v-model="basicRange" granularity="hour" />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">granularity="minute"</label>
          <TimeRangePicker v-model="basicRange" />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">granularity="second"</label>
          <TimeRangePicker v-model="basicRange" granularity="second" />
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">TimeRangePicker - Clearable</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">clearable</code> resets both
        <code class="bg-muted rounded px-1 py-0.5 text-xs">start</code> and
        <code class="bg-muted rounded px-1 py-0.5 text-xs">end</code> to undefined in one click.
      </p>
      <div class="bg-background rounded-2xl border p-8">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Clearable range</label>
          <TimeRangePicker v-model="clearableRange" clearable />
          <span class="text-muted-foreground text-sm tracking-tight">
            Value: <code class="bg-muted rounded px-1 py-0.5">{{ formatRange(clearableRange) }}</code>
          </span>
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">
        TimeRangePicker - Range Validation
      </h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Reka-ui auto-validates that <code class="bg-muted rounded px-1 py-0.5 text-xs">start ≤ end</code>.
        Invalid ranges trigger destructive border via
        <code class="bg-muted rounded px-1 py-0.5 text-xs">data-invalid</code>. To extend with
        custom rules (e.g., disallow lunch break), pass
        <code class="bg-muted rounded px-1 py-0.5 text-xs">is-time-unavailable</code>.
      </p>
      <div class="bg-background grid gap-6 rounded-2xl border p-8 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Valid (start &lt; end)</label>
          <TimeRangePicker v-model="validRange" />
          <span class="text-muted-foreground text-sm tracking-tight">
            09:00 → 17:00 — neutral border
          </span>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium tracking-tight">Invalid (start &gt; end)</label>
          <TimeRangePicker v-model="invalidRange" />
          <span class="text-muted-foreground text-sm tracking-tight">
            15:00 → 09:00 — destructive border
          </span>
        </div>
      </div>
    </section>

    <section class="mb-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">Keyboard shortcuts</h2>
      <div class="bg-background rounded-2xl border p-8">
        <ul class="text-muted-foreground space-y-2 text-sm tracking-tight">
          <li>
            <kbd class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">Tab</kbd> — move to next
            segment
          </li>
          <li>
            <kbd class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">Shift + Tab</kbd> — move
            to previous segment
          </li>
          <li>
            <kbd class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">↑</kbd> /
            <kbd class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">↓</kbd> — increment /
            decrement segment
          </li>
          <li>
            <kbd class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">0-9</kbd> — type number
            directly
          </li>
          <li>
            <kbd class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">A</kbd> /
            <kbd class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">P</kbd> — toggle AM/PM
            (12-hour mode only)
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>
