/**
 * Composable to apply LQIP blur-to-clear loading effect on content images.
 * Targets <img> elements with data-lqip attribute inside a container.
 *
 * @param {import('vue').Ref<string>|string} containerSelector - CSS selector or ref for the article container
 */
export const useContentImageBlur = (containerSelector) => {
  const processImages = () => {
    const selector = unref(containerSelector);
    if (!selector) return;

    const container = document.querySelector(selector);
    if (!container) return;

    const images = container.querySelectorAll("img[data-lqip]");

    images.forEach((img) => {
      // Skip if already processed
      if (img.closest(".blur-image-content")) return;

      const lqipUrl = img.dataset.lqip;
      if (!lqipUrl) return;

      // Create wrapper
      const wrapper = document.createElement("div");
      wrapper.className = "blur-image-content relative overflow-hidden";

      // No aspect-ratio needed - img width/height attributes handle layout

      // Create LQIP background
      const lqipImg = document.createElement("img");
      lqipImg.src = lqipUrl;
      lqipImg.alt = "";
      lqipImg.setAttribute("aria-hidden", "true");
      lqipImg.className =
        "absolute inset-0 size-full object-cover scale-110 blur-lg transition-opacity duration-300 ease-in-out";

      // Create shimmer overlay (shown after LQIP loads)
      const shimmer = document.createElement("div");
      shimmer.className = "absolute inset-0 shimmer-overlay";
      shimmer.style.display = "none";

      // Create skeleton (shown before LQIP loads)
      const skeleton = document.createElement("div");
      skeleton.className = "absolute inset-0 skeleton";

      // Wrap the image
      img.parentNode.insertBefore(wrapper, img);
      wrapper.appendChild(skeleton);
      wrapper.appendChild(lqipImg);
      wrapper.appendChild(shimmer);
      wrapper.appendChild(img);

      // Style actual image for transition
      img.style.opacity = "0";
      img.style.transition = "opacity 300ms ease-in-out";
      img.classList.add("relative");

      // When LQIP loads: hide skeleton, show shimmer
      const onLqipLoaded = () => {
        skeleton.remove();
        shimmer.style.display = "";
      };

      if (lqipImg.complete && lqipImg.naturalWidth > 0) {
        onLqipLoaded();
      } else {
        lqipImg.addEventListener("load", onLqipLoaded, { once: true });
      }

      // When actual image loads: fade in, hide LQIP + shimmer
      const onLoaded = () => {
        img.style.opacity = "1";
        lqipImg.style.opacity = "0";
        shimmer.remove();
      };

      if (img.complete && img.naturalWidth > 0) {
        onLoaded();
      } else {
        img.addEventListener("load", onLoaded, { once: true });
        img.addEventListener("error", onLoaded, { once: true });
      }
    });
  };

  onMounted(() => {
    nextTick(processImages);
  });

  // Re-process when content changes (e.g. async auth fallback)
  const reprocess = () => {
    nextTick(processImages);
  };

  return { reprocess };
};
