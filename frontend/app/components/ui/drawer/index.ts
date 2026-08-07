export { DrawerPortal, DrawerProvider, DrawerIndent, DrawerIndentBackground, DrawerViewport, injectDrawerRootContext } from "./core";

export { default as Drawer, type DrawerSide } from "./Drawer.vue"
export { default as DrawerBar } from "./DrawerBar.vue"
export { default as DrawerClose } from "./DrawerClose.vue"
export { default as DrawerContent } from "./DrawerContent.vue"
export { default as DrawerDescription } from "./DrawerDescription.vue"
export { default as DrawerFooter } from "./DrawerFooter.vue"
export { default as DrawerHeader } from "./DrawerHeader.vue"
export { default as DrawerOverlay } from "./DrawerOverlay.vue"
export { default as DrawerPanel } from "./DrawerPanel.vue"
export { default as DrawerSwipeArea } from "./DrawerSwipeArea.vue"
export { default as DrawerTitle } from "./DrawerTitle.vue"
export { default as DrawerTrigger } from "./DrawerTrigger.vue"

/** Menu primitives, mirroring coss `drawer.tsx`. */
export { default as DrawerMenu } from "./DrawerMenu.vue"
export { default as DrawerMenuCheckboxItem } from "./DrawerMenuCheckboxItem.vue"
export { default as DrawerMenuGroup } from "./DrawerMenuGroup.vue"
export { default as DrawerMenuGroupLabel } from "./DrawerMenuGroupLabel.vue"
export { default as DrawerMenuItem } from "./DrawerMenuItem.vue"
export { default as DrawerMenuRadioGroup } from "./DrawerMenuRadioGroup.vue"
export { default as DrawerMenuRadioItem } from "./DrawerMenuRadioItem.vue"
export { default as DrawerMenuSeparator } from "./DrawerMenuSeparator.vue"
export { default as DrawerMenuTrigger } from "./DrawerMenuTrigger.vue"

/** Base UI aliases, so markup ported from Base UI/coss reads the same. */
export { default as DrawerBackdrop } from "./DrawerOverlay.vue"
export { default as DrawerPopup } from "./DrawerContent.vue"

export { useDrawerSwipeArbiter } from "./useDrawerSwipeArbiter"
export { readKeyboardInset, useDrawerVirtualKeyboard } from "./useDrawerVirtualKeyboard"
export { useTransitionStatus } from "./useTransitionStatus"
