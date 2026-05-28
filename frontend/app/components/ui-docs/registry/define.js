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
 * @typedef {Object} ComponentDoc
 * @property {string} name
 * @property {string} title
 * @property {string} description
 * @property {{ importPath: string, imports: string[] }} [installation]
 * @property {{ title: string, description: string }} [whenToUse]
 * @property {Section[]} sections
 * @property {ApiRef[]} [apiReference]
 *
 * @param {ComponentDoc} config
 * @returns {ComponentDoc}
 */
export function defineComponentDoc(config) {
  return config;
}
