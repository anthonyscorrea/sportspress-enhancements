<?php
/*
Plugin Name: SP Event Image Generator
Description: Auto-generates featured images for SP Events by combining team colors and logos.
Version: 1.0
Author: Your Name
*/

add_action('save_post_sp_event', 'generate_event_featured_image', 10, 3);

function generate_event_featured_image($post_id, $post) {
    // Verify post type
    if ($post->post_type !== 'sp_event') return;

    // Get associated teams from post meta
    $team_ids = get_post_meta($post_id, 'sp_team', false); // false to get an array of values

    // Ensure we have exactly two teams
    if (count($team_ids) < 2) return;

    $team1_id = $team_ids[0];
    $team2_id = $team_ids[1];

    // Get team colors and logos
    $team1_colors = get_post_meta($team1_id, 'sp_colors', true);
    $team2_colors = get_post_meta($team2_id, 'sp_colors', true);

    $default_color = '#FFFFFF'; // Default color (black)
    $team1_color = !empty($team1_colors['primary']) ? $team1_colors['primary'] : $default_color;
    $team2_color = !empty($team2_colors['primary']) ? $team2_colors['primary'] : $default_color;

    $team1_logo_url = get_the_post_thumbnail_url($team1_id, 'full');
    $team2_logo_url = get_the_post_thumbnail_url($team2_id, 'full');

    // Check if both team colors are default and both logos are empty
    if (($team1_color === $default_color && empty($team1_logo_url)) && ($team2_color === $default_color && empty($team2_logo_url))) {
        return; // Do nothing if both teams have no valid color or logo
    }

    $team1_logo_thumbnail_id = get_post_thumbnail_id($team1_id, 'full');
    $team2_logo_thumbnail_id = get_post_thumbnail_id($team2_id, 'full');
    $team1_logo = get_attached_file($team1_logo_thumbnail_id);
    $team2_logo = get_attached_file($team2_logo_thumbnail_id);

    // Generate image
    $image = generate_bisected_image($team1_color, $team2_color, $team1_logo, $team2_logo);

    // Upload and set as featured image
    $attachment_id = upload_image($image, $post_id, $team1_id, $team2_id);
    set_post_thumbnail($post_id, $attachment_id);
}

function generate_bisected_image($color1, $color2, $logo1_path, $logo2_path) {
    $width = 1200;
    $height = 628;
    $x_margin = 0.1 * ($width / 2); // 10% of half the width
    $y_margin = 0.1 * $height; // 10% of the height
    $image = imagecreatetruecolor($width, $height);

    // Allocate colors
    $rgb1 = sscanf($color1, "#%02x%02x%02x");
    $rgb2 = sscanf($color2, "#%02x%02x%02x");
    $color1_alloc = imagecolorallocate($image, $rgb1[0], $rgb1[1], $rgb1[2]);
    $color2_alloc = imagecolorallocate($image, $rgb2[0], $rgb2[1], $rgb2[2]);

    // Fill halves with a 15-degree angled bisection
    $points1 = [
        0, 0, 
        0, $height,
        $width*.40, $height, 
        $width*.60, 0,
    ];
    $points2 = [
        $width, 0, 
        $width, $height, 
        $width*.40, $height, 
        $width*.60, 0,
    ];
    imagefilledpolygon($image, $points1, $color1_alloc);
    imagefilledpolygon($image, $points2, $color2_alloc);

    // Add logos with resizing and positioning if paths are not empty
    if (!empty($logo1_path)) {
        $logo1 = imagecreatefrompng($logo1_path);
        $logo1_width = imagesx($logo1);
        $logo1_height = imagesy($logo1);

        // Calculate max dimensions for logo 1
        $max_width = ($width / 2) - (2 * $x_margin);
        $max_height = $height - (2 * $y_margin);

        // Resize logo 1
        $new_logo1_width = $logo1_width;
        $new_logo1_height = $logo1_height;
        if ($logo1_width > $max_width || $logo1_height > $max_height) {
            $aspect_ratio1 = $logo1_width / $logo1_height;
            if ($logo1_width / $max_width > $logo1_height / $max_height) {
                $new_logo1_width = $max_width;
                $new_logo1_height = $max_width / $aspect_ratio1;
            } else {
                $new_logo1_height = $max_height;
                $new_logo1_width = $max_height * $aspect_ratio1;
            }
        }

        // Center logo 1
        $logo1_x = ($width / 4) - ($new_logo1_width / 2);
        $logo1_y = ($height / 2) - ($new_logo1_height / 2);
        imagecopyresampled($image, $logo1, $logo1_x, $logo1_y, 0, 0, $new_logo1_width, $new_logo1_height, $logo1_width, $logo1_height);
        imagedestroy($logo1);
    }

    if (!empty($logo2_path)) {
        $logo2 = imagecreatefrompng($logo2_path);
        $logo2_width = imagesx($logo2);
        $logo2_height = imagesy($logo2);

        // Calculate max dimensions for logo 2
        $max_width = ($width / 2) - (2 * $x_margin);
        $max_height = $height - (2 * $y_margin);

        // Resize logo 2
        $new_logo2_width = $logo2_width;
        $new_logo2_height = $logo2_height;
        if ($logo2_width > $max_width || $logo2_height > $max_height) {
            $aspect_ratio2 = $logo2_width / $logo2_height;
            if ($logo2_width / $max_width > $logo2_height / $max_height) {
                $new_logo2_width = $max_width;
                $new_logo2_height = $max_width / $aspect_ratio2;
            } else {
                $new_logo2_height = $max_height;
                $new_logo2_width = $max_height * $aspect_ratio2;
            }
        }

        // Center logo 2
        $logo2_x = (3 * $width / 4) - ($new_logo2_width / 2);
        $logo2_y = ($height / 2) - ($new_logo2_height / 2);
        imagecopyresampled($image, $logo2, $logo2_x, $logo2_y, 0, 0, $new_logo2_width, $new_logo2_height, $logo2_width, $logo2_height);
        imagedestroy($logo2);
    }


    // Save to temp location
    $temp_file = tempnam(sys_get_temp_dir(), 'event_image');
    rename($temp_file, $temp_file .= '.png');
    $temp_file .= '.png';
    imagepng($image, $temp_file);
    imagedestroy($image);

    return $temp_file;
}

