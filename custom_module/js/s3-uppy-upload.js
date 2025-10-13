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
          // Get title and description from form fields
          const titleField = document.getElementById('clip-title-field');
          const descriptionField = document.getElementById('clip-description-field');
          const title = titleField ? titleField.value : '';
          const description = descriptionField ? descriptionField.value : '';

          const response = await fetch(uppySettings.generateUploadIdentifierEndpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              filename: file.name,
              title: title,
              description: description,
            }),
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

      // Handle upload success - create media entity
      uppy.on('complete', async (result) => {
        if (result.successful.length > 0 && ovpData && ovpData.mediaclipId) {
          ovpInfoDiv.innerHTML += '<br><em>Creating media entity...</em>';

          try {
            const file = result.successful[0];
            const titleField = document.getElementById('clip-title-field');
            const title = titleField ? titleField.value : '';

            const response = await fetch(uppySettings.uploadCompleteEndpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                key: file.meta.key,
                filename: file.name,
                mediaclipId: ovpData.mediaclipId,
                title: title,
              }),
            });

            const data = await response.json();

            if (data.media_id) {
              // Clear the info div and show success message
              ovpInfoDiv.innerHTML = '<div style="padding: 20px; background: #d4edda; border: 2px solid #28a745; border-radius: 5px; margin: 20px 0;">' +
                                      '<h3 style="color: #155724; margin-top: 0;">✓ Video Uploaded Successfully!</h3>' +
                                      '<p><strong>Media ID:</strong> ' + data.media_id + '</p>' +
                                      '<p><strong>MediaClip ID:</strong> ' + ovpData.mediaclipId + '</p>' +
                                      '<p style="margin-bottom: 0;"><strong>What\'s next?</strong></p>' +
                                      '<ul style="margin-top: 5px;">' +
                                      '<li><a href="/media/' + data.media_id + '/edit" target="_blank" style="font-weight: bold;">Edit this video</a> - Change title, description, etc.</li>' +
                                      '<li><a href="/media/' + data.media_id + '" target="_blank" style="font-weight: bold;">View this video</a> - See how it renders</li>' +
                                      '<li><a href="/admin/content/media" target="_blank" style="font-weight: bold;">Browse all media</a> - View all uploaded videos</li>' +
                                      '<li>Add this video to content using the media library</li>' +
                                      '</ul>' +
                                      '<button onclick="location.reload()" style="margin-top: 10px; padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">Upload Another Video</button>' +
                                      '</div>';

              // Remove uppy dashboard
              document.getElementById('uppy-dashboard').style.display = 'none';
            } else {
              ovpInfoDiv.innerHTML += '<br><strong style="color: orange;">⚠ Upload completed but media entity was not created.</strong>';
            }

          } catch (error) {
            ovpInfoDiv.innerHTML += '<br><strong style="color: red;">Error: ' + error.message + '</strong>';
          }
        }
      });
    }
  };
})(Drupal, drupalSettings);
