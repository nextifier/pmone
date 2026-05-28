<script setup>
import {
  Carousel,
  CarouselContent,
  CarouselDotButtons,
  CarouselItem,
} from "@/components/ui/carousel";
</script>

<template>
  <div class="flex w-full max-w-xs flex-col gap-3">
    <Carousel>
      <CarouselContent>
        <CarouselItem v-for="i in 4" :key="i">
          <div class="bg-muted flex aspect-video items-center justify-center rounded-lg p-6">
            <span class="text-3xl font-semibold tracking-tighter">Slide {{ i }}</span>
          </div>
        </CarouselItem>
      </CarouselContent>
      <div class="mt-3 flex justify-center">
        <CarouselDotButtons />
      </div>
    </Carousel>
  </div>
</template>
