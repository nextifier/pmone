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
});
