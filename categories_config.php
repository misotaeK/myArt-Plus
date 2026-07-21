<?php
// Tek kaynak: eser kategorileri, browse.php / categories.php / myprofile.php arasında paylaşılır.
// Her kategori adı, lang dosyalarındaki çeviri anahtarına eşlenir.
$art_category_groups = [
    'group_digital_label' => [
        'Digital Painting' => 'cat_digital',
        'Pixel Art' => 'cat_pixel',
        '3D & Render' => 'cat_3d',
        'Concept Art' => 'cat_concept',
        'Vector Art' => 'cat_vector',
    ],
    'group_traditional_label' => [
        'Traditional & Sketch' => 'cat_traditional',
        'Watercolor & Ink' => 'cat_watercolor',
        'Crafts & Sculpture' => 'cat_crafts',
        'Typography & Lettering' => 'cat_typography',
    ],
    'group_characters_label' => [
        'Character Design' => 'cat_character',
        'Anime & Manga' => 'cat_anime',
        'Fan Art' => 'cat_fanart',
        'Comics & Sequential Art' => 'cat_comics',
        'Animation' => 'cat_animation',
    ],
    'group_photo_other_label' => [
        'Photography' => 'cat_photography',
        'Landscape' => 'cat_landscape',
        'Portrait' => 'cat_portrait',
        'Abstract' => 'cat_abstract',
        'Other' => 'cat_other',
    ],
];

$art_categories = [];
$art_category_lang_key = [];
foreach ($art_category_groups as $group) {
    foreach ($group as $cat_name => $lang_key) {
        $art_categories[] = $cat_name;
        $art_category_lang_key[$cat_name] = $lang_key;
    }
}
