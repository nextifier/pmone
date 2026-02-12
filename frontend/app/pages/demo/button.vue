<template>
  <div class="container pt-6 pb-24">
    <h1 class="text-center text-3xl font-medium tracking-tighter sm:text-5xl">Button</h1>

    <div class="mt-8 grid grid-cols-1 gap-y-10">
      <div class="space-y-2.5">
        <h2 class="page-title">Variants</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-2.5">
          <div
            v-for="variant in variants"
            :key="variant"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
            :class="
              ['invert', 'outline-invert'].includes(variant)
                ? 'bg-foreground text-background'
                : 'bg-muted/20 border-border/50'
            "
          >
            <GlassButton :variant="variant">{{ variant }}</GlassButton>
            <span class="text-xs">{{ variant }}</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Ghost & Link Examples</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-2.5">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex items-center gap-1">
              <GlassButton variant="ghost" size="icon-sm">
                <Icon name="lucide:heart" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm">
                <Icon name="lucide:message-circle" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm">
                <Icon name="lucide:share" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm">
                <Icon name="lucide:bookmark" />
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Social Actions</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex items-center gap-2">
              <GlassButton variant="ghost" size="sm">
                <Icon name="lucide:copy" />
                Copy
              </GlassButton>
              <GlassButton variant="ghost" size="sm">
                <Icon name="lucide:pencil" />
                Edit
              </GlassButton>
              <GlassButton variant="ghost" size="sm">
                <Icon name="lucide:trash-2" />
                Delete
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Ghost Actions</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <p class="text-muted-foreground text-sm">
              Read our
              <GlassButton variant="link" class="text-primary">Terms of Service</GlassButton>
              and
              <GlassButton variant="link" class="text-primary">Privacy Policy</GlassButton>
            </p>
            <span class="text-muted-foreground text-xs">Inline Links</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex flex-col items-start gap-1">
              <GlassButton variant="link" size="sm">
                <Icon name="lucide:file-text" />
                Documentation
              </GlassButton>
              <GlassButton variant="link" size="sm">
                <Icon name="lucide:github" />
                View on GitHub
              </GlassButton>
              <GlassButton variant="link" size="sm">
                <Icon name="lucide:message-square" />
                Get Support
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Link List</span>
          </div>
        </div>
      </div>

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

      <div class="space-y-2.5">
        <h2 class="page-title">Icon + Text</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton>
              <Icon name="lucide:download" />
              Download
            </GlassButton>
            <span class="text-muted-foreground text-xs">Leading Icon</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton>
              Continue
              <Icon name="lucide:arrow-right" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Trailing Icon</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline">
              <Icon name="lucide:star" />
              Star Project
            </GlassButton>
            <span class="text-muted-foreground text-xs">Outline + Icon</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton rounded="lg">
              <Icon name="lucide:upload" />
              Upload File
            </GlassButton>
            <span class="text-muted-foreground text-xs">Rounded LG</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Full Width</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-2">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton class="w-full" size="lg">
              <Icon name="lucide:log-in" />
              Sign In
            </GlassButton>
            <span class="text-muted-foreground text-xs">Full Width Primary</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" class="w-full" size="lg">
              <Icon name="lucide:user-plus" />
              Create Account
            </GlassButton>
            <span class="text-muted-foreground text-xs">Full Width Outline</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Social Login</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-3">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" class="w-full" rounded="lg">
              <Icon name="logos:google-icon" class="size-5" />
              Continue with Google
            </GlassButton>
            <span class="text-muted-foreground text-xs">Google</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" class="w-full" rounded="lg">
              <Icon name="logos:github-icon" class="size-5" />
              Continue with GitHub
            </GlassButton>
            <span class="text-muted-foreground text-xs">GitHub</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" class="w-full" rounded="lg">
              <Icon name="logos:apple" class="size-5" />
              Continue with Apple
            </GlassButton>
            <span class="text-muted-foreground text-xs">Apple</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Status Actions</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="success" rounded="lg">
              <Icon name="lucide:check-circle" />
              Approve
            </GlassButton>
            <span class="text-muted-foreground text-xs">Success Action</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="warning" rounded="lg">
              <Icon name="lucide:alert-triangle" />
              Review Required
            </GlassButton>
            <span class="text-muted-foreground text-xs">Warning Action</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="destructive" rounded="lg">
              <Icon name="lucide:x-circle" />
              Reject
            </GlassButton>
            <span class="text-muted-foreground text-xs">Destructive Action</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="info" rounded="lg">
              <Icon name="lucide:info" />
              View Details
            </GlassButton>
            <span class="text-muted-foreground text-xs">Info Action</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Outline Status Actions</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="success-outline" rounded="lg">
              <Icon name="lucide:check" />
              Mark Complete
            </GlassButton>
            <span class="text-muted-foreground text-xs">Success Outline</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="warning-outline" rounded="lg">
              <Icon name="lucide:clock" />
              Set Pending
            </GlassButton>
            <span class="text-muted-foreground text-xs">Warning Outline</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="destructive-outline" rounded="lg">
              <Icon name="lucide:archive" />
              Archive
            </GlassButton>
            <span class="text-muted-foreground text-xs">Destructive Outline</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="info-outline" rounded="lg">
              <Icon name="lucide:eye" />
              Preview
            </GlassButton>
            <span class="text-muted-foreground text-xs">Info Outline</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Real Use Cases</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="destructive-outline" size="icon">
              <Icon name="lucide:trash-2" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Delete Icon</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="destructive" rounded="lg">
              <Icon name="lucide:trash-2" />
              Delete Item
            </GlassButton>
            <span class="text-muted-foreground text-xs">Delete Button</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" size="icon" rounded="lg">
              <Icon name="lucide:pencil" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Edit Icon</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="ghost" size="icon">
              <Icon name="lucide:settings" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Settings Ghost</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="outline" rounded="xl">
              <Icon name="lucide:share-2" />
              Share
            </GlassButton>
            <span class="text-muted-foreground text-xs">Share Button</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton rounded="xl">
              <Icon name="lucide:save" />
              Save Changes
            </GlassButton>
            <span class="text-muted-foreground text-xs">Save Button</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Navigation</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-2">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex w-full items-center justify-between">
              <GlassButton variant="outline" rounded="lg">
                <Icon name="lucide:arrow-left" />
                Previous
              </GlassButton>
              <GlassButton rounded="lg">
                Next
                <Icon name="lucide:arrow-right" />
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Step Navigation</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex items-center gap-1">
              <GlassButton variant="outline" size="icon-sm" rounded="lg">
                <Icon name="lucide:chevron-left" />
              </GlassButton>
              <GlassButton variant="ghost" size="sm" rounded="lg">1</GlassButton>
              <GlassButton variant="default" size="sm" rounded="lg">2</GlassButton>
              <GlassButton variant="ghost" size="sm" rounded="lg">3</GlassButton>
              <GlassButton variant="ghost" size="sm" rounded="lg">...</GlassButton>
              <GlassButton variant="ghost" size="sm" rounded="lg">10</GlassButton>
              <GlassButton variant="outline" size="icon-sm" rounded="lg">
                <Icon name="lucide:chevron-right" />
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Pagination</span>
          </div>
        </div>
      </div>

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
            <GlassButton variant="success" disabled rounded="lg">
              <Spinner class="size-4" />
              Saving...
            </GlassButton>
            <span class="text-muted-foreground text-xs">Success Loading</span>
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
              <Icon name="lucide:trash-2" />
              Delete
            </GlassButton>
            <span class="text-muted-foreground text-xs">Destructive Disabled</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton variant="ghost" disabled>
              <Icon name="lucide:plus" />
              Add Item
            </GlassButton>
            <span class="text-muted-foreground text-xs">Ghost Disabled</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">As Link (NuxtLink)</h2>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2.5">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton to="/">
              <Icon name="lucide:home" />
              Go Home
            </GlassButton>
            <span class="text-muted-foreground text-xs">to="/" (internal)</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton to="https://nuxt.com" variant="outline" rounded="xl">
              <Icon name="lucide:external-link" />
              External Link
            </GlassButton>
            <span class="text-muted-foreground text-xs">to="https://..." (new tab)</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <GlassButton to="/" variant="ghost" size="icon" rounded="lg">
              <Icon name="lucide:arrow-left" />
            </GlassButton>
            <span class="text-muted-foreground text-xs">Ghost Icon Link</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Button Groups</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-2">
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex items-center gap-1">
              <GlassButton variant="ghost" size="icon-sm" rounded="lg">
                <Icon name="lucide:bold" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm" rounded="lg">
                <Icon name="lucide:italic" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm" rounded="lg">
                <Icon name="lucide:underline" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm" rounded="lg">
                <Icon name="lucide:strikethrough" />
              </GlassButton>
              <div class="bg-border mx-1 h-6 w-px" />
              <GlassButton variant="ghost" size="icon-sm" rounded="lg">
                <Icon name="lucide:align-left" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm" rounded="lg">
                <Icon name="lucide:align-center" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm" rounded="lg">
                <Icon name="lucide:align-right" />
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Text Formatting Toolbar</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex items-center gap-2">
              <GlassButton variant="ghost" rounded="lg">Cancel</GlassButton>
              <GlassButton variant="outline" rounded="lg">Save Draft</GlassButton>
              <GlassButton rounded="lg">
                <Icon name="lucide:send" />
                Publish
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Action Group</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex items-center gap-1">
              <GlassButton variant="outline" size="sm" rounded="none" class="rounded-l-lg">
                Day
              </GlassButton>
              <GlassButton variant="default" size="sm" rounded="none">Week</GlassButton>
              <GlassButton variant="outline" size="sm" rounded="none">Month</GlassButton>
              <GlassButton variant="outline" size="sm" rounded="none" class="rounded-r-lg">
                Year
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">Segmented Control</span>
          </div>
          <div
            class="border-border/50 bg-muted/20 flex flex-col items-center justify-center gap-3 rounded-xl border p-6"
          >
            <div class="flex items-center gap-1">
              <GlassButton variant="outline" size="icon-sm" rounded="lg">
                <Icon name="lucide:grid-2x2" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm" rounded="lg">
                <Icon name="lucide:list" />
              </GlassButton>
              <GlassButton variant="ghost" size="icon-sm" rounded="lg">
                <Icon name="lucide:layout-grid" />
              </GlassButton>
            </div>
            <span class="text-muted-foreground text-xs">View Toggle</span>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Card Actions</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-3">
          <div class="border-border/50 bg-muted/20 overflow-hidden rounded-xl border">
            <div class="bg-muted/50 aspect-video" />
            <div class="space-y-3 p-4">
              <h3 class="font-medium">Product Name</h3>
              <p class="text-muted-foreground text-sm">$99.00</p>
              <div class="flex gap-2">
                <GlassButton class="flex-1" size="sm">
                  <Icon name="lucide:shopping-cart" />
                  Add to Cart
                </GlassButton>
                <GlassButton variant="outline" size="icon-sm">
                  <Icon name="lucide:heart" />
                </GlassButton>
              </div>
            </div>
          </div>
          <div class="border-border/50 bg-muted/20 overflow-hidden rounded-xl border">
            <div class="bg-muted/50 aspect-video" />
            <div class="space-y-3 p-4">
              <h3 class="font-medium">Blog Post Title</h3>
              <p class="text-muted-foreground text-sm">A brief description of the post...</p>
              <GlassButton variant="link" size="sm" class="px-0">
                Read More
                <Icon name="lucide:arrow-right" />
              </GlassButton>
            </div>
          </div>
          <div class="border-border/50 bg-muted/20 overflow-hidden rounded-xl border p-4">
            <div class="flex items-center gap-3">
              <div class="bg-muted size-12 rounded-full" />
              <div class="flex-1">
                <h3 class="font-medium">John Doe</h3>
                <p class="text-muted-foreground text-sm">@johndoe</p>
              </div>
            </div>
            <div class="mt-4 flex gap-2">
              <GlassButton size="sm" class="flex-1">Follow</GlassButton>
              <GlassButton variant="outline" size="sm" class="flex-1">Message</GlassButton>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Modal/Dialog Actions</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-2">
          <div class="border-border/50 bg-muted/20 rounded-xl border p-6">
            <h3 class="font-medium">Confirm Delete</h3>
            <p class="text-muted-foreground mt-2 text-sm">
              Are you sure you want to delete this item? This action cannot be undone.
            </p>
            <div class="mt-4 flex justify-end gap-2">
              <GlassButton variant="ghost" rounded="lg">Cancel</GlassButton>
              <GlassButton variant="destructive" rounded="lg">
                <Icon name="lucide:trash-2" />
                Delete
              </GlassButton>
            </div>
          </div>
          <div class="border-border/50 bg-muted/20 rounded-xl border p-6">
            <h3 class="font-medium">Save Changes?</h3>
            <p class="text-muted-foreground mt-2 text-sm">
              You have unsaved changes. Would you like to save them before leaving?
            </p>
            <div class="mt-4 flex justify-end gap-2">
              <GlassButton variant="ghost" rounded="lg">Discard</GlassButton>
              <GlassButton variant="outline" rounded="lg">Cancel</GlassButton>
              <GlassButton rounded="lg">
                <Icon name="lucide:save" />
                Save
              </GlassButton>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Form Actions</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-2">
          <div class="border-border/50 bg-muted/20 rounded-xl border p-6">
            <div class="space-y-4">
              <div class="bg-muted/50 h-10 w-full rounded-lg" />
              <div class="bg-muted/50 h-10 w-full rounded-lg" />
              <div class="bg-muted/50 h-24 w-full rounded-lg" />
              <div class="flex justify-end gap-2">
                <GlassButton variant="ghost" rounded="lg">Reset</GlassButton>
                <GlassButton rounded="lg">
                  <Icon name="lucide:send" />
                  Submit
                </GlassButton>
              </div>
            </div>
          </div>
          <div class="border-border/50 bg-muted/20 rounded-xl border p-6">
            <div class="space-y-4">
              <div class="bg-muted/50 h-10 w-full rounded-lg" />
              <div class="bg-muted/50 h-10 w-full rounded-lg" />
              <GlassButton class="w-full" size="lg" rounded="lg">
                <Icon name="lucide:log-in" />
                Sign In
              </GlassButton>
              <div class="relative">
                <div class="absolute inset-0 flex items-center">
                  <div class="border-border w-full border-t" />
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                  <span class="bg-muted/20 text-muted-foreground px-2">Or continue with</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <GlassButton variant="outline" rounded="lg">
                  <Icon name="logos:google-icon" class="size-4" />
                  Google
                </GlassButton>
                <GlassButton variant="outline" rounded="lg">
                  <Icon name="logos:github-icon" class="size-4" />
                  GitHub
                </GlassButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-2.5">
        <h2 class="page-title">Floating Action Button</h2>
        <div
          class="border-border/50 bg-muted/20 relative flex h-64 items-center justify-center rounded-xl border"
        >
          <span class="text-muted-foreground text-sm">Page Content Area</span>
          <div class="absolute right-4 bottom-4 flex flex-col gap-2">
            <GlassButton size="icon-lg" class="shadow-lg">
              <Icon name="lucide:plus" class="size-6" />
            </GlassButton>
          </div>
        </div>
      </div>

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

      <div class="space-y-2.5">
        <h2 class="page-title">On Different Backgrounds</h2>
        <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-2">
          <div
            class="border-border/50 flex flex-col items-center justify-center gap-3 rounded-xl border bg-white p-8 text-black"
          >
            <span class="mb-2 font-medium tracking-tight">Light Background</span>
            <div class="flex flex-wrap justify-center gap-2">
              <GlassButton variant="black">black</GlassButton>
              <GlassButton variant="black-outline">black-outline</GlassButton>
              <GlassButton variant="black-ghost">black-ghost</GlassButton>
            </div>
          </div>
          <div
            class="flex flex-col items-center justify-center gap-3 rounded-xl bg-gray-950 p-8 text-white"
          >
            <span class="mb-2 font-medium tracking-tight">Dark Background</span>
            <div class="flex flex-wrap justify-center gap-2">
              <GlassButton variant="white">white</GlassButton>
              <GlassButton variant="white-outline">white-outline</GlassButton>
              <GlassButton variant="white-ghost">white-ghost</GlassButton>
            </div>
          </div>
          <div
            class="text-foreground gradient-sky-purple flex flex-col items-center justify-center gap-3 rounded-xl p-8"
          >
            <span class="mb-2 font-medium tracking-tight text-white">Gradient Background</span>
            <div class="flex flex-wrap justify-center gap-2">
              <GlassButton>Default</GlassButton>
              <GlassButton variant="outline">Outline</GlassButton>
              <GlassButton variant="ghost" class="text-white hover:bg-white/20">Ghost</GlassButton>
            </div>
          </div>
          <div class="relative overflow-hidden rounded-xl">
            <NuxtImg
              src="/img/wonyoungdollz_1766704382_3795713926466650278_60528094907.jpg"
              alt=""
              class="size-full object-cover"
            />
            <div class="absolute inset-0 bg-black/30" />
            <div
              class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-8 text-white"
            >
              <span class="mb-2 font-medium tracking-tight">Image Background</span>
              <div class="flex flex-wrap justify-center gap-2">
                <GlassButton variant="white">white</GlassButton>
                <GlassButton variant="white-outline">white-outline</GlassButton>
              </div>
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
  "invert",
  "outline-invert",

  "info",
  "success",
  "warning",
  "destructive",

  "info-outline",
  "success-outline",
  "warning-outline",
  "destructive-outline",

  "ghost",
  "link",
] as const;

