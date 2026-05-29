import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "lightbox",
  title: "Lightbox",
  description:
    "Full-screen image and video viewer with zoom, keyboard navigation, fullscreen, download, share, thumbnails, and a slide counter. Highly slot-driven for custom chrome.",
  installation: {
    importPath: "@/components/ui/lightbox",
    imports: [
      "Lightbox",
      "LightboxBody",
      "LightboxContent",
      "LightboxCaption",
      "LightboxClose",
      "LightboxCounter",
      "LightboxDownload",
      "LightboxFullscreen",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Pass an array of images and render thumbnails that open the lightbox.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "custom-trigger",
      title: "Custom trigger",
      description: "Use the #trigger scoped slot to open the lightbox from any element. Receives { open, openAt, items }.",
      examples: ["custom-trigger"],
      align: "start",
    },
    {
      id: "mixed-media",
      title: "Mixed image + video",
      description: "Items can be images or { type: 'video', src, poster }. Videos auto-pause on slide change.",
      examples: ["mixed-media"],
      align: "start",
    },
    {
      id: "minimal",
      title: "Minimal chrome",
      description: "Hide the thumbnail strip, counter, and download button for a bare viewer.",
      examples: ["minimal"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "Lightbox",
      props: [
        { name: "items", type: "LightboxItem[]", default: "[]", description: "Slides to display (image or video objects)." },
        { name: "open", type: "boolean", default: "false", description: "Open state. Supports v-model:open." },
        { name: "index", type: "number", default: "0", description: "Active slide index. Supports v-model:index." },
        { name: "loop", type: "boolean", default: "true", description: "Wrap around at the ends." },
        { name: "limit", type: "number | null", default: "null", description: "Cap how many thumbnails the built-in grid renders." },
        { name: "showThumbnails", type: "boolean", default: "true", description: "Show the thumbnail strip." },
        { name: "showCounter", type: "boolean", default: "true", description: "Show the n / total counter." },
        { name: "showFullscreen", type: "boolean", default: "false", description: "Show the fullscreen toggle." },
        { name: "showShare", type: "boolean", default: "false", description: "Show the share button." },
        { name: "zoomable", type: "boolean", default: "true", description: "Allow zoom on the active image." },
        { name: "keyboard", type: "boolean", default: "true", description: "Enable arrow-key and Escape navigation." },
      ],
      events: [
        { name: "update:open", description: "Fires when the viewer opens or closes. Enables v-model:open." },
        { name: "update:index", description: "Fires when the active slide changes. Enables v-model:index." },
        { name: "change", description: "Fires on slide change. Receives { index, item }." },
        { name: "download", description: "Fires when a slide is downloaded. Receives { index, item }." },
      ],
      slots: [
        { name: "trigger", description: "Scoped slot for the opener. Receives { open, openAt, items }." },
        { name: "counter", description: "Override the n / total counter." },
        { name: "actions", description: "Override the action toolbar (download, share, fullscreen, close)." },
        { name: "previous", description: "Override the previous-slide control." },
        { name: "next", description: "Override the next-slide control." },
        { name: "caption", description: "Scoped slot for the caption. Receives { caption, item }." },
        { name: "thumbnails", description: "Override the thumbnail strip." },
      ],
    },
    {
      component: "Sub-components (LightboxContent, LightboxImage, LightboxVideo, LightboxClose, LightboxCounter, LightboxNext, LightboxPrevious, LightboxThumbnails, ...)",
      props: [
        { name: "class", type: "string", default: "—", description: "Building blocks used internally and available for fully custom layouts. Each reads shared state from the Lightbox provider. See the component source for per-part props." },
      ],
    },
  ],
});
