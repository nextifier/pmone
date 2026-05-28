import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "drawer",
  title: "Drawer",
  description:
    "Bottom-sheet modal built on vaul-vue. Best for mobile-first flows; on desktop, prefer DialogResponsive which auto-swaps between Dialog and Drawer.",
  installation: {
    importPath: "@/components/ui/drawer",
    imports: [
      "Drawer",
      "DrawerTrigger",
      "DrawerContent",
      "DrawerHeader",
      "DrawerFooter",
      "DrawerTitle",
      "DrawerDescription",
      "DrawerClose",
    ],
  },
  whenToUse: {
    title: "When to use Drawer vs DialogResponsive vs Sheet",
    description:
      "Drawer is the mobile-style bottom sheet. DialogResponsive wraps Drawer + Dialog so the same component works on phone and desktop. Sheet is a side-anchored drawer for navigation or filters on desktop layouts.",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Slide-up bottom sheet with header, content, and close.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Drawer",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        { name: "shouldScaleBackground", type: "boolean", default: "true", description: "Scale the page behind the drawer (iOS-style)." },
        { name: "dismissible", type: "boolean", default: "true", description: "Allow swipe-down dismiss." },
      ],
    },
  ],
});
