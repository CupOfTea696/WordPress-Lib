<?php

namespace CupOfTea\WordPress;

use WP_Term;
use WP_User;
use WP_Post_Type;
use CupOfTea\WordPress\Traits\AccessesPost;

class WordPress extends Service
{
    use AccessesPost;
    
    public function acf($field_name, $default = null, $post_id = false)
    {
        $field = get_field($field_name, $post_id);
        
        return  $field !== null ? $field : $default;
    }
    
    public function getFullSlug($post = null, $parent_separator = '/')
    {
        $slug = app('wp.post')->getUrl($post);
        
        if ($parent_separator != '/') {
            $slug = str_replace('/', $parent_separator, $slug);
        }
        
        $slug = trim($slug, $parent_separator);
        
        if (! $slug) {
            return 'home';
        }
        
        return $slug;
    }
    
    public function getSlug($post = null, $type = 'post_type', $term = 'page')
    {
        if ($type == 'taxonomy') {
            return get_term($post, $term);
        }
        
        if (is_home() && is_front_page()) {
            return '';
        }
        
        return object_get($post = $this->post($post), 'post_name', $post->name);
    }
    
    public function getRelativeUrl($from)
    {
        $url = is_object($from) ? $from->url : $from;
        
        if (! starts_with($url, get_home_url())) {
            return $url;
        }
        
        $url = rtrim(str_replace(get_home_url(), '', $url), '/');
        
        if (preg_match('/#/', $url)) {
            return $url;
        }
        
        return preg_replace('/([^\\/])#/', '$1/#', $url) . '/';
    }
    
    public function pages()
    {
        global $wp_query;
        $pages = $wp_query->max_num_pages;
        
        return $wp_query->max_num_pages ?: 1;
    }
    
    protected function getPaginatedClass($classes, $types)
    {
        $class = [$classes['base']];
        
        foreach ((array) $types as $type) {
            if (isset($classes[$type])) {
                $class = array_merge($class, (array) $classes[$type]);
            }
        }
        
        return trim(preg_replace('/\\s{2,}/', ' ', implode(' ', $class)));
    }
    
    protected function getPaginationLink($base, $page)
    {
        $queried = get_queried_object();
        $segments = [];
        
        if ($queried instanceof WP_Term) {
            $segments[] = trim($this->getRelativeUrl(get_term_link($queried)), '/');
        } elseif ($queried instanceof WP_Post_Type && isset($queried->rewrite['slug'])) {
            $segments[] = $queried->rewrite['slug'];
        } elseif ($queried instanceof WP_User) {
            $segments[] = trim($this->getRelativeUrl(get_author_posts_url($queried->ID, $queried->data->user_nicename)), '/');
        } elseif (is_date()) {
            $y = get_the_date('Y');
            
            if (is_year()) {
                $segments[] = trim($this->getRelativeUrl(get_year_link($y)), '/');
            } elseif (is_month()) {
                $segments[] = trim($this->getRelativeUrl(get_month_link($y, get_the_date('n'))), '/');
            } else {
                $segments[] = trim($this->getRelativeUrl(get_day_link($y, get_the_date('n'), get_the_date('j'))), '/');
            }
        }
        
        if ($page > 1) {
            array_push($segments, $base, $page);
        }
        
        $url = implode('/', array_filter($segments));
        
        return '/' . ($url ? $url . '/' : '');
    }
    
