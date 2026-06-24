<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
import { Stack } from "@/components/ui/stack";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <!-- The real Stack uses card/border colors, which vanish on the card-tinted
           frame. Re-map those tokens to muted tones (subtree only) so the isometric
           shape reads clearly in both light and dark mode. -->
      <Stack
        direction="br-tl"
        :layers="3"
        size="2rem"
        :interactive="false"
        :style="{ '--card': 'var(--muted)', '--border': 'var(--muted-foreground)' }"
      />
    </div>
  </IllustrationFrame>
</template>
