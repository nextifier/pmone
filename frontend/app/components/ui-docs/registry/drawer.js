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
  anatomy: {
    tree: [
      { component: "Drawer", children: [
        { component: "DrawerTrigger" },
        { component: "DrawerContent", children: [
          { component: "DrawerHeader", children: [ { component: "DrawerTitle" }, { component: "DrawerDescription" } ] },
          { component: "DrawerFooter" },
          { component: "DrawerClose" },
        ]},
      ]},
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Slide-up bottom sheet with header, content, and close.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "scrollable",
      title: "Scrollable content",
      description: "Long content scrolls within the sheet while the footer stays put.",
      examples: ["scrollable"],
      align: "center",
    },
    {
      id: "with-form",
      title: "With form",
      description: "Collect input with labelled fields and confirm in the footer.",
      examples: ["with-form"],
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
      events: [
        { name: "update:open", description: "Fires when the drawer opens or closes. Enables v-model:open." },
      ],
    },
    {
      component: "DrawerTrigger / DrawerClose",
      props: [
        {
          name: "asChild",
          type: "boolean",
          default: "false",
          description: "Render the child slot without the default button wrapper.",
        },
      ],
    },
    {
      component: "DrawerContent",
      props: [
        { name: "class", type: "string", default: "—", description: "Extra classes for the sheet. Includes the drag handle." },
      ],
      events: [
        { name: "escape-key-down", description: "Fires when Escape is pressed." },
        { name: "pointer-down-outside", description: "Fires on a pointer press outside the sheet." },
      ],
    },
    {
      component: "DrawerHeader / DrawerFooter / DrawerTitle / DrawerDescription",
      props: [
        { name: "class", type: "string", default: "—", description: "Layout and typography wrappers for the sheet content." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Space"], description: "When focus is on the trigger, opens the drawer." },
      { keys: ["Enter"], description: "When focus is on the trigger, opens the drawer." },
      { keys: ["Tab"], description: "Moves focus to the next focusable element inside the drawer." },
      { keys: ["Shift", "Tab"], description: "Moves focus to the previous focusable element." },
      { keys: ["Esc"], description: "Closes the drawer and returns focus to the trigger." },
    ],
    notes: [
      "A vaul-based bottom sheet: focus is trapped while open and on touch it can be dismissed by swiping down.",
      "Provide a DrawerTitle so screen readers announce the drawer via aria-labelledby.",
    ],
  },
});
