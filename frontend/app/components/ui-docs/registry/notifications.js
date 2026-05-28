import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "notifications",
  title: "Notifications",
  description:
    "Header bell with an unread badge and a popover list of notifications. Reads from the global notifications store. For ad-hoc toast messages, use Sonner instead.",
  installation: {
    importPath: "@/components/ui/notifications",
    imports: ["Notifications"],
  },
  whenToUse: {
    title: "When to use Notifications vs Sonner",
    description:
      "Notifications is the persistent unread list (inbox-style) attached to the header. Sonner handles transient toasts that confirm an action or report a quick result.",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Drop into a header next to other actions.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Notifications",
      props: [
        { name: "—", type: "—", default: "—", description: "No props. Wires itself to the global notifications store." },
      ],
    },
  ],
});
