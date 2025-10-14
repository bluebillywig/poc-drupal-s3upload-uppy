<?php

namespace Drupal\s3_uppy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\s3_uppy\Service\BlueBillywigOvpClient;
use Drupal\media\Entity\Media;

/**
 * Form for searching Blue Billywig OVP MediaClips.
 */
class OvpSearchForm extends FormBase {

  /**
   * Items per page.
   */
  const ITEMS_PER_PAGE = 20;

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
    // Get current sort from form state or URL
    $current_sort = $form_state->getValue('sort') ?? \Drupal::request()->query->get('sort', 'createddate desc');

    // Get current page (0-indexed)
    $current_page = (int) ($form_state->getValue('page') ?? \Drupal::request()->query->get('page', 0));

    // Get query - default to *:* if empty
    $query = $form_state->getValue('query') ?? \Drupal::request()->query->get('query', '*:*');

    // Search query input
    $form['query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#placeholder' => $this->t('Search videos by title, description, or tags... (leave empty for all)'),
      '#default_value' => $query === '*:*' ? '' : $query,
    ];

    // Preset filter buttons
    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['filter-buttons']],
    ];

    $form['filters']['all'] = [
      '#type' => 'submit',
      '#value' => $this->t('All Videos'),
      '#name' => 'filter_all',
      '#submit' => ['::filterSubmit'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['filter-button']],
    ];

    $form['filters']['published'] = [
      '#type' => 'submit',
      '#value' => $this->t('Published'),
      '#name' => 'filter_published',
      '#submit' => ['::filterSubmit'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['filter-button']],
    ];

    $form['filters']['live'] = [
      '#type' => 'submit',
      '#value' => $this->t('Live'),
      '#name' => 'filter_live',
      '#submit' => ['::filterSubmit'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['filter-button']],
    ];

    $form['filters']['on_demand'] = [
      '#type' => 'submit',
      '#value' => $this->t('On Demand'),
      '#name' => 'filter_on_demand',
      '#submit' => ['::filterSubmit'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['filter-button']],
    ];

    // Hidden fields for maintaining state
    $form['sort'] = [
      '#type' => 'hidden',
      '#value' => $current_sort,
    ];

    $form['page'] = [
      '#type' => 'hidden',
      '#value' => $current_page,
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

    // Auto-search on page load if no form submission yet
    if (!$form_state->has('search_results') && !$form_state->isSubmitted()) {
      $this->doSearch($form, $form_state, TRUE);
    }

    // Display search results if form has been submitted
    if ($form_state->has('search_results')) {
      $results = $form_state->get('search_results');

      $form['results'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['ovp-search-results']],
      ];

      $total = $results['totalResults'];
      $showing_from = ($current_page * self::ITEMS_PER_PAGE) + 1;
      $showing_to = min(($current_page + 1) * self::ITEMS_PER_PAGE, $total);

      $form['results']['info'] = [
        '#markup' => '<div class="search-info">' .
          $this->t('Showing @from-@to of @total results', [
            '@from' => $showing_from,
            '@to' => $showing_to,
            '@total' => $total,
          ]) .
          '</div>',
      ];

      if (!empty($results['items'])) {
        // Build sortable headers
        $header = $this->buildSortableHeaders($current_sort);

        $form['results']['clips'] = [
          '#type' => 'tableselect',
          '#header' => $header,
          '#options' => $this->buildResultsOptions($results['items']),
          '#empty' => $this->t('No results found.'),
        ];

        // Add pager
        $form['results']['pager'] = $this->buildPager($current_page, $total);

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

    // Add CSS and JS
    $form['#attached']['library'][] = 's3_uppy/ovp-search';

    return $form;
  }

  /**
   * Build pager component.
   *
   * @param int $current_page
   *   Current page number (0-indexed).
   * @param int $total_results
   *   Total number of results.
   *
   * @return array
   *   Render array for pager.
   */
  protected function buildPager($current_page, $total_results) {
    $total_pages = (int) ceil($total_results / self::ITEMS_PER_PAGE);

    if ($total_pages <= 1) {
      return [];
    }

    $pager = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pager']],
    ];

    // Previous link
    if ($current_page > 0) {
      $pager['prev'] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => '« Previous',
        '#attributes' => [
          'href' => '#',
          'class' => ['pager__link', 'pager__link--prev'],
          'onclick' => "document.getElementById('edit-page').value='" . ($current_page - 1) . "'; document.getElementById('ovp-search-form').submit(); return false;",
        ],
      ];
    }

    // Page numbers
    $start_page = max(0, $current_page - 2);
    $end_page = min($total_pages - 1, $current_page + 2);

    // First page link
    if ($start_page > 0) {
      $pager['first'] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => '1',
        '#attributes' => [
          'href' => '#',
          'class' => ['pager__link'],
          'onclick' => "document.getElementById('edit-page').value='0'; document.getElementById('ovp-search-form').submit(); return false;",
        ],
      ];

      if ($start_page > 1) {
        $pager['ellipsis1'] = [
          '#markup' => '<span class="pager__ellipsis">...</span>',
        ];
      }
    }

    // Page number links
    for ($i = $start_page; $i <= $end_page; $i++) {
      $is_current = ($i == $current_page);

      if ($is_current) {
        $pager['page_' . $i] = [
          '#markup' => '<span class="pager__item pager__item--current">' . ($i + 1) . '</span>',
        ];
      }
      else {
        $pager['page_' . $i] = [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#value' => (string) ($i + 1),
          '#attributes' => [
            'href' => '#',
            'class' => ['pager__link'],
            'onclick' => "document.getElementById('edit-page').value='{$i}'; document.getElementById('ovp-search-form').submit(); return false;",
          ],
        ];
      }
    }

    // Last page link
    if ($end_page < $total_pages - 1) {
      if ($end_page < $total_pages - 2) {
        $pager['ellipsis2'] = [
          '#markup' => '<span class="pager__ellipsis">...</span>',
        ];
      }

      $pager['last'] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => (string) $total_pages,
        '#attributes' => [
          'href' => '#',
          'class' => ['pager__link'],
          'onclick' => "document.getElementById('edit-page').value='" . ($total_pages - 1) . "'; document.getElementById('ovp-search-form').submit(); return false;",
        ],
      ];
    }

    // Next link
    if ($current_page < $total_pages - 1) {
      $pager['next'] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => 'Next »',
        '#attributes' => [
          'href' => '#',
          'class' => ['pager__link', 'pager__link--next'],
          'onclick' => "document.getElementById('edit-page').value='" . ($current_page + 1) . "'; document.getElementById('ovp-search-form').submit(); return false;",
        ],
      ];
    }

    return $pager;
  }

  /**
   * Build sortable table headers.
   *
   * @param string $current_sort
   *   Current sort parameter.
   *
   * @return array
   *   Header array with sortable links.
   */
  protected function buildSortableHeaders($current_sort) {
    $headers = [];

    // Parse current sort
    list($sort_field, $sort_dir) = explode(' ', $current_sort . ' desc');

    $sortable_fields = [
      'thumbnail' => ['label' => $this->t('Thumbnail'), 'sortable' => FALSE],
      'title' => ['label' => $this->t('Title'), 'field' => 'title'],
      'status' => ['label' => $this->t('Status'), 'field' => 'status'],
      'sourcetype' => ['label' => $this->t('Type'), 'field' => 'sourcetype'],
      'duration' => ['label' => $this->t('Duration'), 'field' => 'length'],
      'id' => ['label' => $this->t('MediaClip ID'), 'field' => 'id'],
      'created' => ['label' => $this->t('Created'), 'field' => 'createddate'],
    ];

    foreach ($sortable_fields as $key => $config) {
      if (isset($config['sortable']) && !$config['sortable']) {
        $headers[$key] = $config['label'];
      }
      else {
        $field = $config['field'];
        $is_active = ($sort_field == $field);
        $new_dir = ($is_active && $sort_dir == 'asc') ? 'desc' : 'asc';
        $arrow = '';

        if ($is_active) {
          $arrow = $sort_dir == 'asc' ? ' ▲' : ' ▼';
        }

        $headers[$key] = [
          'data' => [
            '#type' => 'link',
            '#title' => $config['label'] . $arrow,
            '#url' => Url::fromRoute('<current>'),
            '#attributes' => [
              'class' => ['sortable-header', $is_active ? 'active' : ''],
              'data-sort' => $field . ' ' . $new_dir,
              'onclick' => "document.getElementById('edit-sort').value='{$field} {$new_dir}'; document.getElementById('edit-page').value='0'; document.getElementById('ovp-search-form').submit(); return false;",
            ],
          ],
        ];
      }
    }

    return $headers;
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
    $ovp_client = new BlueBillywigOvpClient();

    foreach ($items as $item) {
      $id = $item['id'] ?? '';
      if (empty($id)) {
        continue;
      }

      // Check if this mediaclip already exists in Drupal
      $existing = $this->checkExistingMedia($id);
      $title_suffix = $existing ? ' <em>(Already imported)</em>' : '';

      // Get authenticated thumbnail URL
      $thumbnail = '';
      try {
        $config = \Drupal::config('s3_uppy.settings');
        $publication = $config->get('bb_publication') ?: getenv('BB_PUBLICATION');
        $thumbnail_url = $ovp_client->getAuthenticatedThumbnailUrl($id, $publication);
        $thumbnail = '<img src="' . htmlspecialchars($thumbnail_url) . '" alt="' . htmlspecialchars($item['title'] ?? '') . '" style="max-width: 120px; height: auto;" />';
      }
      catch (\Exception $e) {
        $thumbnail = '<em>No thumbnail</em>';
      }

      // Format created date
      $created = '';
      if (!empty($item['createddate'])) {
        if (is_numeric($item['createddate'])) {
          $created = date('Y-m-d H:i', (int) $item['createddate']);
        } else {
          $created = $item['createddate'];
        }
      }

      // Format duration
      $duration = '';
      if (!empty($item['length'])) {
        $seconds = (int) $item['length'];
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
          $duration = sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        } else {
          $duration = sprintf('%d:%02d', $minutes, $secs);
        }
      }

      // Get status and source type
      $status = ucfirst($item['status'] ?? 'unknown');
      $sourcetype = ucfirst($item['sourcetype'] ?? 'unknown');

      $options[$id] = [
        'thumbnail' => ['data' => ['#markup' => $thumbnail]],
        'title' => ['data' => ['#markup' => htmlspecialchars($item['title'] ?? 'Untitled') . $title_suffix]],
        'status' => $status,
        'sourcetype' => $sourcetype,
        'duration' => $duration,
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
   * Filter submit handler.
   */
  public function filterSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $filter_name = $triggering_element['#name'] ?? '';

    $filter_queries = [];

    switch ($filter_name) {
      case 'filter_published':
        $filter_queries[] = 'status:published';
        break;

      case 'filter_live':
        $filter_queries[] = 'sourcetype:live';
        break;

      case 'filter_on_demand':
        $filter_queries[] = 'sourcetype:on_demand';
        break;

      case 'filter_all':
      default:
        // No filters
        break;
    }

    // Store filter for the search
    $form_state->set('filter_queries', $filter_queries);

    // Reset to page 0 when filtering
    $form_state->setValue('page', 0);

    // Trigger search
    $this->doSearch($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Reset to page 0 on new search
    $form_state->setValue('page', 0);
    $this->doSearch($form, $form_state);
  }

  /**
   * Perform the search.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param bool $auto_load
   *   TRUE if this is an auto-load on page open.
   */
  protected function doSearch(array &$form, FormStateInterface $form_state, $auto_load = FALSE) {
    try {
      $ovp_client = new BlueBillywigOvpClient();

      $query = $form_state->getValue('query', '');

      // Default to *:* if empty
      if (empty($query)) {
        $query = '*:*';
      }

      $sort = $form_state->getValue('sort', 'createddate desc');
      $page = (int) $form_state->getValue('page', 0);

      // Calculate offset
      $offset = $page * self::ITEMS_PER_PAGE;

      // Get filter queries from form state or empty array
      $filter_queries = $form_state->get('filter_queries') ?? [];

      // Perform search
      $results = $ovp_client->searchMediaClips(
        $query,
        $filter_queries,
        $sort,
        self::ITEMS_PER_PAGE,
        $offset
      );

      // Store results in form state for display
      $form_state->set('search_results', $results);
      $form_state->setRebuild(TRUE);

      if (!$auto_load) {
        $this->messenger()->addStatus(
          $this->t('Found @count videos.', ['@count' => $results['totalResults']])
        );
      }
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
