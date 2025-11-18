import { createSharedComposable, useActiveElement } from "@vueuse/core";
import type {} from "@vueuse/shared";
import { computed, onMounted, ref } from "vue";

export const _useShortcuts = () => {
  const macOS = computed(
    () =>
      process.client && navigator && navigator.userAgent && navigator.userAgent.match(/Macintosh;/)
  );

  const metaSymbol = ref(" ");

  const activeElement = useActiveElement();

  const isInTipTapEditor = computed(() => {
    if (!activeElement.value) return false;

    // Check if the active element or any of its parents is a TipTap editor
    let element = activeElement.value as HTMLElement;
    while (element) {
      if (element.classList?.contains('ProseMirror')) {
        return true;
      }
      if (element.parentElement) {
        element = element.parentElement;
      } else {
        break;
      }
    }

    return false;
  });

  const usingInput = computed(() => {
    const usingInput = !!(
      activeElement.value?.tagName === "INPUT" ||
      activeElement.value?.tagName === "TEXTAREA" ||
      activeElement.value?.contentEditable === "true" ||
      isInTipTapEditor.value
    );

    if (usingInput) {
      return ((activeElement.value as any)?.name as string) || true;
    }

    return false;
  });

  onMounted(() => {
    metaSymbol.value = macOS.value ? "⌘" : "Ctrl";
  });

  return {
    macOS,
    metaSymbol,
    activeElement,
    usingInput,
  };
};

export const useShortcuts = createSharedComposable(_useShortcuts);
