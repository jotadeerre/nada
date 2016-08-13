<?php
/*
Plugin Name: Justine Roland-Cal Customiser
Author: Jon Diez
Version: 1.0
*/
function get_started() {
  $photo = get_page_by_path("photography")->ID;
  $paint = get_page_by_path("painting")->ID;
  define('TAX',"media_tag");
  define('PHOTO',$photo);
  define('PAINT',$paint);

  Script::register();
  new Email_Hider();
  new Emojicon_Killer();
  new Share(array("facebook","twitter","pinterest","tumblr"),"/images/poster.jpg");
  new JRC_Customiser("media_tag");
  new JRC_Gallery;
  new JRC_News;
  new Unscroll(".secondary-nav","#body");
 } add_action('plugins_loaded','get_started');
class JRC_Customiser {
  private $tax;
  public function __construct($tax) {
    $this->tax = $tax;
	  # GENERAL
    add_action( 'wp_enqueue_scripts', array($this,'load_scripts'));
  	add_action( 'admin_enqueue_scripts', array($this,'datepicker' ));
  	add_filter( 'wp_title', array($this,'wp_title'), 10, 2 );
  	remove_filter('get_the_excerpt', 'wp_trim_excerpt');
    add_filter('get_the_excerpt', array($this,'custom_excerpt'));
    # STRUCTURE
	  add_theme_support( 'post-thumbnails', array( 'post', 'page' ) );
	  add_action( 'init',array($this,'register_elements'));
	  add_action( 'init',array($this,'image_sizes'));
	  add_action( 'init',array($this,'rewrite_rules'));
	  add_filter( 'attachment_link', array($this,'build_permalink'), 20, 2 );
    $this->customise();
  }

  # GENERAL
  function load_scripts() {
    wp_register_style('googleFonts', 'http://fonts.googleapis.com/css?family=Lilita+One|Muli'); wp_enqueue_style( 'googleFonts');
    wp_register_style('style', get_stylesheet_directory_uri() . '/style.css'); wp_enqueue_style( 'style');
	  #wp_enqueue_script('general', plugin_dir_url( __FILE__ ) . 'launch.js', array('jquery'));
    #wp_localize_script('general', 'urls', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'plugin_url' => plugin_dir_url( __FILE__ ),'theme_url' => get_stylesheet_directory_uri()) );
    wp_register_style('fboxa', plugin_dir_url( __FILE__ ) . 'fancybox/jquery.fancybox.css'); wp_enqueue_style( 'fboxa');
    wp_enqueue_script('fboxb', plugin_dir_url( __FILE__ ) . 'fancybox/jquery.fancybox.pack.js', array('jquery'));
  }
  function wp_title( $title, $sep ) {
  	if ( is_feed() ) { return $title; }
    global $page, $paged, $post;

  	$parts = array();
    if (is_tax()){ $parts[] =  single_term_title("",false); }
    $parts[] = get_bloginfo( 'name', 'display' );
    if (  is_home() || is_front_page()  ) { $parts[] = get_bloginfo( 'description', 'display' ); }
    if ( ( $paged >= 2 || $page >= 2 ) && ! is_404() ) { $parts[] = 'Page ' . max($paged,$page);}
    return implode(" {$sep} ",$parts);
  }
  function custom_excerpt($text){
    global $post;
    if ( '' == $text ) {
      $text = get_the_content('');
      $text = apply_filters('the_content', $text);
      $text = str_replace('\]\]\>', ']]&gt;', $text);
      $text = preg_replace('@<script[^>]*?>.*?</script>@si', '', $text);
      $text = strip_tags($text, '<p>');
      $excerpt_length = 20;
      $words = explode(' ', $text, $excerpt_length + 1);
      if (count($words)> $excerpt_length) {
        array_pop($words);
        array_push($words, '[...]');
        $text = implode(' ', $words);
      }
    }
    return $text;
  }
  # STRUCTURE
  function register_elements(){ register_taxonomy($this->tax,'attachment',array('label'=>'Media Tags','public'=>true,'show_admin_column'=>true,'hierarchical'=>false,)); }
  function image_sizes(){
    update_option('medium_size_w', 400);
    update_option('medium_size_h', 400);
    update_option('large_size_w', 1045);
    update_option('large_size_h', 1045);
    add_image_size( 'intermediate', 700, 700 );
  }
  function rewrite_rules() {
    $terms = get_terms(array($this->tax),array('fields'=>'names','hide_empty'=>false));
    $terms = strtolower(implode("|",$terms));
    add_rewrite_rule('^(photography|painting)/(' . $terms. ')/?$','index.php?pagename=$matches[1]&' . $this->tax . '=$matches[2]','top');
    add_rewrite_rule('^(photography|painting)/([^/]*)/?$','index.php?pagename=$matches[1]&imageid=$matches[2]','top');
    add_rewrite_tag('%' . $this->tax . '%','');
    add_rewrite_tag('%imageid%','');
    //flush_rewrite_rules();
  }
  function build_permalink( $link, $post_id ){
    return $link;
    return $post_id;
  }

