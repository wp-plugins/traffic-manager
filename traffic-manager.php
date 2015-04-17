<?php
/**
Plugin Name: Traffic Manager
Plugin Tag: traffic, stats, google, analytics, sitemaps, sitemaps.xml, bing, yahoo
Description: <p>You will be able to manage the Internet traffic on your website and to enhance it.</p><p>You may: </p><ul><li>see statistics on users browsing your website; </li><li>see statistics on web crawler;</li><li>inform Google, Bing, etc. when your site is updated; </li><li>geolocate the visits on your site;</li><li>configure your statistics cookies to be in conformity with the CNIL regulations;</li><li>configure Google Analytics;</li><li>add sitemap.xml information on your website;</li></ul><p>This plugin is under GPL licence</p>
Version: 1.4.3
Framework: SL_Framework
Author: SedLex
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/plugins/traffic-manager/
License: GPL3
*/

//Including the framework in order to make the plugin work

require_once('core.php') ; 


/** ====================================================================================================================================================
* This class has to be extended from the pluginSedLex class which is defined in the framework
*/
class traffic_manager extends pluginSedLex {

	/** ====================================================================================================================================================
	* Plugin initialization
	* 
	* @return void
	*/
	static $instance = false;

	protected function _init() {
		global $wpdb ; 
		
		// Name of the plugin (Please modify)
		$this->pluginName = 'Traffic Manager' ; 
		$this->maxTime = 600 ; 
		
		// The structure of the SQL table if needed (for instance, 'id_post mediumint(9) NOT NULL, short_url TEXT DEFAULT '', UNIQUE KEY id_post (id_post)') 
		$this->tableSQL = "id mediumint(9) NOT NULL AUTO_INCREMENT, count mediumint(9) NOT NULL, uniq_visit mediumint(9) NOT NULL, viewed BOOL, type VARCHAR(15), ip VARCHAR(100), browserName VARCHAR(30), browserVersion VARCHAR(100), platformName VARCHAR(30), platformVersion VARCHAR(30),  browserUserAgent VARCHAR(250), referer VARCHAR(500), page VARCHAR(500), time DATETIME, singleCookie VARCHAR(100), refreshNumber mediumint(9) NOT NULL, geolocate_state TEXT, geolocate TEXT, UNIQUE KEY id (id)" ; 

		// The name of the SQL table (Do no modify except if you know what you do)
		$this->table_name = $wpdb->prefix . "pluginSL_" . get_class() ; 

		//Configuration of callbacks, shortcode, ... (Please modify)
		// For instance, see 
		//	- add_shortcode (http://codex.wordpress.org/Function_Reference/add_shortcode)
		//	- add_action 
		//		- http://codex.wordpress.org/Function_Reference/add_action
		//		- http://codex.wordpress.org/Plugin_API/Action_Reference
		//	- add_filter 
		//		- http://codex.wordpress.org/Function_Reference/add_filter
		//		- http://codex.wordpress.org/Plugin_API/Filter_Reference
		// Be aware that the second argument should be of the form of array($this,"the_function")
		// For instance add_action( "the_content",  array($this,"modify_content")) : this function will call the function 'modify_content' when the content of a post is displayed
		
		add_action( 'wp_ajax_UserWebStat', array( $this, 'UserWebStat'));
		add_action( 'wp_ajax_nopriv_UserWebStat', array( $this, 'UserWebStat'));
		
		add_action( 'wp_ajax_updateCurrentUser', array( $this, 'update_current_user'));
		
		add_action('wp_head', array( $this, 'add_meta_tags'));
		
		add_action("save_post", array( $this, "create_sitemap_upon_save"));
		
		add_shortcode( 'cookies_buttons', array( $this, 'cookies_buttons_shortcode' ) );
		add_shortcode( 'google_cookies_buttons', array( $this, 'google_cookies_buttons_shortcode' ) );

		// Important variables initialisation (Do not modify)
		$this->path = __FILE__ ; 
		$this->pluginID = get_class() ; 
		
		// activation and deactivation functions (Do not modify)
		register_activation_hook(__FILE__, array($this,'install'));
		register_deactivation_hook(__FILE__, array($this,'deactivate'));
		register_uninstall_hook(__FILE__, array('traffic_manager','uninstall_removedata'));
	}
	
	/** ====================================================================================================================================================
	* In order to uninstall the plugin, few things are to be done ... 
	* (do not modify this function)
	* 
	* @return void
	*/
	
	public function uninstall_removedata () {
		global $wpdb ;
		// DELETE OPTIONS
		delete_option('traffic_manager'.'_options') ;
		if (is_multisite()) {
			delete_site_option('traffic_manager'.'_options') ;
		}
		
		// DELETE SQL
		if (function_exists('is_multisite') && is_multisite()){
			$old_blog = $wpdb->blogid;
			$old_prefix = $wpdb->prefix ; 
			// Get all blog ids
			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM ".$wpdb->blogs));
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				$wpdb->query("DROP TABLE ".str_replace($old_prefix, $wpdb->prefix, $wpdb->prefix . "pluginSL_" . 'traffic_manager')) ; 
			}
			switch_to_blog($old_blog);
		} else {
			$wpdb->query("DROP TABLE ".$wpdb->prefix . "pluginSL_" . 'traffic_manager' ) ; 
		}
		
