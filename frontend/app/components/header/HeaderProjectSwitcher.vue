<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <button
        class="text-muted-foreground hover:text-foreground hover:bg-muted flex size-7 shrink-0 items-center justify-center rounded-lg transition"
        aria-label="Switch project"
      >
        <Icon name="lucide:chevrons-up-down" class="size-4" />
      </button>
    </PopoverTrigger>
    <PopoverContent :align="isMobile ? 'end' : 'start'" :side-offset="8" class="w-56 p-0">
      <Command v-model:search-term="search" :ignore-filter="false">
        <CommandInput placeholder="Search project..." />
        <CommandList>
          <CommandEmpty>No project found.</CommandEmpty>
          <CommandGroup>
            <CommandItem
              v-for="project in projects"
              :key="project.id"
              :value="project.name"
              @select="switchProject(project)"
            >
              <div class="flex items-center gap-x-2">
                <Avatar
                  :model="{ name: project.name, profile_image: project.profile_image }"
                  class="size-6 shrink-0"
                  rounded="rounded-sm"
                />
                <span class="truncate text-sm tracking-tight">{{ project.name }}</span>
              </div>
              <Icon
                v-if="project.username === currentUsername"
                name="lucide:check"
                class="text-primary ml-auto size-3.5 shrink-0"
              />
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>

<script setup>
const route = useRoute();
const router = useRouter();

const isMobile = useMediaQuery("(max-width: 1024px)");
const open = ref(false);
const search = ref("");

const { navigation, fetchNavigation } = useHeaderNavigation();

const projects = computed(() => navigation.value || []);
const currentUsername = computed(() => route.params.username);

watch(open, (val) => {
  if (val) {
    fetchNavigation();
    search.value = "";
  }
});

function switchProject(project) {
  if (project.username === currentUsername.value) {
    open.value = false;
    return;
  }

  const path = route.path;
  const currentBase = `/projects/${currentUsername.value}`;

  // If on event/brand page, just go to project root (events are project-specific)
  if (route.params.eventSlug) {
    router.push(`/projects/${project.username}`);
  } else {
    // Swap username, keep the rest of the path
    const remainingPath = path.slice(currentBase.length);
    router.push(`/projects/${project.username}${remainingPath}`);
  }

  open.value = false;
}
</script>
