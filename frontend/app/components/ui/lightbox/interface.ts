import type useEmblaCarousel from "embla-carousel-vue";
import type { CreatePluginType, EmblaCarouselType } from "embla-carousel";
import type { HTMLAttributes, ImgHTMLAttributes, UnwrapRef } from "vue";

type UseEmblaParameters = Parameters<typeof useEmblaCarousel>;
type EmblaOptions = UseEmblaParameters[0];
export type LightboxEmblaPlugin = CreatePluginType<object, object>;

export type LightboxThumbnailKey = "sm" | "md" | "lg" | "xl";

export type LightboxBreakpoint = "sm" | "md" | "lg" | "xl" | "2xl";

/**
 * A conversion key for the full-size image. Either a single key applied at every
 * viewport, or a breakpoint map (`base` for mobile, then min-width overrides) so
 * small screens can reuse a lighter conversion while larger screens load a bigger one.
 */
export type LightboxResponsiveKey =
  | LightboxThumbnailKey
  | ({ base?: LightboxThumbnailKey } & Partial<
      Record<LightboxBreakpoint, LightboxThumbnailKey>
    >);

export type LightboxCounterFormat = (
  index: number,
  total: number,
) => string;

export interface LightboxImageSource {
  type?: "image";
  sm?: string;
  md?: string;
  lg?: string;
  xl?: string;
  url?: string;
  name?: string;
  alt?: string;
  caption?: string;
  downloadUrl?: string;
}

export interface LightboxVideoSource {
  type: "video";
  src: string;
  poster?: string;
  name?: string;
  alt?: string;
  caption?: string;
  muted?: boolean;
  autoplay?: boolean;
  downloadUrl?: string;
}

export type LightboxItem = LightboxImageSource | LightboxVideoSource;

export interface LightboxProps {
  items: LightboxItem[];
  open?: boolean;
  index?: number;
  loop?: boolean;
  showThumbnails?: boolean;
  showCounter?: boolean;
  showDownload?: boolean;
  showClose?: boolean;
  showNavButtons?: boolean;
  showCaption?: boolean;
  showFullscreen?: boolean;
  showShare?: boolean;
  closeOnBackdropClick?: boolean;
  keyboard?: boolean;
  autoplay?: false | number;
  zoomable?: boolean;
  swipeToClose?: boolean;
  thumbnailKey?: LightboxThumbnailKey;
  fullKey?: LightboxResponsiveKey;
  counterFormat?: LightboxCounterFormat;
  imageProps?: ImgHTMLAttributes;
  gridClass?: string;
  itemClass?: HTMLAttributes["class"];
  rounded?: string;
  firstSpansLarge?: boolean;
  limit?: number | null;
  alt?: string;
  emblaOpts?: EmblaOptions;
  emblaPlugins?: LightboxEmblaPlugin[];
  class?: HTMLAttributes["class"];
}

export interface LightboxEmits {
  (e: "update:open", value: boolean): void;
  (e: "update:index", value: number): void;
  (e: "change", payload: { index: number; item: LightboxItem }): void;
  (e: "download", payload: { index: number; item: LightboxItem }): void;
}

export type LightboxApi = UnwrapRef<EmblaCarouselType>;

export interface WithClassAsProps {
  class?: HTMLAttributes["class"];
}
