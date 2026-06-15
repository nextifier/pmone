import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "sonner",
  title: "Sonner",
  description:
    "Toast notification system. Drop Toaster once at the app root and call toast() from anywhere to show a transient message.",
  installation: {
    importPath: "@/components/ui/sonner",
    imports: ["Toaster"],
  },
  whenToUse: {
    title: "When to use Sonner vs Notifications",
    description:
      "Sonner is the transient toast (success, error, info — auto-dismiss). Notifications is the persistent inbox list with unread badge.",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Trigger a toast from a Button click.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "variants",
      title: "Variants",
      description: "Success, error, info, warning.",
      examples: ["variants"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Toaster",
      props: [
        { name: "position", type: '"top-left" | "top-right" | "bottom-left" | "bottom-right" | "top-center" | "bottom-center"', default: '"bottom-right"', description: "Anchor for the stack." },
        { name: "richColors", type: "boolean", default: "false", description: "Use coloured variants instead of plain." },
        { name: "expand", type: "boolean", default: "false", description: "Expand stack on hover instead of overlapping." },
        { name: "progressBar", type: "boolean", default: "true", description: "Show a full-size auto-dismiss progress fill behind each toast. Pauses on hover; follows the global duration; disabled when expand is on." },
      ],
      slots: [
        { name: "info-icon", description: "Override the icon used for info toasts." },
        { name: "success-icon", description: "Override the icon used for success toasts." },
        { name: "warning-icon", description: "Override the icon used for warning toasts." },
        { name: "error-icon", description: "Override the icon used for error toasts." },
      ],
    },
    {
      component: "toast() (function)",
      props: [
        { name: "message", type: "string", default: "—", description: "First argument. The body of the toast." },
        { name: "options", type: "{ description?, action?, duration?, ... }", default: "—", description: "Second argument with extra config." },
      ],
    },
  ],
});
