<script setup>
import { CardNotch } from "@/components/ui/card-notch";

const teams = [
  { name: "Acme Inc", category: "Marketing", members: 24 },
  { name: "Globex Corp", category: "Engineering", members: 12 },
  { name: "Initech", category: "Finance", members: 8 },
  { name: "Umbrella Co", category: "Operations", members: 31 },
];
</script>

<template>
  <div class="grid w-full grid-cols-1 items-start gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <CardNotch v-for="team in teams" :key="team.name" auto-pad body-class="flex flex-col gap-3 p-5">
      <template #notch>
        <Icon name="hugeicons:arrow-up-right-03" class="size-5" />
      </template>
      <div>
        <h3 class="text-base font-medium tracking-tight">{{ team.name }}</h3>
        <p class="text-muted-foreground text-sm tracking-tight">{{ team.category }}</p>
      </div>
      <div class="flex items-center gap-x-2">
        <span class="text-muted-foreground text-sm tracking-tight">Members</span>
        <span class="text-sm font-medium tracking-tight">{{ team.members }}</span>
      </div>
    </CardNotch>
  </div>
</template>
