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
    { id: "with-delimiter", title: "With delimiter", description: "Set delimiter to commit a tag on a different key (here, space).", examples: ["with-delimiter"], align: "center" },
    { id: "disabled", title: "Disabled", description: "Pass disabled to block adding or removing tags.", examples: ["disabled"], align: "center" },
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
      events: [
        { name: "update:modelValue", description: "Fires when tags are added or removed. Enables v-model." },
        { name: "add-tag", description: "Fires with the value of a newly added tag." },
        { name: "remove-tag", description: "Fires with the value of a removed tag." },
      ],
    },
    {
      component: "TagsInputItem / TagsInputItemText / TagsInputItemDelete",
      props: [
        { name: "value", type: "string", default: "—", description: "(TagsInputItem) The tag this chip represents. ItemText renders the label; ItemDelete is the remove button." },
      ],
    },
    {
      component: "TagsInputInput",
      props: [
        { name: "placeholder", type: "string", default: "—", description: "Placeholder for the free-text entry field." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Enter"], description: "Adds the typed text as a new tag." },
      { keys: ["Backspace"], description: "Removes the last tag when the input is empty." },
      { keys: ["←"], description: "Moves focus to the previous tag." },
      { keys: ["→"], description: "Moves focus to the next tag." },
      { keys: ["Delete"], description: "Removes the focused tag." },
    ],
    notes: [
      "A comma also commits the typed text as a new tag.",
      "Each tag is focusable and can be removed individually.",
      "The field is labelled with role-appropriate ARIA so additions and removals are announced.",
    ],
  },
});
