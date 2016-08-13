<?php // JLJ Common
if (!defined('ABSPATH')) die();
if (!defined('ID')) define('ID','vas');




function add_body_class($classes) {
  $classes[] = ID;
  return $classes;
} add_filter( 'body_class', 'add_body_class');
function nada_post_share($val) {
  if ( shortcode_exists( 'share' ) ) {
    global $post;
    $link = get_permalink();
    $title = $post->post_title;
    $image = get_gallery_images($post,1);
    if (empty($image)) { $image = ""; }
    else {
      $image = $image[0];
      $image = wp_get_attachment_image_src($image,"full");
      $image = $image[0];
    }
    $short =  "[share url='{$link}' title='{$title}' image='{$image}']";
    return do_shortcode($short);
  }
} add_filter("nada-post-share","nada_post_share");
function get_gallery_images($post,$count) {
  $return = array();
  $gal = array(); preg_match("/\[gallery(.*?)\]/", $post->post_content, $gal);
  if (! empty($gal)){
    $gal = nada_short_attributes("gallery", $gal[0]);
    $gal = $gal[0]["ids"];
  }
  if (! empty($gal)){
    $gal = explode(",",$gal);
    $gal = array_slice($gal,0,$count);
    $return = $gal;
  }
  return $return;
}
function custom_excerpt( $output ) {
  if (is_home()) {
    global $post;  $items = 3;
    $gal = get_gallery_images($post,$items);
    if ( empty($gal) ) { $gal = ""; }
    else {
      $items = count($gal);
      $gal = implode(",",$gal);
      $gal = do_shortcode("[gallery ids='{$gal}' style='columns-{$items}' classes='blog' show_share='false' show_text='false' show_lightbox='false']");
    }
    $share = apply_filters("nada-post-share", null);
    $title = the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>',false );
    $output = "{$title}{$gal}<div class='excerpt blog'>{$share}{$output}</div>";
  }
  return $output;
}  add_filter( 'get_the_excerpt', 'custom_excerpt' );

function get_started() {
  global $site_settings;
  Script::register(); new Email_Hider(); new JLJ_Gallery(); new Emojicon_Killer();  new Unscroll('#menu','#wbody');
  new Instagram(
    array("instagram"),
    $site_settings[ID]["instagram_api"],
    $site_settings[ID]["instagram_user"],
    array("show_overlay" => true,"show_comments" => true, "show_share" => false, "posts_per_page" => 21));
  new Share(array("facebook","pinterest","tumblr","weheartit","linkedin"),"/images/cover.jpg");

  $woo = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
  $woo = $woo & current_user_can('edit_others_pages');

  $mail  = $site_settings[ID]["mail"];
  $tax   = $site_settings[ID]["product_tax"];
  $menu  = $site_settings[ID]["product_menu"];
  $label = $site_settings[ID]["product_label"];

  new JLJ_Theme($tax);
  if ( "vas" == ID ) { new vas_structure($site_settings[ID]["portfolio_menu"]); }
  if ( $woo ) {
    new shop_structure($tax,$label,$menu);
    new Woo($tax,$mail);
  }
 } add_action('plugins_loaded','get_started');
function vas_get_image($id) {
  $x = new Gallery_Item($id,false,false,true);
  return "<div id='jdbox'><div class='jdbox'>" . $x->output() ."</div></div>";
 }
function jlj_term($pfid = 0,$tax, $return = "term_id") {
   $terms = wp_get_post_terms($pfid,$tax);
   if(!is_wp_error($terms) && !empty($terms) && is_object($terms[0])){
     if ($return == "order") {
       $meta = get_option( "tax_order" );
       if(isset($meta[$terms[0]->term_id]) && ! empty($meta[$terms[0]->term_id])) {
         return $meta[$terms[0]->term_id];
       }
       else {
         return 0;
       }
     } else { return $terms[0]->$return; }
   }
   else {
     if($return == "term_id"){return 0;}
     elseif($return == "name") {return "None";}
     elseif($return == "slug") {return "none";}
     elseif($return == "order") {return 0;}
     else{return "";}
   }
}

