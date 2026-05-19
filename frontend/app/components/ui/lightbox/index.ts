export { default as Lightbox } from "./Lightbox.vue";
export { default as LightboxBody } from "./LightboxBody.vue";
export { default as LightboxCaption } from "./LightboxCaption.vue";
export { default as LightboxClose } from "./LightboxClose.vue";
export { default as LightboxContent } from "./LightboxContent.vue";
export { default as LightboxCounter } from "./LightboxCounter.vue";
export { default as LightboxDownload } from "./LightboxDownload.vue";
export { default as LightboxFullscreen } from "./LightboxFullscreen.vue";
export { default as LightboxImage } from "./LightboxImage.vue";
export { default as LightboxNext } from "./LightboxNext.vue";
export { default as LightboxPrevious } from "./LightboxPrevious.vue";
export { default as LightboxRoot } from "./LightboxRoot.vue";
export { default as LightboxShare } from "./LightboxShare.vue";
export { default as LightboxThumbnails } from "./LightboxThumbnails.vue";
export { default as LightboxVideo } from "./LightboxVideo.vue";

export {
  canUseFullscreen,
  canUseShare,
  defaultCounterFormat,
  isVideoItem,
  pickAlt,
  pickCaption,
  pickDownloadUrl,
  pickFullSrc,
  pickImageSrc,
  useLightbox,
} from "./useLightbox";

export type {
  LightboxApi,
  LightboxCounterFormat,
  LightboxEmblaPlugin,
  LightboxEmits,
  LightboxImageSource,
  LightboxItem,
  LightboxProps,
  LightboxThumbnailKey,
  LightboxVideoSource,
} from "./interface";
