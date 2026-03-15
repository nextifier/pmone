import { useSwipe } from "@vueuse/core";

export default function useTabSwipe(contentEl, tabs) {
  const route = useRoute();
  const router = useRouter();

  const { isSwiping, direction } = useSwipe(contentEl, {
    passive: true,
    threshold: 50,
  });

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

    const tabsValue = toValue(tabs);
    const activeIndex = tabsValue.findIndex((tab) => isActive(tab));
    if (activeIndex === -1) return;

    if (direction.value === "left" && activeIndex < tabsValue.length - 1) {
      router.push(tabsValue[activeIndex + 1].to);
    } else if (direction.value === "right" && activeIndex > 0) {
      router.push(tabsValue[activeIndex - 1].to);
    }
  });
}
