import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "alert-dialog",
  title: "Alert Dialog",
  description:
    "Modal confirmation prompt that interrupts the user with critical content. Use for destructive actions and authorisation prompts; reach for Dialog when the modal is informational.",
  installation: {
    importPath: "@/components/ui/alert-dialog",
    imports: [
      "AlertDialog",
      "AlertDialogTrigger",
      "AlertDialogContent",
      "AlertDialogHeader",
      "AlertDialogTitle",
      "AlertDialogDescription",
      "AlertDialogFooter",
      "AlertDialogAction",
      "AlertDialogCancel",
    ],
  },
  whenToUse: {
    title: "When to use AlertDialog vs Dialog",
    description:
      "AlertDialog is for one-shot confirmations that need explicit acknowledgement (delete, sign out, discard changes). Dialog is the right pick for forms, multi-step flows, and informational content.",
  },
  anatomy: {
    tree: [
      { component: "AlertDialog", children: [
        { component: "AlertDialogTrigger" },
        { component: "AlertDialogContent", children: [
          { component: "AlertDialogHeader", children: [ { component: "AlertDialogTitle" }, { component: "AlertDialogDescription" } ] },
          { component: "AlertDialogFooter", children: [ { component: "AlertDialogCancel" }, { component: "AlertDialogAction" } ] },
        ]},
      ]},
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Destructive confirmation pattern with cancel and action buttons.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "controlled",
      title: "Controlled",
      description: "Drive open state externally with v-model:open from any button.",
      examples: ["controlled"],
      align: "center",
    },
    {
      id: "non-destructive",
      title: "Non-destructive",
      description: "A plain confirmation that asks before a routine action.",
      examples: ["non-destructive"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "AlertDialog",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
      ],
      events: [
        { name: "update:open", description: "Fires when the open state changes. Enables v-model:open." },
      ],
      slots: [
        { name: "default", description: "Trigger and content sub-components." },
      ],
    },
    {
      component: "AlertDialogTrigger",
      props: [
        {
          name: "asChild",
          type: "boolean",
          default: "false",
          description: "Merge props onto the child element instead of rendering a button wrapper.",
        },
      ],
      slots: [
        { name: "default", description: "The element that opens the dialog." },
      ],
    },
    {
      component: "AlertDialogContent",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn(). Forwards remaining props to reka-ui AlertDialogContent.",
        },
      ],
      events: [
        { name: "escape-key-down", description: "Fires when the Escape key is pressed." },
        { name: "open-auto-focus", description: "Fires when focus moves into the content on open." },
        { name: "close-auto-focus", description: "Fires when focus returns to the trigger on close." },
      ],
      slots: [
        { name: "default", description: "Header, description, and footer sub-components." },
      ],
    },
    {
      component: "AlertDialogAction / AlertDialogCancel",
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
      { keys: ["Tab"], description: "Cycles focus between the Cancel and Action buttons." },
      { keys: ["Shift", "Tab"], description: "Cycles focus to the previous button." },
      { keys: ["Esc"], description: "Closes the dialog, cancelling the action." },
    ],
    notes: [
      "On open, focus defaults to the AlertDialogCancel button so the safe choice is selected.",
      "Uses role=alertdialog and is labelled by AlertDialogTitle and described by AlertDialogDescription.",
      "Must be dismissed via an explicit action; outside clicks do not close it.",
    ],
  },
});
