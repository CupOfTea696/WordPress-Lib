<?php

namespace CupOfTea\WordPress\WordPress;

use CupOfTea\WordPress\Service;

use Illuminate\Support\Collection;

class Archive extends Post
{
    public function getLinks()
    {
        global $wpdb, $month;
        
        $baseUrl = rtrim(app('wp.post')->getUrl(get_post(get_option('page_for_posts'))), '/');
        $now = current_time('mysql');
        
        $result = $wpdb->get_results("
            SELECT
                MONTH(post_date) AS month,
                YEAR(post_date) AS year,
                COUNT(id) as posts
				FROM {$wpdb->posts}
				WHERE
                    post_status = 'publish'
                    and post_date <= STR_TO_DATE('{$now}', '%Y-%m-%d %H:%i:%S')
				    and post_type = 'post'
                GROUP BY month, year
				ORDER BY post_date DESC
            ");
        
        $dates = Collection::make($result);
        
        $dates = $dates->map(function($archive) use ($month, $baseUrl) {
            $archive->url = implode('/', [$baseUrl, $archive->year, $archive->month]);
            $archive->name = $month[zeroise($archive->month, 2)];
            
            return $archive;
        });
        
        $dates = $dates->groupBy('year');
        
        $dates = $dates->map(function($months, $year) use ($baseUrl) {
            return (object) [
                'url' => implode('/', [$baseUrl, $year]),
                'months' => $months
            ];
        });
        
        return $dates;
    }
}
