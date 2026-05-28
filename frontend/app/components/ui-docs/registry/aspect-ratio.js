import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "aspect-ratio",
  title: "Aspect Ratio",
  description: "Locks child content to a fixed width-to-height ratio. Built on reka-ui.",
  installation: {
    importPath: "@/components/ui/aspect-ratio",
    imports: ["AspectRatio"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "16:9 image container that scales with its parent width.",
      examples: ["default"],
    },
    {
      id: "square",
      title: "Square",
      description: "Set ratio=1 for square thumbnails.",
      examples: ["square"],
    },
  ],
  apiReference: [
    {
      component: "AspectRatio",
      props: [
        {
          name: "ratio",
          type: "number",
          default: "1",
          description: "Width-to-height ratio. 16/9 ≈ 1.78, 4/3 ≈ 1.33, 1 = square.",
        },
        {
          name: "asChild",
          type: "boolean",
          default: "false",
          description: "Render into the child element instead of a wrapper div.",
        },
      ],
    },
  ],
});
