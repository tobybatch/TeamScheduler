<?php
namespace Drupal\team_scheduler\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'DefaultBlock' block.
 *
 * @Block(
 *  id = "default_block",
 *  admin_label = @Translation("Saved Games"),
 * )
 */
class DefaultBlock extends BlockBase {

  /**
   *
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $streamuri = 'public://team_scheduler';
    $contents = file_scan_directory($streamuri, '#\.json$#');

    $files = [];
    foreach ($contents as $file) {
      $name = $file->name;
      $files[] = $name;
    }

    $build['default_block'] = [
      '#theme' => 'team_scheduler_block',
      '#saved_games' => $files
    ];

    return $build;
  }

  public function getCacheMaxAge() {
    return 0;
  }
}
