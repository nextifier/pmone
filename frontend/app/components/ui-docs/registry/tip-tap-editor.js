import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "tip-tap-editor",
  title: "TipTap Editor",
  description:
    "Rich text editor built on @tiptap/vue-3. Ships with a styled toolbar covering headings, bold/italic, lists, links, images, code blocks, and an embedded media picker. Heavy component — see existing posts/blog form for full usage.",
  installation: { importPath: "@/components/ui/tip-tap-editor", imports: ["TipTapEditor"] },
  sections: [
    { id: "default", title: "Default", description: "Editor with the default toolbar bound to v-model.", examples: ["default"], align: "start" },
    { id: "no-toolbar", title: "Without toolbar", description: "Compact mode for short content like bios.", examples: ["no-toolbar"], align: "start" },
  ],
  apiReference: [
    {
      component: "TipTapEditor",
      props: [
        { name: "modelValue", type: "string", default: '""', description: "HTML content. Supports v-model." },
        { name: "placeholder", type: "string", default: "—", description: "Placeholder text when empty." },
        { name: "sticky", type: "boolean", default: "true", description: "Make the toolbar sticky to the top." },
        { name: "minimal", type: "boolean", default: "false", description: "Hide the toolbar and most extensions." },
        { name: "showCharacterCount", type: "boolean", default: "false", description: "Show a live character counter." },
      ],
      slots: [
        { name: "toolbar-extra", description: "Append extra toolbar buttons after the default set." },
      ],
    },
  ],
});
