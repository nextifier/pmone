export const useUiStore = defineStore("ui", {
  state: () => ({
    isEmbedVideoDialogOpen: false,
    embedVideoSrc: null,

    isInquiryDialogOpen: false,
    isEventGalleryDialogOpen: false,
  }),
  actions: {
    openEmbedVideoDialog(src) {
      this.embedVideoSrc = src;
      this.isEmbedVideoDialogOpen = true;
    },
    closeEmbedVideoDialog() {
      this.isEmbedVideoDialogOpen = false;
      this.embedVideoSrc = null;
    },

    openInquiryDialog() {
      this.isInquiryDialogOpen = true;
    },
    closeInquiryDialog() {
      this.isInquiryDialogOpen = false;
    },

    openEventGalleryDialog() {
      this.isEventGalleryDialogOpen = true;
    },
    closeEventGalleryDialog() {
      this.isEventGalleryDialogOpen = false;
    },
  },
});
