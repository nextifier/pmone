import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "button-glass",
  title: "Button Glass",
  description:
    "Glassmorphic button with layered shadows, blur, and 3D press animation. Use for hero CTAs, marketing pages, and over-image overlays where a regular Button feels flat.",
  installation: {
    importPath: "@/components/ui/button-glass",
    imports: ["ButtonGlass"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Primary glass button.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "variants",
      title: "Variants",
      description: "default, outline, ghost, white, black variants.",
      examples: ["variants"],
      align: "center",
    },
    {
      id: "sizes",
      title: "Sizes",
      description: "sm, md (default), lg, xl with matching rounded shapes.",
      examples: ["sizes"],
      align: "center",
    },
    {
      id: "rounded",
      title: "Rounded shapes",
      description: "Adjust corner radius from sharp (none) to fully pill (full).",
      examples: ["rounded"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ButtonGlass",
      props: [
        {
          name: "variant",
          type: '"default" | "outline" | "ghost" | "white" | "black" | "white-ghost" | "black-ghost" | "link"',
          default: '"default"',
          description: "Visual style.",
        },
        {
          name: "size",
          type: '"sm" | "md" | "lg" | "xl" | "icon"',
          default: '"md"',
          description: "Height and padding.",
        },
        {
          name: "rounded",
          type: '"none" | "sm" | "md" | "lg" | "xl" | "2xl" | "3xl" | "full"',
          default: '"full"',
          description: "Corner radius.",
        },
        {
          name: "to",
          type: "RouteLocationRaw",
          default: "—",
          description: "When set, renders as NuxtLink. External URLs open in a new tab.",
        },
        {
          name: "as",
          type: "string",
          default: '"button"',
          description: "HTML element when to is empty.",
        },
      ],
    },
  ],
});
