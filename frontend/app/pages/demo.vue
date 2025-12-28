<template>
  <div class="container pt-6 pb-24">
    <h1 class="text-center text-3xl font-semibold tracking-tighter">Glass Button</h1>

    <div class="mt-8 grid grid-cols-1 gap-y-10">
      <!-- Variants -->
      <div class="space-y-2.5">
        <h2 class="page-title">Variants</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-2.5">
          <div
            v-for="variant in variants"
            :key="variant"
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton :variant="variant">{{ variant }}</GlassButton>
            <span class="text-muted-foreground text-xs">{{ variant }}</span>
          </div>
        </div>
      </div>

      <!-- Rounded Variants -->
      <div class="space-y-2.5">
        <h2 class="page-title">Rounded Variants</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-2.5">
          <div
            v-for="rounded in roundedVariants"
            :key="rounded"
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton :rounded="rounded">Button</GlassButton>
            <span class="text-muted-foreground text-xs">{{ rounded }}</span>
          </div>
        </div>
      </div>

      <!-- Sizes -->
      <div class="space-y-2.5">
        <h2 class="page-title">Sizes</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-2.5">
          <div
            v-for="size in textSizes"
            :key="size"
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton :size="size">Button</GlassButton>
            <span class="text-muted-foreground text-xs">{{ size }}</span>
          </div>
        </div>
      </div>

      <!-- Icon Sizes -->
      <div class="space-y-2.5">
        <h2 class="page-title">Icon Sizes</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-2.5">
          <div
            v-for="size in iconSizes"
            :key="size"
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton :size="size">
              <Icon name="lucide:plus" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">{{ size }}</span>
          </div>
        </div>
      </div>

      <!-- Icon + Text -->
      <div class="space-y-2.5">
        <h2 class="page-title">Icon + Text</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton>
              <Icon name="lucide:download" class="size-4" />
              Download
            </GlassButton>
            <span class="text-muted-foreground text-xs">Leading Icon</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton>
              Continue
              <Icon name="lucide:arrow-right" class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Trailing Icon</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline">
              <Icon name="lucide:star" class="size-4" />
              Star Project
            </GlassButton>
            <span class="text-muted-foreground text-xs">Outline + Icon</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="secondary" rounded="lg">
              <Icon name="lucide:upload" class="size-4" />
              Upload File
            </GlassButton>
            <span class="text-muted-foreground text-xs">Secondary + Rounded LG</span>
          </div>
        </div>
      </div>

      <!-- Real Use Cases -->
      <div class="space-y-2.5">
        <h2 class="page-title">Real Use Cases</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
          <!-- Delete Action -->
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="destructive-outline" size="icon">
              <Icon name="lucide:trash-2" class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Delete Icon</span>
          </div>
          <!-- Delete with Text -->
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="destructive" rounded="lg">
              <Icon name="lucide:trash-2" class="size-4" />
              Delete Item
            </GlassButton>
            <span class="text-muted-foreground text-xs">Delete Button</span>
          </div>
          <!-- Edit Action -->
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" size="icon" rounded="lg">
              <Icon name="lucide:pencil" class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Edit Icon</span>
          </div>
          <!-- Settings -->
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="secondary" size="icon">
              <Icon name="lucide:settings" class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Settings</span>
          </div>
          <!-- Share -->
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" rounded="xl">
              <Icon name="lucide:share-2" class="size-4" />
              Share
            </GlassButton>
            <span class="text-muted-foreground text-xs">Share Button</span>
          </div>
          <!-- Save -->
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton rounded="xl">
              <Icon name="lucide:save" class="size-4" />
              Save Changes
            </GlassButton>
            <span class="text-muted-foreground text-xs">Save Button</span>
          </div>
        </div>
      </div>

      <!-- Loading States -->
      <div class="space-y-2.5">
        <h2 class="page-title">Loading States</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton disabled>
              <Spinner class="size-4" />
              Loading...
            </GlassButton>
            <span class="text-muted-foreground text-xs">Default Loading</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" disabled>
              <Spinner class="size-4" />
              Processing
            </GlassButton>
            <span class="text-muted-foreground text-xs">Outline Loading</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="secondary" disabled rounded="lg">
              <Spinner class="size-4" />
              Uploading...
            </GlassButton>
            <span class="text-muted-foreground text-xs">Secondary Loading</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton size="icon" disabled>
              <Spinner class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Icon Loading</span>
          </div>
        </div>
      </div>

      <!-- Disabled States -->
      <div class="space-y-2.5">
        <h2 class="page-title">Disabled States</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton disabled>Disabled</GlassButton>
            <span class="text-muted-foreground text-xs">Default Disabled</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" disabled>Disabled</GlassButton>
            <span class="text-muted-foreground text-xs">Outline Disabled</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="destructive" disabled>
              <Icon name="lucide:trash-2" class="size-4" />
              Delete
            </GlassButton>
            <span class="text-muted-foreground text-xs">Destructive Disabled</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="secondary" size="icon" disabled>
              <Icon name="lucide:plus" class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Icon Disabled</span>
          </div>
        </div>
      </div>

      <!-- Button Group Example -->
      <div class="space-y-2.5">
        <h2 class="page-title">Button Groups</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-2">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex items-center gap-2">
              <GlassButton variant="outline" size="icon" rounded="lg">
                <Icon name="lucide:bold" class="size-4" />
              </GlassButton>
              <GlassButton variant="outline" size="icon" rounded="lg">
                <Icon name="lucide:italic" class="size-4" />
              </GlassButton>
              <GlassButton variant="outline" size="icon" rounded="lg">
                <Icon name="lucide:underline" class="size-4" />
              </GlassButton>
              <GlassButton variant="outline" size="icon" rounded="lg">
                <Icon name="lucide:strikethrough" class="size-4" />
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Text Formatting Toolbar</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex items-center gap-2">
              <GlassButton variant="outline" rounded="xl">Cancel</GlassButton>
              <GlassButton rounded="xl">
                <Icon name="lucide:check" class="size-4" />
                Confirm
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Action Buttons</span>
          </div>
        </div>
      </div>

      <!-- Inherit Size Demo -->
      <div class="space-y-2.5">
        <h2 class="page-title">Inherit Size (responsive)</h2>
        <div
          class="border-border/50 bg-muted/20 flex items-center justify-center rounded-xl border p-8"
        >
          <div class="text-[clamp(1.5rem,4vw,4rem)]">
            <GlassButton size="inherit">Responsive</GlassButton>
          </div>
        </div>
      </div>

      <!-- On Different Backgrounds -->
      <div class="space-y-2.5">
        <h2 class="page-title">On Different Backgrounds</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-4">
          <!-- Light bg -->
          <div
            class="border-border/50 flex flex-col items-center justify-center gap-2.5 rounded-xl border bg-white p-8 text-black dark:bg-gray-100"
          >
            <span class="font-medium tracking-tight">Light Background</span>
            <GlassButton variant="default">Default</GlassButton>
            <GlassButton variant="outline">Outline</GlassButton>
          </div>

          <!-- Gradient bg -->
          <div
            class="text-foreground flex flex-col items-center justify-center gap-2.5 rounded-xl bg-linear-to-br from-pink-300 via-purple-300 to-indigo-400 p-8"
          >
            <span class="font-medium tracking-tight">Gradient Background</span>
            <GlassButton variant="default">Default</GlassButton>
            <GlassButton variant="outline">Outline</GlassButton>
          </div>

          <!-- Dark bg -->
          <div
            class="flex flex-col items-center justify-center gap-2.5 rounded-xl bg-gray-950 p-8 text-white dark:bg-gray-900"
          >
            <span class="font-medium tracking-tight">Dark Background</span>
            <GlassButton variant="default">Default</GlassButton>
            <GlassButton variant="outline">Outline</GlassButton>
          </div>

          <div class="relative overflow-hidden rounded-xl">
            <NuxtImg
              src="/img/wonyoungdollz_1766704382_3795713926466650278_60528094907.jpg"
              alt=""
              class="size-full object-cover"
            />

            <div
              class="absolute inset-0 flex flex-col items-center justify-center gap-y-2.5 text-center text-white"
            >
              <span class="font-medium tracking-tight">Image Background</span>
              <GlassButton variant="default">Default</GlassButton>
              <GlassButton variant="outline">Outline</GlassButton>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const variants = [
  "default",
  "outline",
  "destructive",
  "destructive-outline",
  "secondary",
  "invert",
  "outline-invert",
] as const;

const roundedVariants = ["none", "sm", "md", "lg", "xl", "2xl", "3xl", "full"] as const;

const textSizes = ["xs", "sm", "default", "lg", "xl"] as const;

const iconSizes = ["icon-xs", "icon-sm", "icon", "icon-lg", "icon-xl"] as const;
</script>
