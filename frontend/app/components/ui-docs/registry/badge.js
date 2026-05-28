import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "badge",
  title: "Badge",
  description:
    "A small pill for status, counts, or category labels. Seven color variants with a dot prefix by default, swappable for an icon or stripped of chrome entirely with the plain prop.",
  installation: {
    importPath: "@/components/ui/badge",
    imports: ["Badge"],
  },
  sections: [
    {
      id: "variants",
      title: "Variants",
      description:
        "Each variant gets a small dot indicator at the start of the label, except outline.",
      examples: ["variants"],
      align: "center",
    },
    {
      id: "with-icon",
      title: "With icon",
      description:
        "The withIcon prop swaps the dot for a default icon that matches the variant (info, success check, warning triangle, and so on).",
      examples: ["with-icon"],
      align: "center",
    },
    {
      id: "custom-icon",
      title: "Custom icon",
      description: "Pass any hugeicons or lucide name to icon to override the default.",
      examples: ["custom-icon"],
      align: "center",
    },
    {
      id: "plain",
      title: "Plain",
      description:
        "plain removes the border, padding, and radius. Good inside tables or inline with body text.",
      examples: ["plain"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Badge",
      props: [
        {
          name: "variant",
          type: '"default" | "info" | "success" | "warning" | "destructive" | "muted" | "outline"',
          default: '"default"',
          description: "Color of the dot or icon, and the overall tone.",
        },
        {
          name: "icon",
          type: "string",
          default: "—",
          description: "Icon name (hugeicons/lucide). Replaces the dot and disables the default icon.",
        },
        {
          name: "withIcon",
          type: "boolean",
          default: "false",
          description: "Use the default icon for the variant instead of the dot.",
        },
        {
          name: "plain",
          type: "boolean",
          default: "false",
          description: "Strip border, padding, and radius. Keeps only the dot or icon and the label.",
        },
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn().",
        },
      ],
    },
  ],
});