function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}

function upload_image($file, $post_id, $team1_id, $team2_id) {
    $filename = 'event-' . $team1_id .'-'. $team2_id . '.png'; // Set custom file name
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;

    // Find existing attachments with the same name and delete them
    $existing_attachments = get_posts(array(
        'post_type' => 'attachment',
        'name' => sanitize_file_name($filename),
        'posts_per_page' => -1,
        'post_status' => 'any'
    ));
    foreach ($existing_attachments as $attachment) {
        wp_delete_attachment($attachment->ID, true);
    }

    $upload_file = wp_upload_bits($filename, null, file_get_contents($file));

    if (!$upload_file['error']) {
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'private' // Set the status to private
        );
        $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        return $attachment_id;
    }

    return 0;
}

// Add menu item to generate images for existing events
add_action('admin_menu', 'add_generate_images_menu');

function add_generate_images_menu() {
    add_submenu_page(
        'tools.php',
        'Generate Event Images', // Page title
        'Generate Event Images', // Menu title
        'manage_options', // Capability
        'generate-event-images', // Menu slug
        'generate_images_menu_page' // Function to display page content
    );
}

function generate_images_menu_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission
    if (isset($_POST['generate_images'])) {
        $season = isset($_POST['sp_season']) ? sanitize_text_field($_POST['sp_season']) : '';
        generate_images_for_existing_events($season);
        echo '<div class="updated"><p>Images generated for selected events.</p></div>';
    }

    // Get available seasons
    $seasons = get_terms(array(
        'taxonomy' => 'sp_season',
        'hide_empty' => false,
    ));
    ?>
    <div class="wrap">
        <h1>Generate Event Images</h1>
        <form method="post" action="">
            <p>Select a season to generate images for:</p>
            <select name="sp_season">
                <option value="">All Seasons</option>
                <?php
                foreach ($seasons as $season) {
                    echo '<option value="' . esc_attr($season->slug) . '">' . esc_html($season->name) . '</option>';
                }
                ?>
            </select>
            <p><input type="submit" name="generate_images" class="button-primary" value="Generate Images"></p>
        </form>
    </div>
    <?php
}

function generate_images_for_existing_events($season = '') {
    $args = array(
        'post_type' => 'sp_event',
        'posts_per_page' => -1,
    );

    // Add season filter if selected
    if (!empty($season)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'sp_season',
                'field' => 'slug',
                'terms' => $season,
            ),
        );
    }

    $events = new WP_Query($args);

    if ($events->have_posts()) {
        while ($events->have_posts()) {
            $events->the_post();
            $post_id = get_the_ID();

            // Ensure no duplicate processing
            if (has_post_thumbnail($post_id)) continue;

            // Generate the featured image using the existing function
            generate_event_featured_image($post_id, get_post($post_id), false);
        }
        wp_reset_postdata();
    }
}
