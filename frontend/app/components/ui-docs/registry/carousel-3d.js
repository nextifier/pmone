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
      description: "Five image slides with 3D perspective.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Carousel3d",
      props: [
        { name: "items", type: "Carousel3dItem[]", default: "[]", description: "Array of slide objects (image, title, etc.)." },
        { name: "perspective", type: "number", default: "1200", description: "CSS perspective distance in px." },
        { name: "rotation", type: "number", default: "30", description: "Tilt angle of side slides." },
        { name: "autoplay", type: "boolean", default: "false", description: "Auto-advance slides." },
        { name: "interval", type: "number", default: "4000", description: "Autoplay interval in ms." },
      ],
    },
  ],
});
