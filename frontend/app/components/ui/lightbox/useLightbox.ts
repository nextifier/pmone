import {
  createInjectionState,
  useEventListener,
  useMediaQuery,
  useVModel,
} from "@vueuse/core";
import Autoplay from "embla-carousel-autoplay";
import { computed, ref, shallowRef, watch } from "vue";
import type { EmblaCarouselType } from "embla-carousel";
import type {
  LightboxBreakpoint,
  LightboxCounterFormat,
  LightboxEmits,
  LightboxItem,
  LightboxProps,
  LightboxResponsiveKey,
  LightboxThumbnailKey,
  LightboxVideoSource,
} from "./interface";

const VIDEO_EXT = /\.(mp4|webm|mov|m4v|ogv)(\?|$|#)/i;

export const defaultCounterFormat: LightboxCounterFormat = (i, total) =>
  `${i + 1} / ${total}`;

export function isVideoItem(item: LightboxItem): item is LightboxVideoSource {
  if (!item) {
    return false;
  }
  if (item.type === "video") {
    return true;
  }
  const candidate = (item as { src?: string }).src;
  if (candidate && VIDEO_EXT.test(candidate)) {
    return true;
  }
  return false;
}

export function pickImageSrc(
  item: LightboxItem,
  key: "sm" | "md" | "lg" | "xl",
): string {
  if (isVideoItem(item)) {
    return item.poster || "";
  }
  return (
    item[key] ||
    item.xl ||
    item.lg ||
    item.md ||
    item.sm ||
    item.url ||
    ""
  );
}

export function pickFullSrc(item: LightboxItem): string {
  if (isVideoItem(item)) {
    return item.src;
  }
  return pickImageSrc(item, "lg") || item.url || "";
}

export function pickDownloadUrl(item: LightboxItem): string {
  if (item.downloadUrl) {
    return item.downloadUrl;
  }
  if (isVideoItem(item)) {
    return item.src;
  }
  return item.xl || item.lg || item.md || item.sm || item.url || "";
}

export function pickAlt(item: LightboxItem, fallback?: string): string {
  return item.alt || item.name || fallback || "";
}

export function pickCaption(item: LightboxItem): string {
  // Caption is an explicit, human-written label. Never fall back to `item.name`,
  // which for Spatie media is the (often ugly) file name. Alt text and the
  // download file name still use `name` via their own pickers.
  return item.caption || "";
}

function filenameFromUrl(url: string): string {
  try {
    const u = new URL(url, "http://localhost");
    const base = u.pathname.split("/").pop() || "download";
    return base.split("?")[0] || "download";
  } catch {
    return url.split("/").pop()?.split("?")[0] || "download";
  }
}

async function triggerDownload(item: LightboxItem): Promise<void> {
  const url = pickDownloadUrl(item);
  if (!url) {
    return;
  }
  const name = item.name || filenameFromUrl(url);
  try {
    const response = await fetch(url, { mode: "cors", credentials: "omit" });
    if (!response.ok) {
      throw new Error(`Fetch failed: ${response.status}`);
    }
    const blob = await response.blob();
    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = objectUrl;
    anchor.download = name;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(objectUrl);
  } catch {
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = name;
    anchor.target = "_blank";
    anchor.rel = "noopener";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
  }
}

export function createAutoplayPlugin(delay: number) {
  return Autoplay({
    delay,
    stopOnInteraction: false,
    stopOnMouseEnter: true,
  });
}

export function canUseFullscreen(): boolean {
  if (typeof document === "undefined") {
    return false;
  }
  return !!document.fullscreenEnabled;
}

export function canUseShare(): boolean {
  if (typeof navigator === "undefined") {
    return false;
  }
  return typeof navigator.share === "function";
}

const [useProvideLightbox, useInjectLightbox] = createInjectionState(
  (props: LightboxProps, emits: LightboxEmits) => {
    const open = useVModel(props, "open", emits, {
      passive: true,
      defaultValue: false,
    });
    const index = useVModel(props, "index", emits, {
      passive: true,
      defaultValue: 0,
    });

    const items = computed(() => props.items || []);
    const current = computed(() => items.value[index.value] || null);
    const isMultiple = computed(() => items.value.length > 1);

    // Tailwind breakpoints, created once per lightbox so every slide can resolve a
    // responsive `fullKey` (e.g. mobile reuses the lighter thumbnail conversion while
    // larger screens load a bigger one) without each image registering its own listeners.
    const breakpointMatches: Record<LightboxBreakpoint, ReturnType<typeof useMediaQuery>> = {
      sm: useMediaQuery("(min-width: 640px)"),
      md: useMediaQuery("(min-width: 768px)"),
      lg: useMediaQuery("(min-width: 1024px)"),
      xl: useMediaQuery("(min-width: 1280px)"),
      "2xl": useMediaQuery("(min-width: 1536px)"),
    };
    const breakpointOrder: LightboxBreakpoint[] = ["sm", "md", "lg", "xl", "2xl"];

    function resolveResponsiveKey(
      key: LightboxResponsiveKey | undefined,
    ): LightboxThumbnailKey {
      if (!key || typeof key === "string") {
        return key || "lg";
      }
      let resolved: LightboxThumbnailKey = key.base || "lg";
      for (const bp of breakpointOrder) {
        if (breakpointMatches[bp].value && key[bp]) {
          resolved = key[bp] as LightboxThumbnailKey;
        }
      }
      return resolved;
    }

    const mainApi = shallowRef<EmblaCarouselType | null>(null);
    const thumbsApi = shallowRef<EmblaCarouselType | null>(null);

    const canPrev = ref(false);
    const canNext = ref(false);

    const isZoomed = ref(false);
    const isFullscreen = ref(false);

    // Immersive toggle: tapping the image (or pressing Space) hides every
    // control (top bar, caption, thumbnails, nav) except the image itself, and
    // lets the image grow into the freed space. Reset whenever the lightbox opens.
    const controlsVisible = ref(true);
    function toggleControls() {
      controlsVisible.value = !controlsVisible.value;
    }

    const counterFormat = computed<LightboxCounterFormat>(
      () => props.counterFormat || defaultCounterFormat,
    );

    const counterLabel = computed(() =>
      counterFormat.value(index.value, items.value.length),
    );

    watch(items, (next) => {
      if (index.value > Math.max(0, next.length - 1)) {
        index.value = Math.max(0, next.length - 1);
      }
    });

    watch(open, (isOpen) => {
      if (isOpen) {
        controlsVisible.value = true;
      }
    });

    function setCanScroll(api: EmblaCarouselType) {
      canPrev.value = api.canScrollPrev();
      canNext.value = api.canScrollNext();
    }

    function emitChange(i: number) {
      const item = items.value[i];
      if (item) {
        emits("change", { index: i, item });
      }
    }

    function resetZoom() {
      isZoomed.value = false;
    }

    function toggleZoom() {
      if (!props.zoomable) {
        return;
      }
      const item = current.value;
      if (!item || isVideoItem(item)) {
        return;
      }
      isZoomed.value = !isZoomed.value;
    }

    function goTo(i: number) {
      resetZoom();
      mainApi.value?.scrollTo(i);
    }

    function next() {
      resetZoom();
      mainApi.value?.scrollNext();
    }

    function prev() {
      resetZoom();
      mainApi.value?.scrollPrev();
    }

    function close() {
      resetZoom();
      open.value = false;
    }

    function openAt(i: number) {
      index.value = i;
      open.value = true;
    }

    async function download() {
      const item = current.value;
      if (!item) {
        return;
      }
      await triggerDownload(item);
      emits("download", { index: index.value, item });
    }

    async function toggleFullscreen() {
      if (typeof document === "undefined") {
        return;
      }
      if (!document.fullscreenElement) {
        const root = document.querySelector(
          "[data-slot=lightbox-content]",
        ) as HTMLElement | null;
        if (root && root.requestFullscreen) {
          await root.requestFullscreen().catch(() => null);
        }
      } else if (document.exitFullscreen) {
        await document.exitFullscreen().catch(() => null);
      }
    }

    async function share() {
      if (!canUseShare()) {
        return;
      }
      const item = current.value;
      if (!item) {
        return;
      }
      const url = pickFullSrc(item);
      const title = item.name || "Image";
      try {
        await navigator.share({ title, url });
      } catch {
        // user cancelled or browser rejected, ignore
      }
    }

    useEventListener(
      typeof document !== "undefined" ? document : null,
      "fullscreenchange",
      () => {
        isFullscreen.value =
          typeof document !== "undefined" && !!document.fullscreenElement;
      },
    );

    return {
      props,
      open,
      index,
      items,
      current,
      isMultiple,
      mainApi,
      thumbsApi,
      canPrev,
      canNext,
      isZoomed,
      isFullscreen,
      controlsVisible,
      toggleControls,
      counterLabel,
      resolveResponsiveKey,
      setCanScroll,
      emitChange,
      goTo,
      next,
      prev,
      close,
      openAt,
      download,
      toggleZoom,
      resetZoom,
      toggleFullscreen,
      share,
    };
  },
);

function useLightbox() {
  const state = useInjectLightbox();
  if (!state) {
    throw new Error("useLightbox must be used within a <Lightbox /> or <LightboxRoot />");
  }
  return state;
}

export { useLightbox, useProvideLightbox };
