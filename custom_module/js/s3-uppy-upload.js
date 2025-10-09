(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.s3UppyUpload = {
    attach: function (context, settings) {
      // Only initialize once
      if (context !== document) {
        return;
      }

      const uppySettings = drupalSettings.s3Uppy || {};

      // Initialize Uppy
      const uppy = new Uppy.Core({
        autoProceed: false,
        allowMultipleUploadBatches: true,
        restrictions: {
          maxFileSize: uppySettings.maxFileSize || 2147483648, // 2GB default
          allowedFileTypes: uppySettings.allowedFileTypes || ['.mp4', '.mov', '.avi', '.webm', '.ogg'],
        },
      });

      // Add Dashboard UI
      uppy.use(Uppy.Dashboard, {
        inline: true,
        target: '#uppy-dashboard',
        showProgressDetails: true,
        proudlyDisplayPoweredByUppy: false,
        note: 'Video files only, up to 2GB',
        height: 470,
      });

      // Add AWS S3 upload with custom URL generator
      uppy.use(Uppy.AwsS3, {
        async getUploadParameters(file) {
          // Request presigned URL from Drupal backend
          const response = await fetch(uppySettings.generateUrlEndpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              filename: file.name,
              filetype: file.type,
            }),
          });

          if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to get upload URL');
          }

          const data = await response.json();

          return {
            method: 'PUT',
            url: data.url,
            headers: {
              'Content-Type': file.type,
            },
            // Store the S3 key for later use
            fields: {
              key: data.key,
            },
          };
        },
      });

      // Handle successful uploads
      uppy.on('upload-success', (file, response) => {
        console.log('Upload successful:', file.name);

        // Notify backend of successful upload
        fetch(uppySettings.uploadCompleteEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            filename: file.name,
            key: file.meta.key,
            size: file.size,
            type: file.type,
          }),
        })
        .then(res => res.json())
        .then(data => {
          console.log('Upload completion notification sent:', data);
          updateStatus(`✓ ${file.name} uploaded successfully`, 'success');
        })
        .catch(err => {
          console.error('Error notifying backend:', err);
        });
      });

      // Handle errors
      uppy.on('upload-error', (file, error, response) => {
        console.error('Upload error:', file.name, error);
        updateStatus(`✗ Error uploading ${file.name}: ${error.message}`, 'error');
      });

      // Handle complete uploads
      uppy.on('complete', (result) => {
        console.log('Upload complete:', result);
        if (result.successful.length > 0) {
          const message = `${result.successful.length} file(s) uploaded successfully!`;
          updateStatus(message, 'success');
        }
        if (result.failed.length > 0) {
          const message = `${result.failed.length} file(s) failed to upload.`;
          updateStatus(message, 'error');
        }
      });

      // Helper function to update status
      function updateStatus(message, type) {
        const statusDiv = document.getElementById('upload-status');
        if (statusDiv) {
          const messageDiv = document.createElement('div');
          messageDiv.className = `upload-message upload-message--${type}`;
          messageDiv.textContent = message;
          statusDiv.appendChild(messageDiv);

          // Auto-remove after 5 seconds
          setTimeout(() => {
            messageDiv.remove();
          }, 5000);
        }
      }
    },
  };

})(Drupal, drupalSettings);