    // TODO: This looks horrendous, can it be simplified?
    public function pagination($base, $classes, $mid_size = 2, $end_size = 1, $prev = null, $next = null, $ellip = '&hellip;', $first = false, $last = false)
    {
        $page = get_query_var('paged') ?: 1;
        $pages = $this->pages();
        
        if ($pages == 1) {
            return;
        }
        
        if ($prev === null) {
            $prev = str_replace('&laquo; ', '', __('&laquo; Previous'));
        }
        
        if ($next === null) {
            $next = __('Next');
        }
        
        if ($first) {
            if ($page > 1) {
                $compiled['first'] = '<a href="' . $this->getPaginationLink($base, 1) . '" class="' . $this->getPaginatedClass($classes, 'first') . '">' . $first . '</a>';
            } else {
                $compiled['first'] = '<span class="' . $this->getPaginatedClass($classes, ['first', 'disabled']) . '">' . $first . '</span>';
            }
        }
        
        if ($prev) {
            if ($page > 1) {
                $compiled['prev'] = '<a href="' . $this->getPaginationLink($base, $page - 1) . '" class="' . $this->getPaginatedClass($classes, 'prev') . '">' . $prev . '</a>';
            } else {
                $compiled['prev'] = '<span class="' . $this->getPaginatedClass($classes, ['prev', 'disabled']) . '">' . $prev . '</span>';
            }
        }
        
        if ($pages <= (($mid_size + $end_size) * 2 + 1)) {
            for ($i = 1; $i <= $pages; $i++) {
                if ($page == $i) {
                    $compiled['num'][] = '<span class="' . $this->getPaginatedClass($classes, ['num', 'active']) . '">' . $i . '</span>';
                } else {
                    $compiled['num'][] = '<a href="' . $this->getPaginationLink($base, $i) . '" class="' . $this->getPaginatedClass($classes, 'num') . '">' . $i . '</a>';
                }
            }
        } elseif ($page <= ($mid_size + 1 + 1)) {
            for ($i = 1; $i <= $page + $mid_size; $i++) {
                if ($page == $i) {
                    $compiled['num'][] = '<span class="' . $this->getPaginatedClass($classes, ['num', 'active']) . '">' . $i . '</span>';
                } else {
                    $compiled['num'][] = '<a href="' . $this->getPaginationLink($base, $i) . '" class="' . $this->getPaginatedClass($classes, 'num') . '">' . $i . '</a>';
                }
            }
            
            $compiled['num'][] = '<span class="' . $this->getPaginatedClass($classes, ['num', 'disabled']) . '">' . $ellip . '</span>';
            
            for ($i = ($pages - ($end_size - 1)); $i <= $pages; $i++) {
                $compiled['num'][] = '<a href="' . $this->getPaginationLink($base, $i) . '" class="' . $this->getPaginatedClass($classes, 'num') . '">' . $i . '</a>';
            }
        } elseif ($page >= ($pages - ($mid_size + 1))) {
            for ($i = 1; $i <= $end_size; $i++) {
                $compiled['num'][] = '<a href="' . $this->getPaginationLink($base, $i) . '" class="' . $this->getPaginatedClass($classes, 'num') . '">' . $i . '</a>';
            }
            
            $compiled['num'][] = '<span class="' . $this->getPaginatedClass($classes, ['num', 'disabled']) . '">' . $ellip . '</span>';
            
            for ($i = $page - $mid_size; $i <= $pages; $i++) {
                if ($page == $i) {
                    $compiled['num'][] = '<span class="' . $this->getPaginatedClass($classes, ['num', 'active']) . '">' . $i . '</span>';
                } else {
                    $compiled['num'][] = '<a href="' . $this->getPaginationLink($base, $i) . '" class="' . $this->getPaginatedClass($classes, 'num') . '">' . $i . '</a>';
                }
            }
        } else {
            for ($i = 1; $i <= $end_size; $i++) {
                $compiled['num'][] = '<a href="' . $this->getPaginationLink($base, $i) . '" class="' . $this->getPaginatedClass($classes, 'num') . '">' . $i . '</a>';
            }
            
            $compiled['num'][] = '<span class="' . $this->getPaginatedClass($classes, ['num', 'disabled']) . '">' . $ellip . '</span>';
            
            for ($i = $page - $mid_size; $i <= $page + $mid_size; $i++) {
                if ($page == $i) {
                    $compiled['num'][] = '<span class="' . $this->getPaginatedClass($classes, ['num', 'active']) . '">' . $i . '</span>';
                } else {
                    $compiled['num'][] = '<a href="' . $this->getPaginationLink($base, $i) . '" class="' . $this->getPaginatedClass($classes, 'num') . '">' . $i . '</a>';
                }
            }
            
            $compiled['num'][] = '<span class="' . $this->getPaginatedClass($classes, ['num', 'disabled']) . '">' . $ellip . '</span>';
            
            for ($i = ($pages - ($end_size - 1)); $i <= $pages; $i++) {
                $compiled['num'][] = '<a href="' . $this->getPaginationLink($base, $i) . '" class="' . $this->getPaginatedClass($classes, 'num') . '">' . $i . '</a>';
            }
        }
        
        if ($next) {
            if ($page < $pages) {
                $compiled['next'] = '<a href="' . $this->getPaginationLink($base, $page + 1) . '" class="' . $this->getPaginatedClass($classes, 'next') . '">' . $next . '</a>';
            } else {
                $compiled['next'] = '<span class="' . $this->getPaginatedClass($classes, ['next', 'disabled']) . '">' . $next . '</span>';
            }
        }
        
        if ($last) {
            if ($page < $pages) {
                $compiled['last'] = '<a href="' . $this->getPaginationLink($base, $pages) . '" class="' . $this->getPaginatedClass($classes, 'last') . '">' . $last . '</a>';
            } else {
                $compiled['last'] = '<span class="' . $this->getPaginatedClass($classes, ['last', 'disabled']) . '">' . $last . '</span>';
            }
        }
        
        return $compiled;
    }
    
    public function __call($wp_service, $args)
    {
        return $this->app->make('wp.' . $wp_service);
    }
}
