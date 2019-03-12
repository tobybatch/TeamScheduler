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
    $form['pitch_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Pitch count'),
      '#description' => $this->t('The number of pitches available to this age group'),
      '#default_value' => 4,
      '#weight' => '0'
    ];
    $form['age_group'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Age group'),
      '#description' => $this->t('Name of the age group'),
      '#maxlength' => 32,
      '#required' => True,
      '#size' => 32,
      '#weight' => '0'
    ];
    $form['pool_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Pool count'),
      '#description' => $this->t('How many pools to split the teams into'),
      '#default_value' => 4,
      '#weight' => '0'
    ];
    $form['game_length_minutes'] = [
      '#type' => 'number',
      '#title' => $this->t('Game length (minutes)'),
      '#description' => $this->t('How long between the startof one game and the next.  e.g. a 12 minute game and a 3 minute gap would be 15 minutes here.'),
      '#default_value' => 15,
      '#weight' => '0'
    ];
    $form['start_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start time'),
      '#description' => $this->t('The time for the firsty kick off, in the format HH:MM, e.g. 10:15'),
      '#maxlength' => 8,
      '#size' => 8,
      '#default_value' => "10:15",
      '#weight' => '0'
    ];
    $form['teams'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Teams'),
      '#description' => $this->t('The list of teams in this age group, one per line.'),
      '#default_value' => "Norwich 1\nNorwich 2\nNorth Walsham\nWymondham",
      '#weight' => '0'
    ];
    $form['shuffle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Shuffle?'),
      '#description' => $this->t('Should the game orders be randomised in their time slots.'),
      '#default_value' => "No",
      '#weight' => '0'
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate games')
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
    $matches = $this->process($data['pitch_count'], $data['pool_count'], $data['game_length_minutes'], $data['start_time'], $data['teams']);
    // Add those matches to the data array
    $data['matches'] = $matches;

    // Save the data array
    $file_system = \Drupal::service('file_system');
    $uri_stub = 'public://team_scheduler';
    $streamuri = $uri_stub . '/' . $filename . '.json';
    if (\is_dir($file_system->realpath($streamuri))) {
      $file_system->mkdir($uri_stub, 0755, True);
    }
    file_save_data(json_encode($data, JSON_PRETTY_PRINT), $streamuri, 1);
  }

  private function process($pitchCount, $poolCount, $gameTime, $startTime, array $teams) {
    // Sort the teams so we try to avoid teams in the same group
    sort($teams);

    // Split teams into pools
    $gameTimes = $this->generateGameTimes($startTime, $gameTime);
    $pools = [];
    for ($i = 0; $i < count($teams); $i ++) {
      $pools[$i % $poolCount][] = $teams[$i];
    }

    // Generate game lists for pool
    $gameList = [];
    foreach ($pools as $pool) {
      $gameList[] = $this->generateGames($pool);
    }

    // Create an empty array for each pitch.
    $pitches = [];
    for ($i = 0; $i < $pitchCount; $i ++) {
      $pitches[] = [];
    }

    // Now the really tricky but. Try to spead out the games for each team.
    $poolgames = [];
    foreach ($gameList as $pool) {
      // this gets a list of games in a given pool, in order
      $poolgames[] = $pool;
      // $poolgames[] = $this->arrangeGames($pool);
    }

    // kint($poolgames);
    $interleavedGames = [];
    for ($i = 0; $i < count($teams); $i ++) {
      $interleavedGames[] = array_shift($poolgames[$i % count($poolgames)]);
    }

    // Assign the games to pitches.
    for ($i = 0; $i < count($interleavedGames); $i ++) {
      $pitches[$i % $pitchCount][] = $interleavedGames[$i];
    }

    return [
      'pools' => $pools,
      'pitches' => $pitches,
      'gameTimes' => $gameTimes
    ];
  }

  private function arrangeGames($gameList) {
    $interleavedGames = [];
    $teamsPlayed = [];
    while (count($gameList)) {
      // Find the next game does not have a team in the teamsPlayed list.
      foreach ($gameList as $index => $game) {
        $teamA = $game->teamA;
        $teamB = $game->teamB;
        // If neither of these teams have played yet use this game.
        if (! in_array($teamA, $teamsPlayed) && ! in_array($teamB, $teamsPlayed)) {
          $interleavedGames[] = $game;
          // Record that the two teams have played in this phase of games.
          $teamsPlayed[] = $teamA;
          $teamsPlayed[] = $teamB;
          // dpm("$teamA vs $teamB - " . implode(', ', $teamsPlayed));
          // Remove this game from the game list
          unset($gameList[$index]);
          // jump back to the while loop
          continue 2;
        }
      }
      // If we got this far the we don't have a team that hasn't played, reset the teams played.
      // dpm($teamsPlayed, 'Reseting teams played');
      $teamsPlayed = [];
    }
    return $interleavedGames;
  }

  /**
   * For each pool generate the games so each teams plays each other team once.
   *
   * @param array $teams
   * @param boolean $shuffle
   */
  private function generateGames(array $teams, $shuffle = false) {
    // $games = [];
    // // Each team must play each other
    // while (! empty($teamList)) {
    // $team = array_pop($teamList);
    // foreach ($teamList as $_team) {
    // $game = new \stdClass();
    // $game->teamA = $team;
    // $game->teamB = $_team;
    // $games[] = $game;
    // }
    // }
    // if ($shuffle) {
    // shuffle($games);
    // }
    // return $games;
    // $round = [];
    // if (\count($teams) % 2 != 0) {
    // array_push($teams, "bye");
    // }
    // $away = array_splice($teams, (count($teams) / 2));
    // $home = $teams;
    // for ($i = 0; $i < count($home) + count($away) - 1; $i ++) {
    // for ($j = 0; $j < count($home); $j ++) {
    // $game = new \stdClass();
    // $game->teamA = $home[$j];
    // $game->teamB = $away[$j];

    // $round[$i][$j] = $game;
    // }
    // if (count($home) + count($away) - 1 > 2) {
    // $s = array_splice($home, 1, 1);
    // $slice = array_shift($s);
    // array_unshift($away, $slice);
    // array_push($home, array_pop($away));
    // }
    // }
    // dpm($round);
    // return $round;
  }

  private function generateGameTimes($startTime, $gameTime, $paddingTime = 0, $gameCount = 100) {
    $now = strtotime("10:00");
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
