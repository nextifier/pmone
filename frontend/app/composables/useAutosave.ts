import { ref, computed, watch } from 'vue'
import { useDebounceFn, useLocalStorage } from '@vueuse/core'
import { toast } from 'vue-sonner'

export interface AutosaveData {
  post_id?: number | null
  title?: string
  excerpt?: string
  content?: string
  content_format?: string
  meta_title?: string
  meta_description?: string
  status?: string
  visibility?: string
  published_at?: string | null
  featured?: boolean
  settings?: Record<string, any>
  tmp_media?: Record<string, any>
  tags?: string[]
  authors?: Array<{ user_id: number; order: number }>
}

export interface AutosaveStatus {
  status: 'idle' | 'saving' | 'saved' | 'error'
  lastSavedAt: Date | null
  error: string | null
}

export function useAutosave(
  formData: Ref<AutosaveData>,
  options: {
    postId?: Ref<number | null>
    enabled?: Ref<boolean>
    debounceTime?: number
    localStorageKey?: string
  } = {}
) {
  const {
    postId = ref(null),
    enabled = ref(true),
    debounceTime = 2000,
    localStorageKey = 'post-autosave',
  } = options

  const client = useSanctumClient()

  // Autosave status
  const autosaveStatus = ref<AutosaveStatus>({
    status: 'idle',
    lastSavedAt: null,
    error: null,
  })

  // Local storage backup
  const localBackup = useLocalStorage<AutosaveData | null>(localStorageKey, null)

  // Computed properties
  const isSaving = computed(() => autosaveStatus.value.status === 'saving')
  const isSaved = computed(() => autosaveStatus.value.status === 'saved')
  const hasError = computed(() => autosaveStatus.value.status === 'error')
  const lastSavedAt = computed(() => autosaveStatus.value.lastSavedAt)

  // Autosave to server
  const saveToServer = async (data: AutosaveData) => {
    if (!enabled.value) return

    autosaveStatus.value.status = 'saving'
    autosaveStatus.value.error = null

    try {
      const payload = {
        ...data,
        post_id: postId.value,
      }

      const response = await client('/api/posts/autosave', {
        method: 'POST',
        body: payload,
      })

      autosaveStatus.value.status = 'saved'
      autosaveStatus.value.lastSavedAt = new Date()

      // Clear local backup after successful server save
      localBackup.value = null

      return response.data
    } catch (error: any) {
      console.error('Autosave failed:', error)
      autosaveStatus.value.status = 'error'
      autosaveStatus.value.error = error.message || 'Failed to autosave'

      // Keep in local storage as fallback
      localBackup.value = data

      toast.error('Failed to autosave. Changes saved locally.')
      throw error
    }
  }

  // Debounced autosave function
  const debouncedAutosave = useDebounceFn(async (data: AutosaveData) => {
    // Save to local storage immediately
    localBackup.value = data

    // Then save to server with debounce
    await saveToServer(data)
  }, debounceTime)

  // Retrieve autosave from server
  const retrieveAutosave = async () => {
    try {
      const params = new URLSearchParams()
      if (postId.value) {
        params.append('post_id', postId.value.toString())
      }

      const response = await client(`/api/posts/autosave?${params.toString()}`)
      return response.data
    } catch (error: any) {
      if (error.statusCode === 404) {
        // No autosave found, check local storage
        if (localBackup.value) {
          return localBackup.value
        }
        return null
      }
      console.error('Failed to retrieve autosave:', error)
      throw error
    }
  }

  // Discard autosave
  const discardAutosave = async () => {
    try {
      const params = new URLSearchParams()
      if (postId.value) {
        params.append('post_id', postId.value.toString())
      }

      await client(`/api/posts/autosave?${params.toString()}`, {
        method: 'DELETE',
      })

      // Clear local storage
      localBackup.value = null
      autosaveStatus.value.status = 'idle'
      autosaveStatus.value.lastSavedAt = null

      toast.success('Draft discarded')
    } catch (error: any) {
      if (error.statusCode !== 404) {
        console.error('Failed to discard autosave:', error)
        toast.error('Failed to discard draft')
        throw error
      }
    }
  }

  // Preview changes (for existing posts)
  const previewChanges = async (postSlug: string) => {
    try {
      const response = await client(`/api/posts/${postSlug}/preview`)
      return response.data
    } catch (error: any) {
      console.error('Failed to preview changes:', error)
      toast.error('Failed to load preview')
      throw error
    }
  }

  // Restore from local storage
  const restoreFromLocal = () => {
    return localBackup.value
  }

  // Clear local storage
  const clearLocal = () => {
    localBackup.value = null
  }

  // Watch form data and trigger autosave
  watch(
    formData,
    (newData) => {
      if (enabled.value && newData) {
        debouncedAutosave(newData)
      }
    },
    { deep: true }
  )

  return {
    // Status
    autosaveStatus,
    isSaving,
    isSaved,
    hasError,
    lastSavedAt,

    // Actions
    saveToServer,
    retrieveAutosave,
    discardAutosave,
    previewChanges,
    restoreFromLocal,
    clearLocal,

    // Local backup
    localBackup,
  }
}
