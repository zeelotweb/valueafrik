import Uppy from '@uppy/core';
import XHRUpload from '@uppy/xhr-upload';

/**
 * A minimal single-image uploader: pick a file, it uploads immediately,
 * and the caller gets progress/success/error callbacks. Used for profile
 * avatar and cover photo, each driven by its own hidden file input.
 */
function createPhotoUploader({ endpoint, fieldName = 'photo', maxFileSize = null }) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const uppy = new Uppy({
        restrictions: {
            maxNumberOfFiles: 1,
            allowedFileTypes: ['image/*'],
            maxFileSize,
        },
        autoProceed: true,
    }).use(XHRUpload, {
        endpoint,
        fieldName,
        responseType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfToken },
    });

    return {
        upload(file) {
            uppy.getFiles().forEach((f) => uppy.removeFile(f.id));
            uppy.addFile({ name: file.name, type: file.type, data: file });
        },
        on: (event, handler) => uppy.on(event, handler),
    };
}

window.createPhotoUploader = createPhotoUploader;
