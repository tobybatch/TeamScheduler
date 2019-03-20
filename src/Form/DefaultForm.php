<?php
namespace Drupal\team_scheduler\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DefaultForm.
 */
class DefaultForm extends FormBase {

  /**
   *
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'default_form';
  }

  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['age_group'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Age group'),
      '#description' => $this->t('Name of the age group'),
      '#maxlength' => 32,
      '#required' => True,
      '#size' => 32,
      '#weight' => '0'
    ];
    $form['pitch_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Pitch count'),
      '#description' => $this->t('The number of pitches available to this age group'),
      '#default_value' => 4,
      '#weight' => '10'
    ];
    $form['pool_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Pool count'),
      '#description' => $this->t('How many pools to split the teams into'),
      '#default_value' => 4,
      '#weight' => '20'
    ];
    $form['game_length_minutes'] = [
      '#type' => 'number',
      '#title' => $this->t('Game length (minutes)'),
      '#description' => $this->t('How long between the startof one game and the next.  e.g. a 12 minute game and a 3 minute gap would be 15 minutes here.'),
      '#default_value' => 15,
      '#weight' => '30'
    ];
    $form['start_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start time'),
      '#description' => $this->t('The time for the firsty kick off, in the format HH:MM, e.g. 10:15'),
      '#maxlength' => 8,
      '#size' => 8,
      '#default_value' => "10:15",
      '#weight' => '40'
    ];
    $form['teams'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Teams'),
      '#description' => $this->t('The list of teams in this age group, one per line.'),
      '#default_value' => "Norwich 1\nNorwich 2\nNorth Walsham\nWymondham",
      '#rows' => 15,
      '#weight' => '50'
    ];
    $form['shuffle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Shuffle?'),
      '#description' => $this->t('Should the game orders be randomised in their time slots.'),
      '#default_value' => "No",
      '#weight' => '60'
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate games'),
      '#weight' => '70'
    ];

    return $form;
  }

  /**
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $age_group = $values['age_group'];
    $filename = \Drupal::service('pathauto.alias_cleaner')->cleanString($age_group);
    $data = [
      'pitch_count' => $values['pitch_count'],
      'age_group' => $values['age_group'],
      'pool_count' => $values['pool_count'],
      'game_length_minutes' => $values['game_length_minutes'],
      'start_time' => $values['start_time'],
      'teams' => explode("\n", $values['teams'])
    ];

    // Now generate the matches
    // TODO: allow a varied number of pools. Right now pools = pitches
    $matches = $this->process($data['pitch_count'], $data['pool_count'], $data['game_length_minutes'], $data['start_time'], $data['teams']);
    // Add those matches to the data array
    $data['matches'] = $matches;

    // Save the data array
    $file_system = \Drupal::service('file_system');
    $uri_stub = 'public://team_scheduler';
    $streamuri = $uri_stub . '/' . $filename . '.json';
    if (! \is_dir($file_system->realpath(dirname($streamuri)))) {
      $file_system->mkdir($uri_stub, 0755, True);
    }
    file_save_data(json_encode($data, JSON_PRETTY_PRINT), $streamuri, 1);
  }

  private function process($pitchCount, $poolCount, $gameTime, $startTime, array $teams) {
    // Sort the teams so we try to avoid teams in the same group
    sort($teams);
    dpm("pitchCount = $pitchCount, poolCount = $poolCount, gameTime = $gameTime, startTime = $startTime");

    // Split teams into pools
    $gameTimes = $this->generateGameTimes($startTime, $gameTime);
    $pools = [];
    for ($i = 0; $i < \count($teams); $i ++) {
      $pools[$i % $poolCount][] = $teams[$i];
    }

    // Generate game lists for pool
    $gameList = [];
    foreach ($pools as $pool) {
      $gameList[] = $this->generateGames($pool);
    }
    dpm($gameList, 'gameList');

    $matches = [];
    $index = 0;
    while (\count($gameList) > 0) {
      $index ++;
      if ($index > 300) {
        break;
      }
      $game_count = \count($gameList);
      $pool_index = $index % $game_count;
      $pitch_index = $index % $pitchCount;
      $matches[$pitch_index][] = array_shift($gameList[$pool_index]);
      if (\count($gameList[$pool_index]) == 0) {
        unset($gameList[$pool_index]);
      }
    }

    return [
      'pools' => $pools,
      'pitches' => $matches,
      'gameTimes' => $gameTimes
    ];
  }

  /**
   * For each pool generate the games so each teams plays each other team once.
   *
   * @param array $teams
   * @param boolean $shuffle
   */
  private function generateGames(array $teams, $shuffle = false) {
    // for each team in the list
    // set up a game against the last team in the list
    $games = [];
    // Each team must play each other
    while (! empty($teams)) {
      $team = array_pop($teams);
      foreach ($teams as $_team) {
        $game = [];
        $game[] = $team;
        $game[] = $_team;
        $games[] = $game;
      }
    }
    if ($shuffle) {
      shuffle($games);
    }
    return $games;
  }

  private function generateGameTimes($startTime, $gameTime, $paddingTime = 0, $gameCount = 100) {
    $now = strtotime($startTime);
    $kos = [
      $now
    ];
    $time = $gameTime + $paddingTime;
    for ($i = 0; $i < $gameCount; $i ++) {
      $kos[] = strtotime("+" . ($i * $time) . "minutes", $now);
    }
    return $kos;
  }

}
