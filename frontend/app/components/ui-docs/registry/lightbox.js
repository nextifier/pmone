import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "lightbox",
  title: "Lightbox",
  description:
    "Full-screen image viewer with zoom, pan, keyboard navigation, fullscreen, download, and slide counter. Built on photoswipe under the hood.",
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
  ],
  apiReference: [
    {
      component: "Lightbox",
      props: [
        { name: "items", type: "{ src: string, alt?: string, caption?: string }[]", default: "[]", description: "Slides to display." },
        { name: "open", type: "boolean", default: "false", description: "Open state. Supports v-model:open." },
        { name: "index", type: "number", default: "0", description: "Active slide index. Supports v-model:index." },
      ],
    },
  ],
});
