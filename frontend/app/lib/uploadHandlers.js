// Upload-handler factories that bridge the endpoint-agnostic
// CustomFieldFileUpload component to a concrete backend. The component only
// calls uploadHandler(file, onProgress) and revertHandler(folder); these keep
// the XHR-with-progress and endpoint knowledge outside the portable ui folder.

// XMLHttpRequest (not $fetch) so we can report upload progress to the caller.
const xhrUpload = (url, formData, onProgress, headers = {}) =>
  new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", url);

    for (const [key, value] of Object.entries(headers)) {
      xhr.setRequestHeader(key, value);
    }

    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        onProgress(Math.round((event.loaded / event.total) * 100));
      }
    };

    xhr.onload = () => {
      let data = null;
      try {
        data = JSON.parse(xhr.responseText);
      } catch {
        data = null;
      }
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(data);
      } else {
        reject({ data });
      }
    };

    xhr.onerror = () => reject(new Error("Upload failed"));
    xhr.send(formData);
  });

/**
 * Handlers for public Form Builder file fields (pmone.id /f/{slug}).
 * `apiUrl` is the API origin; `formSlug` scopes the tmp upload.
 */
export const createPublicFormUploadHandlers = (apiUrl, formSlug) => ({
  uploadHandler: async (file, onProgress) => {
    const formData = new FormData();
    formData.append("file", file);
    const response = await xhrUpload(
      `${apiUrl}/api/public/forms/${formSlug}/upload`,
      formData,
      onProgress
    );
    return { folder: response.folder, name: file.name, size: file.size };
  },
  revertHandler: async (folder) => {
    await $fetch(`${apiUrl}/api/public/forms/${formSlug}/upload`, {
      method: "DELETE",
      body: folder,
    });
  },
});

/**
 * Handlers for the admin/exhibitor tmp upload endpoint (/api/tmp-upload),
 * used by ops-document mini-form file fields inside the authenticated app.
 * `client` is a useSanctumClient()-style $fetch instance.
 */
export const createTmpUploadHandlers = (client) => ({
  uploadHandler: async (file, onProgress) => {
    onProgress(30);
    const formData = new FormData();
    formData.append("file", file);
    const response = await client("/api/tmp-upload", { method: "POST", body: formData });
    onProgress(100);
    // /api/tmp-upload returns a temp id/path; expose it as `folder` so the
    // component's value map stores it uniformly.
    return { folder: response.path ?? response.folder ?? response.id, name: file.name, size: file.size };
  },
  revertHandler: null,
});
