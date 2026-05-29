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
  anatomy: {
    tree: [
      { component: "Sheet", children: [
        { component: "SheetTrigger" },
        { component: "SheetContent", children: [
          { component: "SheetHeader", children: [ { component: "SheetTitle" }, { component: "SheetDescription" } ] },
          { component: "SheetFooter" },
          { component: "SheetClose" },
        ]},
      ]},
    ],
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
      component: "Sheet",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
      ],
      events: [
        { name: "update:open", description: "Fires when the sheet opens or closes. Enables v-model:open." },
      ],
    },
    {
      component: "SheetTrigger / SheetClose",
      props: [
        { name: "asChild", type: "boolean", default: "false", description: "Render the child slot without the default button wrapper." },
      ],
    },
    {
      component: "SheetContent",
      props: [
        { name: "side", type: '"top" | "right" | "bottom" | "left"', default: '"right"', description: "Edge to slide in from." },
      ],
      events: [
        { name: "escape-key-down", description: "Fires when Escape is pressed." },
        { name: "pointer-down-outside", description: "Fires on a pointer press outside the sheet." },
      ],
    },
    {
      component: "SheetHeader / SheetFooter / SheetTitle / SheetDescription",
      props: [
        { name: "class", type: "string", default: "—", description: "Layout and typography wrappers for the sheet content." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Space"], description: "When focus is on the trigger, opens the sheet." },
      { keys: ["Enter"], description: "When focus is on the trigger, opens the sheet." },
      { keys: ["Tab"], description: "Moves focus to the next focusable element inside the sheet." },
      { keys: ["Shift", "Tab"], description: "Moves focus to the previous focusable element." },
      { keys: ["Esc"], description: "Closes the sheet and returns focus to the trigger." },
    ],
    notes: [
      "A side-anchored Dialog: focus is trapped within the content while open and restored on close.",
      "Content is labelled by SheetTitle and described by SheetDescription via aria-labelledby / aria-describedby.",
    ],
  },
});