class JLJ_Gallery extends Gallery {
   function __construct() {
     parent::__construct();
     $list = array("default"=>"Default","nested"=>"mosaic");  for ($i=1;$i<=6;$i++) {$list["columns-{$i}"] = "Columns {$i}";}
     self::$settings[0] = Field::get("style","Gallery Style","panel",array("type"=>"select","default"=>"default","list"=>$list));
     self::$settings[1] = Field::get("hide_share","Hide Sharing Buttons","panel",array("type"=>"checkbox","default"=>false));
     self::$settings[2] = Field::get("show_text","Show Picture Caption","panel",array("type"=>"checkbox","default"=>false));

     add_filter( 'embed_oembed_html', array($this,'embed_oembed_html'), 99, 4);
     add_filter( 'nada-filter_galleryitem',array($this,'nadafilter_galleryitem') );
     add_filter( 'nada-filter_galleryitems',array($this,'nadafilter_galleryitems') );
   }
   function output($atts) {
     if (isset($atts["hide_share"]) && ! isset($atts["show_share"])) {
       $bshare = ! filter_var($atts["hide_share"], FILTER_VALIDATE_BOOLEAN);
       $atts["show_share"] = $bshare;
       unset($atts["hide_share"]);
     }
     return parent::output($atts);
   }
   function embed_oembed_html($html, $url, $attr, $post_id) {
     return '<div class="wvideo"><div class="ivideo">' . $html . '</div></div>';
   }
   function nadafilter_galleryitem($item){
     # Add ALT & CAption
     if ( "vas" == ID ) {
       $id = $item->post_id;
       $video = $item->is_video();

       $item->caption = get_post_field("post_excerpt",$id);

       $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
       if (strlen($alt) == 0 ) {
         $post = get_post($id);
         $artist_name = get_the_title(get_post($post->post_parent)->post_parent);
         $series_name = get_the_title($post->post_parent);
         $alt = " by {$artist_name} | {$series_name} | " . $post->post_title;
         $item->alt = ($video ? "Video" : "Image" ). $alt;
       }
       else {$item->alt = $alt;}
     }
     return $item;
   }
   function nadafilter_galleryitems($items){
     # Add Half classes for default style
     $style = $this->atts["style"];
     if ( $style == "default" ) {
       $image_portrait = NULL;
       foreach($items as $i) {
         $p = $i->is_portrait_image();
         $n = (NULL === $image_portrait);
         if ( $p  && !$n ) {
           $image_portrait->add_class("half");
           $i->add_class("half");
         }
         $image_portrait = ($p && $n) ? $i : NULL;
       }
     }
     return $items;
   }
   function script_gallery(){
     ?> // Packery
     var gal = $('.gallery.columns, .gallery.nested');
     if (gal.length) { //if gal exists, load scripts then
       $(".gallery.nested .gallery-item").each(function(){  $(this).attr("style","width:"+ ( ( $(this).hasClass("landscape") ? 2 : Math.floor(Math.random() * 2 + 1 ) ) * 25) + "%;")});
       $.ajax({url:urls.plugin_url + "packery.pkgd.min.js",dataType:"script",cache:true}).done(function() {
         gal.packery({itemSelector:'.gallery-item', percentPosition:true});
   	  });
     }
   <?php }
}
class JLJ_Theme {
  public function __construct($product_tax_name=""){
    $this->product_tax_name = $product_tax_name;
    add_action( 'after_setup_theme',array($this,'register_menus'));
    add_action( 'wp_enqueue_scripts', array($this,'load_scripts'));
    add_filter( 'mce_buttons_2', array($this,'vas_mce_buttons_2'));
    add_filter( 'tiny_mce_before_init', array($this,'vas_mce_before_init_insert_formats' ));
    add_action( 'admin_init', array($this,'vas_theme_add_editor_styles' ));
    add_filter( 'wp_title', array($this,'wp_title'), 10, 2 );
    add_filter( 'init', array($this,'register_image_sizes'));
    add_filter( 'nada-filter_imagefolder', array($this,'favicon_folder'));
  }
  function register_image_sizes(){
    update_option('medium_size_w', 400);
    update_option('medium_size_h', 400);
    update_option('large_size_w', 1045);
    update_option('large_size_h', 1045);
    add_image_size( 'intermediate', 700, 700 );
  }
  function register_menus(){ register_nav_menus( array('primary' => 'Primary Menu','alternative' => 'Alternative Menu') ); }
  function load_scripts() {
    wp_enqueue_script( 'jquery');
    $parent_style = 'parent-style';

    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( "parent-style" ));

    // Add boxsizing polyfill
    $custom_css = "* {*behavior: url(" .  get_stylesheet_directory_uri() . "/js/boxsizing.htc);}";
    wp_add_inline_style( 'themestyle', $custom_css );
  }
  function vas_mce_buttons_2( $buttons ) {array_unshift( $buttons, 'styleselect' ); return $buttons; }
  function vas_mce_before_init_insert_formats( $init_array ) {
    // Callback function to filter the MCE settings
    // Define the style_formats array
    $style_formats = array(
      array('title' => 'Metal', 'inline' => 'span',  'classes' => 'metallic', 'wrapper' => true, ),
    );
    $init_array['style_formats'] = json_encode( $style_formats );
    return $init_array;
  }
  function vas_theme_add_editor_styles() {
    add_editor_style( "editor-style.css" );
  }
  function wp_title( $title, $sep ) {
	  if ( is_feed() ) { return $title; }
    global $page, $paged, $post;
	  $parts = array();
    if (is_tax()){ $parts[] =  single_term_title("",false); }

    if($post->post_type == "product" && $this->product_tax_name !== ""){
      $parts[] = $post->post_title . " by " . jlj_term($post->ID,$this->product_tax_name,"name");
    }
    else { // if available, display parent & grandparent
      $parent = $post->post_parent; if($parent !== 0){
        $grandparent = get_post($parent)->post_parent; if($grandparent !== 0) {
          $parts[] = get_the_title($grandparent);
        }
        $parts[] = get_the_title($parent);
      }
      $parts[] = $post->post_title;
    }

    $parts[] = get_bloginfo( 'name', 'display' );
    if (  is_home() || is_front_page()  ) { $parts[] = get_bloginfo( 'description', 'display' ); }
    if ( ( $paged >= 2 || $page >= 2 ) && ! is_404() ) { $parts[] = 'Page ' . max($paged,$page);}
    return implode(" {$sep} ",$parts);
  }
  function favicon_folder($path) {
    return "favicons_" . ID;
  }
}
abstract class structure {
  protected $tax= "", $parent = "",$unit= "", $units=array(),$top,$top_menu;
  public function __construct($tax,$parent,$unit) {
    $this->tax = $tax; $this->parent = $parent; $this->unit = $unit;
	  # Term Change
    add_action( 'edited_term',  array($this,'term_change'),10,3);
    add_action( 'created_term', array($this,'term_change'),10,3);
    add_action( 'delete_term',  array($this,'term_change'),10,3);
	  # Dynamic menu
	  add_action( 'init', array($this,'worklist'));
	  add_filter( 'wp_get_nav_menu_items', array($this,'inject_menus'), 20, 2 );
  }

