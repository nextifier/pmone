import { useSidebar } from "@/components/ui/sidebar/utils";

/**
 * Auto-scrolls a sidebar so the active nav item is centered in its scroll
 * viewport. Works for any sidebar built on the ui/sidebar primitives: it finds
 * the active link via NuxtLink's `[aria-current="page"]` inside
 * `[data-slot="sidebar-content"]` and scrolls the nearest reka-ui ScrollArea
 * viewport — never the main window. (We target aria-current, not `[data-active]`,
 * because reka-ui's SidebarMenuButton sets data-active on every button.)
 *
 * Intentionally runs ONLY on mount (covers reload / direct load, when the
 * sidebar — or the layout/page that owns it — first mounts) and when the mobile
 * sheet opens (its content only mounts then). It deliberately does NOT react to
 * SPA route changes, so a persistent sidebar keeps its scroll position as the
 * user navigates between pages.
 *
 * Only scrolls when the active item is off-screen, so it never fights an
 * already-visible item.
 */
export function useSidebarAutoScroll() {
  const { openMobile } = useSidebar();

  function center() {
    const link = document.querySelector('[data-slot="sidebar-content"] [aria-current="page"]');
    if (!link) return;

    const viewport = link.closest("[data-reka-scroll-area-viewport]");
    if (!viewport) return;

    const linkRect = link.getBoundingClientRect();
    const vpRect = viewport.getBoundingClientRect();
    if (linkRect.height === 0) return; // hidden (e.g. inside a collapsed group)

    const offscreen = linkRect.top < vpRect.top || linkRect.bottom > vpRect.bottom;
    if (!offscreen) return;

    const offsetWithin = linkRect.top - vpRect.top + viewport.scrollTop;
    viewport.scrollTop = Math.max(
      0,
      offsetWithin - viewport.clientHeight / 2 + linkRect.height / 2,
    );
  }

  function schedule() {
    nextTick(() => requestAnimationFrame(center));
  }

  onMounted(schedule);

  watch(openMobile, (open) => {
    if (open) schedule();
  });
}
