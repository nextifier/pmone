import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "carousel",
  title: "Carousel",
  description:
    "Horizontal slider built on embla-carousel-vue. Compose CarouselContent and CarouselItem; add Previous/Next buttons and dot navigation as needed.",
  installation: {
    importPath: "@/components/ui/carousel",
    imports: [
      "Carousel",
      "CarouselContent",
      "CarouselItem",
      "CarouselPrevious",
      "CarouselNext",
      "CarouselDotButtons",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Three slides with prev/next arrows.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-dots",
      title: "With dots",
      description: "Add CarouselDotButtons for indicator navigation.",
      examples: ["with-dots"],
      align: "center",
    },
    {
      id: "vertical",
      title: "Vertical",
      description: "Set orientation to vertical to stack slides.",
      examples: ["vertical"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Carousel",
      props: [
        {
          name: "orientation",
          type: '"horizontal" | "vertical"',
          default: '"horizontal"',
          description: "Slide axis.",
        },
        {
          name: "opts",
          type: "EmblaOptionsType",
          default: "—",
          description: "Pass embla-carousel options (loop, align, dragFree, ...).",
        },
        {
          name: "plugins",
          type: "EmblaPluginType[]",
          default: "[]",
          description: "Embla plugins (Autoplay, WheelGestures, ...).",
        },
      ],
      events: [
        {
          name: "init-api",
          description: "Fired with the embla API once initialised. Use to control programmatically.",
        },
      ],
    },
  ],
});
