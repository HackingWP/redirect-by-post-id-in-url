<?php
/**
 * Plugin Name: Redirect by Post ID in URL
 * Plugin URI:  https://github.com/HackingWP/post-ids-in-url
 * Description: Handles IDs in the URL
 * Version:     v0.1.0
 * Author:      @martin_adamko
 * Author URI:  http://twitter.com/martin_adamko
 * License: MIT
 */
/**
 *
 * 1. Hooks into WordPress setup called in wp-blog-header.php: `wp()`
 * 2. wp-includes/functions.php: `wp()` uses `class WP` and calls `$wp->main( $query_vars );`
 * 3. `$wp->main()`in  wp-includes/class-wp.php calls `$this->parse_request()`
 * 4. `$this->parse_request()` has `do_action_ref_array( 'parse_request', array( &$this ) );`
 *
 */

function redirectByPostByIdInUrl($wp)
{
    // @header('Content-Type: text/plain');
    // print_r($wp);

    // Lets compare the `p` argument with the request path
    if (isset($wp->query_vars) && isset($wp->query_vars['p']) && isset($wp->query_vars['name'])) {
        $slug = ltrim(parse_url(get_permalink($wp->query_vars['p']), PHP_URL_PATH), '/');

        // print_r(['slug',$wp->request,$slug]);

        // Everything is all right
        if ($slug === $wp->request) {
            return;
        }
    }

    // First 301 Aid
    if (isset($wp->query_vars) && isset($wp->query_vars['error']) && $wp->query_vars['error'] == 404) {
        global $wpdb;

        $slug = explode('-', $wpdb->esc_like(basename($wp->request)));

        // Mostly similar to FULL-TEXT MATCH () AGAINST ()
        $sql = "SELECT `ID`, `post_name`, CAST((`post_name` LIKE '".$slug[0]."%') * 0.5 AS DECIMAL(10,2))".
                                       "+ CAST((`post_name` LIKE '%".
            implode ("%') AS UNSIGNED)  + CAST((`post_name` LIKE '%", $slug).
                    "%') AS UNSIGNED) AS `count` FROM `wp_posts` WHERE `post_status` = 'publish' ORDER BY `count` DESC LIMIT 10;";

        if ($results = $wpdb->get_results($sql, ARRAY_A)) {
            $maxCount = $results[0]['count'];

            if ($maxCount > 0) {
                // Just keep those scored the same
                $results = array_filter($results, function($v) use ($maxCount) {
                    return $v['count'] === $maxCount;
                });

                // More options, lets see if...
                if (count($results) > 1) {
                    // ... the words appear in the order
                    $commonWords = array();

                    foreach ($results as &$result) {
                        if (empty($commonWords)) {
                            $commonWords = explode('-', $result['post_name']);
                        } else {
                            $commonWords = array_intersect($commonWords, explode('-', $result['post_name']));
                        }
                    }

                    // Increase value if they do...
                    if (count($commonWords) > 1) {
                        foreach ($results as &$result) {
                            if (preg_match('|'.implode('.+?', $commonWords).'|', $result['post_name'], $m)) {
                                $result['count']++;
                            }
                        }
                    }

                    // ... and sort if new order appears
                    uasort($results, function($a, $b) {
                        return $a['count'] <= $b['count'];
                    });
                }

                // print_r($results);
                // exit;

                wp_redirect(get_permalink($results[0]['ID']), 301);
                exit;
            }
        }
    }

    $currentPermalinkStructure = ltrim(get_option('permalink_structure'), '/');
    // echo $currentPermalinkStructure."\n";

    // Nothing to do
    if (!strstr($currentPermalinkStructure, '%post_id%')) {
        return;
    }

    global $wp_rewrite;

    $regexReplacements = array_combine($wp_rewrite->rewritecode, $wp_rewrite->rewritereplace);

    // WP_Rewrite changed?
    if (!array_key_exists('%post_id%', $regexReplacements)) {
        return;
    }

    $possiblePermalinkStructures = array();

    // Add possible permalink with e.g. slug suffixed after the ID
    if (preg_match('|^(.+%post_id%)|', $currentPermalinkStructure, $matches)) {
        $dirname  = explode('/', dirname($matches[0]));
        $basename = basename($matches[1]);

        while(!empty($dirname)) {
            $possiblePermalinkStructures[] = ltrim(implode('/', $dirname).'/'.$basename, '/');

            array_pop($dirname);
        }

        $dirname  = explode('/', dirname($matches[0]));
        // print_r($dirname);

        while(!empty($dirname)) {
            $possiblePermalinkStructures[] = ltrim(implode('/', $dirname).'/'.$basename, '/');

            array_shift($dirname);
        }

        $possiblePermalinkStructures[] = $basename;
    }

    // Add possible permalink with e.g. slug prefixed before ID
    if (preg_match('|^(.+)/(.+)(%post_id%)|', $currentPermalinkStructure, $matches)) {
        $dirname  = explode('/', dirname($matches[0]));
        $basename = $matches[3];

        while(!empty($dirname)) {
            $possiblePermalinkStructures[] = ltrim(implode('/', $dirname).'/'.$basename, '/');

            array_pop($dirname);
        }

        $dirname  = explode('/', dirname($matches[0]));
        // print_r($dirname);

        while(!empty($dirname)) {
            $possiblePermalinkStructures[] = ltrim(implode('/', $dirname).'/'.$basename, '/');

            array_shift($dirname);
        }

        $possiblePermalinkStructures[] = $basename;
    }

    // Make unique
    $possiblePermalinkStructures = array_unique($possiblePermalinkStructures);

    // echo "\nREQUEST: "; var_dump($wp->request); echo "\n\n";
    unset($matches);

    $id = null;

    foreach($possiblePermalinkStructures as $possiblePermalinkStructure) {
        $regex = '|^'.strtr($possiblePermalinkStructure, $regexReplacements).'[^/]*|';

        // echo $regex."\n\n";

        if (preg_match($regex, $wp->request, $matches)) {
            $id = $matches[count($matches) -1];

            $permalinkPath = ltrim(parse_url(get_permalink($id), PHP_URL_PATH), '/');

            // Suffix the endpoints like trackbacks, feed, comment-page,...
            $permalinkPath = $permalinkPath.str_replace($matches[0], '', $wp->request);

            // We should not even perform matching
            if ($permalinkPath === $wp->request) {
                break;
            }

            if ($permalinkPath !== $wp->request) {
                // var_dump(headers_sent());
                // print_r(['Match! ', $wp->request, $permalinkPath, home_url($permalinkPath), $id]);
                wp_redirect(home_url($permalinkPath), 301);

                exit;
                break;
            }
        }
    }
}

