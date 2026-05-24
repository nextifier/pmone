import VueTippy from "vue-tippy";
import type { TippyPluginOptions } from "vue-tippy";

// Animation CSS imports temporarily disabled — re-enabling them crashes the
// Cloudflare Pages SSR runtime with "500 undefined" on every route (the
// `<link rel="stylesheet">` resolution path the cloudflare-pages preset uses
// for plugin CSS hits an `undefined` codepath inside Nuxt's render layer).
// Originally documented during dev-tunnel work as a Vite SSR + CDN-tunnel
// MIME-mismatch issue, but production hits the same crash. Until a proper
// fix lands, tooltip animations gracefully degrade to default browser
// transitions.
// import "tippy.js/animations/scale.css";
// import "tippy.js/animations/shift-away.css";
// import "tippy.js/animations/shift-toward.css";
// import "tippy.js/animations/perspective.css";

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.use(VueTippy, {
    component: "Tippy",
    directive: "tippy",
    defaultProps: {
      animation: "shift-away",
      delay: [200, 100],
      trigger: "mouseenter",
      touch: ["hold", 500],
      arrow: false,
    },
  } satisfies TippyPluginOptions);
});
