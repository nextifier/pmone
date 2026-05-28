import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "dialog-responsive",
  title: "Dialog Responsive",
  description:
    "Dialog on desktop, Drawer (vaul-vue) on mobile. Use this for almost every modal in the app. Reach for plain Dialog only for short confirmations that still look fine on a phone.",
  installation: {
    importPath: "@/components/ui/dialog-responsive",
    imports: ["DialogResponsive"],
  },
  whenToUse: {
    title: "When to use DialogResponsive vs Dialog",
    description:
      "Default to DialogResponsive for any modal that has a form or scrollable content. Plain Dialog is fine for short confirmations (delete, log out) and small popovers that read well on mobile without becoming a drawer.",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description:
        "Use the trigger slot for the opener, the default slot for the content. Open state is controlled with v-model:open.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-form",
      title: "With form",
      description:
        "Form pattern inside DialogResponsive: padding handled with a wrapper div, action buttons at the end.",
      examples: ["with-form"],
      align: "center",
    },
    {
      id: "prevent-close",
      title: "Prevent close",
      description:
        "preventClose blocks overlay clicks and Escape. The close-prevented event lets you show a confirmation prompt.",
      examples: ["prevent-close"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "DialogResponsive",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        {
          name: "isResponsive",
          type: "boolean",
          default: "true",
          description: "Set false to force the Drawer variant at every breakpoint.",
        },
        {
          name: "dialogMaxWidth",
          type: "string",
          default: '"400px"',
          description: "Max width of the desktop dialog.",
        },
        {
          name: "dialogMaxHeight",
          type: "string",
          default: '"calc(100% - 4rem)"',
          description: "Max height of the desktop dialog.",
        },
        {
          name: "preventClose",
          type: "boolean",
          default: "false",
          description: "Block overlay click and Escape. Emits close-prevented.",
        },
        {
          name: "drawerCloseButton",
          type: "boolean",
          default: "false",
          description: "Show a close button on the mobile drawer (default is swipe only).",
        },
        { name: "hideOverlay", type: "boolean", default: "false", description: "Hide the dark backdrop." },
        {
          name: "flushContent",
          type: "boolean",
          default: "false",
          description: "Render the content without the ScrollArea wrapper.",
        },
        {
          name: "overflowContent",
          type: "boolean",
          default: "true",
          description: "Enable overflow-y-auto on the mobile drawer body.",
        },
      ],
      events: [
        { name: "update:open", description: "Fires when the open state changes." },
        {
          name: "close-prevented",
          description: "Fires when the user tries to close with preventClose enabled.",
        },
      ],
      slots: [
        { name: "trigger", description: "The opener button. Receives open from the scope." },
        { name: "default", description: "Main content. Receives data from the scope." },
        { name: "sticky-header", description: "Header that sticks to the top while scrolling." },
        { name: "sticky-footer", description: "Footer that sticks to the bottom while scrolling." },
      ],
    },
  ],
});
