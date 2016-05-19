<?php

namespace CupOfTea\WordPress\WordPress;

use CupOfTea\WordPress\Service;

class Page extends Post
{
    public function getPostsPage()
    {
        return get_post(get_option('page_for_posts'));
    }
    
    public function getPostsPageUrl()
    {
        $page = get_post(get_option('page_for_posts'));
        $url = get_permalink($page);
        
        return app('wp')->getRelativeUrl($url);
    }
}
