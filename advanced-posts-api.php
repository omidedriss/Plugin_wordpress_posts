<?php
/**
 * Plugin Name: Advanced Posts API
 * Description: ارائه API پیشرفته برای دریافت اطلاعات پست‌ها با قابلیت جستجو
 * Version: 1.0.0
 * Author: omidedriss
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class AdvancedPostsAPI {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_custom_routes'));
    }
    
    public function register_custom_routes() {
        // روت اول: لیست پست‌ها
        register_rest_route('advanced/v1', '/posts', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_posts_list'),
            'permission_callback' => '__return_true'
        ));
        
        // روت دوم: دریافت پست بر اساس ID
        register_rest_route('advanced/v1', '/posts/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_single_post'),
            'permission_callback' => '__return_true'
        ));
        
        // روت سوم: جستجوی پست‌ها بر اساس عنوان
        register_rest_route('advanced/v1', '/posts/search/title', array(
            'methods' => 'GET',
            'callback' => array($this, 'search_posts_by_title'),
            'permission_callback' => '__return_true'
        ));
        
        // روت چهارم: جستجوی پست‌ها بر اساس دسته
        register_rest_route('advanced/v1', '/posts/search/category', array(
            'methods' => 'GET',
            'callback' => array($this, 'search_posts_by_category'),
            'permission_callback' => '__return_true'
        ));
    }
    
    public function get_posts_list($request) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => isset($request['per_page']) ? absint($request['per_page']) : 10,
            'paged' => isset($request['page']) ? absint($request['page']) : 1
        );
        
        // فیلتر بر اساس دسته اگر وجود دارد
        if (isset($request['category'])) {
            $args['category_name'] = sanitize_text_field($request['category']);
        }
        
        $query = new WP_Query($args);
        $posts = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $post;
                
                $posts[] = $this->prepare_post_data($post);
            }
        }
        
        wp_reset_postdata();
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $posts,
            'total' => $query->found_posts,
            'current_page' => $args['paged'],
            'total_pages' => $query->max_num_pages
        ), 200);
    }
    
    public function get_single_post($request) {
        $post_id = $request['id'];
        
        // بررسی وجود پست
        $post = get_post($post_id);
        
        if (empty($post) || $post->post_status !== 'publish') {
            return new WP_Error('post_not_found', 'پست مورد نظر یافت نشد', array('status' => 404));
        }
        
        $post_data = $this->prepare_post_data($post, true);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $post_data
        ), 200);
    }
    
    public function search_posts_by_title($request) {
        if (!isset($request['title']) || empty($request['title'])) {
            return new WP_Error('missing_title', 'پارامتر title الزامی است', array('status' => 400));
        }
        
        $search_title = sanitize_text_field($request['title']);
        
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => isset($request['per_page']) ? absint($request['per_page']) : 10,
            'paged' => isset($request['page']) ? absint($request['page']) : 1,
            's' => $search_title
        );
        
        // فیلتر بر اساس دسته اگر وجود دارد
        if (isset($request['category'])) {
            $args['category_name'] = sanitize_text_field($request['category']);
        }
        
        $query = new WP_Query($args);
        $posts = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $post;
                
                $posts[] = $this->prepare_post_data($post);
            }
        }
        
        wp_reset_postdata();
        
        return new WP_REST_Response(array(
            'success' => true,
            'search_term' => $search_title,
            'data' => $posts,
            'total' => $query->found_posts,
            'current_page' => $args['paged'],
            'total_pages' => $query->max_num_pages
        ), 200);
    }
    
    public function search_posts_by_category($request) {
        if (!isset($request['category']) || empty($request['category'])) {
            return new WP_Error('missing_category', 'پارامتر category الزامی است', array('status' => 400));
        }
        
        $category = sanitize_text_field($request['category']);
        
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => isset($request['per_page']) ? absint($request['per_page']) : 10,
            'paged' => isset($request['page']) ? absint($request['page']) : 1,
            'category_name' => $category
        );
        
        $query = new WP_Query($args);
        $posts = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $post;
                
                $posts[] = $this->prepare_post_data($post);
            }
        }
        
        wp_reset_postdata();
        
        // دریافت اطلاعات دسته
        $category_info = get_category_by_slug($category);
        $category_data = null;
        
        if ($category_info) {
            $category_data = array(
                'id' => $category_info->term_id,
                'name' => $category_info->name,
                'slug' => $category_info->slug,
                'description' => $category_info->description,
                'count' => $category_info->count
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'category' => $category_data,
            'data' => $posts,
            'total' => $query->found_posts,
            'current_page' => $args['paged'],
            'total_pages' => $query->max_num_pages
        ), 200);
    }
    
    private function prepare_post_data($post, $include_content = false) {
        // دریافت دسته‌های پست
        $categories = get_the_category($post->ID);
        $category_list = array();
        
        foreach ($categories as $category) {
            $category_list[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug
            );
        }
        
        // دریافت اطلاعات نویسنده
        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        
        // دریافت آدرس تصویر اصلی
        $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'full');
        
        // آماده‌سازی داده پایه
        $post_data = array(
            'id' => $post->ID,
            'title' => get_the_title($post->ID),
            'slug' => $post->post_name,
            'categories' => $category_list,
            'author' => array(
                'id' => $author_id,
                'name' => $author_name
            ),
            'featured_image' => $thumbnail_url ? $thumbnail_url : '',
            'date' => get_the_date('Y-m-d H:i:s', $post->ID),
            'excerpt' => get_the_excerpt($post->ID),
            'post_url' => get_permalink($post->ID)
        );
        
        // اضافه کردن محتوا اگر درخواست شده
        if ($include_content) {
            $post_data['content'] = apply_filters('the_content', $post->post_content);
            $post_data['site_url'] = get_site_url();
        }
        
        return $post_data;
    }
}

// راه‌اندازی پلاگین
new AdvancedPostsAPI();
