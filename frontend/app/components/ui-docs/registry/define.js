/**
 * Component documentation entry.
 *
 * @typedef {Object} ApiProp
 * @property {string} name
 * @property {string} type
 * @property {string} default
 * @property {string} description
 *
 * @typedef {Object} ApiRef
 * @property {string} component
 * @property {ApiProp[]} [props]
 * @property {{ name: string, description: string }[]} [events]
 * @property {{ name: string, description: string }[]} [slots]
 *
 * @typedef {Object} Section
 * @property {string} id
 * @property {string} title
 * @property {string} [description]
 * @property {string[]} examples - Example IDs that map to examples/{name}/{id}.vue
 *
 * @typedef {Object} AnatomyNode
 * @property {string} component
 * @property {AnatomyNode[]} [children]
 *
 * @typedef {Object} KeyBinding
 * @property {string[]} keys - e.g. ["Esc"] or ["Ctrl", "K"]
 * @property {string} description
 *
 * @typedef {Object} Accessibility
 * @property {KeyBinding[]} keyboard
 * @property {string[]} [notes]
 *
 * @typedef {Object} ComponentDoc
 * @property {string} name
 * @property {string} title
 * @property {string} description
 * @property {{ importPath: string, imports: string[] }} [installation]
 * @property {{ title: string, description: string }} [whenToUse]
 * @property {{ tree: AnatomyNode[] }} [anatomy]
 * @property {Section[]} sections
 * @property {ApiRef[]} [apiReference]
 * @property {Accessibility} [accessibility]
 *
 * @param {ComponentDoc} config
 * @returns {ComponentDoc}
 */
export function defineComponentDoc(config) {
  return config;
}
