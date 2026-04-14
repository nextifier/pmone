<script setup lang="ts">
definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Avatar Group" });

const users = [
  { name: "Ada Lovelace" },
  { name: "Alan Turing" },
  { name: "Grace Hopper" },
  { name: "Linus Torvalds" },
  { name: "Margaret Hamilton" },
  { name: "Donald Knuth" },
];
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">Avatar Group</h1>
      <p class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg">
        Stacked circular avatars with a see-through gap. The overlap is clipped via CSS mask-image,
        so the parent background shows through, works on any surface.
      </p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
      <section class="bg-background rounded-2xl border p-8">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-medium tracking-tight">bg-background</h2>
          <span class="text-muted-foreground text-xs tracking-tight">plain surface</span>
        </div>
        <AvatarGroup :items="users" />
      </section>

      <section class="bg-muted rounded-2xl border p-8">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-medium tracking-tight">bg-muted</h2>
          <span class="text-muted-foreground text-xs tracking-tight">subtle gray</span>
        </div>
        <AvatarGroup :items="users" />
      </section>

      <section class="bg-card text-card-foreground rounded-2xl border p-8 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-medium tracking-tight">bg-card</h2>
          <span class="text-muted-foreground text-xs tracking-tight">with shadow</span>
        </div>
        <AvatarGroup :items="users" />
      </section>

      <section
        class="rounded-2xl p-8"
        style="
          background: linear-gradient(
            135deg,
            oklch(0.685 0.169 237.323),
            oklch(0.667 0.295 322.15)
          );
        "
      >
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-medium tracking-tight text-white">Linear gradient</h2>
          <span class="text-xs tracking-tight text-white/80">sky to fuchsia</span>
        </div>
        <AvatarGroup :items="users" />
      </section>

      <section
        class="rounded-2xl p-8"
        style="
          background: conic-gradient(
            from 180deg at 50% 50%,
            oklch(0.723 0.219 149.579),
            oklch(0.795 0.184 86.047),
            oklch(0.705 0.213 47.604),
            oklch(0.656 0.241 354.308),
            oklch(0.723 0.219 149.579)
          );
        "
      >
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-medium tracking-tight text-white">Conic gradient</h2>
          <span class="text-xs tracking-tight text-white/80">radial sweep</span>
        </div>
        <AvatarGroup :items="users" />
      </section>

      <section
        class="relative overflow-hidden rounded-2xl p-8"
        style="
          background-image: url(&quot;https://picsum.photos/seed/avatar-group/800/400&quot;);
          background-size: cover;
          background-position: center;
        "
      >
        <div class="absolute inset-0 bg-black/20"></div>
        <div class="relative">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-medium tracking-tight text-white">Image background</h2>
            <span class="text-xs tracking-tight text-white/80">photo</span>
          </div>
          <AvatarGroup :items="users" />
        </div>
      </section>
    </div>

    <div class="mt-12">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">Sizes</h2>
      <div class="bg-background flex flex-wrap items-end gap-8 rounded-2xl border p-8">
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">size=1.75</span>
          <AvatarGroup :items="users" :size="1.75" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">size=2.5 (default)</span>
          <AvatarGroup :items="users" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">size=4</span>
          <AvatarGroup :items="users" :size="4" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">size=6</span>
          <AvatarGroup :items="users" :size="6" />
        </div>
      </div>
    </div>

    <div class="mt-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">Overlap</h2>
      <div class="bg-background flex flex-wrap items-center gap-10 rounded-2xl border p-8">
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">overlap=0.2</span>
          <AvatarGroup :items="users.slice(0, 4)" :overlap="0.2" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">overlap=0.33</span>
          <AvatarGroup :items="users.slice(0, 4)" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">overlap=0.5</span>
          <AvatarGroup :items="users.slice(0, 4)" :overlap="0.5" />
        </div>
      </div>
    </div>

    <div class="mt-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">Max + Overflow</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Set <code class="bg-muted rounded px-1 py-0.5 text-xs">:max</code> for auto overflow. Hover
        the +N chip to see hidden users via Tippy.
      </p>
      <div class="bg-background flex flex-wrap items-center gap-10 rounded-2xl border p-8">
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">max=3 (6 users)</span>
          <AvatarGroup :items="users" :max="3" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">max=5 (6 users)</span>
          <AvatarGroup :items="users" :max="5" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">no max</span>
          <AvatarGroup :items="users" />
        </div>
      </div>
    </div>

    <div class="mt-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">Colorful</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:colorful</code> toggles the mesh
        gradient fallback for avatars without a profile image. Set to
        <code class="bg-muted rounded px-1 py-0.5 text-xs">false</code> for a neutral muted look.
      </p>
      <div class="bg-background flex flex-wrap items-center gap-10 rounded-2xl border p-8">
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">colorful=true (default)</span>
          <AvatarGroup :items="users" :max="4" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">colorful=false</span>
          <AvatarGroup :items="users" :max="4" :colorful="false" />
        </div>
      </div>
    </div>

    <div class="mt-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">Tooltip</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:show-tooltip</code> toggles the per-avatar
        Tippy tooltip on hover. Default
        <code class="bg-muted rounded px-1 py-0.5 text-xs">true</code>. Set to
        <code class="bg-muted rounded px-1 py-0.5 text-xs">false</code> to disable.
      </p>
      <div class="bg-background flex flex-wrap items-center gap-10 rounded-2xl border p-8">
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight"
            >show-tooltip=true (default)</span
          >
          <AvatarGroup :items="users" :max="4" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">show-tooltip=false</span>
          <AvatarGroup :items="users" :max="4" :show-tooltip="false" />
        </div>
      </div>
    </div>

    <div class="mt-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">Stacking order</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        <code class="bg-muted rounded px-1 py-0.5 text-xs">:first-on-top</code> (default
        <code class="bg-muted rounded px-1 py-0.5 text-xs">true</code>) controls which avatar sits
        on top. Set to <code class="bg-muted rounded px-1 py-0.5 text-xs">false</code> for
        later-on-top.
      </p>
      <div class="bg-background flex flex-wrap items-center gap-10 rounded-2xl border p-8">
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">default (first on top)</span>
          <AvatarGroup :items="users.slice(0, 5)" />
        </div>
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">first-on-top=false</span>
          <AvatarGroup :items="users.slice(0, 5)" :first-on-top="false" />
        </div>
      </div>
    </div>

    <div class="mt-10">
      <h2 class="mb-4 text-xl font-medium tracking-tighter">Custom overflow slot</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Override the +N chip with a named slot. Receives
        <code class="bg-muted rounded px-1 py-0.5 text-xs">{ count, hiddenItems }</code>.
      </p>
      <div class="bg-background flex flex-wrap items-center gap-10 rounded-2xl border p-8">
        <div class="flex flex-col items-start gap-2">
          <span class="text-muted-foreground text-xs tracking-tight">text label</span>
          <AvatarGroup :items="users" :max="3">
            <template #overflow="{ count }">
              <div
                class="bg-border/80 text-muted-foreground relative flex shrink-0 items-center justify-center text-sm font-medium tracking-tight"
                style="min-width: var(--avatar-size)"
              >
                +{{ count }}
              </div>
            </template>
          </AvatarGroup>
        </div>
      </div>
    </div>
  </div>
</template>
