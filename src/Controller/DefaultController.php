<?php
namespace Drupal\team_scheduler\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormState;

/**
 * Class DefaultController.
 */
class DefaultController extends ControllerBase {

  /**
   * Index.
   */
  public function index($id) {
    $form_state = new FormState();
    $form_state->setRebuild();
    $form = \Drupal::formBuilder()->getForm(\Drupal\team_scheduler\Form\DefaultForm::class);

    $matches = False;

    if ($id) {
      $uri_stub = 'public://team_scheduler';
      $streamuri = $uri_stub . '/' . $id . '.json';
      $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties([
        'uri' => $streamuri
      ]);
      $file = array_pop($files);
      $data = json_decode(file_get_contents($file->getFileUri()), True);

      $form['pitch_count']['#value'] = $data['pitch_count'];
      $form['age_group']['#value'] = $data['age_group'];
      $form['pool_count']['#value'] = $data['pool_count'];
      $form['game_length_minutes']['#value'] = $data['game_length_minutes'];
      $form['start_time']['#value'] = $data['start_time'];
      $form['teams']['#value'] = \implode("\n", $data['teams']);

      $matches = $data['matches'];
    }

    $render = [
      'form' => $form,
      'matches' => [
        '#theme' => 'team_scheduler',
        '#matches' => $matches
      ],
      '#cache' => ['max-age' => 0],
    ];

    return $render;
  }
}
