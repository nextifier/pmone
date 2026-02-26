import { computed, ref, watch } from "vue";

export function useOrderCart(brandEventId) {
  const cartItems = ref([]);

  const storageKey = computed(
    () => `order-cart-${brandEventId.value || brandEventId}`,
  );

  function loadCart() {
    try {
      const stored = localStorage.getItem(storageKey.value);
      if (stored) cartItems.value = JSON.parse(stored);
    } catch {
      cartItems.value = [];
    }
  }

  function saveCart() {
    localStorage.setItem(storageKey.value, JSON.stringify(cartItems.value));
  }

  watch(cartItems, saveCart, { deep: true });

  function addItem(product, quantity = 1) {
    const existing = cartItems.value.find(
      (i) => i.event_product_id === product.id,
    );

    if (existing) {
      existing.quantity += quantity;
    } else {
      cartItems.value.push({
        event_product_id: product.id,
        name: product.name,
        category: product.category || "",
        price: parseFloat(product.price),
        unit: product.unit || "unit",
        product_image: product.product_image || null,
        quantity,
        notes: "",
      });
    }
  }

  function removeItem(productId) {
    cartItems.value = cartItems.value.filter(
      (i) => i.event_product_id !== productId,
    );
  }

  function updateQuantity(productId, quantity) {
    const item = cartItems.value.find((i) => i.event_product_id === productId);

    if (item) {
      if (quantity <= 0) {
        removeItem(productId);
      } else {
        item.quantity = quantity;
      }
    }
  }

  function updateItemNotes(productId, notes) {
    const item = cartItems.value.find((i) => i.event_product_id === productId);

    if (item) {
      item.notes = notes;
    }
  }

  function clearCart() {
    cartItems.value = [];
    localStorage.removeItem(storageKey.value);
  }

  const itemCount = computed(() =>
    cartItems.value.reduce((sum, i) => sum + i.quantity, 0),
  );

  const subtotal = computed(() =>
    cartItems.value.reduce((sum, i) => sum + i.price * i.quantity, 0),
  );

  function taxAmount(taxRate = 11) {
    return Math.round((subtotal.value * taxRate) / 100);
  }

  function total(taxRate = 11) {
    return subtotal.value + taxAmount(taxRate);
  }

  if (import.meta.client) {
    loadCart();
  }

  return {
    cartItems,
    addItem,
    removeItem,
    updateQuantity,
    updateItemNotes,
    clearCart,
    itemCount,
    subtotal,
    taxAmount,
    total,
  };
}