  # HELPERS
  function getlist_video($dummy=""){
    $return = array();
    $videos = get_posts(array('posts_per_page'=> -1,'post_type'=> 'attachment',
                              'post_mime_type'=> 'image','post_status'=> 'inherit',
                              'meta_query' => array( array( 'key' => '_video_link','value' => '','compare' => '!='))));

    if (count($videos) > 0 ) {
      foreach($videos as $v) {$vd[] = $v->ID; }
      $return["listid_" . count($return)] = array("title"=>"Videos (" . count($videos) . ")","id" => implode(",",$vd));
    }
    return $return;
  }
  function getlist_page($dummy=""){
    $return = array();
  	$pages = get_pages(array('post_type'=>'page','posts_per_page'=>-1 ));
  	foreach($pages as $p){
   	  $att = get_children( array( 'post_parent' => $p->ID ) );
   	  $return["listid_" . count($return)] = array("title" => $p->post_title . " (" . count( $att ) . ")", "id"=> $p->ID);
  	}
  	$return["listid_" . count($return)] = array("title" => "--------------", "id"=> 0);
  	$loops = array("tax","parent","unit");
  	$units = $this->my_sort($this->units,$loops);
    $keeptrack = array();
  	foreach($units as $u){
      $cid = $pid = $prefix = "";
  	  foreach($loops as $l){
        $field = "{$l}_id";$id = $u[$field]; $cid = "{$pid}_{$id}"; $prefix .= "&nbsp;&gt;&nbsp;";
  	    if ( !isset($keeptrack[$cid]) ) {
    		  $ids = $this->my_filter($this->units,$field,$id);
    		  $ids = implode(",",array_column($ids,"unit_id"));
    		  $name = $prefix . $u["{$l}_name"];
    		  $return["listid_" . count($return)] = array("title"=>$name,"id"=>$ids);
          $keeptrack[$cid] = "done";
    		}
  		  $pid = $cid;
  	  }
  	}
  	return $return;
  }
  function getlist($return){
    $list = array();
    if("tax" == $return || "taxbyparent" == $return) {$items = get_terms($this->tax,array("hide_empty"=>false)); }
    elseif("parent" == $return) {$items = get_posts(array("posts_per_page"=>-1,"post_type"=>$this->parent)); }

    foreach($items as $i ) {$title = ""; $id = "";
      if("parent" == $return) {$title = $i->post_title; $id = $i->ID;}
      if("tax" == $return) {$title = $i->name; $id = $i->term_id;}
      if("taxbyparent" == $return) {$title = $i->name;
        $par = $this->my_filter($this->units,"tax_id",$i->term_id); # get worklist items matching this tax
        $par = array_column($par,"parent_id"); # extract parent column
        $par = array_unique($par); # remove duplicates
        $id = implode(",",$par);
      }
      $id = ""==$id ? 0 : $id;
      $list["listid_" . count($list)] = array("title"=>$title,"id"=>$id);
    }
    return $list;
  }
  function term($pfid = 0, $return = "term_id") {
    return jlj_term($pfid,$this->tax,$return);
  }
  function my_sort($array,$mode){
    if ($array){
      $dummy_numeric = array_fill(1,count($array),1);
    	$dummy_string = array_fill(1,count($array),"");
      $i = 0;
      foreach (array("tax","parent","unit") as $loop) {
  	    $filter[$i] = in_array($loop,$mode) ? array_column($array,"{$loop}_order") : $dummy_numeric; $i++;
  	    $filter[$i] = in_array($loop,$mode) ? array_column($array,"{$loop}_name") : $dummy_string; $i++;
  	  }
      array_multisort($filter[0], SORT_NUMERIC, $filter[1], SORT_STRING, $filter[2], SORT_NUMERIC, $filter[3],SORT_STRING,$filter[4],SORT_NUMERIC,$filter[5],SORT_STRING,$array);

    }
  	return $array;
  }
  function my_filter($array,$field,$value){
    $return = array();
    foreach($array as $k=>$v){ if ($v[$field] == $value){ $return[$k] = $v; } }
    return $return;
  }
  function merge_terms($arr = array()){
    $terms = get_terms(array($this->tax),array('fields'=>'id=>slug','hide_empty'=>false));
  	$terms = array_values($terms);
	  $terms = array_merge($arr,$terms);
	  return strtolower(implode("|",$terms));
  }

  # REWRITE RULES
  function term_change( $term_id,$tt_id, $taxonomy ) {
    if($taxonomy == $this->tax){
      $this->rewrite_rules();
      flush_rewrite_rules();
    }
   }

