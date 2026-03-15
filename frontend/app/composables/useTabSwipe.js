import { useSwipe, useEventListener } from "@vueuse/core";

export default function useTabSwipe(contentEl, tabs, options = {}) {
  const { enabled, parentTabs, backRoute } = options;
  const route = useRoute();
  const router = useRouter();

  let touchStartTarget = null;

  useEventListener(contentEl, "touchstart", (e) => {
    touchStartTarget = e.target;
  }, { passive: true });

  const { isSwiping, direction } = useSwipe(contentEl, {
    passive: true,
    threshold: 50,
  });

  const isInsideHorizontalScroll = () => {
    let el = touchStartTarget;
    const container = toValue(contentEl);
    while (el && el !== container) {
      if (el.scrollWidth > el.clientWidth) {
        const overflowX = getComputedStyle(el).overflowX;
        if (overflowX === "auto" || overflowX === "scroll") return true;
      }
      el = el.parentElement;
    }
    return false;
  };

  const isActive = (tab) => {
    if (tab.exact) {
      return route.path === tab.to || route.path === `${tab.to}/`;
    }
    if (tab.activeFor?.some((path) => route.path.startsWith(path))) {
      return true;
    }
    return route.path.startsWith(tab.to);
  };

  watch(isSwiping, (swiping) => {
    if (!swiping) return;
    if (enabled && !toValue(enabled)) return;
    if (isInsideHorizontalScroll()) return;

    const tabsValue = toValue(tabs);
    const activeIndex = tabsValue.findIndex((tab) => isActive(tab));
    if (activeIndex === -1) return;

    if (direction.value === "left" && activeIndex < tabsValue.length - 1) {
      router.push(tabsValue[activeIndex + 1].to);
    } else if (direction.value === "right" && activeIndex > 0) {
      router.push(tabsValue[activeIndex - 1].to);
    } else if (parentTabs) {
      const parentTabsValue = toValue(parentTabs);
      const parentIndex = parentTabsValue.findIndex((tab) => isActive(tab));
      if (parentIndex === -1) return;
      if (direction.value === "left" && parentIndex < parentTabsValue.length - 1) {
        router.push(parentTabsValue[parentIndex + 1].to);
      } else if (direction.value === "right" && parentIndex > 0) {
        router.push(parentTabsValue[parentIndex - 1].to);
      }
    } else if (backRoute && direction.value === "right" && activeIndex === 0) {
      router.push(toValue(backRoute));
    }
  });
}