  function customise() {
    add_filter( 'post_mime_types', array($this,'modify_post_mime_types' ));
    $display = Field::get("_display","Display","meta",array("type"=>"checkbox")); #make it a check box
    $until = Field::get("_expiry","Until","meta",array("class"=>"datepicker")); #make it date picker class="datepicker"
    $pages = Field::get("post_parent__in","Pages","post",array($this,'get_page_list'));
    $tags = Field::get("post__in","Tags","post",array($this,'get_tag_list'));

    Admin::meta('post',array('Display in home'=>array($display,$until)));
    Admin::filters('attachment',array($pages,$tags));
  }
  # META
  function datepicker() {
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_style( 'jquery-ui-datepicker', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/themes/smoothness/jquery-ui.css' );
    wp_enqueue_script( 'launchdatepicker', plugin_dir_url( __FILE__ ) . '/launchdatepicker.js', array('jquery-ui-datepicker'));
   }
  # Admin media gallery
  public function get_page_list($return = array()){
  	$pages = get_pages(array('post_type'=>'page','posts_per_page'=>-1 ));
  	foreach($pages as $p){
   	  $att = get_children( array( 'post_parent' => $p->ID, 'post_type'=>'attachment' ) );
   	  $return["listid_" . count($return)] = array("title" => $p->post_title . " (" . count( $att ) . ")", "id"=> $p->ID);
  	}
  	return $return;
  }
  public function get_tag_list($return = array()) {
    $tags = get_terms($this->tax, array( 'hide_empty' => 1 ));
    foreach($tags as $t){
	   $posts = get_posts(array("posts_per_page"=>-1,"post_type"=>"attachment",$this->tax => $t->slug));
	   $ids = wp_list_pluck($posts,"ID");
	   $return["listid_" . count($return)] = array("title" => $t->name . " (" . count( $ids ) . ")", "id"=>$ids);
    }
	   return $return;
  }
  function modify_post_mime_types( $post_mime_types ) {
    $post_mime_types['application/pdf'] = array( __( 'PDFs' ), __( 'Manage PDFs' ), _n_noop( 'PDF <span class="count">(%s)</span>', 'PDFs <span class="count">(%s)</span>' ) );
    return $post_mime_types; // then we return the $post_mime_types variable
  }
 }
class JRC_Gallery extends Gallery {
  private $post, $queried_id, $queried_tax, $terms = array();
  function __construct() {
    parent::__construct();
    add_filter( 'nada-filter_galleryitem',array($this,'nadafilter_galleryitem') );
    add_filter( 'nada-filter_galleryclasses',array($this,'nadafilter_galleryclasses'),10,2 );
    add_filter( 'nada-filter_gallery',array($this,'nadafilter_gallery'),10,3 );
    add_filter( 'admin_init',array($this,'customise'));
  }
  function customise() {
    $list = array("default"=>"Default","carousel"=>"Slider");
    self::$settings[0] = Field::get("style","Gallery Style","panel",array("type"=>"select","default"=>"default","list"=>$list));
  }
  function output($atts) {
    global $post; $this->post = $post;
    $this->queried_id = get_query_var("imageid");
    $this->queried_tax = get_query_var(TAX);
    return parent::output($atts);
  }
  function nadafilter_galleryclasses($classes,$args){
    if($args["style"]=="default"){$classes[]="hasnav";}
    return $classes;
  }
  function nadafilter_galleryitem($item) {
    # Append classes for show/hide
    $qid = $this->queried_id;
    $qtm = $this->queried_tax;
    $show = ( empty($qid) && empty($qtm) ) || ( $qid == $item->post_id ) || ( !empty($qtm) && has_term($qtm, TAX, $item->post) );

    $obj_terms = $this->term_array(wp_get_object_terms( $item->post_id, TAX ));

    $this->terms = array_merge($this->terms,$obj_terms);
    $tags = implode(",",array_keys($obj_terms));

    $item->lightbox = "fancybox";
    $item->alt = "{$this->post->post_title} by Justine Roland-Cal - {$item->post->post_title}";

    $item->add_class( $show ? "tagmatch" : "tagnomatch");
    $item->add_attr("div","data-tags",$tags);
    $item->add_attr("a","data-fancybox-group","tagmatch");
    $item->add_attr("a","data-fancybox-href","");
    return $item;
  }
  function nadafilter_gallery($html,$items,$args) {
    if($args["style"]!=="default") return $html;
    # append navigation
    $nav = ""; $url = get_permalink($this->post->ID); ksort($this->terms);
    foreach ($this->terms as $k => $v) {
    	$class = "side-item " . ($this->queried_tax == $k ? "current_page_item" : "");
    	$nav .= "<li class='{$class}'><a href='{$url}{$k}' class='navigator' data-target='{$k}'>{$v}</a></li>";
    }
    $nav = "<ul id='gallery-nav' class='secondary-nav'>" . $nav . "</ul>";
    return $nav . $html;
  }
  function script_gallery() { ?>
    var gal = $(".gallery").first();   var nav = $("#gallery-nav");
    $.ajax({url:urls.plugin_url + "packery.pkgd.min.js",dataType:"script",cache:true}).done(function() {
      nav.append("<li class='nesttoggle'><a href='#' id='nesttoggle' title='Toggle gallery view' class='icon icon-nest'><span class='alttext'>Toggle</span></a></li>")
         .on("click","#nesttoggle",toggle_nest)
         .on("click",".navigator",function(){
           $(document).trigger("gallery:filter",[{filter: $(this).attr("data-target")}]);
           return false;
         });
      $(document).on("gallery:filter",function(e,data){
        var c = "current_page_item";
        var n = gal.is(".nested");
        if (n) {toggle_nest();}
        nav.find("."+c).removeClass(c);
        nav.find("[data-target='" + data.filter + "']").addClass(c);
        gal.find(".tagmatch").toggleClass("tagmatch tagnomatch");
        gal.find("[data-tags*='" + data.filter + "']").toggleClass("tagmatch tagnomatch");
        if (n) {toggle_nest();}
      });
      function toggle_nest() {
	    var isnested = gal.is(".nested");
        gal.toggleClass("nested");
        $("#nesttoggle").toggleClass("nested icon-list icon-nest");
        if(isnested) {//unnest
	      gal.packery('destroy');
	      $(".tagmatch").removeAttr("style");
        } else {//nest
          $(".tagmatch").each(function(){
            var me = $(this);
            var r = 25 * ( me.is(".landscape") ? 2 : 1) ;
            me.attr("style","width:" + r + "%;");
          });
          gal.packery({itemSelector:".tagmatch", percentPosition:true});
        }
        return false;
      }
  	});
  <?php }
  private function term_array($arr) { $return = array(); foreach($arr as $a) { $return[$a->slug] = $a->name;}return $return;   }
 }
class JRC_News {
  private static $instance = 0;
  function __construct() {
    if ( self::$instance == 0 ) {
      self::$instance = 1;
	    add_shortcode('jrcnews', array($this,'output'));
	    Script::register($this);
	   }
  }
  function output($atts) {
    $atts = shortcode_atts( array("extra" => ""), $atts );
    $html = "";
    $posts = get_posts(array('posts_per_page'=>2,'orderby'=>'post_date','order'=>'DESC','post_type'=>'post','post_status'=>'publish'));

    if (count($posts) > 0 ) {
      foreach($posts as $p){$html .= "<h3 class='side-item'><a href='" . get_the_permalink($p->ID) . "'>" . $p->post_title . "</a></h3>";}
      $html = "<div class='frontnews'><div>" . $html . "</div></div>";
    }
    return $html;
   }
 }
?>
