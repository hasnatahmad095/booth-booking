<?php
/**
 * Uninstall routine for Booth Booking.
 *
 * @package Hasnat\BoothBooking
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$hasnat_bb_meta_keys = [
    '_hasnat_bb_map_image_id',
    '_hasnat_bb_booths_json',
    '_hasnat_bb_booked_booths',
];

foreach ($hasnat_bb_meta_keys as $hasnat_bb_key) {
    delete_post_meta_by_key($hasnat_bb_key);
}

// Best-effort cleanup of transients. WordPress does not provide a direct
// delete-by-prefix API for transients, so we don't attempt bulk deletion here.
