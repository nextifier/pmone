import { toast } from "vue-sonner";

/**
 * Shared composable for Users and Exhibitors table pages (index + trash).
 *
 * @param {Object} options
 * @param {string} options.fetchKey - Unique key for useLazySanctumFetch (e.g. 'users-list', 'exhibitors-list')
 * @param {string} [options.apiEndpoint='/api/users'] - API endpoint for fetching data
 * @param {Object} options.extraParams - Extra query params sent on every request (e.g. { exclude_role: 'exhibitor', role: 'exhibitor', with_brands_count: '1' })
 * @param {Object} options.filterMapping - Column-to-API-param mapping for export (e.g. { name: 'filter.search', status: 'filter.status' })
 * @param {Array} [options.defaultSorting] - Default sorting (e.g. [{ id: 'deleted_at', desc: true }])
 * @param {boolean} [options.isTrash=false] - Enable trash mode (restore + force delete handlers)
 * @param {string} [options.entityLabel='Users'] - Label for toast messages and export filename
 */
export async function useUserTable({
  fetchKey,
  apiEndpoint = "/api/users",
  extraParams = {},
  filterMapping = {},
  defaultSorting,
  isTrash = false,
  entityLabel = "Users",
}) {
  const { getRefreshSignal, clearRefreshSignal } = useDataRefresh();
  const { $dayjs } = useNuxtApp();

  // Table state
  const columnFilters = ref([]);
  const pagination = ref({ pageIndex: 0, pageSize: 20 });
  const sorting = ref(defaultSorting || [{ id: "last_seen", desc: true }]);
  const tableRef = ref();

  // Client-only mode
  const clientOnly = ref(true);

  // Late-bound refresh function (assigned after useLazySanctumFetch)
  let _fetchUsers = null;

  // Register lifecycle hooks BEFORE any await (required for async setup)
  onActivated(async () => {
    const refreshSignal = getRefreshSignal(fetchKey);
    if (refreshSignal > 0 && _fetchUsers) {
      await _fetchUsers();
      clearRefreshSignal(fetchKey);
    }
  });

  // Default filter mapping (set a key to null in filterMapping to exclude it)
  const mergedMapping = {
    name: "filter.search",
    status: "filter.status",
    roles: "filter.role",
    email_verified_at: "filter.verified",
    ...filterMapping,
  };
  const defaultFilterMapping = Object.fromEntries(
    Object.entries(mergedMapping).filter(([, v]) => v !== null)
  );

  // Build query params
  const buildQueryParams = () => {
    const params = new URLSearchParams();

    // Add extra params (exclude_role, role, with_brands_count, etc.)
    Object.entries(extraParams).forEach(([key, value]) => {
      params.append(key, value);
    });

    if (clientOnly.value) {
      params.append("client_only", "true");
    } else {
      params.append("page", pagination.value.pageIndex + 1);
      params.append("per_page", pagination.value.pageSize);

      Object.entries(defaultFilterMapping).forEach(([columnId, paramKey]) => {
        const filter = columnFilters.value.find((f) => f.id === columnId);
        if (filter?.value) {
          const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
          params.append(paramKey, value);
        }
      });

      const sortField = sorting.value[0]?.id || "created_at";
      const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
      params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);
    }

    return params.toString();
  };

  // Fetch users
  const {
    data: usersResponse,
    pending,
    error,
    refresh: fetchUsers,
  } = await useLazySanctumFetch(() => `${apiEndpoint}?${buildQueryParams()}`, {
    key: fetchKey,
    watch: false,
  });

  const data = computed(() => usersResponse.value?.data || []);
  const meta = computed(
    () => usersResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 10, total: 0 }
  );

  // Watch for changes (server-side mode only)
  watch(
    [columnFilters, sorting, pagination],
    () => {
      if (!clientOnly.value) {
        fetchUsers();
      }
    },
    { deep: true }
  );

  // Update handlers
  const onPaginationUpdate = (newValue) => {
    pagination.value.pageIndex = newValue.pageIndex;
    pagination.value.pageSize = newValue.pageSize;
  };

  const onSortingUpdate = (newValue) => {
    sorting.value = newValue;
  };

  const onColumnFiltersUpdate = (newValue) => {
    columnFilters.value = newValue;
  };

  // Bind the fetch function for onActivated (registered before await)
  _fetchUsers = fetchUsers;

  const refresh = fetchUsers;

  // Toggle status
  const handleToggleStatus = async (user) => {
    const newStatus = user.status === "active" ? "inactive" : "active";
    const originalStatus = user.status;

    user.status = newStatus;

    try {
      const client = useSanctumClient();
      const response = await client(`/api/users/${user.username}`, {
        method: "PUT",
        body: { status: newStatus },
      });

      if (response.data) {
        const updatedUser = data.value.find((u) => u.id === user.id);
        if (updatedUser) {
          updatedUser.status = response.data.status;
        }
      }

      toast.success(`User ${newStatus === "active" ? "activated" : "deactivated"} successfully`);
    } catch (error) {
      user.status = originalStatus;
      console.error("Failed to update user status:", error);
      toast.error("Failed to update status", {
        description: error?.data?.message || error?.message || "An error occurred",
      });
    }
  };

  // Table ref helpers
  const hasSelectedRows = computed(() => {
    return tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0;
  });

  const clearSelection = () => {
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
  };

  // Filter helpers
  const getFilterValue = (columnId) => {
    if (clientOnly.value && tableRef.value?.table) {
      return tableRef.value.table.getColumn(columnId)?.getFilterValue() ?? [];
    }
    return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
  };

  const handleFilterChange = (columnId, { checked, value }) => {
    if (clientOnly.value && tableRef.value?.table) {
      const column = tableRef.value.table.getColumn(columnId);
      if (!column) return;

      const current = column.getFilterValue() ?? [];
      const updated = checked ? [...current, value] : current.filter((item) => item !== value);

      column.setFilterValue(updated.length > 0 ? updated : undefined);
      tableRef.value.table.setPageIndex(0);
    } else {
      const current = getFilterValue(columnId);
      const updated = checked ? [...current, value] : current.filter((item) => item !== value);

      const existingIndex = columnFilters.value.findIndex((f) => f.id === columnId);
      if (updated.length) {
        if (existingIndex >= 0) {
          columnFilters.value[existingIndex].value = updated;
        } else {
          columnFilters.value.push({ id: columnId, value: updated });
        }
      } else {
        if (existingIndex >= 0) {
          columnFilters.value.splice(existingIndex, 1);
        }
      }
      pagination.value.pageIndex = 0;
    }
  };

  // Export handler
  const exportPending = ref(false);
  const handleExport = async () => {
    try {
      exportPending.value = true;

      const params = new URLSearchParams();

      // Add extra params for export too
      Object.entries(extraParams).forEach(([key, value]) => {
        params.append(key, value);
      });

      let currentFilters = {};
      let currentSorting = [];

      if (clientOnly.value && tableRef.value?.table) {
        Object.keys(defaultFilterMapping).forEach((columnId) => {
          const filterVal = tableRef.value.table.getColumn(columnId)?.getFilterValue();
          if (filterVal) currentFilters[columnId] = filterVal;
        });
        currentSorting = tableRef.value.table.getState().sorting;
      } else {
        columnFilters.value.forEach((filter) => {
          currentFilters[filter.id] = filter.value;
        });
        currentSorting = sorting.value;
      }

      Object.entries(currentFilters).forEach(([columnId, value]) => {
        const paramKey = defaultFilterMapping[columnId];
        if (paramKey && value) {
          const paramValue = Array.isArray(value) ? value.join(",") : value;
          params.append(paramKey, paramValue);
        }
      });

      const sortField = currentSorting[0]?.id || "created_at";
      const sortDirection = currentSorting[0]?.desc ? "desc" : "asc";
      params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

      const filenamePrefix = entityLabel.toLowerCase().replace(/\s+/g, "_");
      params.append("filename_prefix", filenamePrefix);

      const client = useSanctumClient();
      const response = await client(`/api/users/export?${params.toString()}`, {
        responseType: "blob",
      });

      const blob = new Blob([response], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `${filenamePrefix}_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);

      toast.success(`${entityLabel} exported successfully`);
    } catch (error) {
      console.error(`Failed to export ${entityLabel.toLowerCase()}:`, error);
      toast.error(`Failed to export ${entityLabel.toLowerCase()}`, {
        description: error?.data?.message || error?.message || "An error occurred",
      });
    } finally {
      exportPending.value = false;
    }
  };

  // Verify handlers
  const verifyDialogOpen = ref(false);
  const verifyPending = ref(false);
  const handleVerifyRows = async (selectedRows) => {
    const userIds = selectedRows.map((row) => row.original.id);
    try {
      verifyPending.value = true;
      const client = useSanctumClient();
      const response = await client("/api/users/verify/bulk", {
        method: "POST",
        body: { ids: userIds },
      });
      await refresh();
      verifyDialogOpen.value = false;
      if (tableRef.value?.table) {
        tableRef.value.table.resetRowSelection();
      }

      toast.success(response.message || "Users verified successfully", {
        description: `${response.verified_count} user(s) verified`,
      });
    } catch (error) {
      console.error("Failed to verify users:", error);
      toast.error("Failed to verify users", {
        description: error?.data?.message || error?.message || "An error occurred",
      });
    } finally {
      verifyPending.value = false;
    }
  };

  // Unverify handlers
  const unverifyDialogOpen = ref(false);
  const unverifyPending = ref(false);
  const handleUnverifyRows = async (selectedRows) => {
    const userIds = selectedRows.map((row) => row.original.id);
    try {
      unverifyPending.value = true;
      const client = useSanctumClient();
      const response = await client("/api/users/unverify/bulk", {
        method: "POST",
        body: { ids: userIds },
      });
      await refresh();
      unverifyDialogOpen.value = false;
      if (tableRef.value?.table) {
        tableRef.value.table.resetRowSelection();
      }

      toast.success(response.message || "Users unverified successfully", {
        description: `${response.unverified_count} user(s) unverified`,
      });
    } catch (error) {
      console.error("Failed to unverify users:", error);
      toast.error("Failed to unverify users", {
        description: error?.data?.message || error?.message || "An error occurred",
      });
    } finally {
      unverifyPending.value = false;
    }
  };

  // Delete handlers (soft delete for index, force delete for trash)
  const deleteDialogOpen = ref(false);
  const deletePending = ref(false);
  const handleDeleteRows = async (selectedRows) => {
    const userIds = selectedRows.map((row) => row.original.id);
    try {
      deletePending.value = true;
      const client = useSanctumClient();
      const endpoint = isTrash ? "/api/users/trash/bulk" : "/api/users/bulk";
      const response = await client(endpoint, {
        method: "DELETE",
        body: { ids: userIds },
      });
      await refresh();
      deleteDialogOpen.value = false;
      if (tableRef.value?.table) {
        tableRef.value.table.resetRowSelection();
      }

      const label = isTrash ? "permanently deleted" : "deleted";
      toast.success(response.message || `Users ${label} successfully`, {
        description:
          response.errors?.length > 0
            ? `${response.deleted_count} ${label}, ${response.errors.length} failed`
            : `${response.deleted_count} user(s) ${label}`,
      });
    } catch (error) {
      console.error("Failed to delete users:", error);
      toast.error("Failed to delete users", {
        description: error?.data?.message || error?.message || "An error occurred",
      });
    } finally {
      deletePending.value = false;
    }
  };

  // Restore handlers (trash mode only)
  const restoreDialogOpen = ref(false);
  const restorePending = ref(false);
  const handleRestoreRows = async (selectedRows) => {
    const userIds = selectedRows.map((row) => row.original.id);
    try {
      restorePending.value = true;
      const client = useSanctumClient();
      const response = await client("/api/users/trash/restore/bulk", {
        method: "POST",
        body: { ids: userIds },
      });
      await refresh();
      restoreDialogOpen.value = false;
      if (tableRef.value?.table) {
        tableRef.value.table.resetRowSelection();
      }

      toast.success(response.message || "Users restored successfully", {
        description:
          response.errors?.length > 0
            ? `${response.restored_count} restored, ${response.errors.length} failed`
            : `${response.restored_count} user(s) restored`,
      });
    } catch (error) {
      console.error("Failed to restore users:", error);
      toast.error("Failed to restore users", {
        description: error?.data?.message || error?.message || "An error occurred",
      });
    } finally {
      restorePending.value = false;
    }
  };

  return {
    // Table state
    columnFilters,
    pagination,
    sorting,
    tableRef,
    clientOnly,

    // Data
    data,
    meta,
    pending,
    error,
    refresh,

    // Update handlers
    onPaginationUpdate,
    onSortingUpdate,
    onColumnFiltersUpdate,

    // Actions
    handleToggleStatus,
    hasSelectedRows,
    clearSelection,

    // Filters
    getFilterValue,
    handleFilterChange,

    // Export
    exportPending,
    handleExport,

    // Bulk dialogs
    verifyDialogOpen,
    verifyPending,
    handleVerifyRows,
    unverifyDialogOpen,
    unverifyPending,
    handleUnverifyRows,
    deleteDialogOpen,
    deletePending,
    handleDeleteRows,

    // Trash-specific bulk dialogs
    restoreDialogOpen,
    restorePending,
    handleRestoreRows,

    // Utilities
    $dayjs,
  };
}
