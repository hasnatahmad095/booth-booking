<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$brandjaws_ebb_meta_keys = [
    '_expo_map_image_id',
    '_expo_booths_json',
    '_brandjaws_ebb_map_image_id',
    '_brandjaws_ebb_booths_json',
    '_brandjaws_ebb_booked_booths',
];

foreach ($brandjaws_ebb_meta_keys as $brandjaws_ebb_key) {
    delete_post_meta_by_key($brandjaws_ebb_key);
}

// Best-effort cleanup of transients. WordPress does not provide a direct
// delete-by-prefix API for transients, so we don't attempt bulk deletion here.
