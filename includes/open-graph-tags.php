<?php
/*
Plugin Name: Custom Open Graph Tags with SportsPress Integration
Description: Adds custom Open Graph tags to posts based on their type, specifically handling sp_event post types with methods from the SportsPress SP_Event class.
Version: 1.0
Author: Your Name
*/

add_action('wp_head', 'custom_open_graph_tags_with_sportspress_integration');

function custom_open_graph_tags_with_sportspress_integration() {
    if (is_single()) {
        global $post;
        if ($post->post_type === 'sp_event') {
            // Instantiate SP_Event object
            $event = new SP_Event($post->ID);

            // Fetch details using SP_Event methods
            $publish_date = get_the_date('F j, Y', $post);
            $venue_terms = get_the_terms($post->ID, 'sp_venue');
            $venue_name = $venue_terms ? $venue_terms[0]->name : 'No Venue Specified';
            $results = $event->results();  // Using SP_Event method
            $title = get_the_title() . " " . "(" . $publish_date . ")";
            $sp_status = get_post_meta( $post->ID, 'sp_status', true );
            $status = $event->status();  // Using SP_Event method
            $publish_date_and_time = get_the_date('F j, Y g:i A', $post);
            $description = "{$publish_date_and_time} at {$venue_name}.";
            if ( 'postponed' == $sp_status ) {
              $description = "POSTPONED" . " - " . $description;
              $title = "POSTPONED" . " - " . $title;
            }

            if ( 'results' == $status ) { // checks if there is a final score
              // Get event result data
              $data = $event->results();

              // The first row should be column labels
              $labels = $data[0];

              // Remove the first row to leave us with the actual data
              unset( $data[0] );

              $data = array_filter( $data );

              if ( empty( $data ) ) {
                return false;
              }

              // Initialize
              $i          = 0;
              $result_string = '';
              $title_string = '';

              // Reverse teams order if the option "Events > Teams > Order > Reverse order" is enabled.
              $reverse_teams = get_option( 'sportspress_event_reverse_teams', 'no' ) === 'yes' ? true : false;
              if ( $reverse_teams ) {
                $data = array_reverse( $data, true );
              }

              $teams_result_array = [];

              foreach ( $data as $team_id => $result ) :
                  $outcomes       = array();
                  $result_outcome = sp_array_value( $result, 'outcome' );
                  if ( ! is_array( $result_outcome ) ) :
                    $outcomes = array( '&mdash;' );
                  else :
                    foreach ( $result_outcome as $outcome ) :
                      $the_outcome = get_page_by_path( $outcome, OBJECT, 'sp_outcome' );
                      if ( is_object( $the_outcome ) ) :
                        $outcomes[] = $the_outcome->post_title;
                      endif;
                    endforeach;
                  endif;
              
                unset( $result['outcome'] );
              
                $team_name = sp_team_short_name( $team_id );

                $outcome_abbreviation = get_post_meta( $the_outcome->ID, 'sp_abbreviation', true );
                if ( ! $outcome_abbreviation ) {
                  $outcome_abbreviation = sp_substr( $the_outcome->post_title, 0, 1 );
                }
                
                array_push($teams_result_array, [
                  "result" => $result,
                  "outcome" => $the_outcome->post_title,
                  "outcome_abbreviation" => $outcome_abbreviation,
                  "team_name" => $team_name,
                ]
              );              
                $i++;
              endforeach;
              $title = "{$teams_result_array[0]['team_name']} {$teams_result_array[0]['result']['r']} - {$teams_result_array[1]['result']['r']} {$teams_result_array[1]['team_name']} ({$publish_date})";
              $description .= " " . "{$teams_result_array[0]['team_name']} ({$teams_result_array[0]['outcome']}), {$teams_result_array[1]['team_name']} ({$teams_result_array[1]['outcome']}).";;
            }
            
            $description .= " " . $post->post_content;
            $post_thumbnail =  get_the_post_thumbnail_url($post->ID, 'thumbnail');
            $image = $post_thumbnail ? $post_thumbnail : get_site_icon_url();
            echo '<meta property="og:type" content="article" />' . "\n";
            echo '<meta property="og:image" content="'. $image . '" />' . "\n";
            echo '<meta property="og:title" content="' . $title . '" />' . "\n";
            echo '<meta property="og:description" content="' . $description . '" />' . "\n";
            echo '<meta property="og:url" content="' . get_permalink() . '" />' . "\n";
        }
    }
}
?>
