<?php
// Quick check of available WordPress scripts in admin
require_once( '../../../../wp-load.php' );

global $wp_scripts;

echo "=== Available WordPress Scripts ===\n\n";

$search_terms = [ 'react', 'dataviews', 'dataforms', 'element', 'components' ];

foreach ( $search_terms as $term ) {
    echo "Scripts matching '$term':\n";
    foreach ( $wp_scripts->registered as $handle => $script ) {
        if ( stripos( $handle, $term ) !== false ) {
            echo "  - $handle\n";
        }
    }
    echo "\n";
}

echo "\n=== All registered scripts (first 50) ===\n";
$count = 0;
foreach ( $wp_scripts->registered as $handle => $script ) {
    if ( ++$count > 50 ) break;
    echo "  $handle\n";
}
