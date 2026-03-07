// TEMPORARY: capture full SSR error stack traces in production
// DELETE THIS FILE after debugging is complete
export default defineNitroPlugin((nitroApp) => {
  // Approach 1: Store error on event context
  nitroApp.hooks.hook("error", (error: any, { event }: any) => {
    if (event) {
      event.context._ssrError = {
        message: error?.message,
        stack: error?.stack,
        cause: error?.cause?.stack || error?.cause?.message,
      };
    }
  });

  // Approach 2: Inject into response body (works for error pages)
  nitroApp.hooks.hook("render:response", (response: any, { event }: any) => {
    const ssrError = event?.context?._ssrError;
    if (ssrError && typeof response.body === "string") {
      const escaped = JSON.stringify(ssrError, null, 2)
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
      const debugHtml = `<pre id="ssr-debug" style="position:fixed;bottom:0;left:0;right:0;max-height:50vh;overflow:auto;background:#1a1a2e;color:#0f0;font-size:11px;padding:16px;z-index:99999;border-top:2px solid #f00;white-space:pre-wrap;word-break:break-all;">${escaped}</pre>`;
      response.body = response.body.replace("</body>", `${debugHtml}</body>`);
    }
  });

  // Approach 3: Also inject into render:html (for non-error pages that have errors)
  nitroApp.hooks.hook("render:html", (html: any, { event }: any) => {
    const ssrError = event?.context?._ssrError;
    if (ssrError) {
      const escaped = JSON.stringify(ssrError, null, 2)
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
      html.bodyAppend.push(
        `<pre id="ssr-debug" style="position:fixed;bottom:0;left:0;right:0;max-height:50vh;overflow:auto;background:#1a1a2e;color:#0f0;font-size:11px;padding:16px;z-index:99999;border-top:2px solid #f00;white-space:pre-wrap;word-break:break-all;">${escaped}</pre>`
      );
    }
  });

  // Approach 4: Override error message to include stack (shows in error.vue directly)
  nitroApp.hooks.hook("error", (error: any) => {
    if (error?.stack && error?.message) {
      error.message = `${error.message}\n\n--- STACK TRACE ---\n${error.stack}`;
      if (error.data) {
        error.data.stack = error.stack;
      }
    }
  });
});
