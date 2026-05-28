<script setup>
import { AspectRatio } from "@/components/ui/aspect-ratio";
</script>

<template>
  <div class="w-full max-w-sm">
    <AspectRatio :ratio="16 / 9">
      <img
        src="https://images.unsplash.com/photo-1717689596878-aa7c61c20fbe?w=800&q=80"
        alt="Landscape"
        class="size-full rounded-lg object-cover"
      />
    </AspectRatio>
  </div>
</template>
