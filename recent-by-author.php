<?php
/*
Plugin Name: Recent By Author
Plugin URI: http://www.hawkwood.com/archives/35
Description: Sidebar widget to list all authors of a blog. Based on Robert Tsai's WP-Authors. Navigate to <a href="widgets.php">Presentation &rarr; Widgets</a> to add to your sidebar.
Author: Justin Hawkwood
Author URI: http://www.hawkwood.com/
Version: 1.1.0
*/

//*******************************************************
function list_recent_by_author($args = '') {
	global $wpdb;

	$defaults = array(
		'optioncount' => false, 'recent_number' => '0', 'exclude_admin' => true,
		'show_fullname' => false, 'hide_empty' => true,
		'feed' => '', 'feed_image' => '', 'feed_type' => '', 'echo' => true
	);

	$r = wp_parse_args( $args, $defaults );
	extract($r, EXTR_SKIP);

	$return = '';

	/** @todo Move select to get_authors(). 
	SELECT hkwd_wp_users.ID, hkwd_wp_users.user_nicename, hkwd_wp_usermeta.user_id, hkwd_wp_usermeta.meta_value FROM hkwd_wp_users, hkwd_wp_usermeta WHERE hkwd_wp_users.ID = hkwd_wp_usermeta.user_id AND hkwd_wp_usermeta.meta_key = 'hkwd_wp_user_level'
	*/
	$authors = $wpdb->get_results("SELECT $wpdb->users.ID, $wpdb->users.user_nicename, $wpdb->usermeta.meta_value FROM $wpdb->users, $wpdb->usermeta WHERE  $wpdb->users.ID = $wpdb->usermeta.user_id AND $wpdb->usermeta.meta_key = '".$wpdb->prefix."user_level' " . ($exclude_admin ? "AND user_login <> 'admin' " : '') . "ORDER BY display_name");

	$author_count = array();
	foreach ((array) $wpdb->get_results("SELECT DISTINCT post_author, COUNT(ID) AS count FROM $wpdb->posts WHERE post_type = 'post' AND " . get_private_posts_cap_sql( 'post' ) . " GROUP BY post_author") as $row) {
		$author_count[$row->post_author] = $row->count;
	}

	foreach ( (array) $authors as $author ) {
		$author = get_userdata( $author->ID );
		$posts = (isset($author_count[$author->ID])) ? $author_count[$author->ID] : 0;
		$name = $author->display_name;

		if ( $show_fullname && ($author->first_name != '' && $author->last_name != '') )
			$name = "$author->first_name $author->last_name";

		if ( !($posts == 0 && $hide_empty) )
			$return .= '<li>';
		if ( $posts == 0 ) {
			if ( !$hide_empty )
				$link = $name;
		} else {
			$link = '<a href="' . get_author_posts_url($author->ID, $author->user_nicename) . '" title="' . sprintf(__("Posts by %s"), attribute_escape($author->display_name)) . '">' . $name . '</a>';

			if ( (! empty($feed_image)) || (! empty($feed)) ) {
				$link .= ' ';
				if (empty($feed_image))
					$link .= '(';
				$link .= '<a href="' . get_author_rss_link(0, $author->ID, $author->user_nicename) . '"';

				if ( !empty($feed) ) {
					$title = ' title="' . $feed . '"';
					$alt = ' alt="' . $feed . '"';
					$name = $feed;
					$link .= $title;
				}

				$link .= '>';

				if ( !empty($feed_image) )
					$link .= "<img src=\"$feed_image\" style=\"border: none;\"$alt$title" . ' />';
				else
					$link .= $name;

				$link .= '</a>';

				if ( empty($feed_image) )
					$link .= ')';
			}

			if ( $optioncount )
				$link .= ' ('. $posts . ')';

		}

		if ( !($posts == 0 && $hide_empty) ) {
			$return .= $link . '</li>';
			if ($recent_number > 0) {
			
				$last_posts = (array)$wpdb->get_results("
			SELECT ID, post_title
			FROM {$wpdb->posts}
			WHERE post_author = ".$author->ID." 
			AND post_status = 'publish' 
			AND post_date < NOW() 
			AND post_type = 'post'
			GROUP BY ID 
			ORDER BY post_date DESC 
			LIMIT $recent_number
				");
				
				if ($last_posts) { 
					$return .= "\n<li><ul>\n";
					foreach ($last_posts as $last_post) {
						$return .= '<li><a href="' . get_permalink($last_post->ID) . '">' . $last_post->post_title . '</a></li>' . "\n";
					
					}
					$return .= "</ul></li>\n";
				}
			}
		}
	}
	if ( !$echo )
		return $return;
	echo $return;
}
//*****************************************************

//$locale = get_locale();
//$mofile = dirname(__FILE__) . "/locale/$locale.mo";
//load_textdomain('recent-by-author', $mofile);

function widget_recentbyauthor_init() {
	if ( !function_exists('register_sidebar_widget') )
		return;

	function widget_recent_by_author($args) {
//		echo '<!-- widget_recent_by_author -->';
		extract($args);

		$options = get_option('widget_recent_by_author');
		$c = $options['count'] ? true : false;
		$n = ($options['number'] > 0) ? $options['number'] : 0;
		$f = $options['show_fullname'] ? true : false;
		$hide = $options['hide_empty'] ? true : false;
		$excludeadmin = $options['exclude_admin'] ? true : false;
		$title = empty($options['title']) ? __('Recent By Author', 'recent-by-author') :
			$options['title'];

		$author_args = array(
			'orderby' => 'name',
			'optioncount' => $c,
			'recent_number' => $n,
			'show_fullname' => $f,
			'hide_empty' => $hide,
			'exclude_admin' => $excludeadmin,
			'post_types' => 'post',
			);

		print <<<EOM
		$before_widget
		$before_title$title$after_title
		<ul>
EOM;
		if ($n == 0) wp_list_authors($author_args);
		else list_recent_by_author($author_args);
	//	echo ddpa_show_posts();

		print <<<EOM
		</ul>
		$after_widget
EOM;
	}

	function widget_recent_by_author_control() {
//		echo '<!-- widget_recent_by_author_control -->';
		$defaults = array(
			'title' => __('Recent By Author', 'recent-by-author'),
			'count' => true,
			'show_fullname' => false,
			'hide_empty' => true,
			'exclude_admin' => true,
			);
		if (!($options = get_option('widget_recent_by_author')))
			$options = array();
		$options = array_merge($defaults, $options);
		if ( $_POST['recent-by-author-submit'] ) {
			$options['title'] = strip_tags(stripslashes($_POST['recent-by-author-title']));
			$options['count'] = isset($_POST['recent-by-author-count']);
			$options['number'] = ($_POST['recent-by-author-number'] > 0) ? $_POST['recent-by-author-number'] : 0;
			$options['show_fullname'] = isset($_POST['recent-by-author-show_fullname']);
			$options['hide_empty'] = isset($_POST['recent-by-author-hide_empty']);
			$options['exclude_admin'] = isset($_POST['recent-by-author-exclude_admin']);
			update_option('widget_recent_by_author', $options);
		}
		$title = attribute_escape($options['title']);
		$count = $options['count'] ? 'checked="checked"' : '';
		if ( !$number = (int) $options['number'] ) $number = 0;
		$show_fullname = $options['show_fullname'] ? 'checked="checked"' : '';
		$hide_empty = $options['hide_empty'] ? 'checked="checked"' : '';
		$exclude_admin = $options['exclude_admin'] ? 'checked="checked"' : '';
?>

						<p><label for="recent-by-author-title"><?php _e('Title:', 'recent-by-author'); ?> <input style="width: 250px;" id="recent-by-author-title" name="recent-by-author-title" type="text" value="<?php echo $title; ?>" /></label></p>
						<p style="text-align:right;margin-right:40px;"><label for="recent-by-author-count"><?php _e('Show post counts', 'recent-by-author'); ?> <input class="checkbox" type="checkbox" <?php echo $count; ?> id="recent-by-author-count" name="recent-by-author-count" /></label></p>
						<p style="text-align:right;margin-right:40px;"><label for="recent-by-author-number"><?php _e('Number of recent posts to show:', 'recent-by-author'); ?> <input style="width: 25px; text-align: center;" id="recent-by-author-number" name="recent-by-author-number" type="text" value="<?php echo $number; ?>" /></label><br />
				<small><?php _e('(0 means show none)'); ?></small></p>
						<p style="text-align:right;margin-right:40px;"><label for="recent-by-author-show_fullname"><?php _e('Show full names', 'recent-by-author'); ?> <input class="checkbox" type="checkbox" <?php echo $show_fullname; ?> id="recent-by-author-show_fullname" name="recent-by-author-show_fullname" /></label></p>
						<p style="text-align:right;margin-right:40px;"><label for="recent-by-author-hide_empty"><?php _e('Hide empty authors', 'recent-by-author'); ?> <input class="checkbox" type="checkbox" <?php echo $hide_empty; ?> id="recent-by-author-hide_empty" name="recent-by-author-hide_empty" /></label></p>
						<p style="text-align:right;margin-right:40px;"><label for="recent-by-author-exclude_admin"><?php _e('Exclude admin', 'recent-by-author'); ?> <input class="checkbox" type="checkbox" <?php echo $exclude_admin; ?> id="recent-by-author-exclude_admin" name="recent-by-author-exclude_admin" /></label></p>
						<input type="hidden" id="recent-by-author-submit" name="recent-by-author-submit" value="1" />
<?php
	}

	register_sidebar_widget(__('Recent By Author', 'recent-by-author'), 'widget_recent_by_author');							
	register_widget_control(__('Recent By Author', 'recent-by-author'), 'widget_recent_by_author_control', 300, 200);	
}

function widget_recentbyauthor_deactivate() {
	delete_option('widget_recent_by_author');
}

register_deactivation_hook(__FILE__, 'widget_recentbyauthor_deactivate');
add_action('plugins_loaded', 'widget_recentbyauthor_init');

?>
