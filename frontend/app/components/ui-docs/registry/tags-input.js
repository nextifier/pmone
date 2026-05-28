import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "tags-input",
  title: "Tags Input",
  description: "Free-form tag entry. Type, press Enter or comma, get a chip. Backspace removes the last chip.",
  installation: {
    importPath: "@/components/ui/tags-input",
    imports: ["TagsInput", "TagsInputInput", "TagsInputItem", "TagsInputItemDelete", "TagsInputItemText"],
  },
  sections: [
    { id: "default", title: "Default", description: "Pre-seeded list of tags.", examples: ["default"], align: "center" },
  ],
  apiReference: [
    {
      component: "TagsInput",
      props: [
        { name: "modelValue", type: "string[]", default: "[]", description: "Current tags. Supports v-model." },
        { name: "addOnPaste", type: "boolean", default: "false", description: "Auto-split pasted content on commas/newlines into tags." },
        { name: "duplicate", type: "boolean", default: "false", description: "Allow duplicate tags." },
        { name: "delimiter", type: "string | RegExp", default: '","', description: "Separator that splits typed input into tags." },
      ],
    },
  ],
});
