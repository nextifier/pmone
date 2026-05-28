import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "sheet",
  title: "Sheet",
  description:
    "Side-anchored drawer that slides in from any edge. Use for filter panels, navigation drawers, and detail views that complement the main page.",
  installation: {
    importPath: "@/components/ui/sheet",
    imports: [
      "Sheet",
      "SheetTrigger",
      "SheetContent",
      "SheetHeader",
      "SheetFooter",
      "SheetTitle",
      "SheetDescription",
      "SheetClose",
    ],
  },
  whenToUse: {
    title: "When to use Sheet vs Drawer vs Dialog",
    description:
      "Sheet slides in from a side (left/right/top/bottom). Drawer is a vaul-vue bottom sheet tuned for mobile. Dialog is a centered modal. For modals that should adapt across devices, use DialogResponsive.",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Right-side sheet with header and content.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "sides",
      title: "Sides",
      description: "Set side to top, right, bottom, or left.",
      examples: ["sides"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Sheet",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
      ],
    },
    {
      component: "SheetContent",
      props: [
        { name: "side", type: '"top" | "right" | "bottom" | "left"', default: '"right"', description: "Edge to slide in from." },
      ],
    },
  ],
});
