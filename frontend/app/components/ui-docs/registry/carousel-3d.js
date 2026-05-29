import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "carousel-3d",
  title: "Carousel 3D",
  description:
    "Carousel with a perspective tilt effect. Items rotate in 3D as they enter and leave the centre. Use for hero showcases and product galleries where flat carousel feels static.",
  installation: {
    importPath: "@/components/ui/carousel-3d",
    imports: ["Carousel3d"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Auto-rotating image slides. Each item needs src and alt.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "reverse",
      title: "Reverse & tilt",
      description: "reverse flips the spin direction; tilt angles the whole scene.",
      examples: ["reverse"],
      align: "center",
    },
    {
      id: "interactive",
      title: "Interactive",
      description: "interactive enables keyboard focus and the item-click event.",
      examples: ["interactive"],
      align: "center",
    },
    {
      id: "custom-slot",
      title: "Custom slot",
      description: "Use the #item scoped slot to render any card content instead of an image.",
      examples: ["custom-slot"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Carousel3d",
      props: [
        { name: "items", type: "Carousel3dItem[]", default: "[]", description: "Slide objects with at least src and alt." },
        { name: "cardWidth", type: "string", default: '"clamp(10em, 35vw, 17.5em)"', description: "CSS width of each card." },
        { name: "cardAspect", type: "string", default: '"7 / 10"', description: "CSS aspect-ratio of each card." },
        { name: "cardRadius", type: "string", default: '"1.5em"', description: "Corner radius of each card." },
        { name: "gap", type: "string", default: '"0.5em"', description: "Gap between cards." },
        { name: "perspective", type: "string", default: '"35em"', description: "CSS perspective distance for the 3D scene." },
        { name: "tilt", type: "string", default: '"0deg"', description: "Scene tilt angle." },
        { name: "duration", type: "string", default: '"32s"', description: "Full rotation duration." },
        { name: "animated", type: "boolean", default: "true", description: "Auto-rotate. Supports v-model:animated." },
        { name: "reverse", type: "boolean", default: "false", description: "Reverse rotation direction." },
        { name: "pauseOnHover", type: "boolean", default: "false", description: "Pause rotation while hovered." },
        { name: "clickToToggle", type: "boolean", default: "true", description: "Click a card to pause/resume." },
        { name: "interactive", type: "boolean", default: "false", description: "Enable keyboard focus and item-click events." },
        { name: "fadeEdges", type: "boolean", default: "true", description: "Fade cards near the scene edges." },
        { name: "showShadow", type: "boolean", default: "false", description: "Render a soft ground shadow." },
        { name: "imageLoading", type: '"lazy" | "eager"', default: '"lazy"', description: "Native loading attribute for card images." },
        { name: "ariaLabel", type: "string", default: '"3D rotating carousel"', description: "aria-label for the carousel region." },
      ],
      events: [
        { name: "item-click", description: "Fires when a card is clicked (interactive mode). Receives (item, index, event)." },
        { name: "update:animated", description: "Fires when rotation pauses or resumes. Enables v-model:animated." },
      ],
      slots: [
        { name: "item", description: "Scoped slot to render each card. Receives { item, index }. Defaults to an image." },
        { name: "empty", description: "Rendered when items is empty." },
      ],
    },
  ],
});
