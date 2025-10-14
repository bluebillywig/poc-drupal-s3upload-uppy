<?php

namespace Drupal\s3_uppy\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for OVP Search page.
 */
class OvpSearchController extends ControllerBase {

  /**
   * Display the OVP search form.
   *
   * @return array
   *   Render array containing the search form.
   */
  public function search() {
    return \Drupal::formBuilder()->getForm('Drupal\s3_uppy\Form\OvpSearchForm');
  }

}