		// DELETE FILES if needed
		//SLFramework_Utils::rm_rec(WP_CONTENT_DIR."/sedlex/my_plugin/"); 
		$plugins_all = 	get_plugins() ; 
		$nb_SL = 0 ; 	
		foreach($plugins_all as $url => $pa) {
			$info = pluginSedlex::get_plugins_data(WP_PLUGIN_DIR."/".$url);
			if ($info['Framework_Email']=="sedlex@sedlex.fr"){
				$nb_SL++ ; 
			}
		}
		if ($nb_SL==1) {
			SLFramework_Utils::rm_rec(WP_CONTENT_DIR."/sedlex/"); 
		}
	}

	/**====================================================================================================================================================
	* Function called when the plugin is activated
	* For instance, you can do stuff regarding the update of the format of the database if needed
	* If you do not need this function, you may delete it.
	*
	* @return void
	*/
	
	public function _update() {
		global $wpdb ; 
		// This update aims at adding the nb_hits fields 
		if ( !$wpdb->get_var("SHOW COLUMNS FROM ".$this->table_name." LIKE 'geolocate'")  ) {
			$wpdb->query("ALTER TABLE ".$this->table_name." ADD geolocate_state TEXT;");
			$wpdb->query("ALTER TABLE ".$this->table_name." ADD geolocate TEXT;");
		}  
		// This update aims at increasing the size of the size of the page field
		$wpdb->query("ALTER TABLE `wp_pluginSL_traffic_manager` CHANGE `page` `page` VARCHAR(500)") ;
	}
	
	
	/** ====================================================================================================================================================
	* Init CSS for the admin side
	*
	* @return void
	*/
	
	function _admin_css_load() {
		$this->add_css(plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)) .'css/jquery-jvectormap-1.2.2.css') ; 
	}
	
	/** ====================================================================================================================================================
	* Init javascript for the admin side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('my_plugin_script', plugins_url('/script.js', __FILE__));</code>
	*
	* @return void
	*/
	
	function _admin_js_load() {	
		global $wpdb ; 
		
		$this->add_js(plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)) .'js/raphael-min.js') ; 
		$this->add_js(plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)) .'js/elycharts.min.js') ; 
					
		if (($this->get_param('localwebstat'))&&($this->get_param('local_current_user'))) {
			ob_start() ; 
			?>
		
			function update_current_user() {
				var arguments = {
					action: 'updateCurrentUser'
				} ;
	
				//POST the data and append the results to the results div
				jQuery.post(ajaxurl, arguments, function(response) {
						jQuery("#nb_current_user").html(response);
						var t=setTimeout("update_current_user()",2000);
				}); }

			// We launch the callback
			if (window.attachEvent) {window.attachEvent('onload', update_current_user);}
			else if (window.addEventListener) {window.addEventListener('load', update_current_user, false);}
			else {document.addEventListener('load', update_current_user, false);} 
			
			<?php
			$java = ob_get_clean() ; 
			$this->add_inline_js($java) ; 
		}
		
		if (($this->get_param('geolocate_show_world'))||($this->get_param('geolocate_show_europe'))||(trim($this->get_param('geolocate_show_state'))!="")){
			$this->add_js(plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)) .'js/jquery-jvectormap-1.2.2.min.js') ; 
		}
		
		if ($this->get_param('geolocate_show_world')){
			$this->add_js(plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)) .'js/jquery-jvectormap-world-mill-en.js') ; 
			ob_start();
				$results = $wpdb->get_results("SELECT geolocate, count(*) as nombre FROM ".$this->table_name." WHERE type='single' AND geolocate_state != '' AND time BETWEEN '".date_i18n('Y-m-d 0:0:0', strtotime(date_i18n("Y-m-d").' -'.$this->get_param('local_keep_detailed_info').' day'))."' AND '".date_i18n('Y-m-d H:i:s')."' GROUP BY geolocate_state") ; 
				echo "\r\nvar gdpData = {\r\n" ; 
				$first = true;
				foreach ($results as $r){
					if (!$first){
						echo ", " ; 
					}
					$rus = @unserialize($r->geolocate) ; 
					if (is_array($rus)){
						$first = false ; 
						echo '"'.$rus['countryCode'].'":'.$r->nombre ; 
					}
				}
				echo "};\r\n" ; 
			?>
				jQuery(function(){
					jQuery('#geolocate_show_world').vectorMap({
						map: 'world_mill_en',
						backgroundColor: '#A1A1A1',
						series: {
						    regions: [{
						      values: gdpData,
							  hoverOpacity: 0.7,
    						  hoverColor: false,
						      scale: ['#C8EEFF', '#000066'],
						      normalizeFunction: 'polynomial'
						    }]
						},
						onRegionLabelShow: function(e, el, code){
						    el.html(el.html()+' ('+gdpData[code]+')');
						}
					});
				}) ; 
				
			<?php
			
			
				
			$java = ob_get_clean() ; 
			$this->add_inline_js($java) ;
		}
		
		if ($this->get_param('geolocate_show_europe')){
			$this->add_js(plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)) .'js/jquery-jvectormap-europe-mill-en.js') ; 
			ob_start();
				$results = $wpdb->get_results("SELECT geolocate, count(*) as nombre FROM ".$this->table_name." WHERE type='single' AND geolocate_state != '' AND time BETWEEN '".date_i18n('Y-m-d 0:0:0', strtotime(date_i18n("Y-m-d").' -'.$this->get_param('local_keep_detailed_info').' day'))."' AND '".date_i18n('Y-m-d H:i:s')."' GROUP BY geolocate_state") ; 
				echo "\r\nvar gdpData_europe = {\r\n" ; 
				$first = true;
				foreach ($results as $r){
					if (!$first){
						echo ", " ; 
					}
					$rus = @unserialize($r->geolocate) ; 
					if (is_array($rus)){
						$first = false ; 
						echo '"'.$rus['countryCode'].'":'.$r->nombre ; 
					}
				}
				echo "};\r\n"
			?>
				jQuery(function(){
					jQuery('#geolocate_show_europe').vectorMap({
						map: 'europe_mill_en',
						backgroundColor: '#A1A1A1',
						series: {
						    regions: [{
						      values: gdpData_europe,
							  hoverOpacity: 0.7,
    						  hoverColor: false,
						      scale: ['#C8EEFF', '#000066'],
						      normalizeFunction: 'polynomial'
						    }]
						},
						onRegionLabelShow: function(e, el, code){
						    el.html(el.html()+' ('+gdpData_europe[code]+')');
						}
					});
				}) ; 
			<?php 
				
			$java = ob_get_clean() ; 
			$this->add_inline_js($java) ;
		}
		
		if (trim($this->get_param('geolocate_show_state'))!=""){
			$state = explode(',', $this->get_param('geolocate_show_state')) ;
			foreach ($state as $st) {
				$this->add_js(plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)) .'js/jquery-jvectormap-'.$st.'.js') ; 
				ob_start();
					$results = $wpdb->get_results("SELECT geolocate, count(*) as nombre FROM ".$this->table_name." WHERE type='single' AND geolocate_state != '' AND time BETWEEN '".date_i18n('Y-m-d 0:0:0', strtotime(date_i18n("Y-m-d").' -'.$this->get_param('local_keep_detailed_info').' day'))."' AND '".date_i18n('Y-m-d H:i:s')."' GROUP BY geolocate ORDER BY nombre DESC ") ; 
					// markers
					echo "\r\nvar markers_".sha1($st)." = [\r\n" ; 
					$first = true;
					foreach ($results as $r){
						if (!$first){
							echo ", " ; 
						}
						$rus = @unserialize($r->geolocate) ; 
						$rus['cityName'] = str_replace("'", " ", $rus['cityName']) ; 
						if (is_array($rus)){
							$first = false ; 
							echo "{latLng: [".$rus['latitude'].", ".$rus['longitude']."], name: '".sprintf(__('%s visits', $this->pluginID), $r->nombre)." - ".$rus['cityName']." (".$rus['zipCode'].")'}" ; 
						}
					}
					echo "];\r\n" ;
					
					// taille
					echo "\r\nvar size_".sha1($st)." = [\r\n" ; 
					$first = true;
					foreach ($results as $r){
						if (!$first){
							echo ", " ; 
						}
						$rus = @unserialize($r->geolocate) ; 
						$rus['cityName'] = str_replace("'", " ", $rus['cityName']) ; 
						if (is_array($rus)){
							$first = false ; 
							echo $r->nombre ; 
						}
					}
					echo "];\r\n";
					
				?>
					jQuery(function(){
						jQuery('#geolocate_show_<?php echo sha1($st) ; ?>').vectorMap({
							map: '<?php echo str_replace('-', '_', $st) ; ?>',
							scaleColors: ['#C8EEFF', '#000066'],
							normalizeFunction: 'polynomial',
							hoverOpacity: 0.7,
							hoverColor: false,
							markerStyle: {
							  initial: {
								fill: '#F8E23B',
								stroke: '#383f47'
							  }
							},
							backgroundColor: '#A1A1A1',
							markers: markers_<?php echo sha1($st) ; ?>,
							series: {
								markers: [{
      							  attribute: 'fill',
      							  scale: ['#C8EEFF', '#000066'],
      							  values: size_<?php echo sha1($st) ; ?>
      						    },{
								  attribute: 'r',
      							  scale: [2, 40],
      							  values: size_<?php echo sha1($st) ; ?>
								}]
							}
						});
					}) ; 
				<?php 
				
				$java = ob_get_clean() ; 
				$this->add_inline_js($java) ;
			}
		}

		return ; 
	}
	
	/** ====================================================================================================================================================
	* Top load the metadata
	*
	* @return void
	*/
	
	function add_meta_tags() {	
		global $post ; 
		$og = "" ; 
		$norm = "" ; 
		$dc = "" ; 
		
		if ($this->get_param('metatag')) {
			$norm .= '<meta name="robots" content="index,follow"/>'."\n" ; 
			$norm .= '<meta name="googlebot" content="index,follow"/>'."\n" ; 
			$dc .='<meta name="DC.format" content="text/html"/>'."\n" ; 
			if (is_single()) {
				$og .= '<meta property="og:type" content="article"/>'."\n" ; 
				$og .= '<meta property="og:url" content="'.get_permalink().'"/>'."\n" ; 
			} else {
				$og .= '<meta property="og:url" content="'.home_url().'"/>'."\n" ; 
			}
			// TITLE
			if ($this->get_param('metatag_title')) {
				if (is_single()) {
					$dc .= '<meta name="DC.title" content="'.$post->post_title.'"/>'."\n" ; 
					$og .= '<meta property="og:title" content="'.$post->post_title.'"/>'."\n" ; 
				} else {
					$dc .= '<meta name="DC.title" content="'.get_bloginfo("title").'"/>'."\n" ; 
					$og .= '<meta property="og:title" content="'.get_bloginfo("title").'"/>'."\n" ; 
				}
				$og .= '<meta property="og:site_name" content="'.get_bloginfo("title").'"/>'."\n" ; 
			}
			// DESCRIPTION	
			if ($this->get_param('metatag_description')) {
				if (is_single()) {
					$shortcontent = trim(preg_replace ('/\[[^\]]*?\]/', '', wp_trim_words($post->post_content)));
					$norm .= '<meta name="description" content="'.$shortcontent.'"/>'."\n" ; 
					$dc .= '<meta name="DC.description" content="'.$shortcontent.'"/>'."\n" ; 
					$dc .= '<meta name="DC.description.abstract" content="'.$shortcontent.'"/>'."\n" ; 
					$og .= '<meta property="og:description" content="'.$shortcontent.'"/>'."\n" ; 
				} else {
					$norm .= '<meta name="description" content="'.get_bloginfo("description").'"/>'."\n" ; 
					$dc .= '<meta name="DC.description" content="'.get_bloginfo("description").'"/>'."\n" ; 
					$dc .= '<meta name="DC.description.abstract" content="'.get_bloginfo("description").'"/>'."\n" ; 
					$og .= '<meta property="og:description" content="'.get_bloginfo("description").'"/>'."\n" ; 
				}
			}
			// COPYRIGHT	
			if ($this->get_param('metatag_copyright')) {
				if ($this->get_param('metatag_copyright_override')!="") {
					$dc .= '<meta name="DC.right" content="'.$this->get_param('metatag_copyright_override').'"/>'."\n" ; 
				} else {
					$dc .= '<meta name="DC.right" content="Copyright - '.get_bloginfo('name').' - '.get_bloginfo('url').' - All right reserved"/>'."\n" ; 
				}
			}
			// DATE	
			if ($this->get_param('metatag_date')) {
				$formatdate = "Y-m-d\TH:i:sO" ;
				if (is_single()) {
					$dc .= '<meta name="DC.date" content="'.date($formatdate,strtotime($post->post_date)).'"/>'."\n" ; 
					$dc .= '<meta name="DC.date.created" content="'.date($formatdate,strtotime($post->post_date)).'"/>'."\n" ; 
					$dc .= '<meta name="DC.date.available" content="'.date($formatdate,strtotime($post->post_date)).'"/>'."\n" ; 
					$dc .= '<meta name="DC.date.modified" content="'.date($formatdate,strtotime($post->post_modified)).'"/>'."\n" ; 
					$og .= '<meta property="og:article:published_time" content="'.date($formatdate,strtotime($post->post_date)).'"/>'."\n" ; 
					$og .= '<meta property="og:article:modified_time" content="'.date($formatdate,strtotime($post->post_modified)).'"/>'."\n" ; 
				} else {
				 	$args=array(
					  'orderby'=> 'modified',
					  'order' => 'DESC',
					  'post_type' => 'any',
					  'post_status' => 'publish',
					  'posts_per_page' => 1,
					);
					$myposts = get_posts($args);
					$date = "1970-1-1 00:00:00" ; 
					$modified_date = "1970-1-1 00:00:00" ; 
					foreach( $myposts as $p ) {
						$date = $p->post_date ; 
						$modified_date = $p->post_modified ; 
					}	
					$dc .= '<meta name="DC.date" content="'.date($formatdate, strtotime($date)).'"/>'."\n" ; 
					$dc .= '<meta name="DC.date.created" content="'.date($formatdate, strtotime($date)).'"/>'."\n" ; 
					$dc .= '<meta name="DC.date.available" content="'.date($formatdate, strtotime($date)).'"/>'."\n" ; 
					$dc .= '<meta name="DC.date.modified" content="'.date($formatdate, strtotime($modified_date)).'"/>'."\n" ; 
					$og .= '<meta property="og:article:published_time" content="'.date($formatdate, strtotime($date)).'"/>'."\n" ; 
					$og .= '<meta property="og:article:modified_time" content="'.date($formatdate, strtotime($modified_date)).'"/>'."\n" ; 
				}
			}
			// AUTHOR	
			if ($this->get_param('metatag_author')) {
				$dc .= '<meta name="DC.publisher" content="'.get_bloginfo('name').' - '.get_bloginfo('url').'"/>'."\n" ; 
				if ($this->get_param('metatag_author_override')!="") {
					$dc .= '<meta name="DC.creator" content="'.$this->get_param('metatag_author_override').'"/>'."\n" ; 
				} else {
					if (is_single()) {
						$user_p = get_userdata($post->post_author) ; 
						$dc .= '<meta name="DC.creator" content="'.trim($user_p->user_firstname.' '.$user_p->user_lastname).'"/>'."\n" ; 
					} else {
						// pas d'auteur sur ces pages 
					}
				}
			}
			
			// KEYWORDS	
			if ($this->get_param('metatag_keywords')) {
				if (is_single()) {
					$kw = "" ; 
					//category
					$cat_array = get_the_category() ; 
					if (is_array($cat_array)) {
						foreach($cat_array as $category) { 
							if ($kw != "") {
								$kw .= "; " ; 
							} 
							$kw .= $category->cat_name ; 
							$og .= '<meta property="og:article:section" content="'.$category->cat_name.'"/>'."\n" ; 

						}
					}
					//keywords
					$tag_array = get_the_tags() ; 
					if (is_array($tag_array)) {
						foreach($tag_array as $tag) { 
							if ($kw != "") {
								$kw .= "; " ; 
							} 
							$kw .= $tag->name ; 
							$og .= '<meta property="og:article:tag" content="'.$tag->name.'"/>'."\n" ; 

						}
					}
					$dc .= '<meta name="DC.subject" content="'.$kw.'"/>'."\n" ; 
				} else {
					// pas de mots clefs pour ces pages
				}
			}
			
			// TABLE OF CONTENT	
			if ($this->get_param('metatag_toc')) {
				if (is_single()) {
					$content = $post->post_content ; 
					$toc = "" ; 
					preg_match_all("#<h([1-6])>(.*?)</h[1-6]>#iu", $content, $matches, PREG_SET_ORDER) ;
					foreach($matches as $m) {
						if ($toc != "") {
							$toc .= " --- " ; 
						}	
						$toc .= $m[2] ; 
					} 
					
					$dc .= '<meta name="DC.description.tableOfContents" content="'.$toc.'"/>'."\n" ; 
				} else {
					// pas de mots clefs pour ces pages
				}
			}
			// IMAGES	
			if ($this->get_param('metatag_image')) {
				if (is_single()) {
					$files = get_children("post_parent=".$post->ID."&post_type=attachment&post_mime_type=image");
					foreach($files as $ai => $f) {
						$image = wp_get_attachment_image_src($ai, 'full');
						$og .= '<meta property="og:image" content="'.$image[0].'"/>'."\n" ; 
						$og .= '<meta property="og:image:width" content="'.$image[1].'"/>'."\n" ; 
						$og .= '<meta property="og:image:height" content="'.$image[2].'"/>'."\n" ; 
					}
				} else {
					// pas de mots clefs pour ces pages
				}
			}
			
			echo $norm ; 
			echo $og ; 
			echo $dc ; 
		}
		
		return ; 
	}
	
	/**====================================================================================================================================================
	* Function called to return a number of notification of this plugin
	* This number will be displayed in the admin menu
	*
	* @return int the number of notifications available
	*/
	 
	public function _notify() {
		return 0 ; 
	}
	
	/**====================================================================================================================================================
	* Function to instantiate the class and make it a singleton
	* This function is not supposed to be modified or called (the only call is declared at the end of this file)
	*
	* @return void
	*/
	
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/** ====================================================================================================================================================
	* Define the default option values of the plugin
	* This function is called when the $this->get_param function do not find any value fo the given option
	* Please note that the default return value will define the type of input form: if the default return value is a: 
	* 	- string, the input form will be an input text
	*	- integer, the input form will be an input text accepting only integer
	*	- string beggining with a '*', the input form will be a textarea
	* 	- boolean, the input form will be a checkbox 
	* 
	* @param string $option the name of the option
	* @return variant of the option
	*/
	public function get_default_option($option) {
		switch ($option) {
			case 'localwebstat' 		: return false 		; break ; 
			case 'local_track_user'		: return true ; break ; 
			case 'local_detail'		: return false ; break ; 
			case 'local_detail_nb'		: return array(array("100", "nb100"), array("*50", "nb50"), array("20", "nb20"), array("10", "nb10") ) ; break ; 
			case 'local_show_visits'		: return false ; break ; 
			case 'local_period'		: return array(array(__('3 Year', $this->pluginID), "a3"), array(__('1 Year', $this->pluginID), "a1"), array(__('6 Month', $this->pluginID), "m6"), array("*".__('1 Month', $this->pluginID), "m1"), array(__('2 Week', $this->pluginID), "w2"), array(__('1 Week', $this->pluginID), "w1")) ; break ; 
			case 'local_show_type'		: return false ; break ; 
			case 'local_cron_concat'		: return "" ; break ; 
			case 'local_current_user'		: return true ; break ; 
			case 'local_cnil_compatible'		: return false ; break ; 			
			case 'local_cnil_compatible_html'		: return  "*<div id='infoLocalCookies' style='z-index:1000; border:1px solid black; opacity:0.9;background-color:#999999;width:100%;position:fixed;bottom:0px;color:#EEEEEE;'>
   <p style='text-align:center'>This site uses cookies for anonymous statistics. These statistics are used for local use only. If you prefer, you may refuse these cookies. %accept% or %refuse%</p>
</div>" ; 
			case 'local_keep_detailed_info'		: return 10 ; break ; 	


			case 'googlewebstat' 		: return false 		; break ;
			case 'googlewebstat_universal_analytics' 		: return false 		; break ;
			case 'googlewebstat_user' 		: return "" 		; break ; 
			case 'googlewebstat_acc_id' 		: return "" 		; break ; 
			case 'googlewebstat_list' 		: return array("")		; break ; 
			case 'googlewebstat_auth' 		: return false 		; break ; 
			case 'googlewebstat_auth_token' 		: return "" 		; break ; 
			case 'google_api_key'		: return "" ; break ; 
			case 'google_double_click'		: return false ; break ; 
			case 'google_show_visits'		: return false ; break ; 
			case 'google_show_type'		: return false ; break ; 
			case 'google_track_user'		: return true ; break ; 
			case 'google_period'		: return array(array(__('3 Year', $this->pluginID), "a3"), array(__('1 Year', $this->pluginID), "a1"), array(__('6 Month', $this->pluginID), "m6"), array("*".__('1 Month', $this->pluginID), "m1"), array(__('2 Week', $this->pluginID), "w2"), array(__('1 Week', $this->pluginID), "w1")) ; break ; 
			case 'google_cnil_compatible'		: return false ; break ; 			
			case 'google_cnil_compatible_html'		: return  "*<div id='infoGoogleCookies' style='z-index:1000; border:1px solid black; opacity:0.9;background-color:#999999;width:100%;position:fixed;top:0px;color:#EEEEEE;'>
   <p style='text-align:center'>This site may uses cookies for statistics with Google Analytics. Do you accept such cookies? %accept% or %refuse%</p>
</div>" ; 

			case 'sitemaps'		: return false ; break ; 
			case 'sitemaps_date'		: return "" ; break ; 
			case 'sitemaps_nb'		: return 0 ; break ; 
			case 'sitemaps_notify_google'		: return false ; break ; 
			case 'sitemaps_notify_google_date'		: return "" ; break ; 
			case 'sitemaps_notify_bing'		: return false ; break ; 
			case 'sitemaps_notify_bing_date'		: return "" ; break ; 
			case 'sitemaps_notify_ask'		: return false ; break ; 
			case 'sitemaps_notify_ask_date'		: return "" ; break ; 
			
			case 'metatag'		: return false ; break ; 
			case 'metatag_title'		: return true ; break ; 
			case 'metatag_description'		: return true ; break ; 
			case 'metatag_copyright'		: return true ; break ; 
			case 'metatag_copyright_override'		: return "" ; break ; 
			case 'metatag_date'		: return true ; break ; 
			case 'metatag_keywords'		: return true ; break ; 
			case 'metatag_author'		: return true ; break ; 
			case 'metatag_author_override'		: return "" ; break ; 
			case 'metatag_toc'		: return true ; break ; 
			case 'metatag_image'		: return true ; break ; 

			case 'geolocate_ipinfodb'		: return false ; break ; 
			case 'geolocate_ipinfodb_key'		: return "" ; break ; 
			case 'geolocate_show_world'		: return false ; break ; 
			case 'geolocate_show_europe'		: return false ; break ; 
			case 'geolocate_show_state'		: return "" ; break ; 
			
		}
		return null ;
	}

	/** ====================================================================================================================================================
	* Init css for the frontend side
	* If you want to load a style sheet, please type :
	*	<code>$this->add_inline_css($css_text);</code>
	*	<code>$this->add_css($css_url_file);</code>
	*
	* @return void
	*/
	
	function _front_css_load() {	
		global $blog_id ; 
		
		// Add sitemap declaration
		
		if ($this->get_param('sitemaps')) {
			if (is_multisite()) {
				if (is_file(ABSPATH . "sitemap".$blog_id.".xml.gz"))
					echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="'.get_site_url()."/".'sitemap'.$blog_id.'.xml.gz" />'."\n" ; 
				if (is_file(ABSPATH . "sitemap".$blog_id.".xml"))
					echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="'.get_site_url()."/".'sitemap'.$blog_id.'.xml" />'."\n" ; 
			} else {
				if (is_file(ABSPATH . "sitemap.xml.gz"))
					echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="'.get_site_url()."/".'sitemap.xml.gz" />'."\n" ; 
				if (is_file(ABSPATH . "sitemap.xml"))
					echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="'.get_site_url()."/".'sitemap.xml" />'."\n" ; 
			}
		}
	}	
	
	/** ====================================================================================================================================================
	* Init javascript for the public side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('my_plugin_script', plugins_url('/script.js', __FILE__));</code>
	*	<code>$this->add_inline_js($js_text);</code>
	*	<code>$this->add_js($js_url_file);</code>
	*
	* @return void
	*/
	
	function _public_js_load() {	
		
		// On charge le jquery si ce n'est deja fait
		wp_enqueue_script("jquery") ; 
		
		if ($this->get_param('localwebstat')) {
			$current_user = wp_get_current_user();			
			if ( (($this->get_param('local_track_user')) && (0 != $current_user->ID))  || (0 == $current_user->ID) ) {
				ob_start() ; 
					?>

					function UserWebStat_sC(name,value,days) {
						if (days) {
							var date = new Date();
							date.setTime(date.getTime()+(days*24*60*60*1000));
							var expires = "; expires="+date.toGMTString();
						}
						else var expires = "";
						document.cookie = name+"="+value+expires+"; path=/";
					}
			
					function UserWebStat_gC(name) {
						var nameEQ = name + "=";
						var ca = document.cookie.split<?php $a='avoid problem with deprecated function';?>(';');
						for(var i=0; i < ca.length;i++) {
							var c = ca[i];
							while (c.charAt(0)==' ') c = c.substring(1,c.length);
							if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
						}
						return null;
					}
					
					function whatChoiceForLocalCookies() {
						var choix = UserWebStat_gC("whatChoiceForLocalCookies") ; 
						if (choix==null) {
							return "NO_CHOICE" ; 
						}
						return choix ; 
					}
					
					function acceptLocalCookies() {
						UserWebStat_sC("whatChoiceForLocalCookies","ACCEPT_COOKIE",30) ; 
						jQuery('#infoLocalCookies').remove() ;
						
						jQuery(".traffic_cookies_allow").hide() ; 
						jQuery(".traffic_cookies_refuse").show() ; 
						
					}
					function refusLocalCookies() {
						UserWebStat_sC("whatChoiceForLocalCookies","REFUS_COOKIE",30) ; 
						jQuery('#infoLocalCookies').remove() ;
						UserWebStat_sC('sC', null) ; 
						UserWebStat_sC('rN', null) ; 
						
						jQuery(".traffic_cookies_allow").show() ; 
						jQuery(".traffic_cookies_refuse").hide() ; 
					}
					
					jQuery(function() {
						// On gere les boutons 
						if (whatChoiceForLocalCookies()=="REFUS_COOKIE") {
							jQuery(".traffic_cookies_allow").show() ; 
							jQuery(".traffic_cookies_refuse").hide() ; 
						} else if (whatChoiceForLocalCookies()=="ACCEPT_COOKIE") {
							jQuery(".traffic_cookies_allow").hide() ; 
							jQuery(".traffic_cookies_refuse").show() ; 
						} else {
							jQuery(".traffic_cookies_allow").show() ; 
							jQuery(".traffic_cookies_refuse").show() ; 						
						}
					}) ; 

					function UserWebStat() {
										
						<?php if ($this->get_param('local_cnil_compatible')) {	?>
						if (whatChoiceForLocalCookies()!="REFUS_COOKIE") {
						<?php } ?>
						
							if (UserWebStat_gC('sC')!=null) {
								var sC = UserWebStat_gC('sC') ; 
							} else {
								var sC = "" ; 
							}
							if (UserWebStat_gC('rN')!=null) {
								var rN = UserWebStat_gC('rN') ; 
							} else {
								var rN = 0 ; 
							}
						
							var arguments = {
								action: 'UserWebStat', 
								browserName : navigator.appName, 
								browserVersion : navigator.appVersion, 
								platform : navigator.platform, 
								browserUserAgent: navigator.userAgent,
								cookieEnabled: navigator.cookieEnabled,
								singleCookie: sC,
								refreshNumber: rN,
								referer : document.referrer,
								page: window.location.pathname
							} 
						
							var ajaxurl2 = "<?php echo admin_url()."admin-ajax.php"?>" ; 
							jQuery.post(ajaxurl2, arguments, function(response) {
								//We put the return values in cookie and we relaunch
								if (response+""=="0") {
									UserWebStat_sC('rN', 0) ; 
								} else {
									var val = (response+"").split<?php $a='avoid problem with deprecated function';?>(",") ; 
									if (val.length==2) {
										UserWebStat_sC('sC', val[0], 365) ; 
										UserWebStat_sC('rN', val[1]) ;
										// if the browser does not accept cookie, we do not iterate
										if (UserWebStat_gC('rN')+""==val[1]+"") {
											var t=setTimeout("UserWebStat()",10000);
										}
									}
								}
							});    
						
						<?php if ($this->get_param('local_cnil_compatible')) {	?>
						}
						<?php } ?>
					}
					
					<?php if ($this->get_param('local_cnil_compatible')) {	?>
					if (whatChoiceForLocalCookies()!="REFUS_COOKIE") {
					<?php } ?>
					
						// We launch the callback when jQuery is loaded or at least when the page is loaded
						if (typeof(jQuery) == 'function') {
							UserWebStat() ; 			
						} else { 
							if (window.attachEvent) {window.attachEvent('onload', UserWebStat);}
							else if (window.addEventListener) {window.addEventListener('load', UserWebStat, false);}
							else {document.addEventListener('load', UserWebStat, false);} 
						}
					
					<?php if ($this->get_param('local_cnil_compatible')) {	?>
					}
				
					function show_optOut(){
						<?php 
						$text = $this->get_param('local_cnil_compatible_html') ; 
												
						$text = str_replace("\r","", $text);
						$text = str_replace("\n","", $text);
						$text = str_replace("%accept%","<input type='button' onclick='acceptLocalCookies()' value='".str_replace("'","",__('Accept',$this->pluginID))."' />", $text);
						$text = str_replace("%refuse%","<input type='button' onclick='refusLocalCookies()' value='".str_replace("'","",__('Refuse',$this->pluginID))."' />", $text);
						?>

						jQuery("<?php echo $text ?>").appendTo( "body" );						             
					}
					if (whatChoiceForLocalCookies()=="NO_CHOICE") {
						if (window.attachEvent) {window.attachEvent('onload', show_optOut);}
						else if (window.addEventListener) {window.addEventListener('load', show_optOut, false);}
						else {document.addEventListener('load', show_optOut, false);} 
					}					
					<?php }	?>
											
					<?php 
				
				$java = ob_get_clean() ; 
				$this->add_inline_js($java) ; 
			}
		}
		if ($this->get_param('googlewebstat')) {
			if ($this->get_param('googlewebstat_user') != "") {
				$current_user = wp_get_current_user();
				if ( (($this->get_param('google_track_user')) && (0 != $current_user->ID)) || (0 == $current_user->ID) ) {
					ob_start() ; 
					?>
					<?php if ($this->get_param('google_cnil_compatible')) {	?>
					if (whatChoiceForLocalCookies()=="ACCEPT_COOKIE") {
					<?php }	
						if (!$this->get_param('googlewebstat_universal_analytics')) {
						?>


						var _gaq = _gaq || [];
						_gaq.push(['_setAccount', '<?php echo $this->get_param('googlewebstat_user') ; ?>']);
						_gaq.push(['_trackPageview']);
						_gaq.push(['_trackPageLoadTime']);
	
						(function() {
							var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
							<?php if (!$this->get_param("google_double_click")) {?>
							ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
							<?php } else {?>
							ga.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js';
							<?php } ?>
							var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
						})();
						
					<?php } else {?>
					
						<!-- Google Universal Analytics -->
						
						(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
						(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
						m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
						})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

						ga('create', '<?php echo $this->get_param('googlewebstat_user') ; ?>', 'auto');  // Replace with your property ID.
						ga('send', 'pageview');

					<?php 
					}
					
					if ($this->get_param('google_cnil_compatible')) {	?>
					}
					<?php }	?>
														
					function acceptGoogleCookies() {
						UserWebStat_sC_g("whatChoiceForGoogleCookies","ACCEPT_COOKIE",30) ; 
						jQuery('#infoGoogleCookies').remove() ;
						
						jQuery(".google_traffic_cookies_allow").hide() ; 
						jQuery(".google_traffic_cookies_refuse").show() ; 
					}
					
					function refusGoogleCookies() {
						UserWebStat_sC_g("whatChoiceForGoogleCookies","REFUS_COOKIE",30) ; 
						jQuery('#infoGoogleCookies').remove() ;
						
						jQuery(".google_traffic_cookies_allow").show() ; 
						jQuery(".google_traffic_cookies_refuse").hide() ; 
					}
					
					function UserWebStat_gC_g(name) {
						var nameEQ = name + "=";
						var ca = document.cookie.split<?php $a='avoid problem with deprecated function';?>(';');
						for(var i=0; i < ca.length;i++) {
							var c = ca[i];
							while (c.charAt(0)==' ') c = c.substring(1,c.length);
							if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
						}
						return null;
					}
					
					function UserWebStat_sC_g(name,value,days) {
						if (days) {
							var date = new Date();
							date.setTime(date.getTime()+(days*24*60*60*1000));
							var expires = "; expires="+date.toGMTString();
						}
						else var expires = "";
						document.cookie = name+"="+value+expires+"; path=/";
					}
					
					function whatChoiceForGoogleCookies() {
						var choix = UserWebStat_gC_g("whatChoiceForGoogleCookies") ; 
						if (choix==null) {
							return "NO_CHOICE" ; 
						}
						return choix ; 
					}
					
					jQuery(function() {
						// On gere les boutons 
						if (whatChoiceForGoogleCookies()=="REFUS_COOKIE") {
							jQuery(".google_traffic_cookies_allow").show() ; 
							jQuery(".google_traffic_cookies_refuse").hide() ; 
						} else if (whatChoiceForGoogleCookies()=="ACCEPT_COOKIE") {
							jQuery(".google_traffic_cookies_allow").hide() ; 
							jQuery(".google_traffic_cookies_refuse").show() ; 
						} else {
							jQuery(".google_traffic_cookies_allow").show() ; 
							jQuery(".google_traffic_cookies_refuse").show() ; 						
						}
					}) ; 

					
					function show_optIn(){
						<?php 
						$text = $this->get_param('google_cnil_compatible_html') ; 
												
						$text = str_replace("\r","", $text);
						$text = str_replace("\n","", $text);
						$text = str_replace("%accept%","<input type='button' onclick='acceptGoogleCookies()' value='".str_replace("'","",__('Accept',$this->pluginID))."' />", $text);
						$text = str_replace("%refuse%","<input type='button' onclick='refusGoogleCookies()' value='".str_replace("'","",__('Refuse',$this->pluginID))."' />", $text);
						?>

						jQuery("<?php echo $text ?>").appendTo( "body" );						             
					}
					
					<?php if ($this->get_param('google_cnil_compatible')) {	?>
					
					if (whatChoiceForGoogleCookies()=="NO_CHOICE") {
						if (window.attachEvent) {window.attachEvent('onload', show_optIn);}
						else if (window.addEventListener) {window.addEventListener('load', show_optIn, false);}
						else {document.addEventListener('load', show_optIn, false);} 
					}	
									
					<?php }	?>
					<?php 
					$java = ob_get_clean() ; 
					$this->add_inline_js($java) ;
				}
			}
		}
	}

	/** ====================================================================================================================================================
	* The admin configuration page
	* This function will be called when you select the plugin in the admin backend 
	*
	* @return void
	*/
	
	public function configuration_page() {
	
		global $wpdb;
		global $_GET ; 
		global $_POST ; 
		global $blog_id ; 
		

		// On concatene si besoin pour economiser de la place dans la bdd
		if ($this->get_param('local_cron_concat')=="") {
			$this->set_param('local_cron_concat', strtotime(date_i18n("Y-m-d 0:0:1")." +1 day")) ; 
		}
				
		// Si la page est avec un GET token, on refresh en postant 
		if (isset($_GET['token']))  {
			?>
			<form action='<?php echo get_admin_url()."admin.php?page=traffic-manager/traffic-manager.php" ;  ?>' method='post' name='frm'>
			<input type='hidden' name='token' value='<?php echo $_GET['token'] ; ?>'>
			</form>
			<script language="JavaScript">
				document.frm.submit();
			</script>
			<?php
			return ; 
		}
		
		// We save the parameters !
		ob_start() ; 
			if ((isset($_POST['token'])) && ($this->get_param('googlewebstat_auth_token') == "") ){
				$this->set_param('googlewebstat', true) ; 
				$this->set_param('googlewebstat_auth', true) ; 
				$token = $this->get_session_token($_POST['token']) ; 
				if ($token != false) {
					$this->set_param('googlewebstat_auth_token', $token) ; 
					echo "<div class='updated fade'><p>".__('The authentication with Google Analytics is successful',  $this->pluginID)."</p></div>" ; 
				} else {
					echo "<div class='error fade'><p>".__('The authentication with Google Analytics have failed... please retry!',  $this->pluginID)."</p></div>" ; 
				}
			}
			
			if (isset($_POST['untoken'])) {
				$this->set_param('googlewebstat_auth', false) ; 
				if ($this->get_param('googlewebstat_auth_token')!="") {
					$this->revoke_session_token($this->get_param('googlewebstat_auth_token')) ; 
					$this->set_param('googlewebstat_auth_token', "") ; 
					$this->set_param('googlewebstat_acc_id', "") ; 
					echo "<div class='updated fade'><p>".__("The Google Analytics's authorization has been revoked as requested",  $this->pluginID)."</p></div>" ; 
				}
			}
			
			$params = new SLFramework_Parameters($this, "tab-parameters") ; 
			$params->add_title(__("Local Web Statistics", $this->pluginID)) ; 
			$params->add_param('localwebstat', __('Do you want to manage the web statistics locally?', $this->pluginID),"", "", array('local_track_user', 'local_detail', 'local_detail_nb', 'local_show_visits', 'local_show_type', 'local_period')) ; 
			$params->add_comment(__("If so, stats will be stored in the local SQL database. Be sure that you have free space into your database", $this->pluginID)) ; 
			if ($this->get_param('localwebstat')) {
				$params->add_comment(sprintf(__("The next compression of the database will occur on %s", $this->pluginID), date_i18n("Y-m-d H:i:s", $this->get_param('local_cron_concat')))) ; 
			}
			
			$params->add_param('local_detail', __('Do you want to display the last viewed pages?', $this->pluginID),"", "", array('local_detail_nb')) ; 
			$params->add_comment(__("If so, a list of the last viewed pages will be displayed including IP of the user, the url of the viewed page, the time, the referer, the browser name, the OS name, etc.", $this->pluginID)) ; 
			$params->add_param('local_detail_nb', __('How many pages should be displayed?', $this->pluginID)) ; 
			$params->add_param('local_keep_detailed_info', __('How many days do you want to keep detailed info in your database?', $this->pluginID)) ; 
			$params->add_comment(__("All detailled info will be used for the geolocation (see below).", $this->pluginID)) ; 
			$params->add_comment(sprintf(__("It is recommended to keep the detailled information no more than %s days.", $this->pluginID), "15")) ; 
			$params->add_param('local_show_visits', __('Show statistics on number of visits and viewed pages?', $this->pluginID)) ; 
			$params->add_param('local_show_type', __('Show statistics on the OS and browser types of your visitors?', $this->pluginID)) ; 
			$params->add_param('local_period', __('What are the period for which charts should be provided?', $this->pluginID)) ; 
			$params->add_param('local_track_user', __('Do you want to track the logged user?', $this->pluginID)) ; 
			$params->add_param('local_current_user', __('Show the number of current connected users on your website', $this->pluginID)) ; 
			$params->add_param('local_cnil_compatible', __("Configure the local statistics to be compatible with French CNIL's recommandations", $this->pluginID), "", "", array('local_cnil_compatible_html')) ; 
			$params->add_comment(__("The last two bytes of the IP will be masked and a small banner to allow the user to refuse cookies will be displayed on the front side.", $this->pluginID)) ; 
			$params->add_param('local_cnil_compatible_html', __("The HTML to be displayed for the banner to be compatible with French CNIL's recommandations.", $this->pluginID)) ; 
			$params->add_comment(__("The French CNIL recommends to add buttons in a page to inform the users that he can refuse / allow the cookies (in addition of the banner).", $this->pluginID)) ; 
			$params->add_comment(sprintf(__("You can add these buttons in any posts/pages with this code %s.", $this->pluginID), "<code>[cookies_buttons]</code>")) ; 
			$params->add_param('geolocate_ipinfodb', sprintf(__("Use %s to know from where your users are.", $this->pluginID), "<code>IPInfoDb</code>"),"","",array('geolocate_ipinfodb_key')) ;
			$params->add_param('geolocate_ipinfodb_key', sprintf(__("The API key for %s.", $this->pluginID), "<code>IPInfoDb</code>")) ; 
			if ($this->get_param('geolocate_ipinfodb_key')==""){
				$params->add_comment(sprintf(__("You have to create your own key on %s.", $this->pluginID), "<a href='http://www.ipinfodb.com/ip_location_api.php'>IPInfoDb</a>")) ; 
			} else {
				$geo = $this->geolocate("", false) ; 
				if (is_array($geo)){
					$params->add_comment(__("You API key appears to be correct.", $this->pluginID)) ; 				
					$params->add_comment(sprintf(__("Your server appears to be located in %s.", $this->pluginID), "<code>".ucfirst(strtolower($geo['countryName']))." (".$geo['zipCode']." ".ucfirst(strtolower($geo['cityName'])).")</code>")) ; 				
				} else {
					$params->add_comment(__("There was a problem while contacting the server.", $this->pluginID)) ; 				
					if (is_string($geo)){
						$params->add_comment("<code>".$geo."</code>") ; 				
					}
				}
			}
			$params->add_param('geolocate_show_world', __("Show the World map.", $this->pluginID)) ; 
			$params->add_param('geolocate_show_europe', __("Show the European map.", $this->pluginID)) ; 
			$params->add_param('geolocate_show_state', __("Show a state map.", $this->pluginID)) ; 
			$params->add_comment(__("This is a comma separated list.", $this->pluginID)) ; 
			$comment_regions = sprintf(__("Use %s for the Argentina map.", $this->pluginID), "<code>ar-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Australia map.", $this->pluginID), "<code>at-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Austria map.", $this->pluginID), "<code>au-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Beligium map.", $this->pluginID), "<code>be-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Canadia map.", $this->pluginID), "<code>ca-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Chinese map.", $this->pluginID), "<code>cn-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Colombian map.", $this->pluginID), "<code>co-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Denmark map.", $this->pluginID), "<code>dk-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the France map.", $this->pluginID), "<code>fr-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Germany map.", $this->pluginID), "<code>de-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the India map.", $this->pluginID), "<code>in-mill-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Italy map.", $this->pluginID), "<code>it-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the Netherlands map.", $this->pluginID), "<code>nl-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the New Zealand map.", $this->pluginID), "<code>nz-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the Norway map.", $this->pluginID), "<code>no-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the Philippines map.", $this->pluginID), "<code>ph-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the Poland map.", $this->pluginID), "<code>pl-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the Portugal map.", $this->pluginID), "<code>pt-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the South Africa map.", $this->pluginID), "<code>za-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the Spain map.", $this->pluginID), "<code>es-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the Sweden map.", $this->pluginID), "<code>se-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the Switzerland map.", $this->pluginID), "<code>ch-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the Thailand map.", $this->pluginID), "<code>th-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the UK map.", $this->pluginID), "<code>uk-mill-en</code>") ;
			$comment_regions .= " ".sprintf(__("Use %s for the USA map.", $this->pluginID), "<code>us-aea-en</code>") ; 
			$comment_regions .= " ".sprintf(__("Use %s for the Venezuela map.", $this->pluginID), "<code>ve-aea-en</code>") ; 
			$params->add_comment($comment_regions) ; 

			$params->add_title(__("Google Analytics Web Statistics", $this->pluginID)) ; 
			$params->add_param('googlewebstat', __('Do you want to manage the web statistics with Google Analytics?', $this->pluginID), "", "", array('googlewebstat_user', 'googlewebstat_list', 'google_show_visits', 'google_show_type', 'google_show_time', 'google_period', 'google_track_user', 'google_api_key', 'google_double_click', 'googlewebstat_universal_analytics')) ; 
			$params->add_comment(sprintf(__("For additional information, please visit the %s website. Moreover you could see all your authorized accesses on this %spage%s.", $this->pluginID), "<a href='http://www.google.com/analytics/'>Google Analytics</a>", "<a href='https://accounts.google.com/b/0/IssuedAuthSubTokens'>", "</a>")) ; 
			
			if (!$this->get_param('googlewebstat_auth')) {
				$params->add_param('googlewebstat_user', __('Google Analytics ID:', $this->pluginID)) ; 
				$params->add_comment(sprintf(__("The Google Analytics ID is should be similar to %s.", $this->pluginID), "<code>UA-XXXXX-X</code>")) ; 
				$params->add_comment(sprintf(__("You may also %sauthenticate%s with your Google account and thus ease the process.", $this->pluginID), "<a href='https://www.google.com/accounts/AuthSubRequest?next=".urlencode(get_admin_url()."admin.php?page=traffic-manager/traffic-manager.php")."&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fanalytics.readonly&secure=0&session=1&hd=default'>", "</a>")) ; 
			} else {
				$params->add_comment(sprintf(__("For now, this page is authenticated with your Google Analytics accounts. %sUnapprove the authentication%s", $this->pluginID), '<input name="untoken" class="button validButton" value="', '" type="submit">')) ; 
				
				$account = $this->get_analytics_accounts() ; 

				$list = array() ; 
				$list[] = __("Select...", $this->pluginID) ; 
				
				// On enregistre googlewebstat_user
				if (isset($_POST['submitOptions'])) {
					$id = "" ; 
					$name = "" ; 
					$acc_id = "" ; 
					$selected = $_POST['googlewebstat_list'] ; 
					
					foreach ($account as $a) {
						if ($selected == SLFramework_Utils::create_identifier($a['title'])) {
							$acc_id = $a['tableId'] ; 
							$id = $a['webPropertyId'] ; 
							$name = $a['title'] ; 
						}
					}
					$this->set_param('googlewebstat_user', $id ) ; 
					$this->set_param('googlewebstat_acc_id', $acc_id) ; 
				}
				
				// On affiche 
				$id = "" ; 
				$name = "" ; 
				$acc_id = "" ; 
				if (!isset($account['error'])) {
					foreach ($account as $a) {
						if ($a['webPropertyId']==$this->get_param('googlewebstat_user')) {
							$list[] = "*".$a['title'] ; 
							$id = $a['webPropertyId'] ; 
							$acc_id = $a['tableId'] ; 
							$name = $a['title'] ; 
						} else {
							$list[] = $a['title'] ; 
						}
					}
				} else {
					echo "<div class='error fade'><p>".$account['error']."</p></div>" ; 
				}
				
				if (($this->get_param('googlewebstat_acc_id')=="")&&($acc_id!="")) {
					$this->set_param('googlewebstat_acc_id', $acc_id) ; 
				}
				
				$this->set_param('googlewebstat_list', $list) ; 
				$params->add_param('googlewebstat_list', __('Choose your website in this list', $this->pluginID)) ; 
				if ($name != "") {
					$params->add_comment(sprintf(__("Please note that the Google Analytics ID for %s is %s.", $this->pluginID), "<code>".$name."</code>", "<code>".$id."</code>")) ; 
				} else {
					$params->add_comment(__("No Google Analytics ID configured for now.", $this->pluginID)) ; 			
				}
				
				$params->add_param('googlewebstat_universal_analytics', sprintf(__('Do you want to use the %s?', $this->pluginID), "<code><a href='https://support.google.com/analytics/answer/2790010'>Universal Analytics</a></code>")) ; 

				$params->add_param('google_api_key', __('What is the API key?', $this->pluginID)) ; 
				$params->add_comment(__("This API key is useful to avoid any quota limit. If you do not set this key, only very few requests may be allowed by Google", $this->pluginID)) ; 
				$params->add_comment(sprintf(__("To get this API key, please visit %s, create a projet, allow Google Analytics, and then go to API console to get a %s", $this->pluginID), "<a href='https://code.google.com/apis/console'>https://code.google.com/apis/console</a>", "'<i>Key for browser apps</i>'")) ; 
				
				$params->add_param('google_show_visits', __('Show statistics on number of visits and viewed pages?', $this->pluginID)) ; 
				$params->add_param('google_show_type', __('Show statistics on the OS and browser types of your visitors?', $this->pluginID)) ; 
				$params->add_param('google_period', __('What are the period for which charts should be provided?', $this->pluginID)) ; 
	
			}
			$params->add_param('google_track_user', __('Do you want to track the logged user?', $this->pluginID)) ; 
			$params->add_param('google_double_click', __('Support Display Advertising for Google (only if Universal Analytics is not activated)?', $this->pluginID)) ; 
			$params->add_comment(__("This option is to enable Remarketing with Google Analytics or Google Display Network (GDN) Impression Reporting.", $this->pluginID)) ; 
			$params->add_param('google_cnil_compatible', __("Configure the Google Analytics statistics to be compatible with French CNIL's recommandations", $this->pluginID), "", "", array('google_cnil_compatible_html')) ; 
			$params->add_comment(__("A small banner will be displayed to allow cookies (by default, no cookie will be used)", $this->pluginID)) ; 
			$params->add_param('google_cnil_compatible_html', __("The HTML to be displayed for the banner to be compatible with French CNIL's recommandations.", $this->pluginID)) ; 
			$params->add_comment(__("The French CNIL recommends to add buttons in a page to inform the users that he can refuse / allow the cookies (in addition of the banner).", $this->pluginID)) ; 
			$params->add_comment(sprintf(__("You can add these buttons in any posts/pages with this code %s.", $this->pluginID), "<code>[google_cookies_buttons]</code>")) ; 
			
			$params->add_title(__("Sitemaps Configuration", $this->pluginID)) ; 
			$params->add_param('sitemaps', __('Do you want to compute a Sitemaps file?', $this->pluginID), "", "", array('sitemaps_nb', 'sitemaps_notify_google', 'sitemaps_notify_bing', 'sitemaps_notify_ask')) ; 
			$params->add_comment(__("A Sitemap is an XML file that lists the URLs for your website. It informs search engines about URLs on your website that are available for crawling.", $this->pluginID)) ; 
			if ($this->get_param('sitemaps_date')=="") {
				$params->add_comment(__("No sitemap has been generated yet.", $this->pluginID)) ; 
			} else if (@date_i18n("Ymd", $this->get_param('sitemaps_date'))===FALSE) {
				$params->add_comment(sprintf(__("An error occured on the next sitemap generation : %s.", $this->pluginID), $this->get_param('sitemaps_date'))) ; 
			} else {
				$params->add_comment(sprintf(__("The last sitemap has been generated on %s.", $this->pluginID), $this->get_param('sitemaps_date'))) ; 
				$filename = "sitemap" ; 
				if (is_multisite()) {
					$filename = $filename.$blog_id;
				} 
				$params->add_comment(sprintf(__("You may see your sitemap at %s.", $this->pluginID), "<a href='".get_site_url()."/".$filename.".xml'>".get_site_url()."/".$filename.".xml</a>")) ; 
			}
			$params->add_param('sitemaps_nb', __('How many posts and pages will be included in this file?', $this->pluginID)) ; 
			$params->add_comment(__("If you have too many posts, set this number to 1000 for instance in order to avoid any memory issue. If you set this number to 0, all posts will be included.", $this->pluginID)) ; 
			// Google
			$name_crawler = "Google" ; 
			$name_crawler_s = strtolower($name_crawler) ; 
			$params->add_param('sitemaps_notify_'.$name_crawler_s, sprintf(__('Do you want to notify %s when the sitemap is updated?', $this->pluginID), $name_crawler)) ; 
			if ($this->get_param('sitemaps_notify_'.$name_crawler_s.'_date')=="") {
				$params->add_comment(sprintf(__("%s has never been notified yet. Waiting for the next update.", $this->pluginID), $name_crawler)) ; 
			} else if (@date_i18n("Ymd", $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))===FALSE) {
				$params->add_comment(sprintf(__("An error occured when notifying %s for the last time.", $this->pluginID), $name_crawler)) ; 
			} else {
				$params->add_comment(sprintf(__("%s has be notified for the last time on %s.", $this->pluginID), $name_crawler, $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))) ; 
			}
			// Ask
			$name_crawler = "Ask" ; 
			$name_crawler_s = strtolower($name_crawler) ; 
			$params->add_param('sitemaps_notify_'.$name_crawler_s, sprintf(__('Do you want to notify %s when the sitemap is updated?', $this->pluginID), $name_crawler)) ; 
			if ($this->get_param('sitemaps_notify_'.$name_crawler_s.'_date')=="") {
				$params->add_comment(sprintf(__("%s has never been notified yet. Waiting for the next update.", $this->pluginID), $name_crawler)) ; 
			} else if (@date_i18n("Ymd", $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))===FALSE) {
				$params->add_comment(sprintf(__("An error occured when notifying %s for the last time.", $this->pluginID), $name_crawler)) ; 
			} else {
				$params->add_comment(sprintf(__("%s has be notified for the last time on %s.", $this->pluginID), $name_crawler, $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))) ; 
			}
			// Bing
			$name_crawler = "Bing" ; 
			$name_crawler_s = strtolower($name_crawler) ; 
			$params->add_param('sitemaps_notify_'.$name_crawler_s, sprintf(__('Do you want to notify %s when the sitemap is updated?', $this->pluginID), "Bing & Yahoo")) ; 
			if ($this->get_param('sitemaps_notify_'.$name_crawler_s.'_date')=="") {
				$params->add_comment(sprintf(__("%s has never been notified yet. Waiting for the next update.", $this->pluginID), $name_crawler)) ; 
			} else if (@date_i18n("Ymd", $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))===FALSE) {
				$params->add_comment(sprintf(__("An error occured when notifying %s for the last time.", $this->pluginID), $name_crawler)) ; 
			} else {
				$params->add_comment(sprintf(__("%s has be notified for the last time on %s.", $this->pluginID), $name_crawler, $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))) ; 
			}
			
			$params->add_title(__("Improve your SEO with metatags", $this->pluginID)) ; 
			$params->add_param('metatag', __('Do you want to add metatag to your page/post', $this->pluginID), "", "", array('metatag_title', 'metatag_description', 'metatag_copyright', 'metatag_copyright_override', 'metatag_keywords', 'metatag_author', 'metatag_author_override')) ; 
			$params->add_comment(__("Meta tags are snippets of code on a web page. They offer information about a page (metadata) in an invisible way but may enhanced the SEO (Search Engine Optimization) of your website.", $this->pluginID)) ; 
			$params->add_param('metatag_title', __('Add meta title', $this->pluginID)) ; 
			$params->add_param('metatag_description', __('Add meta description', $this->pluginID)) ; 
			$params->add_param('metatag_copyright', __('Add meta copyright', $this->pluginID)) ; 
			$params->add_comment(sprintf(__("It will be by default: %s.", $this->pluginID), "<code>Copyright - ".get_bloginfo('name')." - ".get_bloginfo('url')." - All right reserved"."</code>")) ; 
			$params->add_param('metatag_copyright_override', __('Override the copyright of the website:', $this->pluginID)) ; 
			$params->add_param('metatag_author', __('Add meta author', $this->pluginID)) ; 
			$params->add_param('metatag_author_override', __('Override the author name', $this->pluginID)) ; 
			$params->add_param('metatag_date', __('Add meta date (publication date, modification date, etc.)', $this->pluginID)) ;
			$params->add_param('metatag_keywords', __('Add meta keywords (category, keywords, etc.)', $this->pluginID)) ;
			$params->add_param('metatag_toc', __('Add meta table of content', $this->pluginID)) ;
			$params->add_param('metatag_toc', __('Add meta images', $this->pluginID)) ;
			 
			$params->flush() ; 
		$parameters = ob_get_clean() ; 
		
		if ($this->get_param('sitemaps')) {
			$resu = $this->generateSitemaps("sitemap") ; 
			// If an error occurred
			if (isset($resu['error'])) {
				echo "<div class='error fade'><p>".$resu['error']."</p></div>" ; 				
			} 
		}
	
		?>
		<div class="plugin-titleSL">
			<h2><?php echo $this->pluginName ?></h2>
		</div>
		
		<div class="plugin-contentSL">		
			<?php echo $this->signature ; ?>
						
			<?php
			//===============================================================================================
			// After this comment, you may modify whatever you want
					
			// We check rights
			$this->check_folder_rights( array(array(WP_CONTENT_DIR."/sedlex/test/", "rwx")) ) ;
			
			$tabs = new SLFramework_Tabs() ; 
				
				ob_start() ; 
			
				if ($this->get_param('googlewebstat')) {
				
					// Get the Google Data
					//----------------------------------
				
					$period = $this->get_param('google_period') ; 
					$date2 = date_i18n("Y-m-d") ; 
					$date1 = date_i18n("Y-m-d", strtotime($date2 . " - 1 month")) ;
					$pas = "ga:date" ; 
					$ptd=sprintf(__("from %s to %s", $this->pluginID), date_i18n(get_option('date_format') , strtotime($date1)), date_i18n(get_option('date_format') , strtotime($date2))) ; 
					foreach ($period as $p) {
						if (strpos($p[0], "*")!==false) {
							switch ($p[1]) {
								case 'a3' : $date1 = date_i18n("Y-m-d", strtotime(date_i18n("Y-m-01", strtotime($date2 . " -3 years")))) ; $pas = "ga:year,ga:month" ; break ; 
								// -1day car commence le dimanche
								case 'a1' : $date1 = date_i18n("Y-m-d", strtotime(date_i18n("o-\\WW", strtotime($date2 . " -1 year")) ." -1 day")) ;  $pas = "ga:year,ga:week" ; break ; 
								// -1day car commence le dimanche
								case 'm6' : $date1 = date_i18n("Y-m-d", strtotime(date_i18n("o-\\WW", strtotime($date2 . " -6 months")) ." -1 day"));  $pas = "ga:year,ga:week" ;  break ; 
								case 'm1' : $date1 = date_i18n("Y-m-d", strtotime($date2 . " -1 month")) ;  $pas = "ga:date" ;  break ; 
								case 'w2' : $date1 = date_i18n("Y-m-d", strtotime($date2 . " -2 weeks")) ;  $pas = "ga:date" ;  break ; 
								case 'w1' : $date1 = date_i18n("Y-m-d", strtotime($date2 . " -1 week")) ;  $pas = "ga:date" ;  break ; 
							}
						}
					}
					$data = $this->get_analytics_data("start-date=$date1&end-date=$date2", $pas) ; 
					$ptd=sprintf(__("from %s to %s", $this->pluginID), date_i18n(get_option('date_format') , strtotime($date1)), date_i18n(get_option('date_format') , strtotime($date2))) ; 
				}
				
				if ($this->get_param('localwebstat')) {
				
					// Get the Local Data
					//----------------------------------
				
					$period_local = $this->get_param('local_period') ; 
					$date2_local = date_i18n("Ymd") ; 
					$date1_local = date_i18n("Ymd", strtotime($date2_local . " - 1 month")) ;
					$pas_local = "day" ; 
					$ptd_local = sprintf(__("from %s to %s", $this->pluginID), date_i18n(get_option('date_format') , strtotime($date1_local)), date_i18n(get_option('date_format') , strtotime($date2_local))) ; 
					foreach ($period_local as $p) {
						if (strpos($p[0], "*")!==false) {
							switch ($p[1]) {
								case 'a3' : $date1_local = date_i18n("Ymd", strtotime(date_i18n("Y-m-01", strtotime($date2_local . " - 3 years")))) ; $pas_local = "month" ;  break ; 
								case 'a1' : $date1_local = date_i18n("Ymd", strtotime(date_i18n("o-\\WW", strtotime($date2 . " -1 year"))))  ;  $pas_local = "week" ;  break ; 
								case 'm6' : $date1_local = date_i18n("Ymd", strtotime(date_i18n("o-\\WW", strtotime($date2 . " -6 months"))));  $pas_local = "week" ;  break ; 
								case 'm1' : $date1_local = date_i18n("Ymd", strtotime($date2_local . " - 1 month")) ;  $pas_local = "day" ;  break ; 
								case 'w2' : $date1_local = date_i18n("Ymd", strtotime($date2_local . " - 2 weeks")) ;  $pas_local = "day" ;  break ; 
								case 'w1' : $date1_local = date_i18n("Ymd", strtotime($date2_local . " - 1 week")) ;  $pas_local = "day" ;  break ; 
							}
						}
					}
					
					$data_local = $this->get_local_data($date1_local, date_i18n("Ymd", strtotime($date2_local . " + 1 day")), $pas_local) ; 
					$ptd_local =sprintf(__("from %s to %s", $this->pluginID), date_i18n(get_option('date_format') , strtotime($date1_local )), date_i18n(get_option('date_format') , strtotime($date2_local ))) ; 
				}
				
				if ( $this->get_param('googlewebstat') || $this->get_param('localwebstat') ) {
				
					ob_start() ; 
						if ($this->get_param('local_current_user')) {
							echo "<p>".sprintf(__("The number of current user is %s", $this->pluginID), "<span id='nb_current_user'>? <img src='".plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/ajax-loader.gif'></span>")."</p>" ; 
							echo "<script></script>" ; 
						}	
					$content_graph = ob_get_clean() ; 
					if (strlen($content_graph)>0) {
						$box = new SLFramework_Box (__("Current users", $this->pluginID), $content_graph) ; 
						echo $box->flush() ; 
					}
				
								
					// Creating the graph for the Google
					// Analytics visits count
					//----------------------------------
				
					ob_start() ; 
						$google_show = false ; 

						if ( ($this->get_param('googlewebstat')) && ($this->get_param('google_show_visits')) && (isset($data['visits'])) ) {
							$google_show = true ; 

							$rows_legend = "" ; 
							$rows_series1= "" ; 
							$rows_series1_tooltip = "" ; 
							$rows_series2= "" ; 
							$rows_series2_tooltip = "" ; 
							
							$max_value = 10 ;
							
							$first = true ; 
							$nb = 0 ; 
							$last_persons = "0" ; 
							$last_visits = "0" ; 
							foreach ($data['visits'] as $k => $d) {
								if ($pas=="ga:date") {
									if (!$first) {
										$rows_legend .= "," ; 
										$rows_series1 .= "," ; 
										$rows_series1_tooltip .= "," ;  
										$rows_series2 .= "," ; 
										$rows_series2_tooltip .= "," ; 
									}
									$date = date_i18n(get_option('date_format') , strtotime($k)) ; 
									$visit = $d["ga:visits"] ; 
									$pageViews = $d["ga:pageviews"] ; 
									
									$rows_legend .= "'".date_i18n(get_option('date_format') , strtotime($k))."'" ; 
									$rows_series1 .= $d["ga:visits"] ; 
									$rows_series1_tooltip .= "'".str_replace("'", "", sprintf(__("%s visitors"),$d["ga:visits"]))."'" ; 
									$rows_series2 .= $d["ga:pageviews"] ; 
									$rows_series2_tooltip .= "'".str_replace("'", "", sprintf(__("%s visits"),$d["ga:pageviews"]))."'" ; 
									
									$max_value = max($max_value, $d["ga:visits"], $d["ga:pageviews"]) ; 

									$first = false ; 
									$nb++ ; 
								}
								if ($pas=="ga:year,ga:week") {
									// On boucle sur les annees
									foreach ($d as $w => $a) {
										if (!$first) {
											$rows_legend .= "," ; 
											$rows_series1 .= "," ; 
											$rows_series1_tooltip .= "," ;  
											$rows_series2 .= "," ; 
											$rows_series2_tooltip .= "," ; 
										} 
										$visit = $a["ga:visits"] ; 
										$pageViews = $a["ga:pageviews"] ; 
										
										$rows_legend .= "'".sprintf(__('Week %s (%s)', $this->pluginID), $w, $k)."'" ; 
										$rows_series1 .= $a["ga:visits"] ; 
										$rows_series1_tooltip .= "'".str_replace("'", "", sprintf(__("%s visitors"),$a["ga:visits"]))."'" ; 
										$rows_series2 .= $a["ga:pageviews"] ; 
										$rows_series2_tooltip .= "'".str_replace("'", "", sprintf(__("%s visits"),$a["ga:pageviews"]))."'" ; 
										
										$max_value = max($max_value, $a["ga:visits"], $a["ga:pageviews"]) ; 
										
										$first = false ; 
										$nb++ ; 
									}
								}
								if ($pas=="ga:year,ga:month") {
									// On boucle sur les annees
									foreach ($d as $m => $a) {
										if (!$first) {
											$rows_legend .= "," ; 
											$rows_series1 .= "," ; 
											$rows_series1_tooltip .= "," ;  
											$rows_series2 .= "," ; 
											$rows_series2_tooltip .= "," ; 
										}
																				
										$visit = $a["ga:visits"] ; 
										$pageViews = $a["ga:pageviews"] ; 
										
										$rows_legend .= "'".sprintf(__('%s %s', $this->pluginID), date_i18n("F",mktime(date_i18n("H"),date_i18n("i"),date_i18n("s") ,$m, date_i18n("j"))), $k)."'" ; 
										$rows_series1 .= $a["ga:visits"] ; 
										$rows_series1_tooltip .= "'".str_replace("'", "", sprintf(__("%s visitors"),$a["ga:visits"]))."'" ; 
										$rows_series2 .= $a["ga:pageviews"] ; 
										$rows_series2_tooltip .= "'".str_replace("'", "", sprintf(__("%s visits"),$a["ga:pageviews"]))."'" ; 
										
										$max_value = max($max_value, $a["ga:visits"], $a["ga:pageviews"]) ; 

										$first = false ; 
										$nb++ ; 
									}
								}
								$beforelast_persons = $last_persons ; 	
								$beforelast_visits = $last_visits ; 
								$last_persons = $visit ; 
								$last_visits = $pageViews ; 
							}
							$width = "900" ; 
							$height = "400" ; 
							
							$max_value = ceil($max_value*1.06/10)*10 ; 
							
							?>
							<h3><?php echo __('Google Analytics Data', $this->pluginID)?></h3>
							<p><?php echo __('According to Google Analytics Data, here is the number of visits and the number of page views.', $this->pluginID)?></p>
							<p><?php 
								if ($pas=="ga:date") {
									echo sprintf(__('Today, %s people have visited your site and %s pages have been viewed (%s and %s for yesterday).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								} else if ($pas=="ga:year,ga:week") {
									echo sprintf(__('This week, %s people have visited your site and %s pages have been viewed (%s and %s for last week).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								} else if ($pas=="ga:year,ga:month") {
									echo sprintf(__('This month, %s people have visited your site and %s pages have been viewed (%s and %s for last month).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								}?>
							</p>
							<?php
							if ($pas=="ga:year,ga:week") {
								?>
								<p><?php echo __('NOTA: Google considers that the week starts on sundays which is not ISO 8601 compliant (normally starts on mondays). So be careful when comparing weeks with your calendar: week numbers may be sligthly different.', $this->pluginID)?></p>
								<?php
							}
							
							?>
							<div id="google_visits_count" style="margin: 0px auto; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
							<script  type="text/javascript">
								jQuery(function(){
									jQuery.elycharts.templates['google_visits_count'] = {
									 	type : "line",
									 	margins : [10, 10, 80, 50],
									 	defaultSeries : {
									 		plotProps : {
									   			opacity : 0.6
									  		},
									  		highlight : {
									   			overlayProps : {
													fill : "white",
													opacity : 0.2
									   			}
									  		},
									  		tooltip : {
										   		frameProps : {
											   		opacity: 0.95,
		                							fill: "#292929",
									                stroke: "#CDCDCD",
									                'stroke-width': 1
										   		},
										   		height : 20,
										  		padding: [3, 3],
										   		offset : [10, 0],
										   		contentStyle : {
											   		"font-weight": "normal",
											   		"font-family": "sans-serif, Verdana", 
											   		color: "#FFFFFF",
											   		"text-align": "center"
											 	}
											},
										},
								 		series : {
										  	serie1 : {
										   		color : "#000066"
										  	},
								 			serie2 : {
								   				color : "#9494BF"
								  			}
								 		},
								 		defaultAxis : {
								  			labels : true
								 		},
								 		axis : {
								 			l  : {
								 				max:<?php echo $max_value?>, 
								 				labelsProps : {
												font : "10px Verdana"
												}
								 			}, 
								 			x : {
												labelsRotate : 35,
												labelsProps : {
												font : "10px bold Verdana"
												}
											}
								 		},
								 		features : {
								  			grid : {
								   				draw : [true, false],
								   				forceBorder : false,
								   				evenHProps : {
													fill : "#FFFFFF",
													opacity : 0.2
								   				},
								  				oddHProps : {
													fill : "#AAAAAA",
													opacity : 0.2
								   				}
								  			}
								 		}
									};
								
									jQuery("#google_visits_count").chart({
										 template : "google_visits_count",
										 tooltips : {
										  serie1 : <?php echo "[$rows_series1_tooltip]" ; ?>,
										  serie2 : <?php echo "[$rows_series2_tooltip]" ; ?>
										 },
										 values : {
										  serie1 : <?php echo "[$rows_series1]" ; ?>,
										  serie2 : <?php echo "[$rows_series2]" ; ?>
										 },
										 labels : <?php echo "[$rows_legend]" ; ?>,
										 defaultSeries : {
										  type : "bar"
										 },
										 barMargins : 10
									});
								});								
							</script>
							<?php
						}	
						
						
						if ( ($this->get_param('localwebstat')) && ($this->get_param('local_show_visits')) && (isset($data_local['visits'])) ) {
							$google_show = true ; 
							
							$rows_legend = "" ; 
							$rows_series1= "" ; 
							$rows_series1_tooltip = "" ; 
							$rows_series2= "" ; 
							$rows_series2_tooltip = "" ; 
							
							$max_value = 10 ; 
							
							$first = true ; 
							$nb = 0 ; 
							
							$last_persons = "0" ; 
							$last_visits = "0" ; 
							foreach ($data_local['visits'] as $k => $d) {
								$k = trim($k) ; 
								if ($pas_local=="day") {
									if (!$first) {
										$rows_legend .= "," ; 
										$rows_series1 .= "," ; 
										$rows_series1_tooltip .= "," ;  
										$rows_series2 .= "," ; 
										$rows_series2_tooltip .= "," ; 
									}
									
									$visit = $d["visits"] ;
									$pageViews = $d["pageviews"] ; 
									
									$rows_legend .= "'".date_i18n(get_option('date_format') , strtotime($k))."'" ; 
									$rows_series1 .= $d["visits"] ; 
									$rows_series1_tooltip .= "'".str_replace("'", "", sprintf(__("%s visitors"),$d["visits"]))."'" ; 
									$rows_series2 .= $d["pageviews"] ; 
									$rows_series2_tooltip .= "'".str_replace("'", "", sprintf(__("%s visits"),$d["pageviews"]))."'" ; 
									
									$max_value = max($max_value, $d["visits"], $d["pageviews"]) ; 
									
									$first = false ; 
									$nb++ ; 
								}
								if ($pas_local=="week") {
									// On boucle sur les annees
									foreach ($d as $w => $a) {
										if (!$first) {
											$rows_legend .= "," ; 
											$rows_series1 .= "," ; 
											$rows_series1_tooltip .= "," ;  
											$rows_series2 .= "," ; 
											$rows_series2_tooltip .= "," ; 
										}
										
										$visit = $a["visits"] ;
										$pageViews = $a["pageviews"] ; 
									
										$rows_legend .= "'".sprintf(__('Week %s (%s)', $this->pluginID), $w, $k)."'" ; 
										$rows_series1 .= $a["visits"] ; 
										$rows_series1_tooltip .= "'".str_replace("'", "", sprintf(__("%s visitors"),$a["visits"]))."'" ; 
										$rows_series2 .= $a["pageviews"] ; 
										$rows_series2_tooltip .= "'".str_replace("'", "", sprintf(__("%s visits"),$a["pageviews"]))."'" ; 
										
										$max_value = max($max_value, $a["visits"], $a["pageviews"]) ; 
										
										$first = false ; 
										$nb++ ; 
									}
								}
								if ($pas_local=="month") {
									// On boucle sur les annees
									foreach ($d as $m => $a) {
										if (!$first) {
											$rows_legend .= "," ; 
											$rows_series1 .= "," ; 
											$rows_series1_tooltip .= "," ;  
											$rows_series2 .= "," ; 
											$rows_series2_tooltip .= "," ; 
										}
										
										$visit = $a["visits"] ;
										$pageViews = $a["pageviews"] ; 
										$rows_legend .= "'".sprintf(__('%s %s', $this->pluginID), date_i18n("F",mktime(date_i18n("H"),date_i18n("i"),date_i18n("s") ,intval($m), date_i18n("j"))), intval($k))."'" ; 
										$rows_series1 .= $a["visits"] ; 
										$rows_series1_tooltip .= "'".str_replace("'", "", sprintf(__("%s visitors"),$a["visits"]))."'" ; 
										$rows_series2 .= $a["pageviews"] ; 
										$rows_series2_tooltip .= "'".str_replace("'", "", sprintf(__("%s visits"),$a["pageviews"]))."'" ; 
										
										$max_value = max($max_value, $a["visits"], $a["pageviews"]) ; 
										
										$first = false ; 
										$nb++ ; 
									}
								}
								$beforelast_persons = $last_persons ; 	
								$beforelast_visits = $last_visits ; 
								$last_persons = $visit ; 
								$last_visits = $pageViews ; 
							}
							$width = "900" ; 
							$height = "400" ; 
							
							$max_value = ceil($max_value*1.06/10)*10 ; 
							
							?>
							<h3><?php echo __('Local Data', $this->pluginID)?></h3>
							<p><?php echo __('According to Local Data, here is the number of visits and the number of page views.', $this->pluginID)?></p>
							<p><?php 
								if ($pas_local=="day") {
									echo sprintf(__('Today, %s people have visited your site and %s pages have been viewed (%s and %s for yesterday).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								} else if ($pas_local=="week") {
									echo sprintf(__('This week, %s people have visited your site and %s pages have been viewed (%s and %s for last week).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								} else if ($pas_local=="month") {
									echo sprintf(__('This month, %s people have visited your site and %s pages have been viewed (%s and %s for last month).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								}?>
							</p>							
							<div id="local_visits_count" style="margin: 0px auto; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
							<script  type="text/javascript">
								jQuery(function(){
									jQuery.elycharts.templates['local_visits_count'] = {
									 	type : "line",
									 	margins : [10, 10, 80, 50],
									 	defaultSeries : {
									 		plotProps : {
									   			opacity : 0.6
									  		},
									  		highlight : {
									   			overlayProps : {
													fill : "white",
													opacity : 0.2
									   			}
									  		},
									  		tooltip : {
										   		frameProps : {
											   		opacity: 0.95,
		                							fill: "#292929",
									                stroke: "#CDCDCD",
									                'stroke-width': 1
										   		},
										   		height : 20,
										  		padding: [3, 3],
										   		offset : [10, 0],
										   		contentStyle : {
											   		"font-weight": "normal",
											   		"font-family": "sans-serif, Verdana", 
											   		color: "#FFFFFF",
											   		"text-align": "center"
											 	}
											},
										},
								 		series : {
										  	serie1 : {
										   		color : "#000066"
										  	},
								 			serie2 : {
								   				color : "#9494BF"
								  			}
								 		},
								 		defaultAxis : {
								  			labels : true
								 		},
								 		axis : {
								 			l  : {
								 				max:<?php echo $max_value?>, 
								 				labelsProps : {
												font : "10px Verdana"
												}
								 			}, 
								 			x : {
												labelsRotate : 35,
												labelsProps : {
												font : "10px bold Verdana"
												}
											}
								 		},
								 		features : {
								  			grid : {
								   				draw : [true, false],
								   				forceBorder : false,
								   				evenHProps : {
													fill : "#FFFFFF",
													opacity : 0.2
								   				},
								  				oddHProps : {
													fill : "#AAAAAA",
													opacity : 0.2
								   				}
								  			}
								 		}
									};
								
									jQuery("#local_visits_count").chart({
										 template : "local_visits_count",
										 tooltips : {
										  serie1 : <?php echo "[$rows_series1_tooltip]" ; ?>,
										  serie2 : <?php echo "[$rows_series2_tooltip]" ; ?>
										 },
										 values : {
										  serie1 : <?php echo "[$rows_series1]" ; ?>,
										  serie2 : <?php echo "[$rows_series2]" ; ?>
										 },
										 labels : <?php echo "[$rows_legend]" ; ?>,
										 defaultSeries : {
										  type : "bar"
										 },
										 barMargins : 10
									});
								});
							</script>
							<?php
						}
						

						
					$content_graph = ob_get_clean() ; 
					if (strlen($content_graph)>0) {
						$box = new SLFramework_Box (__("Visits Count", $this->pluginID), $content_graph) ; 
						echo $box->flush() ; 
					}
					
					// Creating the graph for the Google
					// Analytics type distribution
					//----------------------------------
					
					ob_start() ; 
						if ( ($this->get_param('google_show_type')) && (isset($data['browser'])) && (isset($data['os'])) ) {
							$google_show = true ; 
							$width = 450 ; 
							$height = 300 ; 
							
							?>
							<h3><?php echo __('Google Analytics Data', $this->pluginID)?></h3>
							<p><?php echo __('According to Google Analytics Data, here is the distribution of browser types and OS.', $this->pluginID)?></p>
							<div style="margin: 0px auto; width:<?php echo $width*2; ?>px; height:<?php echo $height; ?>px;">
								<div id="google_visitors_browser" style="float: left; margin: 0; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
								<script type="text/javascript">
									jQuery(function() {
										jQuery.elycharts.templates['google_visitors_browser'] = {
											type : "pie",
											defaultSeries : {
												plotProps : {
													stroke : "white",
													"stroke-width" : 1,
													opacity : 0.8
												},
												highlight : {
													move : 10
												},
												tooltip : {
													frameProps : {
														opacity: 0.95,
														fill: "#292929",
														stroke: "#CDCDCD",
														'stroke-width': 1
													},
													height : 20,
													width : 150,
													padding: [3, 3],
													offset : [10, 0],
													contentStyle : {
														"font-weight": "normal",
														"font-family": "sans-serif, Verdana", 
														color: "#FFFFFF",
														"text-align": "center"
													}
												}
											},
											features : {
												legend : {
													horizontal : false,
													width : 120,
													height : 200,
													x : 1,
													y : 60,
													borderProps : {
														"fill-opacity" : 0.3
													}
												}
											}
										};
								
										jQuery("#google_visitors_browser").chart({
											template : "google_visitors_browser",
											values : {
												serie1 : [<?php
													$first = true ; 
													foreach ($data['browser'] as $k => $d) {
														if (!$first) echo "," ; 
														echo $d["ga:visits"] ; 
														$first = false ; 
													}
												?>]
											},
											labels : [<?php
												$first = true ; 
												foreach ($data['browser'] as $k => $d) {
													if (!$first) echo "," ; 
													echo "'".str_replace("'","",$k)."'" ; 
													$first = false ; 
												}
											?>],
											legend : [<?php
												$first = true ; 
												foreach ($data['browser'] as $k => $d) {
													if (!$first) echo "," ; 
													echo "'".str_replace("'","",$k)."'" ; 
													$first = false ; 
												}
											?>],
											tooltips : {
												serie1 : [<?php
													$first = true ; 
													foreach ($data['browser'] as $k => $d) {
														if (!$first) echo "," ; 
														echo "'".str_replace("'","",$k)." (".$d["ga:visits"].")'" ; 
														$first = false ; 
													}
												?>]
											},
											defaultSeries : {
												values : [<?php
													$list_color = array("#6699CC", "#003366", "#C0C0C0", "#000044", "#E8D0A9", "#B7AFA3",  "#C1DAD6",  "#F5FAFA",  "#ACD1E9",  "#6D929B") ; 
													$first = true ; 
													$j=0 ; 
													foreach ($data['browser'] as $k => $d) {
														if (!$first) echo "," ; 
														echo '{plotProps : {fill : "'.$list_color[$j].'" }}' ; 
														$first = false ; 
														$j++ ; 
														if ($j>count($list_color)-1) {
															$j=0 ; 
														}
													}
												?>]
											}
										});
									
									});
								</script>
								<div id="google_visitors_os" style="float: left; margin: 0; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
								<script  type="text/javascript">
									jQuery(function() {
										jQuery.elycharts.templates['google_visitors_os'] = {
											type : "pie",
											defaultSeries : {
												plotProps : {
													stroke : "white",
													"stroke-width" : 1,
													opacity : 0.8
												},
												highlight : {
													move : 10
												},
												tooltip : {
													frameProps : {
														opacity: 0.95,
														fill: "#292929",
														stroke: "#CDCDCD",
														'stroke-width': 1
													},
													height : 20,
													width : 150,
													padding: [3, 3],
													offset : [10, 0],
													contentStyle : {
														"font-weight": "normal",
														"font-family": "sans-serif, Verdana", 
														color: "#FFFFFF",
														"text-align": "center"
													}
												}
											},
											features : {
												legend : {
													horizontal : false,
													width : 110,
													height : 200,
													x : 330,
													y : 60,
													borderProps : {
														"fill-opacity" : 0.3
													}
												}
											}
										};
								
										jQuery("#google_visitors_os").chart({
											template : "google_visitors_os",
											values : {
												serie1 : [<?php
													$first = true ; 
													foreach ($data['os'] as $k => $d) {
														if (!$first) echo "," ; 
														echo $d["ga:visits"] ; 
														$first = false ; 
													}
												?>]
											},
											labels : [<?php
												$first = true ; 
												foreach ($data['os'] as $k => $d) {
													if (!$first) echo "," ; 
													echo "'".str_replace("'","",$k)."'" ; 
													$first = false ; 
												}
											?>],
											legend : [<?php
												$first = true ; 
												foreach ($data['os'] as $k => $d) {
													if (!$first) echo "," ; 
													echo "'".str_replace("'","",$k)."'" ; 
													$first = false ; 
												}
											?>],
											tooltips : {
												serie1 : [<?php
													$first = true ; 
													foreach ($data['os'] as $k => $d) {
														if (!$first) echo "," ; 
														echo "'".str_replace("'","",$k)." (".$d["ga:visits"].")'" ; 
														$first = false ; 
													}
												?>]
											},
											defaultSeries : {
												values : [<?php
													$list_color = array("#6699CC", "#003366", "#C0C0C0", "#000044", "#E8D0A9", "#B7AFA3",  "#C1DAD6",  "#F5FAFA",  "#ACD1E9",  "#6D929B") ; 
													$first = true ; 
													$j=0 ; 
													foreach ($data['os'] as $k => $d) {
														if (!$first) echo "," ; 
														echo '{plotProps : {fill : "'.$list_color[$j].'" }}' ; 
														$first = false ; 
														$j++ ; 
														if ($j>count($list_color)-1) {
															$j=0 ; 
														}
													}
												?>]
											}
										});
									
									});
								</script>
							</div>
							<?php
						}
						
						if ( ($this->get_param('local_show_type')) && (isset($data_local['browser'])) && (isset($data_local['os'])) ) {
							$google_show = true ; 
							$width = 450 ; 
							$height = 300 ; 
							
							?>
							<h3><?php echo __('Local Data', $this->pluginID)?></h3>
							<p><?php echo __('According to Local Data, here is the distribution of browser types and OS.', $this->pluginID)?></p>
							<div style="margin: 0px auto; width:<?php echo $width*2; ?>px; height:<?php echo $height; ?>px;">
								<div id="local_visitors_browser" style="float: left; margin: 0; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
								<script type="text/javascript">
									jQuery(function() {
										jQuery.elycharts.templates['local_visitors_browser'] = {
											type : "pie",
											defaultSeries : {
												plotProps : {
													stroke : "white",
													"stroke-width" : 1,
													opacity : 0.8
												},
												highlight : {
													move : 10
												},
												tooltip : {
													frameProps : {
														opacity: 0.95,
														fill: "#292929",
														stroke: "#CDCDCD",
														'stroke-width': 1
													},
													height : 20,
													width : 150,
													padding: [3, 3],
													offset : [10, 0],
													contentStyle : {
														"font-weight": "normal",
														"font-family": "sans-serif, Verdana", 
														color: "#FFFFFF",
														"text-align": "center"
													}
												}
											},
											features : {
												legend : {
													horizontal : false,
													width : 120,
													height : 200,
													x : 1,
													y : 60,
													borderProps : {
														"fill-opacity" : 0.3
													}
												}
											}
										};
								
										jQuery("#local_visitors_browser").chart({
											template : "local_visitors_browser",
											values : {
												serie1 : [<?php
													$first = true ; 
													foreach ($data_local['browser'] as $k => $d) {
														if (!$first) echo "," ; 
														echo $d["visits"] ; 
														$first = false ; 
													}
												?>]
											},
											labels : [<?php
												$first = true ; 
												foreach ($data_local['browser'] as $k => $d) {
													if (!$first) echo "," ; 
													echo "'".str_replace("'","",$k)."'" ; 
													$first = false ; 
												}
											?>],
											legend : [<?php
												$first = true ; 
												foreach ($data_local['browser'] as $k => $d) {
													if (!$first) echo "," ; 
													echo "'".str_replace("'","",$k)."'" ; 
													$first = false ; 
												}
											?>],
											tooltips : {
												serie1 : [<?php
													$first = true ; 
													foreach ($data_local['browser'] as $k => $d) {
														if (!$first) echo "," ; 
														echo "'".str_replace("'","",$k)." (".$d["visits"].")'" ; 
														$first = false ; 
													}
												?>]
											},
											defaultSeries : {
												values : [<?php
													$list_color = array("#6699CC", "#003366", "#C0C0C0", "#000044", "#E8D0A9", "#B7AFA3",  "#C1DAD6",  "#F5FAFA",  "#ACD1E9",  "#6D929B") ; 
													$first = true ; 
													$j=0 ; 
													foreach ($data_local['browser'] as $k => $d) {
														if (!$first) echo "," ; 
														echo '{plotProps : {fill : "'.$list_color[$j].'" }}' ; 
														$first = false ; 
														$j++ ; 
														if ($j>count($list_color)-1) {
															$j=0 ; 
														}
													}
												?>]
											}
										});
									
									});

									
								</script>
								<div id="local_visitors_os" style="float: left; margin: 0; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
								<script  type="text/javascript">
									jQuery(function() {
										jQuery.elycharts.templates['local_visitors_os'] = {
											type : "pie",
											defaultSeries : {
												plotProps : {
													stroke : "white",
													"stroke-width" : 1,
													opacity : 0.8
												},
												highlight : {
													move : 10
												},
												tooltip : {
													frameProps : {
														opacity: 0.95,
														fill: "#292929",
														stroke: "#CDCDCD",
														'stroke-width': 1
													},
													height : 20,
													width : 150,
													padding: [3, 3],
													offset : [10, 0],
													contentStyle : {
														"font-weight": "normal",
														"font-family": "sans-serif, Verdana", 
														color: "#FFFFFF",
														"text-align": "center"
													}
												}
											},
											features : {
												legend : {
													horizontal : false,
													width : 110,
													height : 200,
													x : 330,
													y : 60,
													borderProps : {
														"fill-opacity" : 0.3
													}
												}
											}
										};
								
										jQuery("#local_visitors_os").chart({
											template : "local_visitors_os",
											values : {
												serie1 : [<?php
													$first = true ; 
													foreach ($data_local['os'] as $k => $d) {
														if (!$first) echo "," ; 
														echo $d["visits"] ; 
														$first = false ; 
													}
												?>]
											},
											labels : [<?php
												$first = true ; 
												foreach ($data_local['os'] as $k => $d) {
													if (!$first) echo "," ; 
													echo "'".str_replace("'","",$k)."'" ; 
													$first = false ; 
												}
											?>],
											legend : [<?php
												$first = true ; 
												foreach ($data_local['os'] as $k => $d) {
													if (!$first) echo "," ; 
													echo "'".str_replace("'","",$k)."'" ; 
													$first = false ; 
												}
											?>],
											tooltips : {
												serie1 : [<?php
													$first = true ; 
													foreach ($data_local['os'] as $k => $d) {
														if (!$first) echo "," ; 
														echo "'".str_replace("'","",$k)." (".$d["visits"].")'" ; 
														$first = false ; 
													}
												?>]
											},
											defaultSeries : {
												values : [<?php
													$list_color = array("#6699CC", "#003366", "#C0C0C0", "#000044", "#E8D0A9", "#B7AFA3",  "#C1DAD6",  "#F5FAFA",  "#ACD1E9",  "#6D929B") ; 
													$first = true ; 
													$j=0 ; 
													foreach ($data_local['os'] as $k => $d) {
														if (!$first) echo "," ; 
														echo '{plotProps : {fill : "'.$list_color[$j].'" }}' ; 
														$first = false ; 
														$j++ ; 
														if ($j>count($list_color)-1) {
															$j=0 ; 
														}
													}
												?>]
											}
										});
									
									});
								</script>
							</div>
							<?php
						}
						
					$content_graph = ob_get_clean() ; 
					if (strlen($content_graph)>0) {
						$box = new SLFramework_Box (__("Browser and OS Statistics", $this->pluginID), $content_graph) ; 
						echo $box->flush() ; 
					}
					
					// Display maps
					//----------------------------------
					
					$content_map = "" ; 
					
					if ($this->get_param('geolocate_show_world')) {
						
						ob_start() ; 
							echo "<div id='geolocate_show_world' style='margin:0px auto;width:800px;height:500px;'></div>" ; 
						$content_map .= ob_get_clean() ; 
					}
					
					if ($this->get_param('geolocate_show_europe')) {
						ob_start() ; 
							if (strlen($content_map)>0) {
								echo "<br/>" ; 
							}
							echo "<div id='geolocate_show_europe' style='margin:0px auto;width:800px;height:500px;'></div>" ; 
						$content_map .= ob_get_clean() ; 
						
					}
					
					if (trim($this->get_param('geolocate_show_state'))!=""){
						$state = explode(',', $this->get_param('geolocate_show_state')) ;
						foreach ($state as $st) {
							ob_start() ; 
								if (strlen($content_map)>0) {
									echo "<br/>" ; 
								}
								echo "<div id='geolocate_show_".sha1($st)."' style='margin:0px auto;width:800px;height:500px;'></div>" ; 
							$content_map .= ob_get_clean() ; 
						}
					}
					
					if (strlen($content_map)>0) {
						$box = new SLFramework_Box (sprintf(__("Geolocalisation of visits/visitors during the last %s days", $this->pluginID), $this->get_param('local_keep_detailed_info')), $content_map) ; 
						echo $box->flush() ; 
					}
					
					// Display Information on last viewed pages
					//----------------------------------

					ob_start() ; 
						if ($this->get_param('local_detail')) {
							$sql_select = "SELECT * FROM ".$this->table_name." WHERE " ; 
							$sql_select .= "type='single' " ; 
							$sql_select .= "ORDER BY time DESC " ; 
							$nombre = "50" ;

							foreach ($this->get_param('local_detail_nb') as $n) {
								if (strpos($n[0], "*")!==false) {
									switch ($n[1]) {
										case 'nb100' : $nombre="100" ; break ; 
										case 'nb50' : $nombre="50" ; break ; 
										case 'nb20' : $nombre="20" ; break ; 
										case 'nb10' : $nombre="10" ; break ; 
									}
								}
							}							
							$sql_select .= "LIMIT ".$nombre." ; " ; 
							$results = $wpdb->get_results($sql_select) ; 

							$nb_result = 0 ; 
							$table = new SLFramework_Table() ;
							$table->title(array(__("Time", $this->pluginID),__("IP Address", $this->pluginID), __("Viewed Page", $this->pluginID), __("Browser", $this->pluginID), __("OS", $this->pluginID) , __("Referer", $this->pluginID), __("Time spent", $this->pluginID) )) ;
							?>
							
							<p><?php echo sprintf(__('Please note that the current local time of the server is %s. If it is not correct, please set the Wordpress installation correctly.', $this->pluginID), "<strong>".date_i18n('Y-m-d H:i:s')."</strong>")?></p>
							<p><?php echo __('If the entry is displayed for the first time, the time field will be in bold characters.', $this->pluginID)?></p>
							<p><?php echo __('If the user accesses your website for the first time today, the IP field will be in blod characters.', $this->pluginID)?></p>
							<?php
							foreach ( $results as $l ) {
								$time = $l->time ; 
								if (!$l->viewed) {
									$time = "<b>".$l->time."</b>" ; 
								}
								$cel1 =  new adminCell($time) ;
								$ip = $l->ip ; 
								$ips = explode (",", $ip) ; 
								$content_ip = "" ; 
								for ($i=0; $i<count($ips) ; $i++) {
									if ($i!=count($ips)-1) {
										$content_ip .= "<small>".$ips[$i]."</small> via " ; 
									} else {
										if ($l->uniq_visit>0) {
											$content_ip .= "<b>".$ips[$i]."</b>" ; 
										} else {
											$content_ip .= $ips[$i] ; 
										}
									}
								}
								
								if (($l->geolocate!="")&&(!is_null($l->geolocate))){
									$array_geo = @unserialize($l->geolocate) ;
									if (is_array($array_geo)){
										$content_ip .= "<br/>".ucfirst(strtolower($array_geo['countryName']))." (".$array_geo['zipCode']." ".ucfirst(strtolower($array_geo['cityName'])).")" ; 
									}
								}
								
								$cel2 =  new adminCell($content_ip);
								$idpage =  url_to_postid( $l->page ) ; 
								// Si on a trouve
								if ($idpage!=0) {
									$page = "<a href='".get_permalink($idpage)."'>".get_the_title($idpage)."</a>" ; 
								} else {
									$page = "<a href='".$l->page."'>".$l->page."</a>" ; 
								}
								$cel3 =  new adminCell($page);
								$cel4 =  new adminCell($l->browserName." ".$l->browserVersion);
								//$cel5 = new adminCell($l->platformName." ".$l->platformVersion." [".$l->browserUserAgent."]") ;
								$cel5 = new adminCell($l->platformName." ".$l->platformVersion) ;
								$source = "" ; 
								$referer = $l->referer ; 
								$type_referer = "link" ;
								if (preg_match("/^http:\/\/www\.google(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "google" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[2]))) ; 
								} else if ( (preg_match("/^http:\/\/www\.google(.*)imgres(.*)imgurl=([^&|^#]+)/i", $referer, $matches)) ) {
									$source = "google_image" ; 
									$type_referer = "img" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if ( (preg_match("/^http:\/\/www\.google(.*)[?|&|#]q=([&#]+)/i", $referer, $matches)) || (preg_match("/^http:\/\/www\.google(.*)[?|&|#]q=$/i", $referer, $matches)) || (preg_match("/^http[s]*:\/\/www\.google\.(.*)$/i", $referer, $matches)) ) {
									$source = "google" ; 
									$type_referer = "stripped_words" ;
									$referer = __('Unknown keywords.', $this->pluginID) ; 
								} else if (preg_match("/^http:\/\/www\.bing(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "bing" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[2]))) ; 
								} else if (preg_match("/^http:\/\/(.*)\.yahoo\.(.*)[?|&|#]p=([^&|^#]+)/i", $referer, $matches)) {
									$source = "yahoo" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if (preg_match("/^http:\/\/(.*)\.search\.yahoo\.(.*)/i", $referer, $matches)) {
									$source = "yahoo" ; 
									$type_referer = "stripped_words" ;
									$referer = __('Unknown keywords.', $this->pluginID) ; 
								} else if (preg_match("/^http:\/\/(.*)\.aol\.(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "aol" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if (preg_match("/^http:\/\/(.*)\.search\.sweetim\.(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "sweetim" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if (preg_match("/^http:\/\/(.*)\.mysearchresults\.com\/search(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "mysearchresults" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if (preg_match("/^http:\/\/(.*)\.search-results\.com\/(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "search-results" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if (preg_match("#^http://wordpress\.org/extend/plugins/([^/]*)/#i", $referer, $matches)) {
									$source = "wordpress" ; 
									$type_referer = "words" ;
									$referer = sprintf(__('Plugin: %s', $this->pluginID),strip_tags($matches[1])) ; 
								} else if (preg_match("#^http://(.*)/wp-admin/admin\.php\?page=([^/]*)/#i", $referer, $matches)) {
									$source = "wordpress_local" ; 
									$type_referer = "words" ;
									$referer = sprintf(__('Plugin: %s, website: %s', $this->pluginID),strip_tags($matches[2]), "<a href='http://".strip_tags($matches[1])."'>http://".strip_tags($matches[1])."</a>") ; 
								} else if (preg_match("/^http:\/\/search\.babylon(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "babylon" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[2]))) ; 
								} else if ( (preg_match("/^http:\/\/search\.free(.*)/i", $referer, $matches)) ) {
									$source = "free" ; 
									$type_referer = "stripped_words" ;
									$referer = __('Unknown keywords.', $this->pluginID) ; 
								} else if (preg_match("/^http:\/\/(.*)\.ask\.(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "ask" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if (preg_match("/^http:\/\/(.*)\.ask\.(.*)[?|&|#]searchfor=([^&|^#]+)/i", $referer, $matches)) {
									$source = "ask" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if (strpos($referer, get_site_url())!==false) {
									if (url_to_postid($referer)!=0) {
										$source = "internal" ; 
										$type_referer = "local" ;
										$referer = get_the_title(url_to_postid($referer)) ; 
									} else if ((trim(str_replace(get_site_url(), "", $referer))=="")||(trim(str_replace(get_site_url(), "", $referer))=="/")) {
										$source = "internal" ; 
										$type_referer = "local" ;
										$referer = get_bloginfo('name') ; 									
									}
								
								}
								
								$content_referer = "" ; 
								if ($type_referer == "words") {
									$content_referer = "<a href='".strip_tags($l->referer)."'><img style='border:0' src='".plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."img/".$source.".png"."' alt='".$source."'></a> ".ucfirst(strtolower($referer)) ;
								} else if ($type_referer == "stripped_words") {
									$content_referer = "<a href='".strip_tags($l->referer)."'><img src='".plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."img/".$source."_masqued.png"."' alt='".ucfirst(strtolower($source))."'></a> <span style='color:#BBBBBB'>".$referer."</span>" ;
								} else if ($type_referer == "img") {
									$content_referer = "<a href='".strip_tags($l->referer)."'><img src='".plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."img/".$source.".png"."' alt=''></a> <img src='$referer' alt='' width='100px'/>" ;
								} else if ($type_referer == "link") {
									$length = 50 ; 
									$refererDisplay = substr($referer, 0, $length);
									if (strlen($referer) > $length) {
										$refererDisplay .= '...';
									}
									$content_referer = "<a href='".$referer."'>".urldecode($refererDisplay)."</a>" ;
								} else if ($type_referer == "local") {
									$content_referer = "<a href='".strip_tags($l->referer)."'><img style='border:0' src='".plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."img/local.png"."' alt='".$source."'> <span style='color:#D4DDEE'>".$referer."</span></a>" ;
								}
								
								
								$cel6 = new adminCell($content_referer) ;
								$minutes = intval(($l->refreshNumber*10 / 60)); 
								$hms = str_pad($minutes, 2, "0", STR_PAD_LEFT). ":";
								$seconds = intval(($l->refreshNumber*10) % 60); 
								$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);
								
								if ($l->refreshNumber>=($this->maxTime/10)) {
									$minutes = intval(($this->maxTime / 60)); 
									$hms = str_pad($minutes, 2, "0", STR_PAD_LEFT). ":";
									$seconds = intval($this->maxTime % 60); 
									$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);
									$hms = ">".$hms ; 
								}
								$cel7 = new adminCell($hms) ;
								$table->add_line(array($cel1, $cel2, $cel3, $cel4, $cel5, $cel6, $cel7), $l->id) ;
								$nb_result ++ ; 
							}
							
							// On met a jour la table pour dire qu'on a tout vu
							$sql_update = "UPDATE ".$this->table_name." SET viewed=TRUE " ; 
							$wpdb->query($sql_update) ; 
							
							if ($nb_result==0) {
								$cel1 = new adminCell(__("No data for now... Please wait until someone visits your website", $this->pluginID)) ;
								$cel2 = new adminCell("") ;
								$cel3 = new adminCell("") ;
								$cel4 = new adminCell("") ;
								$cel5 = new adminCell("") ;
								$cel6 = new adminCell("") ;
								$cel7 = new adminCell("") ;
								$table->add_line(array($cel1, $cel2, $cel3, $cel4, $cel5, $cel6, $cel7), '1') ;
							}
							echo $table->flush() ; 
						}
					$content_graph = ob_get_clean() ; 
					if (strlen($content_graph)>0) {
						$box = new SLFramework_Box (sprintf(__("Details on the Last %s Viewed Pages", $this->pluginID),$nombre), $content_graph) ; 
						echo $box->flush() ; 
					}					
					
				} else {
					echo "<p>".__('You have configured no graph... Please go on the parameters tab to configure them.', $this->pluginID)."</p>" ; 
				}
				

				
			$tabs->add_tab(__('Web Statistics',  $this->pluginID), ob_get_clean()) ; 	
				
			$tabs->add_tab(__('Parameters',  $this->pluginID), $parameters , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_param.png") ; 	
			
			// HOW To
			ob_start() ;
				echo "<p>".__('This plugin enables the improving of your traffic by supervising it and by helping the web crawlers to identify your contents to be indexed.', $this->pluginID)."</p>" ;
			$howto1 = new SLFramework_Box (__("Purpose of that plugin", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".sprintf(__('In order to help the web crawlers to identifify the contents you want to share, this plugin creates a file named %s and a compressed file %s that list the different url to be indexed with a weight.', $this->pluginID), "<code>sitemaps.xml</code>", "<code>sitemaps.xml.gz</code>" )."</p>" ;
				echo "<p>".__('The updates of these files may be notified to the following crawlers:', $this->pluginID)."</p>" ;
				echo "<ul style='list-style-type: disc;padding-left:40px;'>" ; 
					echo "<li>Google</li>" ;
					echo "<li>Yahoo</li>" ; 
					echo "<li>Bing</li>" ; 
					echo "<li>Ask</li>" ; 
				echo "</ul>" ; 
				echo "<p>".sprintf(__('You also may add some %s to your headers. These tags helps the crawler to understand the page.', $this->pluginID), "<code>metatags</code>")."</p>" ;
			$howto2 = new SLFramework_Box (__("How to help web crawlers?", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".sprintf(__('This plugin also enables the creation of a chart that summarize the traffic volume on your server (either thanks to a local database or thanks to %s).', $this->pluginID), '<code>Google Analytics</code>')."</p>" ; 
			$howto3 = new SLFramework_Box (__("Supervising the traffic", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".sprintf(__('This plugin may be render compliant with %s recommandation regarding personal data management.', $this->pluginID), '<code>CNIL</code>')."</p>" ; 
				echo "<p>".__('Then, if you use local database, a warning will be display to indicate that the plugin use some cookies and the two last bytes of the IP address will be masked.', $this->pluginID)."</p>" ; 
				echo "<p>".sprintf(__('If you use %s, the system will work only for users that accept to explicitely accept this system thanks to a popup system.', $this->pluginID), '<code>Google Analytics</code>')."</p>" ; 
			$howto4 = new SLFramework_Box (__("Compliance with Personal data management", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				 echo $howto1->flush() ; 
				 echo $howto2->flush() ; 
				 echo $howto3->flush() ; 
				 echo $howto4->flush() ; 
			$tabs->add_tab(__('How To',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_how.png") ; 				

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new SLFramework_Translation($this->pluginID, $plugin) ; 
				$trans->enable_translation() ; 
			$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_trad.png") ; 	

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new SLFramework_Feedback($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_mail.png") ; 	
			
			ob_start() ; 
				// A liste of plugin slug to be excluded
				$exlude = array('wp-pirate-search') ; 
				// Replace sedLex by your own author name
				$trans = new SLFramework_OtherPlugins("sedLex", $exlude) ; 
				$trans->list_plugins() ; 
			$tabs->add_tab(__('Other plugins',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_plug.png") ; 	
			
			echo $tabs->flush() ; 
			
			
			// Before this comment, you may modify whatever you want
			//===============================================================================================
			?>
			<?php echo $this->signature ; ?>
		</div>
		<?php
	}
	
	
	
	/** ====================================================================================================================================================
	* Test if IP is private
	*
	* @return string adress
	*/

	function ip_is_private($ip) {
		if ($ip=="unknown") {
			return true ; 
		}
			
		if (function_exists('filter_var')){
			return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
		}
		return false ; 
	}
	/** ====================================================================================================================================================
	* Get the Remote address (support of proxy)
	*
	* @return string adress
	*/

	function getRemoteAddress($hideifrequired=true) {
		$hostname = $_SERVER['REMOTE_ADDR'];
		
		// GET the header of the HTTP request
		
		$headers = array();
		$rx_http = '/\AHTTP_/';
		foreach($_SERVER as $key => $val) {
			if( preg_match($rx_http, $key) ) {
				$headers_key = preg_replace($rx_http, '', $key);
				$rx_matches = array();
				// do some nasty string manipulations to restore the original letter case
				// this should work in most cases
				$rx_matches = explode('_', $headers_key);
				if (count($rx_matches) > 0 and strlen($headers_key) > 2 ) {
					foreach($rx_matches as $ak_key => $ak_val) {
						$rx_matches[$ak_key] = ucfirst($ak_val);
					}
					$headers_key = implode('-', $rx_matches);
				}	
				$headers[$headers_key] = $val;
			}
		}
		
		// Get the list of proxy
		
		$list_proxy = array() ; 
		$list_proxy[] = $hostname ; 
		
		foreach($headers as $k => $v) {
			if(strcasecmp($k, "x-forwarded-for"))
				continue;
			$list_proxy_temp = explode(",", $v);
			foreach ($list_proxy_temp as $h) {
				if ($this->ip_is_private($h)) {
					continue ; 
				}
				$list_proxy[] = $h ;
			}
			break;
		}
		
		// We remove the two last bytes if need to be compliant with CNIL
		if (($this->get_param('local_cnil_compatible'))&&($hideifrequired)) {
			for ($i=0 ; $i<count($list_proxy) ; $i++) {
				$ip = $list_proxy[$i] ;
				$ipv4 = explode(".", $list_proxy[$i]) ;
				if (count($ipv4)==4) {
					$list_proxy[$i] = $ipv4[0].".".$ipv4[1].".xxx.xxx" ; 
				}
				$ipv6 = explode(":", $list_proxy[$i]) ;
				if (count($ipv6)==8) {
					$list_proxy[$i] = $ipv6[0].":".$ipv6[1].":".$ipv6[2].":".$ipv6[3].":".$ipv6[4].":".$ipv6[5].":".$ipv6[6].":xxxx" ; 
				}
			}
		}	
		
		// We remove duplicate and reverse it )
		$list_proxy = implode(",", array_unique($list_proxy)) ; 

		return $list_proxy ;
	}
	
	/** ====================================================================================================================================================
	* Get the number of current user ... (i.e. those that has connected less then 20sec)
	*
	* @return void
	*/
	
	function update_current_user() {
		global $wpdb ; 
		$res = $wpdb->get_results("SELECT page, singleCookie FROM ".$this->table_name." WHERE type='single' AND TIMESTAMP(time) > TIMESTAMP(DATE_SUB('".date_i18n("Y-m-d H:i:s")."',INTERVAL 30 SECOND)) ORDER BY time DESC") ; 
		
		$resultFinal = array() ;	
		$pageFinal = array() ; 
		if ($res) {
			foreach ($res as $r) {
				if (!isset($resultFinal[$r->singleCookie])) {
					$resultFinal[$r->singleCookie] = $r->page ; 
					if (isset($pageFinal[$r->page])) {
						$pageFinal[$r->page] ++ ; 
					} else {
						$pageFinal[$r->page] = 1 ; 
					}
				}
			}
		}
		
		
		$nb=count($resultFinal) ; 
		echo $nb ; 
		// On affiche les 5 pages les plus visités
		if ($nb!=0) {
			echo ":" ; 
			echo "<ul style='list-style-type: disc;padding-left:40px;'>\r\n" ; 
			$pageFinal2 = array() ; 
			foreach ($pageFinal as $p => $n) {
				$pageFinal2[] = "$n####$p" ; 
			}
			$maxnb = 5 ; 
			arsort($pageFinal2) ; 
			foreach ($pageFinal2 as $mm) {
				list($n, $p) = explode("####", $mm) ; 
				$maxnb -- ; 
				$idpage =  url_to_postid( $p ) ; 
				// Si on a trouve
				if ($idpage!=0) {
					$page = "<a href='".get_permalink($idpage)."'>".get_the_title($idpage)."</a>" ; 
				} else {
					$page = "<a href='".$p."'>".$p."</a>" ; 
				}
				echo "<li>(".$n.") ".$page."</li>\r\n" ; 
				if ($maxnb==0) {
					break ; 
				}
			}
			echo "</ul>\r\n" ; 
		} else {
			echo "." ; 
		}
		die() ; 
	}
	
	
	/** ====================================================================================================================================================
	* Callback updating the SQL table with browser info
	*
	* @return void
	*/
	
	function UserWebStat() {
		global $wpdb ; 
		$wpdb->show_errors(); 
		
		// Retrieve Information and Parameters
		$browserUserAgent = esc_sql($_POST['browserUserAgent']) ; 
		$cookieEnabled = esc_sql($_POST['cookieEnabled']) ; 
		$referer = esc_sql($_POST['referer']) ; 
		$page = esc_sql($_POST['page']) ; 
		$singleCookie = esc_sql($_POST['singleCookie']) ; 
		$refreshNumber = esc_sql($_POST['refreshNumber']) ; 
		
		// DETECTION DE L'OS
		$brow = new SLFramework_BrowsersOsDetection($browserUserAgent) ; 
		$browserName = 	$brow->getBrowserName() ; 
		$browserVersion = 	$brow->getBrowserVersion() ; 
		$platformName = 	$brow->getPlatformName() ; 
		$platformVersion = 	$brow->getPlatformVersion() ; 
		
		// La longueur du SHA1 est de 40 caracteres
		if (($singleCookie=="")||(!preg_match("/^[a-z0-9]{40}$/u",$singleCookie))) {
			$singleCookie = sha1(microtime()) ; 
		} else {
			// On regarde s'il existe deja une page avec les memes informations il y a moins d'une minute, 
			// si c'est le cas, on met a jour l'entree 
			$sql_select = "SELECT id, refreshNumber FROM ".$this->table_name." WHERE " ; 
			$sql_select .= "type='single' AND " ;   
			$sql_select .= "page='".$page."' AND " ;   
			$sql_select .= "singleCookie='".$singleCookie."' AND " ; 
			$sql_select .= "time > DATE_SUB('".date_i18n('Y-m-d H:i:s')."',INTERVAL 1 MINUTE) LIMIT 1" ; 
			$result = $wpdb->get_row($sql_select) ; 
			if ($result) {
				// Si depasse le max time  on arrete le rafraichissement car c'est probablement une page 'ouverte sans personne devant'.
				if (floor($result->refreshNumber*10) > $this->maxTime) {
					echo "0" ; 
					die() ; 
				}
				// On met a jour les informations de la base
				$sql_update = "UPDATE ".$this->table_name." SET " ; 
				$sql_update .= "time='".date_i18n('Y-m-d H:i:s')."', " ; 
				$sql_update .= "refreshNumber='".$refreshNumber."' WHERE " ; 
				$sql_update .= "id='".$result->id."' " ; 
				$wpdb->query($sql_update) ; 
			} else {
				// We check if this user has already seen a page today
				$sql_select = "SELECT id FROM ".$this->table_name." WHERE " ; 
				$sql_select .= "type='single' AND " ;   
				$sql_select .= "singleCookie='".$singleCookie."' AND " ; 
				$sql_select .= "time BETWEEN '".date_i18n('Y-m-d 0:0:0')."' AND '".date_i18n('Y-m-d H:i:s')."' " ; 
				$result = $wpdb->get_row($sql_select) ; 
				if ($result) {
					$unique = "0" ; 
				} else {
					$unique = "1" ; 
				}
				
				// Insert new entry for this visitor
				$refreshNumber = 0 ; 
				$sql_insert = "INSERT INTO ".$this->table_name." SET " ; 
				$sql_insert .= "type='single', " ; 
				$sql_insert .= "count=1, " ; 
				$sql_insert .= "uniq_visit=".$unique.", " ; 
				$sql_insert .= "viewed=FALSE, " ; 
				$sql_insert .= "ip='".$this->getRemoteAddress()."', " ; 
				$sql_insert .= "browserName='".$browserName."', " ; 
				$sql_insert .= "browserVersion='".$browserVersion."', " ;  
				$sql_insert .= "platformName='".$platformName."', " ;  
				$sql_insert .= "platformVersion='".$platformVersion."', " ;  
				$sql_insert .= "browserUserAgent='".$browserUserAgent."', " ;  
				$sql_insert .= "referer='".$referer."', " ;  
				$sql_insert .= "page='".$page."', " ;   
				$sql_insert .= "time='".date_i18n('Y-m-d H:i:s')."', " ; 
				$sql_insert .= "singleCookie='".$singleCookie."', " ; 
				$sql_insert .= "refreshNumber='".$refreshNumber."'" ; 
				$sql_insert .= $this->geolocate() ; 
				$wpdb->query($sql_insert) ; 
			}
		}
		
		// On concatene si besoin pour economiser de la place dans la bdd
		if ($this->get_param('local_cron_concat')=="") {
			$this->set_param('local_cron_concat', strtotime(date_i18n("Y-m-d 0:0:1")." +1 day")) ; 
		}
		
		if (strtotime(date_i18n("Y-m-d H:i:s"))>$this->get_param('local_cron_concat')) {
			// On me en place le prochain cron
			$this->set_param('local_cron_concat', strtotime(date_i18n("Ymd 0:0:1")." +1 day")) ; 
			
			$offset = $this->get_param('local_keep_detailed_info') ; 
			$max = -1 ; 
			// On trouve le plus vieux single existant
			$sql_old = "SELECT time FROM ".$this->table_name." WHERE " ; 
			$sql_old .= "type='single' " ; 
			$sql_old .= "ORDER BY time ASC LIMIT 1" ; 
			$oldest = $wpdb->get_row($sql_old) ; 
			if ($oldest) {
				$max = floor((strtotime(date_i18n("Y-m-d 0:0:0")." -".($offset)." day")-strtotime($oldest->time))/86400) ; 
			}
			
			for ($i=0 ; $i<=$max ; $i++) {
				$offset ++ ; 
				$date1 = date_i18n("Y-m-d  0:0:0", strtotime(date_i18n("Y-m-d")." -".($offset)." day"));
				$date2 = date_i18n("Y-m-d  23:59:59", strtotime(date_i18n("Y-m-d")." -".($offset)." day"));

				// On concatene les donnees single en donnees day_browser et day_os
				$sql_browser = "SELECT SUM(count) as nb, SUM(uniq_visit) as nb2, browserName FROM ".$this->table_name." WHERE " ; 
				$sql_browser .= "time between '".$date1."' and '".$date2."' AND " ; 
				$sql_browser .= "type='single' " ; 
				$sql_browser .= "GROUP BY browserName" ; 
				$res = $wpdb->get_results($sql_browser) ; 
				foreach ($res as $r) {
					$sql_insert = "INSERT INTO ".$this->table_name." SET " ; 
					$sql_insert .= "type='day_browser', " ; 
					$sql_insert .= "count=".$r->nb.", " ; 
					$sql_insert .= "uniq_visit=".$r->nb2.", " ; 
					$sql_insert .= "viewed=TRUE, " ; 
					$sql_insert .= "ip='', " ; 
					$sql_insert .= "browserName='".$r->browserName."', " ; 
					$sql_insert .= "browserVersion='', " ;  
					$sql_insert .= "platformName='', " ;  
					$sql_insert .= "platformVersion='', " ;  
					$sql_insert .= "browserUserAgent='', " ;  
					$sql_insert .= "referer='', " ;  
					$sql_insert .= "page='', " ;   
					$sql_insert .= "time='".$date1."', " ; 
					$sql_insert .= "singleCookie='', " ; 
					$sql_insert .= "refreshNumber=0" ; 
					$wpdb->query($sql_insert) ; 
				}
				
				$sql_os = "SELECT SUM(count) as nb, SUM(uniq_visit) as nb2, platformName FROM ".$this->table_name." WHERE " ; 
				$sql_os .= "time between '".$date1."' and '".$date2."' AND " ; 
				$sql_os .= "type='single' " ; 
				$sql_os .= "GROUP BY platformName" ; 
				$res = $wpdb->get_results($sql_os) ; 
				foreach ($res as $r) {
					$sql_insert = "INSERT INTO ".$this->table_name." SET " ; 
					$sql_insert .= "type='day_platform', " ; 
					$sql_insert .= "count=".$r->nb.", " ; 
					$sql_insert .= "uniq_visit=".$r->nb2.", " ; 
					$sql_insert .= "viewed=TRUE, " ; 
					$sql_insert .= "ip='', " ; 
					$sql_insert .= "browserName='', " ; 
					$sql_insert .= "browserVersion='', " ;  
					$sql_insert .= "platformName='".$r->platformName."', " ;  
					$sql_insert .= "platformVersion='', " ;  
					$sql_insert .= "browserUserAgent='', " ;  
					$sql_insert .= "referer='', " ;  
					$sql_insert .= "page='', " ;   
					$sql_insert .= "time='".$date1."', " ; 
					$sql_insert .= "singleCookie='', " ; 
					$sql_insert .= "refreshNumber=0" ; 
					$wpdb->query($sql_insert) ; 
				}
				
				$sql_delete = "DELETE FROM ".$this->table_name." WHERE " ; 
				$sql_delete .= "time between '".$date1."' and '".$date2."' AND " ; 
				$sql_delete .= "type='single' " ; 
				$wpdb->query($sql_delete) ; 
			}
		}
		
		// On ne force le rafraichissement que s'il y a des cookies (car sinon doublons)
		if ($cookieEnabled==true)
			echo $singleCookie.",".($refreshNumber+1) ; 

		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Convert the on-time token into a session token
	*
	* @return string the token (or false in case of error)
	*/
	
	function geolocate($ip=null, $serialize_for_database=true) {
		$geolocate_data = "" ; 
		if ($this->get_param('geolocate_ipinfodb')) {
			// Si l'IP n'est pas forcé, cela veut dire que l'on regarde l'IP du client qui fait la requête.
			if (is_null($ip)){
				$ip = explode(",", $this->getRemoteAddress(false)) ; 
				$ip=$ip[0] ;
				$ip = "&ip=".$ip;

			} else {
				if ($ip!="") {
					$ip = "&ip=".$ip ; 
				}
			}
			if ($this->get_param('geolocate_ipinfodb_key')!="") {
				$result_geo_xml = @file_get_contents("http://api.ipinfodb.com/v3/ip-city/?key=".trim($this->get_param('geolocate_ipinfodb_key'))."&format=xml".$ip) ; 
				
				if ($result_geo_xml!==false){
					
					if (!preg_match("/<\?xml/ui", $result_geo_xml)){
						$result_geo_xml = "<".""."?".""."xml version='1.0'?>".$result_geo_xml ; 
					}
					
					$result_geo_xml = @simplexml_load_string($result_geo_xml); 
					if ($result_geo_xml!==false){
						
						$geolocate_data['countryCode'] = (string)$result_geo_xml->countryCode ; 
						$geolocate_data['countryName'] = (string)$result_geo_xml->countryName ; 
						$geolocate_data['regionName'] = (string)$result_geo_xml->regionName ; 
						$geolocate_data['cityName'] = (string)$result_geo_xml->cityName ; 
						$geolocate_data['zipCode'] = (string)$result_geo_xml->zipCode ; 
						$geolocate_data['latitude'] = (string)$result_geo_xml->latitude ; 
						$geolocate_data['longitude'] = (string)$result_geo_xml->longitude ; 
						
						// On le prepare pour la BDD
						if ($serialize_for_database){
							$geolocate_state['countryCode'] = $geolocate_data['countryCode'];
							$geolocate_state['countryName'] = $geolocate_data['countryName'];
							
							$geolocate_data = ", geolocate='".esc_sql(@serialize($geolocate_data))."',geolocate_state='".esc_sql(@serialize($geolocate_state))."' " ; 
						}
					} else {
						if (!$serialize_for_database){
							$error = error_get_last();
							return $error['message'] ; 
						}
					}
				} else {
					if (!$serialize_for_database){
						$error = error_get_last();
						return $error['message'] ; 
					}
				}
			}
		}
		return $geolocate_data ; 
	}
	
	
	/** ====================================================================================================================================================
	* Convert the on-time token into a session token
	*
	* @return string the token (or false in case of error)
	*/

	function get_session_token($ott) {
		$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
		$args['headers']['Authorization'] = 'AuthSub token="' . $ott . '"';
		$response = wp_remote_post("https://www.google.com/accounts/AuthSubSessionToken", $args);
		
		// Check for WordPress error
		if ( is_wp_error($response) ) {
			return false;
		}
		// Get the response code
		if ($response['response']['code']!="200") {
			return false ; 
		}
	
		// Get the authentication token and  update it
		$token = substr(strstr($response['body'], "Token="), 6);	
		return $token ; 
	}
	
	/** ====================================================================================================================================================
	* Revoke the session token 
	*
	* @return void
	*/

	function revoke_session_token($ost) {
		$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
		$args['headers']['Authorization'] = 'AuthSub token="' . $ost . '"';
		$response = wp_remote_get("https://www.google.com/accounts/AuthSubRevokeToken", $args);
	}
	
	/** ====================================================================================================================================================
	* Get the correct Google error message
	*
	* @return void
	*/

	function get_google_error($resp) {
		$orig_resp = $resp ; 
		$resp = str_replace("<","&lt;",$resp) ; 
		$resp = str_replace(">","&gt;",$resp) ; 
		$resp = str_replace("\n","<br/>",$resp) ; 
		$resp = str_replace(" ","&nbsp;",$resp) ; 
		
		if (preg_match("/userRateLimitExceededUnreg/", $orig_resp)) {
			return array('error' => __('Google states that the User Rate Limit has been exceeded!',  $this->pluginID)."<br/>".__('In order to avoid User Rate Limit, you should get an API key.',  $this->pluginID)."<br/>".sprintf(__("To get this API key, please visit %s, create a projet, allow Google Analytics, and then go to API console to get a %s", $this->pluginID), "<a href='https://code.google.com/apis/console'>https://code.google.com/apis/console</a>", "'<i>Key for browser apps</i>'")."<br/><br/><img src='".plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."img/api_access_key.jpg'><br/><br/>".__('Once a key generated, please configure it in the configuration tab.',  $this->pluginID)."<br/><br/><b>".__('If you have just saved the API key in the parameters, please reload the page and it shoud work.',  $this->pluginID)."</b>");
		}

		return array('error' => __('The authorization seems to have expired or has been revoked. Please revoke the authorization (see below) and re-authentify!',  $this->pluginID)."<br/>".$resp);
	}
	
		
	
	/** ====================================================================================================================================================
	* Checks if the WordPress API is a valid method for selecting an account
	*
	* @return array list of accounts if available, if there is an error the array contain an 'error' field
	*/

	function get_analytics_accounts(){
		$accounts = array();
		
		if (!$this->get_param('googlewebstat')) 
			return array();
		
		$api_key = "" ; 
		if ($this->get_param('google_api_key')!="") {
			$api_key = "?key=".$this->get_param('google_api_key') ; 
		}
		
		if ( $this->get_param('googlewebstat_auth_token') != "" ) {
			$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
			$args['headers']['Authorization'] = 'AuthSub token="' . $this->get_param('googlewebstat_auth_token')  . '"';
			$response = wp_remote_get("https://www.googleapis.com/analytics/v2.4/management/accounts/~all/webproperties/~all/profiles".$api_key, $args);
			// Check for WordPress error
			if ( is_wp_error($response) ) {
				return array('error' => __('An unknown error occured during the API request',  $this->pluginID));
			}
			// Get the response code
			if ($response['response']['code']!="200") {
				return $this->get_google_error($response['body']) ; 
			}
			
			$doc = new DOMDocument();
			$doc->loadXML($response['body']);
			
			$entries = $doc->getElementsByTagName('entry');
			$i = 0;
			$profiles= array();
			foreach($entries as $entry) {
			    $profiles[$i] = array();
			    $properties = $entry->getElementsByTagName('property');
			    foreach($properties as $property) {
				if (strcmp($property->getAttribute('name'), 'ga:accountId') == 0)
					$profiles[$i]["accountId"] = $property->getAttribute('value');
		 
				if (strcmp($property->getAttribute('name'), 'ga:profileName') == 0)
					$profiles[$i]["title"] = $property->getAttribute('value');
				
				if (strcmp($property->getAttribute('name'), 'ga:accountName') == 0)
					$profiles[$i]["accountName"] = $property->getAttribute('value');
		 
				if (strcmp($property->getAttribute('name'), 'dxp:tableId') == 0)
					$profiles[$i]["tableId"] = $property->getAttribute('value');
					
				if (strcmp($property->getAttribute('name'), 'ga:profileId') == 0)
					$profiles[$i]["profileId"] = $property->getAttribute('value');
					
				if (strcmp($property->getAttribute('name'), 'ga:webPropertyId') == 0)
					$profiles[$i]["webPropertyId"] = $property->getAttribute('value');
			    }
				 
			    $i++;
			}
			
			return $profiles;
		} else {
			return array('error' => __('No token provided!',  $this->pluginID));
		}
	}
	
	/** ====================================================================================================================================================
	* Get the analytics data
	* 
	* @param string $date should be similar to "start-date=2011-12-21&end-date=2011-12-22"
	* @return 
	*/

	function get_analytics_data($date, $pas){
		$accounts = array();
		
		$api_key = "" ; 
		if ($this->get_param('google_api_key')!="") {
			$api_key = "&key=".$this->get_param('google_api_key') ; 
		}
		
		if ( $this->get_param('googlewebstat_auth_token') != "" ) {
			if ($this->get_param('googlewebstat_acc_id') != "") {
				$result = array() ; 
				$errorGoogle = "" ; 
				if ($this->get_param('google_show_visits')) {
				
					// GET THE VISIT COUNT
					//==========================
					$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
					$args['headers']['Authorization'] = 'AuthSub token="' . $this->get_param('googlewebstat_auth_token')  . '"';
					$dimensions = $pas ; 
					$response = wp_remote_get("https://www.googleapis.com/analytics/v2.4/data?ids=".$this->get_param('googlewebstat_acc_id')."&".$date."&metrics=ga:visits,ga:pageviews&dimensions=".$dimensions.$api_key, $args);

					$result['visits'] = $this->parseGoogleResponse($response) ; 
					
					if (isset($result['visits']['error'])) {
						$errorGoogle .=  "<p>".$result['visits']['error']."</p>" ; 
						$result['visits'] = array() ; 
					}
				}

				if ($this->get_param('google_show_type')) {
					// GET THE TYPE OF THE BROWSER
					//========================================
					$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
					$args['headers']['Authorization'] = 'AuthSub token="' . $this->get_param('googlewebstat_auth_token')  . '"';
					$dimensions = "ga:browser" ; 
					$response = wp_remote_get("https://www.googleapis.com/analytics/v2.4/data?ids=".$this->get_param('googlewebstat_acc_id')."&".$date."&metrics=ga:visits&dimensions=".$dimensions.$api_key, $args);
					
					$result['browser'] = $this->parseGoogleResponse($response) ; 
					if (isset($result['browser']['error'])) {
						if (strpos($errorGoogle ,$result['browser']['error'] )===FALSE) {
							$errorGoogle .= "<p>".$result['browser']['error']."</p>" ; 
						}
						$result['browser'] = array() ; 
					}
					
					// We remove the extra browsers if there is more than 10 browsers
					if (count($result['browser'])>10) {
						$toBeShown = array() ; 
						foreach ($result['browser'] as $k => $d) {
							$toBeShown[$k] = $d["ga:visits"] ; 
						}
						arsort($toBeShown) ; 
						$nb_visits_other = 0 ; 
						$i = 0 ; 
						foreach ($toBeShown as $val_visit) {
							if ($i>10) {
								$nb_visits_other += $val_visit ; 
							}
							$i++ ; 
						}
						$toBeShown = array_slice($toBeShown, 0, 10) ; 
												
						foreach ($result['browser'] as $k => $d) {
							if (!isset($toBeShown[$k])) {
								unset($result['browser'][$k]) ; 
							}
						}
						if ($nb_visits_other>0) {
							if (isset($result['browser']['Other'])) {
								$result['browser']['Other']['ga:visits'] += $nb_visits_other ; 
							} else {
								$result['browser']['Other']['ga:visits'] = $nb_visits_other ; 
							}
						}
					}
					
					// GET THE TYPE OF THE OS
					//========================================
					$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
					$args['headers']['Authorization'] = 'AuthSub token="' . $this->get_param('googlewebstat_auth_token')  . '"';
					$dimensions = "ga:operatingSystem" ; 
					$response = wp_remote_get("https://www.googleapis.com/analytics/v2.4/data?ids=".$this->get_param('googlewebstat_acc_id')."&".$date."&metrics=ga:visits&dimensions=".$dimensions.$api_key, $args);
					
					$result['os'] = $this->parseGoogleResponse($response) ; 
					if (isset($result['os']['error'])) {
						if (strpos($errorGoogle , $result['os']['error'])===FALSE) {
							$errorGoogle .= "<p>".$result['os']['error']."</p>" ; 
						}
						$result['os'] = array() ; 
					}
					
					// We remove the extra system if there is more than 10 system
					if (count($result['os'])>10) {
						$toBeShown = array() ; 
						foreach ($result['os'] as $k => $d) {
							$toBeShown[$k] = $d["ga:visits"] ; 
						}
						arsort($toBeShown) ; 
						$nb_visits_other = 0 ; 
						$i = 0 ; 
						foreach ($toBeShown as $val_visit) {
							if ($i>10) {
								$nb_visits_other += $val_visit ; 
							}
							$i++ ; 
						}
						$toBeShown = array_slice($toBeShown, 0, 10) ; 
												
						foreach ($result['os'] as $k => $d) {
							if (!isset($toBeShown[$k])) {
								unset($result['os'][$k]) ; 
							}
						}
						if ($nb_visits_other>0) {
							if (isset($result['os']['Other'])) {
								$result['os']['Other']['ga:visits'] += $nb_visits_other ; 
							} else {
								$result['os']['Other']['ga:visits'] = $nb_visits_other ; 
							}
						}
					}
				}
				
				if ($errorGoogle!="") {
					echo "<div class='error fade'>$errorGoogle</div>" ; 
				}
				
				return $result ; 
			} else {
				echo "<div class='error fade'><p>".__('No website selected for Google statistics (see the parameter tab)!',  $this->pluginID)."</p></div>" ; 
				return array('error' => __('No website selected!',  $this->pluginID));
			}
		} else {
			return array('error' => __('No token provided!',  $this->pluginID));
		}
	
	
	}
	
	/** ====================================================================================================================================================
	* Parse the Google Analytics response
	* 
	* @return 
	*/

	function parseGoogleResponse($response) {
		// Check for WordPress error
		if ( is_wp_error($response) ) {
			return array('error' => __('An unknown error occured during the API request',  $this->pluginID));
		}
		// Get the response code
		if ($response['response']['code']!="200") {
			return $this->get_google_error($response['body']) ; 
		}
		
		$aResult = array();

		$oDoc = new DOMDocument();
		$oDoc->loadXML($response['body']);
		$oEntries = $oDoc->getElementsByTagName('entry');
		foreach($oEntries as $oEntry){

			$cell_array = &$aResult ; 
			foreach($oEntry->getElementsByTagName('dimension') as $dimension) {
			    $cell_array = &$cell_array[$dimension->getAttribute('value')] ; 
			}
			
			$oMetrics = $oEntry->getElementsByTagName('metric');
			foreach($oMetrics as $oMetric){
				$cell_array[$oMetric->getAttribute('name')] = $oMetric->getAttribute('value') ; 
			}
		}
		return $aResult ; 
	}
	
	/** ====================================================================================================================================================
	* Get the local data
	* 
	* @param string $date1 should be similar to "20111221"
	* @param string $date2 should be similar to "20111230"
	* @return 
	*/

	function get_local_data($date1, $date2, $pas){
		global $wpdb ; 
		$result = array() ; 
		
		if ($this->get_param('local_show_visits')) {
			// GET THE VISIT COUNT
			//==========================
			if ($pas == 'day') {
				$sql_day = "SELECT DATE(time) AS day, SUM(count) as pageviews, SUM(uniq_visit) as visits FROM ".$this->table_name." WHERE " ; 
				$sql_day .= "(type='single' OR type='day_browser') AND " ; 
				$sql_day .= "time between '".$date1."' and '".$date2."' GROUP BY YEAR(time), MONTH(time), DAY(time)" ; 
				$res = $wpdb->get_results($sql_day) ; 
				$result['visits'] = array() ; 
				// On complete avec des fausses donnees si les jours ne sont pas presents
				for ($i=0 ; $i < floor((strtotime($date2)-strtotime($date1))/86400) ; $i++) {
					$result['visits'][" ".date_i18n("Ymd", strtotime($date1." +".$i." day"))." "] = array("pageviews" => 0, "visits" => 0) ; 
				}
				
				foreach ($res as $r) {
					$result['visits'][" ".date_i18n("Ymd", strtotime($r->day))." "] = array("pageviews" => $r->pageviews, "visits" => $r->visits) ; 
				}
			}
			if ($pas == 'week') {
				$sql_week = "SELECT YEARWEEK(time, 3) AS week, " ; 
				$sql_week .= 		"SUM(count) as pageviews, " ; 
				$sql_week .= 		"SUM(uniq_visit) as visits " ; 
				$sql_week .= 		"FROM ".$this->table_name." WHERE " ; 
				$sql_week .= "(type='single' OR type='day_browser') AND " ; 
				$sql_week .= "time between '".$date1."' and '".$date2."' GROUP BY YEARWEEK(time, 3)" ; 
				$res = $wpdb->get_results($sql_week) ;
				
				$result['visits'] = array() ; 
				
				// On complete avec des fausses donnees si les semaines ne sont pas presents
				$startTime = strtotime($date1);
				$endTime = strtotime($date2);
				$lastWeek = "" ; 
				while ($startTime < $endTime) {  
    				$result['visits'][" ".date_i18n('Y', $startTime)." "][" ".date_i18n('W', $startTime)." "] = array("pageviews" => 0, "visits" => 0) ; 
    				$lastWeek = date_i18n('YW', $startTime) ; 
    				$startTime += strtotime('+1 week', 0);
				}
				// Pour eviter de louper la derniere
				if (date_i18n('YW', $endTime)!=$lastWeek) {
    				$result['visits'][" ".date_i18n('Y', $endTime)." "][" ".date_i18n('W', $endTime)." "] = array("pageviews" => 0, "visits" => 0) ; 				
				}
				
				foreach ($res as $r) {
					$year = substr($r->week, 0,4) ; 
					$week = substr($r->week, 4) ; 
					$result['visits'][" ".$year." "][" ".$week." "] = array("pageviews" => $r->pageviews, "visits" => $r->visits) ; 
				}
			}
			
			if ($pas == 'month') {
				$sql_month = "SELECT CONCAT(YEAR(time), MONTH(time)) AS month, " ; 
				$sql_month .= 		"SUM(count) as pageviews, " ; 
				$sql_month .= 		"SUM(uniq_visit) as visits " ; 
				$sql_month .= 		"FROM ".$this->table_name." WHERE " ; 
				$sql_month .= "(type='single' OR type='day_browser') AND " ; 
				$sql_month .= "time between '".$date1."' and '".$date2."' GROUP BY YEAR(time), MONTH(time)" ; 
				$res = $wpdb->get_results($sql_month) ;
				
				$result['visits'] = array() ; 
				
				// On complete avec des fausses donnees si les mois ne sont pas presents
				$startTime = strtotime($date1);
				$endTime = strtotime($date2);
				$lastWeek = "" ; 
				while ($startTime < $endTime) {  
    				$result['visits'][" ".date_i18n('Y', $startTime)." "][" ".date_i18n('m', $startTime)." "] = array("pageviews" => 0, "visits" => 0) ; 
    				$lastMonth = date_i18n('Ym', $startTime) ; 
    				$startTime += strtotime('+1 month', 0);
				}
				// Pour eviter de louper la derniere
				if (date_i18n('Ym', $endTime)!=$lastMonth) {
    				$result['visits'][" ".date_i18n('Y', $endTime)." "][" ".date_i18n('m', $endTime)." "] = array("pageviews" => 0, "visits" => 0) ; 				
				}
				
				foreach ($res as $r) {
					$year = substr($r->month, 0,4) ; 
					$month = substr($r->month, 4) ; 
					$month = str_pad($month, 2, '0', STR_PAD_LEFT) ; 
					$result['visits'][" ".$year." "][" ".$month." "] = array("pageviews" => $r->pageviews, "visits" => $r->visits) ; 
				}
			}			
		}

		if ($this->get_param('local_show_type')) {
			// GET THE TYPE OF THE BROWSER
			//========================================
			$sql_browser = "SELECT SUM(count) as nb, browserName FROM ".$this->table_name." WHERE " ; 
			$sql_browser .= "time between '".$date1."' and '".$date2."' AND " ; 
			$sql_browser .= "(type='single' OR type='day_browser') AND " ; 
			$sql_browser .= "uniq_visit > 0 " ; 
			$sql_browser .= "GROUP BY browserName" ; 
			$res = $wpdb->get_results($sql_browser) ; 
			$result['browser'] = array() ; 
			foreach ($res as $r) {
				$result['browser'][" ".$r->browserName." "] = array("visits" => $r->nb) ; 
			}
			
			// GET THE TYPE OF THE OS
			//========================================
			
			$sql_os = "SELECT SUM(count) as nb, platformName FROM ".$this->table_name." WHERE " ; 
			$sql_os .= "time between '".$date1."' and '".$date2."' AND " ; 
			$sql_os .= "(type='single' OR type='day_platform') AND " ; 
			$sql_os .= "uniq_visit > 0 " ; 
			$sql_os .= "GROUP BY platformName" ; 
			$res = $wpdb->get_results($sql_os) ; 
			$result['os'] = array() ; 
			foreach ($res as $r) {
				$result['os'][" ".$r->platformName." "] = array("visits" => $r->nb) ; 
			}
		}

		return $result ; 
	}
	
	/** ====================================================================================================================================================
	* Callback to Compute a sitemaps upon save of posts
	* 
	* @return 
	*/

	function create_sitemap_upon_save () {
		$this->generateSitemaps("sitemap", true) ; 
	}		
	
	/** ====================================================================================================================================================
	* Create the cookies buttons for the info page (allow and disallow)
	*
	* @return string the text to replace the shortcode
	*/

    function cookies_buttons_shortcode( $_atts, $string ) {
    	global $wpdb ; 
    	
		$result = "<input type='button' class='traffic_cookies_allow' value='".__('Allow')."' onclick='acceptLocalCookies()'/> <input type='button' class='traffic_cookies_refuse' value='".__('Refuse')."' onclick='refusLocalCookies()'/>" ; 

		return $result ; 
	}	
	
	/** ====================================================================================================================================================
	* Create the cookies buttons for the info page (allow and disallow)
	*
	* @return string the text to replace the shortcode
	*/

    function google_cookies_buttons_shortcode( $_atts, $string ) {
    	global $wpdb ; 
    	
		$result = "<input type='button' class='google_traffic_cookies_allow' value='".__('Allow')."' onclick='acceptGoogleCookies()'/> <input type='button' class='google_traffic_cookies_refuse' value='".__('Refuse')."' onclick='refusGoogleCookies()'/>" ; 

		return $result ; 
	}
	
	/** ====================================================================================================================================================
	* Compute a sitemaps
	* 
	* @param string $filename the filename of the sitemap (probably sitemap.xml)
	* @return 
	*/

	function generateSitemaps($filename, $force=false) {
		global $blog_id;
		
		if (is_multisite()) {
			$filename = $filename.$blog_id;
		} 
	
		if (is_file(ABSPATH . $filename.".xml") && !$force) {
			return array('info'=>'sitemaps already exists') ; 
		}
	
		$nb = $this->get_param('sitemaps_nb') ; 
		if ($nb==0)
			$nb=-1 ; 
		
	 	$postsForSitemap = get_posts(array(
			'numberposts' => $nb,
			'orderby' => 'modified',
			'post_type'  => array('post','page'),
			'order'    => 'DESC'
  		));

		$sitemap = '<'.'?xml version="1.0" encoding="UTF-8"?>'."\n";
		$sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
		
		$sitemap .= "\t".'<url>'."\n" ; 
  		if (!is_multisite()) {
			$sitemap .= "\t\t".'<loc>'. get_home_url() .'</loc>'."\n" ; 
		} else {
			$sitemap .= "\t\t".'<loc>'. get_home_url($blog_id) .'</loc>'."\n" ; 
		}
  		$sitemap .= "\t\t".'<lastmod>'. date_i18n("Y-m-d") .'</lastmod>'."\n" ; 
  		$sitemap .= "\t\t".'<changefreq>daily</changefreq>'."\n" ; 
  		$sitemap .= "\t\t".'<priority>1.0</priority>'."\n" ; 
		$sitemap .= "\t".'</url>'."\n";
		
		foreach($postsForSitemap as $post) {
			$postdate = explode(" ", $post->post_modified);
			$sitemap .= "\t".'<url>'."\n" ; 
  			$sitemap .= "\t\t".'<loc>'. get_permalink($post->ID) .'</loc>'."\n" ; 
  			$sitemap .= "\t\t".'<lastmod>'. $postdate[0] .'</lastmod>'."\n" ; 
  			$sitemap .= "\t\t".'<changefreq>monthly</changefreq>'."\n" ; 
  			$sitemap .= "\t\t".'<priority>0.5</priority>'."\n" ; 
			$sitemap .= "\t".'</url>'."\n";
  		}

  		$sitemap .= '</urlset>'."\n";

  		if (@file_put_contents(ABSPATH . $filename.".xml", $sitemap)===FALSE) {
			return array('error'=>sprintf(__('The file %s cannot be created. Please make sure that the file rights allow writing in the following folder: %s', $this->pluginID), "<code>".$filename.".xml"."</code>", "<code>".ABSPATH."</code>")) ; 
			$this->set_param("sitemaps_date", sprintf(__('Problem with folder rights on %s',  $this->pluginID), "<code>".ABSPATH."</code>"));
		} else {
			if (function_exists('gzencode')) {
				$gz = gzencode($sitemap, 9);
				@file_put_contents(ABSPATH . $filename.".xml.gz", $gz) ; 
			}
			$this->set_param("sitemaps_date",  date_i18n("Y-m-d H:i:s"));
			$this->notifyCrawlers(get_site_url()."/".$filename.'.xml') ; 
			return array('info'=>'sitemaps saved') ; 
		}
		
		
	}
	
	/** ====================================================================================================================================================
	* Notify sitemaps to crawler
	* 
	* @param string $filename the filename of the sitemap (probably sitemap.xml)
	* @return 
	*/

	function notifyCrawlers($url) {
		//Ping Google
		if($this->get_param("sitemaps_notify_google")) {
			$sPingUrl="http://www.google.com/webmasters/sitemaps/ping?sitemap=" . urlencode($url);
			$response = wp_remote_get($sPingUrl) ;
			if ( is_wp_error($response) || ($response['response']['code']!="200") )  {
				$this->set_param("sitemaps_notify_google_date", __('An unknown error occured during the API request',  $this->pluginID));
			} else {
				$this->set_param("sitemaps_notify_google_date", date_i18n("Y-m-d H:i:s"));
			}
		}
		//Ping Ask
		if($this->get_param("sitemaps_notify_ask")) {
			$sPingUrl="http://submissions.ask.com/ping?sitemap=" . urlencode($url);
			$response = wp_remote_get($sPingUrl) ;
			if ( is_wp_error($response) || ($response['response']['code']!="200") )  {
				$this->set_param("sitemaps_notify_ask_date", __('An unknown error occured during the API request',  $this->pluginID));
			} else {
				$this->set_param("sitemaps_notify_ask_date", date_i18n("Y-m-d H:i:s"));
			}
		}
		//Ping Bing
		if($this->get_param("sitemaps_notify_bing")) {
			$sPingUrl="http://www.bing.com/webmaster/ping.aspx?siteMap=" . urlencode($url);
			$response = wp_remote_get($sPingUrl) ;
			if ( is_wp_error($response) || ($response['response']['code']!="200") )  {
				$this->set_param("sitemaps_notify_bing_date", __('An unknown error occured during the API request',  $this->pluginID));
			} else {
				$this->set_param("sitemaps_notify_bing_date", date_i18n("Y-m-d H:i:s"));
			}
		}
	}
		
}

$traffic_manager = traffic_manager::getInstance();

?>