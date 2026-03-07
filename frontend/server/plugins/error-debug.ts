// TEMPORARY: capture full SSR error stack traces in production
// DELETE THIS FILE after debugging is complete
export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook("error", (error: any, { event }: any) => {
    // Store the full error on the event for later retrieval
    if (event) {
      event.context._ssrError = {
        message: error?.message,
        stack: error?.stack,
        cause: error?.cause?.stack || error?.cause?.message,
      };
    }
  });

  nitroApp.hooks.hook("render:html", (html: any, { event }: any) => {
    const ssrError = event?.context?._ssrError;
    if (ssrError) {
      // Inject full stack trace as a hidden comment and visible pre block
      const escaped = JSON.stringify(ssrError, null, 2)
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
      html.bodyAppend.push(
        `<pre id="ssr-debug" style="position:fixed;bottom:0;left:0;right:0;max-height:50vh;overflow:auto;background:#1a1a2e;color:#0f0;font-size:11px;padding:16px;z-index:99999;border-top:2px solid #f00;white-space:pre-wrap;word-break:break-all;">${escaped}</pre>`
      );
    }
  });
});
