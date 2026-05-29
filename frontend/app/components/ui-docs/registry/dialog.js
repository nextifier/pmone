import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "dialog",
  title: "Dialog",
  description:
    "Modal overlay for confirmations, short forms, or extra detail. For modals that should turn into bottom drawers on mobile, use DialogResponsive instead.",
  installation: {
    importPath: "@/components/ui/dialog",
    imports: [
      "Dialog",
      "DialogTrigger",
      "DialogContent",
      "DialogHeader",
      "DialogTitle",
      "DialogDescription",
      "DialogFooter",
      "DialogClose",
    ],
  },
  anatomy: {
    tree: [
      { component: "Dialog", children: [
        { component: "DialogTrigger" },
        { component: "DialogContent", children: [
          { component: "DialogHeader", children: [ { component: "DialogTitle" }, { component: "DialogDescription" } ] },
          { component: "DialogFooter" },
          { component: "DialogClose" },
        ]},
      ]},
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Standard confirmation dialog. Trigger opens, Close dismisses.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-form",
      title: "With form",
      description: "Common layout: header, body with Field and Input, footer with action buttons.",
      examples: ["with-form"],
      align: "center",
    },
    {
      id: "controlled",
      title: "Controlled",
      description:
        "Drive the open state externally via v-model:open. Use when the dialog is triggered by a non-button event.",
      examples: ["controlled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Dialog",
      props: [
        {
          name: "open",
          type: "boolean",
          default: "—",
          description: "Open state. Supports v-model:open.",
        },
        {
          name: "modal",
          type: "boolean",
          default: "true",
          description: "Whether the dialog blocks outside interaction (focus trap and overlay click).",
        },
      ],
      events: [
        { name: "update:open", description: "Fires when the open state changes. Enables v-model:open." },
      ],
    },
    {
      component: "DialogContent",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Default width is sm:max-w-md. Override to go wider.",
        },
      ],
      events: [
        { name: "escape-key-down", description: "Fires when Escape is pressed. Call preventDefault to keep it open." },
        { name: "pointer-down-outside", description: "Fires on a pointer press outside the content." },
        { name: "interact-outside", description: "Fires on any outside interaction (pointer or focus)." },
      ],
    },
    {
      component: "DialogScrollContent",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Variant of DialogContent that scrolls the whole dialog (overlay + content) for very tall bodies.",
        },
      ],
      events: [
        { name: "escape-key-down", description: "Same outside-interaction events as DialogContent." },
      ],
    },
    {
      component: "DialogTrigger / DialogClose",
      props: [
        {
          name: "asChild",
          type: "boolean",
          default: "false",
          description: "Render the child slot without the default button wrapper.",
        },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Space"], description: "When focus is on the trigger, opens the dialog." },
      { keys: ["Enter"], description: "When focus is on the trigger, opens the dialog." },
      { keys: ["Tab"], description: "Moves focus to the next focusable element inside the dialog." },
      { keys: ["Shift", "Tab"], description: "Moves focus to the previous focusable element." },
      { keys: ["Esc"], description: "Closes the dialog and returns focus to the trigger." },
    ],
    notes: [
      "Focus is trapped within the content while open and restored to the trigger on close.",
      "Built on reka-ui Dialog: content is labelled by DialogTitle and described by DialogDescription via aria-labelledby / aria-describedby.",
      "Always include a DialogTitle (visually hidden if needed) so screen readers announce the dialog.",
    ],
  },
});
