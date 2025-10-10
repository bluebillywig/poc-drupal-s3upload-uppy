(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.s3UppyUpload = {
    attach: function (context, settings) {
      const uppySettings = drupalSettings.s3Uppy || {};

      const ovpInfoDiv = document.getElementById('ovp-info');
      const uploadIdentifierField = document.getElementById('upload-identifier-field');
      let ovpData = null;
      let fetchingIdentifier = false;

      // ✅ NEW v3 syntax
      const {Uppy, Dashboard, AwsS3} = window.Uppy;
      const uppy = new Uppy({
        autoProceed: false,
        allowMultipleUploadBatches: true,
        restrictions: {
          maxFileSize: uppySettings.maxFileSize || 21474836480,
          allowedFileTypes: uppySettings.allowedFileTypes || ['.mp4', '.mov', '.avi', '.webm', '.ogg', '.mxf', '.mpg', '.mpeg', '.mkv'],
        },
      });

      // ✅ NEW v3 plugin syntax
      uppy.use(Dashboard, {
        inline: true,
        target: '#uppy-dashboard',
        showProgressDetails: true,
        proudlyDisplayPoweredByUppy: false,
        note: 'Video files only, up to 20GB',
        height: 470,
      });

      // Fetch upload identifier when file is added
      uppy.on('file-added', async (file) => {
        if (fetchingIdentifier || ovpData) {
          return; // Already fetching or have identifier
        }

        fetchingIdentifier = true;
        ovpInfoDiv.innerHTML = '<em>Registering upload with Blue Billywig OVP...</em>';

        try {
          const response = await fetch(uppySettings.generateUploadIdentifierEndpoint, {
            method: 'POST',
          });

          const data = await response.json();

          if (data.error) {
            ovpInfoDiv.innerHTML = '<strong style="color: red;">Error: ' + data.error + '</strong>';
            uppy.removeFile(file.id);
            return;
          }

          ovpData = data;
          uploadIdentifierField.value = data.uploadidentifier;
          ovpInfoDiv.innerHTML = '<strong>Upload Identifier:</strong> ' + data.uploadidentifier +
                                 ' | <strong>MediaClip ID:</strong> ' + data.mediaclipId +
                                 ' | <strong>GUID:</strong> ' + data.guid;
        } catch (error) {
          ovpInfoDiv.innerHTML = '<strong style="color: red;">Failed to register upload: ' + error.message + '</strong>';
          uppy.removeFile(file.id);
        } finally {
          fetchingIdentifier = false;
        }
      });

      // ✅ NEW v3 plugin syntax
      uppy.use(AwsS3, {
        async getUploadParameters(file) {
          // Wait for upload identifier if still fetching
          while (fetchingIdentifier) {
            await new Promise(resolve => setTimeout(resolve, 100));
          }

          const uploadIdentifier = uploadIdentifierField ? uploadIdentifierField.value : '';

          if (!uploadIdentifier) {
            throw new Error('Upload identifier not available. Please try again.');
          }

          const response = await fetch(uppySettings.generateUrlEndpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              filename: file.name,
              filetype: file.type,
              uploadidentifier: uploadIdentifier,
            }),
          });

          if (!response.ok) {
            const error = await response.json();
            console.error('Server error response:', error);
            const errorMessage = error.error || 'Failed to get upload URL';
            const debugInfo = error.received_data ? ` (Received: ${JSON.stringify(error.received_data)})` : '';
            throw new Error(errorMessage + debugInfo);
          }

          const data = await response.json();

          return {
            method: 'PUT',
            url: data.url,
            headers: {
              'Content-Type': file.type,
              'x-amz-meta-uploadidentifier': uploadIdentifier,
            },
            fields: {
              key: data.key,
            },
          };
        },
      });

      // Event handlers (should be the same)
      uppy.on('upload-success', (file, response) => {
        // ... your code
      });
    }
  };
})(Drupal, drupalSettings);
