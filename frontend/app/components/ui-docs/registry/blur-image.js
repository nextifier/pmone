import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "blur-image",
  title: "Blur Image",
  description:
    "Image with low-quality image placeholder (LQIP) blur-up effect. Shows a tiny blurred preview while the full image loads, then crossfades in.",
  installation: {
    importPath: "@/components/ui/blur-image",
    imports: ["BlurImage"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Pass src plus a base64 lqip data URL for the blur effect.",
      examples: ["default"],
    },
    {
      id: "without-lqip",
      title: "Without LQIP",
      description: "Without lqip, falls back to a shimmer skeleton while loading.",
      examples: ["without-lqip"],
    },
  ],
  apiReference: [
    {
      component: "BlurImage",
      props: [
        { name: "src", type: "string", default: "—", description: "Full-resolution image URL." },
        {
          name: "lqip",
          type: "string",
          default: '""',
          description: "Tiny placeholder data URL or low-quality preview source.",
        },
        { name: "alt", type: "string", default: '""', description: "Alt text." },
        { name: "width", type: "number | string", default: "—", description: "Used to compute aspect-ratio when both are set." },
        { name: "height", type: "number | string", default: "—", description: "Used to compute aspect-ratio." },
        { name: "loading", type: "string", default: '"lazy"', description: "Native loading attribute." },
        { name: "imageClass", type: "string | object | array", default: '""', description: "Classes applied to the inner img." },
        { name: "imageStyle", type: "string | object", default: "—", description: "Inline style for the inner img." },
      ],
    },
  ],
});