const roundedVariants = ["none", "sm", "md", "lg", "xl", "2xl", "3xl", "full"] as const;

const textSizes = ["xs", "sm", "default", "lg", "xl"] as const;

const iconSizes = ["icon-xs", "icon-sm", "icon", "icon-lg", "icon-xl"] as const;
</script>

<style scoped>
@keyframes hero-gradient-animation {
  0% {
    --x-0: 11%;
    --c-0: hsla(195, 79%, 51%, 1);
    --y-0: 96%;
    --s-start-0: 37%;
    --s-end-0: 90%;
    --x-1: 95%;
    --s-start-1: 31%;
    --s-end-1: 84%;
    --c-1: hsla(272, 100%, 64%, 1);
    --y-1: 92%;
    --x-2: 58%;
    --c-2: hsla(216, 95%, 54%, 1);
    --s-start-2: 41%;
    --s-end-2: 61%;
    --y-2: 7%;
    --s-start-3: 22%;
    --s-end-3: 59%;
    --x-3: 62%;
    --c-3: hsla(239, 76%, 52%, 1);
    --y-3: 35%;
    --y-4: 63%;
    --s-start-4: 19%;
    --s-end-4: 70%;
    --x-4: 2%;
    --c-4: hsla(232, 81%, 92%, 1);
  }
  50% {
    --x-0: 6%;
    --c-0: hsla(313, 78%, 76%, 1);
    --y-0: 12%;
    --s-start-0: 29%;
    --s-end-0: 73%;
    --x-1: 77%;
    --s-start-1: 8%;
    --s-end-1: 52.61503700898072%;
    --c-1: hsla(235, 87%, 56%, 1);
    --y-1: 29%;
    --x-2: 75%;
    --c-2: hsla(220, 51%, 96%, 1);
    --s-start-2: 29%;
    --s-end-2: 80%;
    --y-2: 23%;
    --s-start-3: 2%;
    --s-end-3: 81%;
    --x-3: 76%;
    --c-3: hsla(218, 76%, 75%, 1);
    --y-3: 64%;
    --y-4: 86%;
    --s-start-4: 24%;
    --s-end-4: 81%;
    --x-4: 41%;
    --c-4: hsla(315, 89%, 98%, 1);
  }
  100% {
    --x-1: 36%;
    --s-start-1: 8%;
    --s-end-1: 21.71776697271709%;
    --c-1: hsla(235, 87%, 56%, 1);
    --y-1: 13%;
  }
}
@property --x-0 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 11%;
}
@property --c-0 {
  syntax: "<color>";
  inherits: false;
  initial-value: hsla(195, 79%, 51%, 1);
}
@property --y-0 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 96%;
}
@property --s-start-0 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 37%;
}
@property --s-end-0 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 90%;
}
@property --x-1 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 95%;
}
@property --s-start-1 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 31%;
}
@property --s-end-1 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 84%;
}
@property --c-1 {
  syntax: "<color>";
  inherits: false;
  initial-value: hsla(272, 100%, 64%, 1);
}
@property --y-1 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 92%;
}
@property --x-2 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 58%;
}
@property --c-2 {
  syntax: "<color>";
  inherits: false;
  initial-value: hsla(216, 95%, 54%, 1);
}
@property --s-start-2 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 41%;
}
@property --s-end-2 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 61%;
}
@property --y-2 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 7%;
}
@property --s-start-3 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 22%;
}
@property --s-end-3 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 59%;
}
@property --x-3 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 62%;
}
@property --c-3 {
  syntax: "<color>";
  inherits: false;
  initial-value: hsla(239, 76%, 52%, 1);
}
@property --y-3 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 35%;
}
@property --y-4 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 63%;
}
@property --s-start-4 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 19%;
}
@property --s-end-4 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 70%;
}
@property --x-4 {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 2%;
}
@property --c-4 {
  syntax: "<color>";
  inherits: false;
  initial-value: hsla(232, 81%, 92%, 1);
}
.gradient-sky-purple {
  --x-0: 11%;
  --c-0: hsla(195, 79%, 51%, 1);
  --y-0: 96%;
  --x-1: 95%;
  --c-1: hsla(272, 100%, 64%, 1);
  --y-1: 92%;
  --x-2: 58%;
  --c-2: hsla(216, 95%, 54%, 1);
  --y-2: 7%;
  --x-3: 62%;
  --c-3: hsla(239, 76%, 52%, 1);
  --y-3: 35%;
  --y-4: 63%;
  --x-4: 2%;
  --c-4: hsla(232, 81%, 92%, 1);
  background-color: hsla(272, 59%, 39%, 1);
  background-image:
    url("data:image/svg+xml,%3Csvg viewBox='0 0 1495 1495' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E"),
    radial-gradient(
      circle at var(--x-0) var(--y-0),
      var(--c-0) var(--s-start-0),
      transparent var(--s-end-0)
    ),
    radial-gradient(
      circle at var(--x-1) var(--y-1),
      var(--c-1) var(--s-start-1),
      transparent var(--s-end-1)
    ),
    radial-gradient(
      circle at var(--x-2) var(--y-2),
      var(--c-2) var(--s-start-2),
      transparent var(--s-end-2)
    ),
    radial-gradient(
      circle at var(--x-3) var(--y-3),
      var(--c-3) var(--s-start-3),
      transparent var(--s-end-3)
    ),
    radial-gradient(
      circle at var(--x-4) var(--y-4),
      var(--c-4) var(--s-start-4),
      transparent var(--s-end-4)
    );
  animation: hero-gradient-animation 10s linear infinite alternate;
  background-blend-mode: overlay, normal, hard-light, normal, normal, normal;
}
</style>