  # Dynamic Menu
  function worklist() {
    $units = get_posts(array('posts_per_page'=>-1,'post_type'=>$this->unit,'orderby'=>'menu_order date','order'=>'ASC'));

  	foreach ( $units as $wk ) {
       $this->units[$wk->ID] = array("unit_id"=>$wk->ID,
	   								 "unit_name"=>$wk->post_title,
                                     "unit_slug"=>$wk->post_name,
                                     "unit_order"=>$wk->menu_order,
                                     "unit_status"=>$wk->post_status,
							         "parent_id"=>$wk->post_parent,
                                     "parent_name"=>get_the_title($wk->post_parent),
                                     "parent_slug"=>get_post_field("post_name",$wk->post_parent),
                                     "parent_order"=>get_post_field("menu_order",$wk->post_parent),
                                     "tax_id"=>$this->term($wk->ID),
                                     "tax_name"=>$this->term($wk->ID,"name"),
                                     "tax_slug"=>$this->term($wk->ID,"slug"),
                                     "tax_order"=>$this->term($wk->ID,"order"));
	  }
	  if ( $this->unit == $this->parent ) { $this->units = $this->my_filter($this->units,"parent_id","0");}
  }
  function inject_menus($itms, $menu) {
    if ( is_admin() ) { return $itms; }
  	else {
      $order = 1; $items = array(); $page_url = get_url(); $bio = 0; $count = 0;
  	  foreach($itms as $mi) {
    		if ( ! ("custom" == $mi->object && $mi->title == $this->unit) ) { # native menu items
    		  $mi->menu_order = $order; $order++; $items[] = $mi;
    		} else { # Custom Menu Items: prepare list and loops
          # prepare list and loops, urls
    		  $list = $this->my_filter($this->units, "unit_status", "publish");
    		  if ( $this->unit == $this->parent ) { $loops = array("tax"); }
    		  elseif ( $this->top !== "" ) {$loops = array("parent");  }
    		  else {$loops = array("tax","parent"); }
          if ( $this->top !== "" ) {$holder = $this->top_menu;}
    		  $loops[] = "unit";
    		  $list = $this->my_sort($list,$loops);
    		  $rooturl = get_bloginfo("siteurl") . "/";
    		  $pageurl = get_url();
    		  # prepare top level holder if required
    		  if ( isset($holder) ) {
    		    $rooturl .= "{$holder}/";
    			  $classes = $this->get_classes($rooturl,$pageurl,array("empty"));
    		    $items[$holder] = $this->get_nav_menu($holder,ucfirst($holder),$rooturl,$order,0,0,$classes); $order++;
    		  }
    		  # loop through items and required levels outputting the actual menus
    		  foreach ($list as $l) {
      			$cid = ""; $pid = ( isset($holder) ? "$holder" : "" );
      			$url = $rooturl;
      			foreach ($loops as $lvl) {
      			  $id = $l["{$lvl}_id"];$cid = "{$pid}_{$id}";
      			  $url .= $l["{$lvl}_slug"] . "/";
      			  if ( ! isset($items[$cid]) ) {
      				  $printurl = $url . ( "parent" == $lvl && "vas" == ID ? $l["unit_slug"] . "/" : "" );
      				  $classes = $this->get_classes($url,$pageurl);
      				  if ( ( "tax" == $lvl && "vas" == ID ) || 0 == $id ) { $classes[] = "empty"; }
                $parent = isset($items[$pid]) ? $items[$pid] : null;
        				$items[$cid] = $this->get_nav_menu($lvl,$l["{$lvl}_name"],$printurl,$order,$parent,$id,$classes);$order++;
        				if ( "parent" == $lvl && "vas" == ID ) { # Add Bio
        				  if ( 0 < strlen(get_post_field("post_content",$l["parent_id"])) ) {
        				    #$count = count($this->my_filter($list,"parent_id",$id));
                    #$count = 0;
                    $name = "Biography";
        					  $parent = $items["{$holder}_" . $l["parent_id"]];
        					  #$items[] = $this->get_nav_menu($name,$name,$url . strtolower($name) . "/",$order + $count,$items[$cid]);$order++;
                    $count = $order + count($this->my_filter($list,"parent_id",$id));
                    $bio = $this->get_nav_menu($name,$name,$url . strtolower($name) . "/",0,$items[$cid]);
                  }
        				}
                if($count == $order) {$bio->menu_order = $order; $items[] = $bio; $order++; $count = 0; $bio = 0; }
              }
      			  $pid = $cid;
      			}
    		  }
    		  $items = array_values($items);
    		}
  	  }
	  return $items;
	 }
  }
  function get_nav_menu($object, $title, $url, $order = 0, $parent = 0, $object_id = 0, $class = array() ) {
    $parent = isset($parent->ID) ? $parent->ID : $parent;
    $item = new stdClass();
    $item->ID = 1000000 + $order + $parent;
    $item->db_id = $item->ID; $item->title = $title; $item->url = $url; $item->menu_order = $order; $item->menu_item_parent = $parent;
    $item->type = 'dynamic'; $item->object = $object; $item->object_id = $object_id; $item->classes = $class;
    $item->target = ''; $item->attr_title = ''; $item->description = ''; $item->xfn = ''; $item->status = '';
    return $item;
  }
  function get_classes($url,$pageurl,$classes=array()){
    if ( $url == $pageurl ) { $classes[] = "current_page_item"; }
  	elseif ( startsWith($pageurl,$url) ) { $classes[] = "current_page_ancestor"; }
  	return $classes;
  }
 }
