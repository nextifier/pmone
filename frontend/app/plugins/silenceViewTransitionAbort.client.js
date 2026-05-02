export default defineNuxtPlugin(() => {
  const isViewTransitionAbort = (err) =>
    (err?.name === "InvalidStateError" || err?.name === "TimeoutError") &&
    typeof err.message === "string" &&
    err.message.includes("Transition was aborted");

  window.addEventListener("unhandledrejection", (event) => {
    if (isViewTransitionAbort(event.reason)) event.preventDefault();
  });

  window.addEventListener("error", (event) => {
    if (isViewTransitionAbort(event.error)) event.preventDefault();
  });
});
