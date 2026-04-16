<template>
  <div v-if="loading || projects.length > 0" class="space-y-2">
    <div class="flex items-center justify-between">
      <h3 class="page-title text-lg!">Choose Your Project</h3>
    </div>

    <!-- Loading -->
    <div
      v-if="loading"
      class="*:bg-background relative grid grid-cols-2 gap-px p-px *:relative sm:grid-cols-[repeat(auto-fit,minmax(180px,1fr))]"
    >
      <div
        v-for="i in 8"
        :key="i"
        class="flex aspect-square flex-col items-center justify-center gap-y-2 p-4"
      >
        <Skeleton class="squircle size-20" />
        <Skeleton class="h-4 w-24" />
      </div>
    </div>

    <!-- Projects -->
    <GridFill
      v-else
      :count="projects.length"
      filler-class="bg-pattern-diagonal aspect-square"
      class="*:[--pattern-fg:var(--color-primary)]/10"
    >
      <NuxtLink
        v-for="project in projects"
        :key="project.id"
        :to="`/projects/${project.username}`"
        class="hover:bg-muted flex aspect-square flex-col items-center justify-center gap-y-2 p-4 text-center transition"
      >
        <Avatar
          :model="{ name: project.name, profile_image: project.profile_image }"
          class="aspect-square size-20"
          size="md"
          rounded="squircle"
        />
        <h3 class="line-clamp-2 text-base leading-tight font-medium tracking-tighter">
          {{ project.name }}
        </h3>
      </NuxtLink>
    </GridFill>
  </div>
</template>

<script setup lang="ts">
interface ProjectItem {
  id: number;
  name: string;
  username: string;
  profile_image: Record<string, string> | null;
}

defineProps<{
  projects: ProjectItem[];
  loading?: boolean;
}>();
</script>
