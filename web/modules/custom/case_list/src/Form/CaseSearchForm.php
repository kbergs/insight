<?php

namespace Drupal\case_list\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class CaseSearchForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'case_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Cases'),
      '#description' => $this->t('Enter a case name to search.'),
      '#size' => 30,
      '#maxlength' => 128,
      '#default_value' => $form_state->getValue('search', ''),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Search'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Redirect to the case list page with the search query as a query parameter.
    $search_query = $form_state->getValue('search');
    $form_state->setRedirect('case_list.page', ['search' => $search_query]);
  }
} 