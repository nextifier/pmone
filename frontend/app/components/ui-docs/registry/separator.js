import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "separator",
  title: "Separator",
  description: "Horizontal or vertical divider between sections.",
  installation: {
    importPath: "@/components/ui/separator",
    imports: ["Separator"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Horizontal line between blocks of content.",
      examples: ["default"],
    },
    {
      id: "vertical",
      title: "Vertical",
      description: "Inline vertical divider with orientation=\"vertical\".",
      examples: ["vertical"],
    },
  ],
  apiReference: [
    {
      component: "Separator",
      props: [
        { name: "orientation", type: '"horizontal" | "vertical"', default: '"horizontal"', description: "Axis." },
        { name: "decorative", type: "boolean", default: "true", description: "Mark as decorative (skipped by assistive tech)." },
      ],
    },
  ],
});
