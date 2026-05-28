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
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Destructive confirmation pattern with cancel and action buttons.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "AlertDialog",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
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
});
