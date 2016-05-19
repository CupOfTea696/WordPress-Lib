<?php

namespace CupOfTea\WordPress\WordPress;

use CupOfTea\WordPress\Service;
use CupOfTea\WordPress\Traits\AccessesPost;

class Post extends Service
{
    use AccessesPost;
    
    public function getUrl($post = null)
    {
        $post = $this->post($post);
        $url = get_permalink($post);
        
        return app('wp')->getRelativeUrl($url);
    }
    
    public function getHomeUrl()
    {
        return app('wp')->getRelativeUrl(get_home_url());
    }
    
    public function getFeedUrl()
    {
        $postsUrl = app('wp.page')->getPostsPageUrl();
        
        if(isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], $postsUrl)) {
            return app('wp')->getRelativeUrl($_SERVER['HTTP_REFERER']);
        }
        
        return $postsUrl;
    }
    
    public function hasParent($post = null)
    {
        return (bool) $this->post($post)->post_parent;
    }
    
    public function getParent($post = null)
    {
        return get_post($this->post($post)->post_parent);
    }
    
    public function hasChildren($post = null)
    {
        $post = $this->post($post);
        
        $args = [
            'post_parent' => $post->ID,
            'post_type' => $post->post_type
        ];
        
        $children = get_children($args);
        
        return (bool) count($children);
    }
    
    public function getChildren($post = null)
    {
        $post = $this->post($post);
        
        $args = [
            'post_parent' => $post->ID,
            'post_type' => $post->post_type
        ];
        
        $children = get_children($args);
        
        uasort($children, function($a, $b) {
            // if PHP7 -> return $a <=> $b;
            
            if ($a->menu_order == $b->menu_order) {
                return 0;
            }
            
            return ($a->menu_order < $b->menu_order) ? -1 : 1;
        });
        
        return $children;
    }
    
    public function getSiblings($post = null)
    {
        return $this->getChildren($this->getParent($post));
    }
}