class shop_structure extends structure {
  private $works = array(); var $tax, $tax_label, $unit = "product", $unit_label = "Products";
  function __construct($tax,$tax_label,$top_menu=""){
    $this->tax = $tax; $this->tax_label = $tax_label;$this->top_menu = $top_menu;
    $this->top = ($this->top_menu == "") ? "" : $this->top_menu . "/";

  	# Setup & URLS
  	add_action('init', array($this,'register_elements'));
  	add_action('init', array($this,'rewrite_rules'));
  	add_filter('post_link', array(&$this,'permalink'), 10, 3);
  	add_filter('post_type_link', array(&$this,'permalink'), 10, 3);
    add_filter('admin_init',array($this,'customise'));
    parent::__construct($this->tax,$this->unit,$this->unit);
  }
  # Setup & URLS
  function register_elements(){
   register_taxonomy($this->tax,array($this->unit),array('label'=>$this->unit_label,'public'=>true,'show_admin_column'=>true,'hierarchical'=>false,'query_vars'=>true,'rewrite'=>true));
  }
  function rewrite_rules() {
  	$terms = $this->merge_terms(array("products","works","none"));
    add_rewrite_rule('^' . $this->top . '(' . $terms . ')/([^/]+)/?$','index.php?product=$matches[2]','top');
    add_rewrite_rule('^' . $this->top . '(' . $terms . ')/?$','index.php?' . $this->tax . '=$matches[1]','top');
  }
  function permalink($permalink, $post, $leavename) {
    if (!$post) return $permalink;

    if ($this->unit == get_post_type($post)) {
	    $taxonomy_slug = $this->term($post->ID,"slug");
      return str_replace('/' . $this->unit . '/', '/' . $this->top . $taxonomy_slug . '/', $permalink);
	  }
    return $permalink;
  }
  function customise() {
    $tax = $this->tax; $tlabel = $this->tax_label;
    $unit = $this->unit; $tunit = $this->unit_label;

    #FIELDS
    $price = Field::get("_jlj_price","Price Complement","option");
    $suffix = Field::get("_jlj_suffix","SKU Suffix","option");
    $order_tax = Field::get("tax_order","Menu Order","option");
    $order = Field::get("menu_order","Order","post");
    $ftax = Field::get($tax,$tlabel,"taxonomy",array($this,"getlist","tax"));

    #CUSTOMISATIONS
    #$gal = Field::get("gallery","Choose Gallery Pictures","meta");
    #Admin::meta("post",array("Gallery"=>array($gal)));
    Admin::tabs(array('edit-comments.php'));
    Admin::metatax("taxonomy_{$tax}",$order_tax);
    Admin::metatax("taxonomy_pa_*",array($price,$suffix));
    Admin::meta($unit,array($tlabel=>array($ftax,$order)),array($ftax,"postcustom","postexcerpt","commentsdiv"));
    Admin::columns($unit,array($ftax,$order,"date"),array("date",$ftax));
    Admin::filters($unit,$ftax);

    $video = Field::get("_video_link","Video Link","meta");
    Admin::metaatt($video);

    #$att_parent = Field::get("post_parent__in",$ulabel,"post",array($this,"getlist_page",""));
    $att_type = Field::get("post__in","Formats","post",array($this,"getlist_video",""));
    Admin::filters("attachment",array($att_type));

  }
}
class Woo {
  var $tax, $mail;
  function __construct($tax = "",$mail=""){
    $this->tax = $tax;
    $this->mail = $mail;
  	add_filter('wp_nav_menu_items', array($this,'inject_cart'), 20, 2 );
    add_filter("after_setup_theme",array($this,'setup_wc'));
  }
  function inject_cart($itms, $menu){ # add cart menu item
    if (sizeof(WC()->cart->get_cart()) != 0) {
	     $cart = WC()->cart->get_cart_url();
	      $cart_totals = WC()->cart->get_cart_contents_count();
        $itms .=  "<a href='{$cart}'><span class='icon icon-cart'><span class='alttext'>My cart</span></span> (<span class='nada_cart_totals'>{$cart_totals}</span>)</a>";
    }
    return $itms;
  }
  public function product_button($product){
    return sprintf( '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" data-quantity="%s" class="button %s product_type_%s">%s</a>',
      esc_url( $product->add_to_cart_url() ),
      esc_attr( $product->id ),
      esc_attr( $product->get_sku() ),
      esc_attr( isset( $quantity ) ? $quantity : 1 ),
      $product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
      esc_attr( $product->product_type ),
      "Add to cart" #esc_html( $product->add_to_cart_text() )
    );
  }
  function setup_wc() {
    # Misc
    add_theme_support( 'woocommerce' );
    add_filter( 'loop_shop_per_page', create_function( '$cols', 'return 100;' ), 20 ); #numbe of products per page
  	# Customise shop
  	add_filter( 'woocommerce_product_add_to_cart_text', array($this,'shop_atc_text') );
  	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
  	add_filter( 'woocommerce_after_shop_loop_item_title', array($this,'shop_price'), 10 );
  	# Customise product pages
  	// remove breadcrumbs
  	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );
  	// replace product image gallery
  	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
  	add_action( 'woocommerce_before_single_product_summary', array($this,'product_image_gallery'), 20 );
    // remove below tabs
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
    remove_action( 'woocommerce_after_single_product', 'action_woocommerce_after_single_product', 10, 0 );
    remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
   	// change description
  	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
  	add_action( 'woocommerce_single_product_summary', array($this, 'product_description'), 20 );
  	// remove price from top product + add below description
  	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
  	add_action( 'woocommerce_single_product_summary', array($this,'product_price'), 25 );
  	// remove price meta product
  	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
    // change output of grouped + variable products
    remove_action( 'woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', 30 );
    add_action( 'woocommerce_grouped_add_to_cart', array($this, 'product_cart_grouped'), 30 );
    remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
    add_action( 'woocommerce_variable_add_to_cart', array($this, 'product_cart_variable'), 30 );
  	remove_action( 'woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30 );
    add_action( 'woocommerce_simple_add_to_cart', array($this, 'product_cart_simple'), 30 );
    // insert defaults on variable variation
    add_action( 'product_variation_linked', array($this,'admin_default_variations') );
    // Inject artist biography in shop page
    add_action( 'woocommerce_after_shop_loop', array($this,'admin_shop_description'));

    // Inject attribute condition in advanced shipping plugins_loaded
    // Customise no shipping available message
    add_filter( 'woocommerce_no_shipping_available_html', array($this,'admin_shipping_none_message'),10, 1 );
    add_filter( 'woocommerce_cart_no_shipping_available_html', array($this,'admin_shipping_none_message'), 10, 1 );
    add_action( 'woocommerce_flat_rate_shipping_add_rate', array($this,'add_another_custom_flat_rate'), 10, 2 );
    add_filter('gettext', array($this,'translate_text'));
    add_filter('ngettext', array($this,'translate_text'));
  }


