<?php 
function getImage($img, $default) {
    return $img && file_exists('../' . $img) ? '/' . $img : $default;
}

$default_profile = '/tbcph/assets/images/placeholder.jpg';
$default_bg = '/tbcph/assets/images/backgrounds/default_cover.jpg';
echo getImage($busker_data['profile_image'] ?? '', $default_profile); ?>