<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 flex-col gap-1.5 p-3">
      <div class="grid grid-cols-7 gap-0.5">
        <div
          v-for="i in 7"
          :key="`h${i}`"
          class="bg-muted-foreground/20 mx-auto h-1 w-1 rounded-full"
        />
      </div>
      <div class="grid grid-cols-7 gap-0.5">
        <div
          v-for="i in 14"
          :key="i"
          class="flex size-3.5 flex-col items-center justify-center gap-px rounded-sm"
          :class="i === 6 ? 'bg-primary text-primary-foreground' : 'bg-muted-foreground/10'"
        >
          <div class="bg-current size-0.5 rounded-full opacity-70" />
          <div class="bg-current h-px w-1.5 rounded-full opacity-50" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
