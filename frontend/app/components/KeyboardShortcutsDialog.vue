<template>
  <DialogResponsive v-model:open="shortcutsDialogOpen" dialogMaxWidth="500px">
    <template #trigger>
      <Tippy>
        <button
          class="text-primary hover:bg-muted flex size-8 items-center justify-center rounded-lg"
          @click="shortcutsDialogOpen = true"
        >
          <Icon name="hugeicons:keyboard" class="size-5" />
        </button>
        <template #content>
          <span class="inline-flex items-center gap-x-1.5 tracking-tight">
            <span>Keyboard Shortcuts</span>
            <kbd class="keyboard-symbol">?</kbd>
          </span>
        </template>
      </Tippy>
    </template>

    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <h2 class="text-primary pr-8 text-lg font-semibold tracking-tight">
          Keyboard Shortcuts
        </h2>

        <div class="mt-5 space-y-5">
          <div>
            <h3 class="text-muted-foreground text-xs font-medium tracking-tight uppercase">
              General
            </h3>
            <div class="divide-border mt-2 divide-y">
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Toggle Light / Dark Mode</span>
                <KbdGroup class="inline-flex">
                  <Kbd>D</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Go Back</span>
                <KbdGroup class="inline-flex">
                  <Kbd>B</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">New Item</span>
                <KbdGroup class="inline-flex">
                  <Kbd>N</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Toggle Sidebar</span>
                <KbdGroup class="inline-flex">
                  <Kbd>{{ metaSymbol }}</Kbd>
                  <Kbd>B</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Search</span>
                <KbdGroup class="inline-flex">
                  <Kbd>{{ metaSymbol }}</Kbd>
                  <Kbd>K</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Save</span>
                <KbdGroup class="inline-flex">
                  <Kbd>{{ metaSymbol }}</Kbd>
                  <Kbd>S</Kbd>
                </KbdGroup>
              </div>
            </div>
          </div>

          <div>
            <h3 class="text-muted-foreground text-xs font-medium tracking-tight uppercase">
              Navigation
            </h3>
            <div class="divide-border mt-2 divide-y">
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Close dialog, dropdown, drawer</span>
                <KbdGroup class="inline-flex">
                  <Kbd>Esc</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Navigate focusable elements</span>
                <KbdGroup class="inline-flex">
                  <Kbd>Tab</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Confirm / Select</span>
                <KbdGroup class="inline-flex">
                  <Kbd>Enter</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Toggle / Select</span>
                <KbdGroup class="inline-flex">
                  <Kbd>Space</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Navigate list items</span>
                <KbdGroup class="inline-flex">
                  <Kbd>↑</Kbd>
                  <Kbd>↓</Kbd>
                </KbdGroup>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Navigate tabs</span>
                <KbdGroup class="inline-flex">
                  <Kbd>←</Kbd>
                  <Kbd>→</Kbd>
                </KbdGroup>
              </div>
            </div>
          </div>

          <div>
            <h3 class="text-muted-foreground text-xs font-medium tracking-tight uppercase">
              Gestures
            </h3>
            <div class="divide-border mt-2 divide-y">
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Close drawer</span>
                <span class="text-muted-foreground text-sm font-medium tracking-tight"
                  >Swipe Down</span
                >
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Close sidebar</span>
                <span class="text-muted-foreground text-sm font-medium tracking-tight"
                  >Swipe Left</span
                >
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Navigate between tabs</span>
                <span class="text-muted-foreground text-sm font-medium tracking-tight"
                  >Swipe Left / Right</span
                >
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-sm tracking-tight">Reorder list items</span>
                <span class="text-muted-foreground text-sm font-medium tracking-tight"
                  >Drag Handle</span
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
const { metaSymbol } = useShortcuts();

const shortcutsDialogOpen = ref(false);
defineShortcuts({
  "?": {
    handler: () => {
      shortcutsDialogOpen.value = !shortcutsDialogOpen.value;
    },
  },
});
</script>
