<?php

/**
 * @file
 * HDBT Admin tools hooks.
 */

declare(strict_types=1);

/**
 * @file
 * Hooks provided by the Helfi admin tools module.
 */

/**
 * Modify the image list items for the field preview images.
 *
 * @param array $images
 *   Keyed array of image URLs.
 * @param string $field_name
 *   Field name which is uses the preview images.
 */
function hook_design_selection_images_alter(array &$images, string $field_name) {
}

/**
 * Alter the node form ids that show the hero visibility states.
 *
 * @param array $form_ids
 *   The hero form ids.
 */
function hook_hero_visibility_alter(array &$form_ids) {
}
