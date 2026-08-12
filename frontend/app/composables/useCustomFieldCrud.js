import { toast } from "vue-sonner";

/**
 * The network half of a custom-field manager: list, save, delete and reorder.
 *
 * Parameterised rather than shared wholesale because the four contexts really
 * do differ on the wire - ulid vs integer id, PUT vs POST for reorder, and a
 * different payload per FormRequest. Everything around those differences was
 * duplicated three times over.
 *
 * @param {object} options
 * @param {import("vue").Ref<string>|import("vue").ComputedRef<string>} options.baseUrl
 * @param {"id"|"ulid"} [options.idKey] Which key the write routes take.
 * @param {"PUT"|"POST"} [options.reorderMethod] Event fields use POST.
 * @param {() => object} [options.reorderExtra] Extra body keys for reorder -
 *   the event routes want `context` alongside `orders`.
 * @param {string} [options.noun] Used in toasts: "field", "custom field".
 * @param {import("vue").Ref<Array>} [options.externalFields] Hand in a list this
 *   composable should drive instead of owning one. The event contexts load
 *   theirs through `useLazySanctumFetch` so the panel renders on the server.
 * @param {() => Promise<unknown>} [options.externalRefresh] Paired with the
 *   above: what to call after a write.
 * @param {import("vue").Ref<boolean>} [options.externalLoading]
 */
export function useCustomFieldCrud(options) {
  const {
    baseUrl,
    idKey = "id",
    reorderMethod = "PUT",
    reorderExtra = () => ({}),
    noun = "field",
    externalFields = null,
    externalRefresh = null,
    externalLoading = null,
  } = options;

  const client = useSanctumClient();

  const fields = externalFields ?? ref([]);
  const loading = externalLoading ?? ref(true);
  const saving = ref(false);

  const deleteDialogOpen = ref(false);
  const deletingItem = ref(null);
  const deleting = ref(false);

  const capitalised = noun.charAt(0).toUpperCase() + noun.slice(1);

  async function fetchFields() {
    if (externalRefresh) {
      await externalRefresh();
      return;
    }

    loading.value = true;
    try {
      const res = await client(toValue(baseUrl));
      fields.value = res.data ?? [];
    } catch (e) {
      console.error(`Failed to load ${noun}s:`, e);
    } finally {
      loading.value = false;
    }
  }

  /**
   * @param {object|null} editing The record being edited, or null to create.
   * @param {object} body
   * @param {(error: unknown) => boolean} onError Return true when the error was
   *   already surfaced inline (a 422), so no toast fires on top of it.
   */
  async function saveField(editing, body, onError) {
    saving.value = true;
    try {
      const url = editing ? `${toValue(baseUrl)}/${editing[idKey]}` : toValue(baseUrl);
      await client(url, { method: editing ? "PUT" : "POST", body });
      toast.success(editing ? `${capitalised} updated` : `${capitalised} created`);
      await fetchFields();
      return true;
    } catch (error) {
      if (!onError?.(error)) {
        toast.error(
          error?.data?.message ||
            error?.response?._data?.message ||
            `Failed to save ${noun}`
        );
      }
      return false;
    } finally {
      saving.value = false;
    }
  }

  const confirmDelete = (field) => {
    deletingItem.value = field;
    deleteDialogOpen.value = true;
  };

  async function handleDelete() {
    if (!deletingItem.value) return;
    deleting.value = true;
    try {
      await client(`${toValue(baseUrl)}/${deletingItem.value[idKey]}`, { method: "DELETE" });
      toast.success(`${capitalised} deleted`);
      deleteDialogOpen.value = false;
      await fetchFields();
    } catch (error) {
      toast.error(error?.data?.message || `Failed to delete ${noun}`);
    } finally {
      deleting.value = false;
    }
  }

  /**
   * Hand this to `useSortableList`'s `onReorder`. Patches `order_column`
   * locally on success so the list does not need a refetch to look settled.
   */
  async function reorder() {
    const orders = fields.value.map((field, index) => ({ id: field.id, order: index + 1 }));
    try {
      await client(`${toValue(baseUrl)}/reorder`, {
        method: reorderMethod,
        body: { ...reorderExtra(), orders },
      });
      fields.value.forEach((field, index) => {
        field.order_column = index + 1;
      });
    } catch {
      toast.error(`Failed to reorder ${noun}s`);
      await fetchFields();
    }
  }

  return {
    fields,
    loading,
    saving,
    deleteDialogOpen,
    deletingItem,
    deleting,
    fetchFields,
    saveField,
    confirmDelete,
    handleDelete,
    reorder,
  };
}
