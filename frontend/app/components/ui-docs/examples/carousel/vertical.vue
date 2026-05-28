<script setup>
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
} from "@/components/ui/carousel";
</script>

<template>
  <Carousel orientation="vertical" class="w-full max-w-xs">
    <CarouselContent class="h-56">
      <CarouselItem v-for="i in 5" :key="i" class="basis-1/2">
        <div class="bg-muted flex h-full items-center justify-center rounded-lg p-6">
          <span class="text-2xl font-semibold tracking-tighter">{{ i }}</span>
        </div>
      </CarouselItem>
    </CarouselContent>
    <CarouselPrevious />
    <CarouselNext />
  </Carousel>
</template>
