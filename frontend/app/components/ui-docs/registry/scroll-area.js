import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "scroll-area",
  title: "Scroll Area",
  description:
    "Themed scrollable container with custom scrollbars that match the design system. Use when default OS scrollbars look out of place.",
  installation: {
    importPath: "@/components/ui/scroll-area",
    imports: ["ScrollArea", "ScrollBar"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Long list inside a fixed-height container.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "horizontal",
      title: "Horizontal",
      description: "Add a horizontal ScrollBar for sideways overflow.",
      examples: ["horizontal"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "ScrollArea",
      props: [
        { name: "scrollHideDelay", type: "number", default: "600", description: "Hide scrollbar after this many ms of inactivity." },
        { name: "type", type: '"auto" | "always" | "scroll" | "hover"', default: '"hover"', description: "Scrollbar visibility behaviour." },
      ],
      slots: [
        { name: "default", description: "Scrollable content." },
      ],
    },
    {
      component: "ScrollBar",
      props: [
        { name: "orientation", type: '"vertical" | "horizontal"', default: '"vertical"', description: "Add a second ScrollBar with orientation=\"horizontal\" for sideways overflow." },
      ],
    },
  ],
});
