<template>
  <div v-if="loading || projects.length > 0" class="space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="page-title text-lg!">My Projects</h3>
    </div>

    <!-- Loading -->
    <template v-if="loading">
      <div class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-2">
        <div
          v-for="i in 8"
          :key="i"
          class="flex flex-col gap-y-2.5 rounded-xl border px-3 py-4"
        >
          <!-- Avatar + name + members -->
          <div class="flex items-start gap-x-2">
            <Skeleton class="squircle size-10 shrink-0" />
            <div class="flex grow flex-col gap-y-1.5">
              <Skeleton class="h-3.5 w-28" />
              <div class="flex items-center gap-x-1">
                <div class="flex -space-x-1.5">
                  <Skeleton v-for="j in 3" :key="j" class="size-6 rounded-full" />
                </div>
                <Skeleton class="h-3 w-16" />
              </div>
            </div>
          </div>
          <!-- Event thumbnails -->
          <div class="flex items-center gap-x-1">
            <Skeleton v-for="j in 3" :key="j" class="aspect-4/5 w-12 rounded-md" />
          </div>
        </div>
      </div>
    </template>

    <!-- Projects -->
    <div v-else class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-2">
      <div
        v-for="project in projects"
        :key="project.id"
        class="bg-card border-border flex flex-col gap-y-2.5 rounded-xl border px-3 py-4"
      >
        <NuxtLink :to="`/projects/${project.username}`" class="flex items-start gap-x-2">
          <Avatar
            :model="{ name: project.name, profile_image: project.profile_image }"
            class="squircle size-10 overflow-hidden"
          />

          <div class="flex grow flex-col gap-y-1">
            <p class="line-clamp-1 text-sm font-medium tracking-tight">
              {{ project.name }}
            </p>

            <span v-if="project.members?.length" class="flex items-center gap-x-1">
              <div class="relative z-20 flex -space-x-1.5">
                <Avatar
                  v-for="member in project.members.slice(0, 4)"
                  :model="member"
                  :key="member.id"
                  class="bg-border! ring-background [&_.initial]:text-muted-foreground size-6 shrink-0 overflow-hidden rounded-full! ring-1 [&_.initial]:text-[10px] [&_.initial]:font-medium"
                  v-tippy="member.name"
                />
                <span
                  v-if="project.members_count && project.members_count > 4"
                  class="ring-background bg-border text-muted-foreground relative flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border text-center text-[10px] font-medium tracking-tighter ring-1"
                  >+{{ project.members_count - 4 }}</span
                >
              </div>
              <span
                v-if="project.members_count"
                class="text-muted-foreground text-xs tracking-tight"
                >{{ project.members_count }} member{{ project.members_count > 1 ? "s" : "" }}</span
              >
            </span>
          </div>
        </NuxtLink>

        <!-- Recent Events -->
        <div v-if="project.recent_events?.length" class="flex items-center gap-x-1">
          <NuxtLink
            v-for="event in project.recent_events"
            :key="event.id"
            :to="`/projects/${project.username}/events/${event.slug}`"
            v-tippy="event.title"
          >
            <div class="bg-muted aspect-4/5 w-12 overflow-hidden rounded-md">
              <img
                v-if="event.poster_image"
                :src="event.poster_image?.sm || event.poster_image?.url"
                :alt="event.title"
                class="size-full object-cover"
                loading="lazy"
              />
            </div>
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface MemberItem {
  id: number;
  name: string;
  profile_image: Record<string, string> | null;
}

interface EventItem {
  id: number;
  title: string;
  slug: string;
  poster_image: Record<string, string> | null;
}

interface ProjectItem {
  id: number;
  name: string;
  username: string;
  profile_image: Record<string, string> | null;
  members_count: number;
  members: MemberItem[];
  recent_events: EventItem[];
  total_inquiries: number;
  total_exhibitors: number;
  new_inquiries: number;
  open_tasks: number;
}

defineProps<{
  projects: ProjectItem[];
  loading?: boolean;
}>();
</script>
