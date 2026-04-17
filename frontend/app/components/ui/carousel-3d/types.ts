import type { HTMLAttributes } from "vue";

export interface Carousel3dItem {
  src?: string;
  alt?: string;
  [key: string]: unknown;
}

export interface Carousel3dProps<T extends Carousel3dItem = Carousel3dItem> {
  class?: HTMLAttributes["class"];
  cardClass?: HTMLAttributes["class"];
  items?: T[];
  cardWidth?: string;
  cardAspect?: string;
  cardRadius?: string;
  gap?: string;
  perspective?: string;
  tilt?: string;
  initialRotation?: string;
  centerIndex?: number;
  duration?: string;
  animated?: boolean;
  reverse?: boolean;
  pauseOnHover?: boolean;
  pauseOffScreen?: boolean;
  pauseOnHidden?: boolean;
  hoverScale?: string;
  fadeEdges?: boolean;
  showShadow?: boolean;
  clickToToggle?: boolean;
  clipOverflow?: boolean;
  interactive?: boolean;
  imageLoading?: "lazy" | "eager";
  ariaLabel?: string;
}
