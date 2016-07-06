<?php

namespace CupOfTea\WordPress\Traits;

trait AccessesPost
{
    public function post($the_post = null, $type = 'post_type', $term = 'page')
    {
        if ($type == 'taxonomy') {
            return get_term($the_post, $term);
        }
        
        global $post;

        return get_post($the_post ?: (is_home() ? get_option('page_for_posts') :
            (is_archive() ? (get_queried_object() ?: get_option('page_for_posts')) : $post)));
    }
}