  function translate_text($translated) {
    $translated = str_ireplace(
      'Your order has been received and is now being processed. Your order details are shown below for your reference:',
      "Your order has been confirmed. You will be informed about the delivery of your order.<br>
      If you have questions about the shipping or delivery, do not hesitate to contact our team on {$this->mail}.", $translated);
    return $translated;
  }
  function add_another_custom_flat_rate( $method, $rate ) {
    unset($method->rates[$rate['id']]); # remove rate added by flat rates

    $package = $rate["package"];
    $classes = $method->find_shipping_classes( $package );
    $itemcount = $method->get_package_item_qty( $package );
    $classcount = count($classes);

    #echo "<hr>";
    #echo "Items in package: {$itemcount} <br>";
    #echo "Classes in package: {$classcount} <br>";

    # ensure produts <5 & classes = 1
    if($itemcount > 5 || $classcount >1) {return;}

    $class_name = array_keys($classes)[0];
    $class_term = get_term_by('slug',$class_name,'product_shipping_class');
    $cost_zone = $method->get_option('cost');
    $cost_class =
       $class_term && $class_term->term_id ?
       $method->get_option( 'class_cost_' . $class_term->term_id,
          $method->get_option( 'class_cost_' . $class_name, '' ) ) :
       $method->get_option( 'no_class_cost', '' );

    #echo "Zone: " . WC()->customer->get_shipping_country() . "<br>";
    #echo "Class name: {$class_name} <br>";
    #echo "Cost a: {$cost_zone} <br>";
    #echo "Cost b: {$cost_class} <br>";

    #echo "Class Term: "; loose_print($class_term);
    #echo "class cost id: " . $method->get_option( 'class_cost_' . $class_term->term_id);
    #echo "class cost val: " . $method->get_option( 'class_cost_' . $class_name);
    #echo "class no cost: " . $method->get_option( 'no_class_cost');

    # ensure none of the prices is request
    if(strtoupper($cost_zone) == 'REQUEST' || strtoupper($cost_class) == 'REQUEST') {return;}

    if(strpos($cost_class,"|") !== false) {
      # if cost is provided in the form a|b|C
      $cost_class = explode("|",$cost_class);
      $cost_class = $cost_class[$itemcount-1];
    }

    $rate["cost"] = (float)$cost_class + (float)$cost_zone;

    #echo "Total Cost: " . $rate["cost"] . " <br>";
    #echo "<hr><br>";

    $method->add_rate($rate);
  }
  public function admin_shipping_none_message($message) {
    #There are no shipping methods available. Please double check your address, or contact us if you need any help
    $cart = "";
    global $woocommerce;  foreach($woocommerce->cart->cart_contents as $c) {
      $cart .= strlen($cart) > 0 ? " + " : "CART SHIPPING ENQUIRY: ";
      $cart .= $c["quantity"] . " x " . get_the_title($c["product_id"]) . " (" . implode("|", $c["variation"]) . ")";
    }
    $message = "Please contact us to arrange shipping for this address: ";
    $email = do_shortcode("[email tag='span']{$this->mail}?subject={$cart}[/email]");

    return $message . $email;
  }
  function admin_shop_description() { echo "<div class='page-content clear'>" . term_description() . "</div>"; }
  function admin_default_variations($id) {
    # THANK YOU https://github.com/m4olivei/woocommerce-attribute-pricing/blob/master/includes/wc-ap-admin.php
    # AND https://gist.github.com/greenbicycle/13c454072f76c6939b2d
    $post = get_post($id);
    if ($post && $post->post_type == 'product_variation') {
      $prod = wc_get_product($id);
      $att = $prod->get_variation_attributes();

      $description = "";
      $price = floatval(get_post_meta($post->post_parent,"_price",true));
      $sku = get_post_meta($post->post_parent,"_sku",true) . "_";

      foreach($att as $k=>$v) {
          $tax = str_replace('attribute_','',$k);
          $trm = get_term_by("name",$v,$tax);
          $description .= ( strlen($description) == 0 ? "" : "\r\n") . $trm->description;
          $suffix = get_option( "_jlj_suffix" );
          $suffix = $suffix[$trm->term_id];
          $complement = get_option( "_jlj_price" );
          $complement = $complement[$trm->term_id];
          $complement = floatval($complement);

          $sku .= $suffix;
          $price += $complement;
      }
      $defaults = array("_sku"=>"mynewsku:" . $sku,
                        "_variation_description"=>$description,
                        "_regular_price" => $price,
                        "_price" => $price);
        foreach($defaults as $k=>$v) {
          update_post_meta($post->ID, $k, $v);
        }
        #update_post_meta($post->ID, '_price', "23.3");
        #$prod = wc_get_product( $variation_id );
        #$prod->set_price(23);
        #$post->post_content = "Something Dynamic";

    }
  }
  function product_image_gallery () {
    global $post, $product;
    $product_image_ids = $product->get_gallery_attachment_ids();
    if (count($product_image_ids) == 0 ) { $product_image_ids = array($product->get_image_id()); }
    if (count($product_image_ids) !== 0 ) {
	    $atts = array("classes"=>"images","style"=>"columns-1","show_share"=>"false","show_text"=>"false","show_lighbox"=>"true","ids"=>implode(",",$product_image_ids));
      echo do_shortcode(array_short($atts,"gallery"));
	  }
  }
  function product_description() {
     global $post;
     $term = jlj_term($post->ID,$this->tax, "name" );
     echo "<div class='wc_artist'>{$term}</div>";
     ?><div itemprop="description"><?php
	   echo apply_filters( 'woocommerce_short_description', $post->post_content );
	 ?></div><?php
   }
  function product_price() {
    global $product; if ( $product->product_type == "simple" ) { woocommerce_template_single_price(); }
  }
  function request_price($product){
    return do_shortcode("[email tag='button' class='single_add_to_cart_button button alt add_to_cart_button' content='Price on request']{$this->mail}?subject=Price enquiry for " . $product->post->post_title . "[/email]");;
  }
  function product_cart_simple(){
  	global $product;
  	$availability = $product->get_availability();
  	$availability_html = empty( $availability['availability'] ) ? '' :
  						 '<p class="stock ' . esc_attr( $availability['class'] ) . '">' . esc_html( $availability['availability'] ) . '</p>';
  	echo apply_filters( 'woocommerce_stock_html', $availability_html, $availability['availability'], $product );
  	if ( $product->price == 0 ) {
  	  echo $this->request_price($product);
  	} elseif ( $product->is_in_stock() ) {
  	  do_action( 'woocommerce_before_add_to_cart_form' );
  	  echo "<form class='cart' method='post' enctype='multipart/form-data'>";
  	  do_action( 'woocommerce_before_add_to_cart_button' );
      if ( ! $product->is_sold_individually() ) {
	 			woocommerce_quantity_input( array(
	 				'min_value'   => apply_filters( 'woocommerce_quantity_input_min', 1, $product ),
	 				'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->backorders_allowed() ? '' : $product->get_stock_quantity(), $product ),
	 				'input_value' => ( isset( $_POST['quantity'] ) ? wc_stock_amount( $_POST['quantity'] ) : 1 )
	 			) );
	 		}
      echo "<input type='hidden' name='add-to-cart' value='" . esc_attr( $product->id ) . "' />";
      echo "<button type='submit' class='single_add_to_cart_button button alt'>" . esc_html( $product->single_add_to_cart_text() ) . "</button>";
      do_action( 'woocommerce_after_add_to_cart_button' );
  	  echo "</form>";
  	  do_action( 'woocommerce_after_add_to_cart_form' );
  	}
  }
  function product_cart_grouped() {
     global $product, $post;
     $grouped_product = $product;
  	 $grouped_products = $product->get_children();
  	 $quantites_required = false;
  	 $parent_product_post = $post;

     do_action( 'woocommerce_before_add_to_cart_form' );
  	 foreach ( $grouped_products as $product_id ) {
  	 	$product = wc_get_product( $product_id );
  		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && ! $product->is_in_stock() ) {	continue; }
  		$post = $product->post;
  		setup_postdata( $post );
  		echo "<div class='child-product product' id='product-{$product_id}'>";
  		echo "<div class='child-product-content'>" . $post->post_content . "</div>";
  		do_action ( 'woocommerce_grouped_product_list_before_price', $product );
  		if ($product->price == 0 ) {
  		  #echo "<a href='#' class='single_add_to_cart_button button alt add_to_cart_button'>Price on request</a>";
        echo $this->request_price($product);
  		} else {
  		  echo "<div class='child-product-price price'>" . $product->get_price_html() . "</div>";
  		  echo $this->product_button($product);
  		}
  		echo "</div>";
  	  }

      // Reset to parent grouped product
  	  $post    = $parent_product_post;
  	  $product = wc_get_product( $parent_product_post->ID );
  	  setup_postdata( $parent_product_post );
    	do_action( 'woocommerce_after_add_to_cart_form' );
  }
  function product_cart_variable() {
    global $product;
  	$id = absint( $product->id );
  	$vars = $product->get_available_variations();
    $hold = array();

  	if ( empty( $vars ) && false !== $vars ) { echo "<p class='stock out-of-stock'>This product is currently out of stock and unavailable.</p>";}
  	else {
  	  foreach($vars as $v) {
  	    $description = $v["variation_description"];
  	    $price = $v["price_html"];
  	    $vid = $v["variation_id"];
    		$iprice = $v["display_price"];

        echo "<form class='variations_form cart single_variation_wrap product' method='post' enctype='multipart/form-data'>
	              <div class='woocommerce-variation-description'>{$description}</div>";
  		if( $iprice == 0 ) {
  		  //echo "<a href='#' class='single_add_to_cart_button button alt add_to_cart_button'>Price on request</a>";
        echo $this->request_price($product);
  		} else {
  		  echo "<div class='single_variation price'>{$price}</div>
  			     <div class='variations_button'>
  			       <div class='quantity'><input type='number' step='1' name='quantity' value='1' title='Qty' class='input-text qty text' size='4' min='1'></div>
  			       <button type='submit' class='single_add_to_cart_button button alt'>Add to cart</button>
  			       <input type='hidden' name='add-to-cart' value='{$id}'>
  			       <input type='hidden' name='product_id' value='{$id}'>
  			       <input type='hidden' name='variation_id' class='variation_id' value='{$vid}'>";
  		  foreach	($v["attributes"] as $key => $val) {
  		    echo "<input type='hidden' name='{$key}' value='{$val}'>";
  		  }
  		  echo "</div>";
  		}
  		echo "	</form>";
  	  }
  	}
  }
  function shop_atc_text() { #shop price add to cart
    global $product;
  	$t = $product->product_type;
  	$p = $product->price;
  	switch ( $t ) {
  		case 'external': return 'Buy product'; break;
  		case 'grouped':	return 'View';	break;
  		case 'simple': return $p ? 'Add to cart' : "View"; break;
  		case 'variable': return 'View';	break;
  		default: 'Read more';
  	}
  }
  function shop_price() {  # price in shop page
    global $product;
  	if ( $product->product_type == "simple" ) {
        $price_html = $product->get_price_html();
        echo "<span class='price'>{$price_html}</span>";
  	}
  }
 }
