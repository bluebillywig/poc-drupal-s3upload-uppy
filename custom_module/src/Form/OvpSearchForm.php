<?php

namespace Drupal\s3_uppy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\s3_uppy\Service\BlueBillywigOvpClient;
use Drupal\media\Entity\Media;

/**
 * Form for searching Blue Billywig OVP MediaClips.
 */
class OvpSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ovp_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Search query input
    $form['query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#placeholder' => $this->t('Search videos by title, description, or tags...'),
      '#default_value' => $form_state->getValue('query', ''),
    ];

    // Sort options
    $form['sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => [
        'createddate desc' => $this->t('Newest first'),
        'createddate asc' => $this->t('Oldest first'),
        'title asc' => $this->t('Title A-Z'),
        'title desc' => $this->t('Title Z-A'),
      ],
      '#default_value' => $form_state->getValue('sort', 'createddate desc'),
    ];

    // Filter queries (advanced options)
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Filters'),
      '#open' => FALSE,
    ];

    $form['advanced']['status_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        '' => $this->t('- All -'),
        'status:published' => $this->t('Published'),
        'status:unpublished' => $this->t('Unpublished'),
      ],
      '#default_value' => $form_state->getValue('status_filter', ''),
    ];

    // Search button
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
    ];

    // Display search results if form has been submitted
    if ($form_state->has('search_results')) {
      $results = $form_state->get('search_results');

      $form['results'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['ovp-search-results']],
      ];

      $form['results']['info'] = [
        '#markup' => '<div class="search-info">' .
          $this->t('Found @count results', ['@count' => $results['totalResults']]) .
          '</div>',
      ];

      if (!empty($results['items'])) {
        $form['results']['clips'] = [
          '#type' => 'tableselect',
          '#header' => [
            'thumbnail' => $this->t('Thumbnail'),
            'title' => $this->t('Title'),
            'id' => $this->t('MediaClip ID'),
            'created' => $this->t('Created'),
          ],
          '#options' => $this->buildResultsOptions($results['items']),
          '#empty' => $this->t('No results found.'),
        ];

        $form['results']['import'] = [
          '#type' => 'submit',
          '#value' => $this->t('Import Selected Videos'),
          '#submit' => ['::importSubmit'],
          '#button_type' => 'primary',
        ];
      }
      else {
        $form['results']['empty'] = [
          '#markup' => '<p>' . $this->t('No videos found matching your search criteria.') . '</p>',
        ];
      }
    }

    // Add CSS
    $form['#attached']['library'][] = 's3_uppy/ovp-search';

    return $form;
  }

  /**
   * Build options array for tableselect from search results.
   *
   * @param array $items
   *   Array of MediaClip items from OVP API.
   *
   * @return array
   *   Formatted options for tableselect.
   */
  protected function buildResultsOptions(array $items) {
    $options = [];

    foreach ($items as $item) {
      $id = $item['id'] ?? '';
      if (empty($id)) {
        continue;
      }

      // Check if this mediaclip already exists in Drupal
      $existing = $this->checkExistingMedia($id);
      $title_suffix = $existing ? ' <em>(Already imported)</em>' : '';

      $thumbnail = '';
      if (!empty($item['assets']['image'] ?? NULL)) {
        $thumbnail_url = $item['assets']['image'];
        $thumbnail = '<img src="' . htmlspecialchars($thumbnail_url) . '" alt="' . htmlspecialchars($item['title'] ?? '') . '" style="max-width: 120px; height: auto;" />';
      }

      $created = '';
      if (!empty($item['createddate'])) {
        $created = date('Y-m-d H:i', $item['createddate']);
      }

      $options[$id] = [
        'thumbnail' => ['data' => ['#markup' => $thumbnail]],
        'title' => ['data' => ['#markup' => htmlspecialchars($item['title'] ?? 'Untitled') . $title_suffix]],
        'id' => $id,
        'created' => $created,
        '#disabled' => $existing,
      ];
    }

    return $options;
  }

  /**
   * Check if a MediaClip already exists in Drupal.
   *
   * @param string $mediaclip_id
   *   The MediaClip ID to check.
   *
   * @return bool
   *   TRUE if media entity exists, FALSE otherwise.
   */
  protected function checkExistingMedia($mediaclip_id) {
    $query = \Drupal::entityTypeManager()
      ->getStorage('media')
      ->getQuery()
      ->condition('bundle', 'bluebillywig_video')
      ->condition('field_mediaclip_id', $mediaclip_id)
      ->accessCheck(FALSE)
      ->range(0, 1);

    $result = $query->execute();
    return !empty($result);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $ovp_client = new BlueBillywigOvpClient();

      $query = $form_state->getValue('query', '');
      $sort = $form_state->getValue('sort', 'createddate desc');

      // Build filter queries
      $filter_queries = [];
      $status_filter = $form_state->getValue('status_filter', '');
      if (!empty($status_filter)) {
        $filter_queries[] = $status_filter;
      }

      // Perform search
      $results = $ovp_client->searchMediaClips(
        $query,
        $filter_queries,
        $sort,
        20, // limit
        0   // offset
      );

      // Store results in form state for display
      $form_state->set('search_results', $results);
      $form_state->setRebuild(TRUE);

      $this->messenger()->addStatus(
        $this->t('Found @count videos.', ['@count' => $results['totalResults']])
      );
    }
    catch (\Exception $e) {
      $this->messenger()->addError(
        $this->t('Error searching OVP: @error', ['@error' => $e->getMessage()])
      );
    }
  }

  /**
   * Submit handler for importing selected videos.
   */
  public function importSubmit(array &$form, FormStateInterface $form_state) {
    $selected = array_filter($form_state->getValue('clips', []));

    if (empty($selected)) {
      $this->messenger()->addWarning($this->t('Please select at least one video to import.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $results = $form_state->get('search_results');
    $imported_count = 0;
    $skipped_count = 0;

    foreach ($selected as $mediaclip_id) {
      // Find the full item data
      $item = NULL;
      foreach ($results['items'] as $result_item) {
        if ($result_item['id'] == $mediaclip_id) {
          $item = $result_item;
          break;
        }
      }

      if (!$item) {
        continue;
      }

      // Check if already exists
      if ($this->checkExistingMedia($mediaclip_id)) {
        $skipped_count++;
        continue;
      }

      try {
        // Create media entity
        $media = Media::create([
          'bundle' => 'bluebillywig_video',
          'name' => $item['title'] ?? 'Video ' . $mediaclip_id,
          'field_mediaclip_id' => $mediaclip_id,
          'uid' => \Drupal::currentUser()->id(),
          'status' => 1,
        ]);
        $media->save();

        $imported_count++;
      }
      catch (\Exception $e) {
        $this->messenger()->addError(
          $this->t('Error importing video @id: @error', [
            '@id' => $mediaclip_id,
            '@error' => $e->getMessage(),
          ])
        );
      }
    }

    if ($imported_count > 0) {
      $this->messenger()->addStatus(
        $this->formatPlural(
          $imported_count,
          'Successfully imported 1 video.',
          'Successfully imported @count videos.'
        )
      );
    }

    if ($skipped_count > 0) {
      $this->messenger()->addWarning(
        $this->formatPlural(
          $skipped_count,
          'Skipped 1 video that was already imported.',
          'Skipped @count videos that were already imported.'
        )
      );
    }

    // Rebuild form to refresh the already-imported status
    $form_state->setRebuild(TRUE);
  }

}
