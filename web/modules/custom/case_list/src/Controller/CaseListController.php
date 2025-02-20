<?php

namespace Drupal\case_list\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\case_list\Form\CaseSearchForm;
use Symfony\Component\HttpFoundation\Response;

class CaseListController extends ControllerBase {
  public function listCases() {
    // Get the database connection.
    $connection = Database::getConnection();

    // Get the search query from the URL.
    $search_query = \Drupal::request()->query->get('search');

    // Query the cases table.
    $query = $connection->select('cases', 'c')
      ->fields('c', ['_id', 'case_name', 'case_number', 'date_opened', 'plaintiff', 'defendant'])
      ->orderBy('date_opened', 'DESC'); // Order by date opened, adjust as needed.

    // If there is a search query, add a condition to filter results.
    if (!empty($search_query)) {
      $query->condition('case_name', '%' . $connection->escapeLike($search_query) . '%', 'LIKE');
    }

    $case_records = $query->execute()->fetchAllAssoc('_id');

    // Build the render array for the list of cases.
    $items = [];
    foreach ($case_records as $case) {
      // Create a link to the case details page.
      $url = Url::fromUri('internal:/cases/' . $case->_id); // Adjust the path as necessary.
      $link = Link::fromTextAndUrl($case->case_name, $url);
      $items[] = $link->toRenderable(); // Convert the link to a renderable array.
    }

    // Render the search form.
    $search_form = \Drupal::formBuilder()->getForm(CaseSearchForm::class);

    return [
      '#theme' => 'list_template',
      '#items' => $items,
      '#title' => $this->t('Cases'),
      '#search_form' => $search_form, // Add the search form to the render array.
    ];
  }

  public function viewCase($case_id) {
    // Get the database connection.
    $connection = Database::getConnection();

    // Query the cases table for the specific case.
    $query = $connection->select('cases', 'c')
      ->fields('c', ['_id', 'case_name', 'case_number', 'date_opened', 'plaintiff', 'defendant', 'case_notes']) // Add any other fields you want to display
      ->condition('_id', $case_id)
      ->range(0, 1); // Limit to one result

    $case_record = $query->execute()->fetchAssoc();

    // Check if the case exists.
    if (!$case_record) {
      throw $this->createNotFoundException($this->t('Case not found.'));
    }

    // Build the render array for the case details.
    $build = [
      '#theme' => 'case_details', // You may need to create a custom theme for case details.
      '#case' => $case_record,
      '#title' => $this->t('@case_name', ['@case_name' => $case_record['case_name']]),
    ];

    return $build;
  }
}