class vas_structure extends structure {
  const TAX = "arttype"; const TAX_LABEL = "Art Types";
  const CPTA = "artist"; const CPTA_LABEL = "Artists";
  const CPTP = "portfolio"; const CPTP_LABEL = "Portfolios";
  private $works = array(); var $top, $top_menu;
  function __construct($top_menu=""){
    $this->top_menu = $top_menu;
    $this->top = ($this->top_menu == "") ? "" : $this->top_menu . "/";
  	add_action( 'init', array($this,'register_elements'));
  	add_action( 'init', array($this,'rewrite_rules'));
  	add_filter( 'post_link', array(&$this,'permalink'), 10, 2 );
  	add_filter( 'post_type_link', array(&$this,'permalink'), 10, 2 );
  	add_filter( 'attachment_link', array(&$this,'permalink'), 10, 2 );
    add_filter('admin_init',array($this,'customise'));
    parent::__construct(self::TAX,self::CPTA,self::CPTP,$this->top_menu);
  }
  # CPT Setup
  function register_elements(){
    register_post_type(self::CPTA,array('label'=>self::CPTA_LABEL,'public'=>true,'menu_position'=>20,'hierarchical'=>true,'query_vars'=>true,
					   'rewrite'=>array('slug'=>'artists')));
    register_post_type(self::CPTP,array('label'=>self::CPTP_LABEL,'public' => true,'menu_position'=>20,'hierarchical'=>false,'query_vars'=>true,
					   'rewrite'=>array( 'slug' => ($this->top !== "" ? $this->top : '%arttype%') . '/%artist%')));
    register_taxonomy (self::TAX, array(self::CPTP),array('label' =>self::TAX_LABEL,'public' => true,'show_admin_column' => true,'hierarchical' => false,'query_vars'=>true,'rewrite'=>false));
    register_post_type("news",array('label'=>"News",'public' => true,'menu_position'=>20,'hierarchical'=>false,'query_vars'=>true)) ;

  }
  function rewrite_rules() {
    $terms = $this->merge_terms(array("artists"));
    add_rewrite_rule('^(' . $terms . ')/([^/]+)/biography/?$','index.php?' . self::CPTA . '=$matches[2]','top');
    add_rewrite_rule('^(' . $terms . ')/([^/]+)/([^/]+)/([1-9]+)/?$','index.php?attachment_id=$matches[4]','top');
    add_rewrite_rule('^(' . $terms . ')/([^/]+)/([^/]+)/([^/]+)/?$','index.php?attachment=$matches[4]','top');
    add_rewrite_rule('^(' . $terms . ')/([^/]+)/([^/]+)/?$','index.php?' . self::CPTP . '=$matches[3]','top');
    add_rewrite_rule('^(' . $terms . ')/([^/]+)/?$','index.php?post_type=' . self::CPTP,'top');
    add_rewrite_rule('^(' . $terms . ')/?$','index.php?post_type=' . self::CPTP . '&' . self::TAX . '=$matches[1]','top');
    add_rewrite_rule('^([1-9]+)/?$','index.php?attachment_id=$matches[1]','top');
  }
  function permalink( $url, $post ) {
    # Attachment link filter passes ID, while other link filters pass a post: convert to post
    $post = get_post($post);
    $type = get_post_type($post);
    if ( self::CPTP == $type ) {
      $p = get_post($post->post_parent);
  	  $url = str_replace("%artist%",$p->post_name,$url);
  	  $url = str_replace("%arttype%",$this->term($post->ID,"slug"),$url);
    }
    elseif ( "attachment" == $type ) {
      $parent = $post->post_parent;
  	  if ($parent == 0) {
  	    $url = get_url() . $post->ID;
  	  } else {
  	    $url = get_permalink($parent) . $post->ID;
  	  }
  	}
    return $url;
  }
  function customise() {
    # STRUCTURE
    $tax = self::TAX;$tlabel = self::TAX_LABEL;
    $parent = self::CPTA;$plabel = self::CPTA_LABEL;
    $unit = self::CPTP;$ulabel = self::CPTP_LABEL;

    # FIELDS
    $order_tax = Field::get("tax_order","Menu Order","option");
    $order = Field::get("menu_order","Order","post");
    $video = Field::get("_video_link","Video Link","meta");
    $name = Field::get("ID","Name","post");
    $ftax = Field::get($tax,$tlabel,"taxonomy",array($this,"getlist","tax"));
    $fparent = Field::get("post_parent",$plabel,"post",array($this,"getlist","parent"));
    $fsiblings = Field::get("siblings-field",$ulabel,"custom");
    $fchildren = Field::get("children-field",$tlabel,"custom");
    $taxforparents = Field::get("post__in",$tlabel,"post",array($this,"getlist","taxbyparent"));
    $att_parent = Field::get("post_parent__in",$ulabel,"post",array($this,"getlist_page",""));
    $att_type = Field::get("post__in","Formats","post",array($this,"getlist_video",""));

    #$gal = Field::get("gallery","Choose Gallery Pictures","meta");
    #Admin::meta("post",array("Gallery"=>array($gal)));


    # CUSTOMISATIONS
    Admin::tabs(array('edit-comments.php'));
    Admin::metaatt($video);
    Admin::metatax("taxonomy_{$tax}",$order_tax);
    Admin::meta($unit,array($tlabel=>array($ftax,$fparent,$order)),$ftax);
    Admin::meta($parent,array($ulabel=>array($fsiblings,$order)));
    #Admin::columns($unit,array($name,$ftax,$fparent,$order,"date"),array($name,"date","title",$ftax));
    Admin::columns($unit,array($fparent,$order));
    Admin::columns($parent,array($fchildren,$order,"date"),array("date"));
    Admin::filters($unit,array($ftax,$fparent));
    Admin::filters($parent,$taxforparents);
    Admin::filters("attachment",array($att_parent,$att_type));

    # FILTERS
    add_filter("nada-filter-admin",array($this,"nada_filter_admin"),10,4);
  }
  # LISTS FOR ADMIN
  function nada_filter_admin($value,$raw,$id,$field){
    $ismeta = ("meta" == $field->what);
    $iscolumn = ("column" == $field->what);
    $isartist = (self::CPTA == $field->where);
    if($iscolumn && $isartist && "children-field" == $field->field){
      $gr = $group = array();
      $base = $this->my_filter($this->units,"parent_id",$id);
      foreach ($base as $w) {$group[$w["tax_name"]] = isset($group[$w["tax_name"]]) ? $group[$w["tax_name"]] + 1: 1;}
      foreach ($group as $g=>$ct) { $gr[] = "{$g} ({$ct})"; }
      $value = implode(" | ",$gr);
    }
    if($ismeta && $isartist && "siblings-field" == $field->field){
      $value = "";
      $args = $this->my_filter($this->units,"parent_id",$id);
      foreach($args as $a){
        $name = $a["unit_name"] . " (" . $a["tax_name"] . ")";
        $link = get_edit_post_link($a["unit_id"]);
        $value .= "<li><a class='button' href='{$link}'>{$name}</a></li>";
      }
      $urldata = array("post_type"=>$this->unit,"post_parent"=>$id);
      $link = admin_url("post-new.php?" . http_build_query($urldata));
      $value = "<ul>{$value}<li><a class='button' href='{$link}'>Add New</a></li></ul>";
    }
    return $value;
  }
 }
?>
