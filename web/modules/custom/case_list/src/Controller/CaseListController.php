<?php

namespace Drupal\case_list\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Link;
use Drupal\Core\Url;

class CaseListController extends ControllerBase {
  public function listCases() {
    // Get the database connection.
    $connection = Database::getConnection();

    // Query the cases table.
    $query = $connection->select('cases', 'c')
      ->fields('c', ['_id', 'case_name', 'case_number', 'date_opened', 'plaintiff', 'defendant'])
      ->orderBy('date_opened', 'DESC'); // Order by date opened, adjust as needed.

    $case_records = $query->execute()->fetchAllAssoc('_id');

    // Build the render array for the list of cases.
    $items = [];
    foreach ($case_records as $case) {
      // Create a link to the case details page.
      $url = Url::fromUri('internal:/cases/' . $case->_id); // Adjust the path as necessary.
      $link = Link::fromTextAndUrl($case->case_name, $url);
      $items[] = $link->toRenderable(); // Convert the link to a renderable array.
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Cases'),
    ];
  }
}
