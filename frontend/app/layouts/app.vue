<template>
  <SidebarProvider
    variant="sidebar"
    id="layout-app"
    :open="sidebarOpen"
    :persist="false"
    @update:open="setSidebarOpen"
  >
    <AppSidebar v-if="!isExcluded" class="select-none" />

    <SidebarInset
      :class="isExcluded ? 'contents' : 'mx-auto min-h-screen min-w-0 max-w-[1920px]'"
    >
      <ImpersonateExitBanner />
      <AppHeader v-if="!isExcluded" :show-breadcrumb="isProjectPage" />
      <div
        :class="isExcluded ? 'contents' : 'grow overflow-x-clip px-4'"
      >
        <slot />
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
import ImpersonateExitBanner from "@/components/user/ImpersonateExitBanner.vue";

const route = useRoute();

// Report presence (current page + keepalive) for every authenticated admin.
usePresenceHeartbeat();

const isExcluded = computed(() => ["posts-create", "posts-slug-edit"].includes(route.name));

const isProjectPage = computed(() => {
  const name = route.name?.toString() || "";
  return name === "projects-username" || name.startsWith("projects-username-");
});

/**
 * A form's detail screens are the same kind of surface as a project page: their
 * own tab nav, and (on the Editor tab) a two-pane layout that wants every pixel
 * of width. They share the collapsed-rail default for the same reason.
 */
const isFormDetailPage = computed(() => {
  const name = route.name?.toString() || "";
  return name === "forms-slug" || name.startsWith("forms-slug-");
});

const prefersCollapsedSidebar = computed(() => isProjectPage.value || isFormDetailPage.value);

/**
 * Focused surfaces (project pages, form detail) carry their own sidebar state.
 * They open as an icon rail by default - they already have their own tab nav,
 * so the full sidebar would be a second navigation competing with it - while
 * the dashboard keeps whatever the user last chose. One shared cookie could not
 * express both, so the provider runs controlled here (`:persist="false"`) and
 * the layout owns the two cookies. `useCookie` is SSR-aware, so the first paint
 * already has the right `data-state` and the rail never flashes open.
 */
const SIDEBAR_COOKIE_OPTS = { maxAge: 60 * 60 * 24 * 7 };

const mainSidebarOpen = useCookie("sidebar_state", {
  ...SIDEBAR_COOKIE_OPTS,
  default: () => true,
});
const projectSidebarOpen = useCookie("sidebar_state_project", {
  ...SIDEBAR_COOKIE_OPTS,
  default: () => false,
});

const sidebarOpen = computed(() =>
  prefersCollapsedSidebar.value ? projectSidebarOpen.value : mainSidebarOpen.value,
);

function setSidebarOpen(value) {
  if (prefersCollapsedSidebar.value) {
    projectSidebarOpen.value = value;
  } else {
    mainSidebarOpen.value = value;
  }
}

// Block default "user" role from accessing pages beyond dashboard & settings
const { hasRole, hasAnyRole, isStaffOrAbove } = usePermission();
const isDefaultUser = computed(
  () => hasRole("user") && !hasAnyRole(["staff", "admin", "master", "exhibitor", "writer"]),
);

// Force English locale for non-exhibitor users (multi-language only for Exhibitor Dashboard)
const { locale, setLocale } = useI18n();
const isExhibitor = computed(() => hasRole("exhibitor") && !isStaffOrAbove.value);

/**
 * Locales whose dashboard strings are actually translated. `ja` and `ko` are
 * registered for the public form pages only - they carry the `forms` block and
 * nothing else - so browser detection landing on one of them would otherwise
 * leave an exhibitor staring at a fully English dashboard under a Japanese flag.
 */
const DASHBOARD_LOCALES = ["en", "id", "zh"];

watch(
  [isExhibitor, locale],
  ([exhibitor, current]) => {
    if (!exhibitor && current !== "en") {
      setLocale("en");
    } else if (exhibitor && !DASHBOARD_LOCALES.includes(current)) {
      setLocale("en");
    }
  },
  { immediate: true },
);

const allowedPrefixes = ["/dashboard", "/settings"];

watch(
  () => route.path,
  (path) => {
    if (isDefaultUser.value && !allowedPrefixes.some((p) => path === p || path.startsWith(p + "/"))) {
      navigateTo("/dashboard");
    }
  },
  { immediate: true },
);
</script>