function removePostIdFromUrl($url)
{
    global $wp_rewrite;

    $regexReplacements = array_combine($wp_rewrite->rewritecode, $wp_rewrite->rewritereplace);
    $currentPermalinkStructure = get_option('permalink_structure');
    $currentPermalinkRegex = strtr($currentPermalinkStructure, $regexReplacements);

    $keys = array();

    if (preg_match_all('|%([^%]+)%|', $currentPermalinkStructure, $matches)) {
        $keys = $matches[1];

        $postIdIndex = array_search('post_id', $keys);

        // print_r([$keys, $postIdIndex]);

        if (preg_match("|{$currentPermalinkRegex}|", $url, $matches, PREG_OFFSET_CAPTURE)) {
            array_shift($matches);
            $postId = $matches[$postIdIndex][0];

            $postIDPositionStart = $matches[$postIdIndex][1];
            $postIDPositionEnd   = $matches[$postIdIndex][1] + strlen($postIDPositionStart);

            if ($url[$postIDPositionStart - 1] === '-') {
                $postIDPositionStart--;
            } elseif ($url[$postIDPositionEnd] === '-') {
                $postIDPositionEnd++;
            }

            // print_r([$matches, $postId, $postIDPositionStart, $postIDPositionEnd]);

            return substr($url, 0, $postIDPositionStart) . substr($url, $postIDPositionEnd);
        }
    }

    return $url;
}

if (!is_admin()) {
    add_action('parse_request', 'redirectByPostByIdInUrl');

    if (apply_filters('redirect-by-post-id-in-url-remove-from-canonical', true)) {
        add_filter('wpseo_canonical', 'removePostIdFromUrl');
    }
}
