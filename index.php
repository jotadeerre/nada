<?php
/*
Plugin Name: Newsletters for Health Poverty Action
Author: Jon Diez
Version: 1.0
*/
new HPA_Newsletters;
class HPA_Newsletters {
  private $post_type = array(
      "type" => 'newsletter',
      "label" => 'Newsletters',
      "archive" => 'newsletters',
      "template" => "newsletter.php",
      "shortcode" => "newsletter");
  private $metaboxes = array(
    array("id"=>"head","label"=>"Head","type"=>"box","fields"=>
      array(
        array("id"=>"head_title","label"=>"Newsletter Title","type"=>"text"),
        array("id"=>"head_image","label"=>"Header Image","type"=>"image")
      )
    ),
    array("id"=>"welcome","label"=>"Welcome","type"=>"box","fields"=>
      array(
        array("id"=>"head_worker","label"=>"Featured Worker","type"=>"image","before"=>"<div class='row'>"),
        array("id"=>"head_map","label"=>"Country Map","type"=>"image","after"=>"</div>"),
        array("id"=>"head_welcome","label"=>"Welcome text","type"=>"editor")
      )
    ),
    array("id"=>"main","label"=>"Main","type"=>"box","fields"=>
      array(
        array("id"=>"main_picture","label"=>"Header Image","type"=>"image","collapse"=>"true"),
        array("id"=>"main_summary","label"=>"Summary","type"=>"editor","collapse"=>"true"),
        array("id"=>"main_story","label"=>"Main Story","collapse"=>"true","type"=>"editor"),
      )
    ),
    array("id"=>"featured_health_worker","label"=>"Featured Health Worker","type"=>"box","fields"=>
      array(
        array("id"=>"fhw_image","label"=>"Header Image","type"=>"image","before"=>"<div class='row'>"),
        array("id"=>"fhw_header","label"=>"Header","type"=>"textarea"),
        array("id"=>"fhw_footer","label"=>"Footer","type"=>"textarea","after"=>"</div>"),
        array("id"=>"fhw_story","label"=>"Main Story","type"=>"editor","collapse"=>"true"),
      )
    ),
    array("id"=>"global_movement","label"=>"Global Movement for Health","type"=>"box","fields"=>
      array(
        array("id"=>"gm_main","label"=>"Main Story","type"=>"editor"),
      )
    ),
    array("id"=>"events_actions_news","label"=>"Events, Actions, News","type"=>"box","fields"=>
      array(
        array("id"=>"events","label"=>"Events","collapse"=>"true","type"=>"repeatable","fields"=>
          array(
            array("id"=>"event","label"=>"Event","collapse"=>"true","type"=>"editor")
          )
        ),
        array("id"=>"actions","label"=>"Actions","type"=>"repeatable","collapse"=>"true","fields"=>
          array(
            array("id"=>"action","label"=>"Action","type"=>"editor","collapse"=>"true")
          )
        ),
        array("id"=>"news","label"=>"News","type"=>"repeatable","collapse"=>"true","fields"=>
          array(
            array("id"=>"new","label"=>"News Piece","type"=>"editor","collapse"=>"true")
          )
        )
      )
    ));

  function __construct() {
    add_action( 'init', array($this,'register') );
    add_filter( 'template_include', array($this,'template'), 99 );
    add_shortcode( $this->post_type["shortcode"], array($this,'output'));
    # Dequeue all styles for newsletters
    add_action( 'wp_print_styles', array($this,'remove_styles'), 20 );
    add_action( 'wp_enqueue_scripts' , array($this,'add_styles'));

    new HPA_Meta($this->metaboxes, $this->post_type);

    # Admin: editor styles
    add_filter( 'mce_buttons_2', array($this,'mce_buttons'));
    add_filter( 'tiny_mce_before_init', array($this,'mce_formats' ));
    add_action( 'admin_init', array($this,'mce_styles' ));
  }

  function register() {
    register_post_type(
      $this->post_type["type"],
      array(
        'public' => true,
        'label'=> $this->post_type["label"],'has_archive'=>$this->post_type["archive"]));
  }
  function template( $template ) {
    global $post;
    if ($post->post_type == $this->post_type["type"] && is_single()){
      $template = plugin_dir_path( __FILE__ ) . $this->post_type["template"];
    }
    return $template;}
  function remove_styles(){
    if(is_single() && get_post_type() == $this->post_type["type"]) {
      global $wp_styles;
      foreach($wp_styles->queue as $id=>$style){
        if("admin-bar" !== $style && "newsletter-style" !== $style) {
          unset($wp_styles->queue[$id]);
        }
      }
    }
  }
  function add_styles(){
    wp_enqueue_style( "newsletter-style", plugins_url('/styles/newsletter.css',__FILE__) , array(), '1.1', 'all');
    wp_enqueue_script( "way", plugins_url('/scripts/jquery.waypoints.min.js',__FILE__) , array(), '1.1', 'all');
  }
  function output(){
    global $post;
    $meta = get_post_meta($post->ID);
    $options = array("lightbox"=>"true","caption"=>"true","description"=>"true","mode"=>"background");
    $values = array();$sections = array(); $menu1 = $menu2 = $waypoints = "";
    foreach($this->metaboxes as $mb){
      if("head" !== $mb["id"]) {
        $label = $mb["label"] == "Main" ? $meta["head_title"][0] : $mb["label"];
        $id = $mb["id"];
        $sections[$id] = $label;
        $menu1 .= "<a class='menu-plus' id='link_{$id}' href='#{$id}'>{$label}</a>";
        $menu2 .= "<a class='waypoint' id='link_{$id}' href='#{$id}'>{$label}</a>";
      }
      foreach($mb["fields"] as $f){
        $id = $f["id"];
        if("image" == $f["type"]){$x = new Responsive_Item($meta[$id][0],$options);$values[$id] = $x->output();}
        else {$values[$id] = apply_filters("the_content",$meta[$id][0]);}
      }
    }
    $waypoints = "<script>
      jQuery(document).ready(function($){
        $('#" . implode(",#",array_keys($sections)) . "').each(function(){
          var me = $(this); var meid = me.attr('id');
          me.waypoint(function(){
            $('.waypoints a').removeClass('activepoint');
            $('#link_' + meid).addClass('activepoint');
          });
        });
      });
    </script>";

    $html  = "<header id='page-header' class='bg'>
      <nav class='wrapper waypoints'>
      <a class='icon icon-logo-healthpovertyaction' href='" . get_bloginfo('url') . "'></a>
      {$menu2}
      </nav>
      </header>";
    $html .= "<section id='head'>
      <div class='wrapper'>
        <div id='head-inner'>
          <div id='head_issue' class='bg_orange'>
            <div id='head_issue_logo' class='icon icon-logo-healthinaction'></div>
            <div id='head_issue_title'>
              <div>Health Poverty Action Newsletter</div>
              <div class='f_grey'>" . $post->post_title . "</div>
            </div>
          </div>
          <div id='head_bottom'>
            <div id='head_title_wrap' class='bg_white icon-border-green'>
              <div class='inner'>
                <div id='head_title_logo' class='icon icon-logo-focuson'></div>
                <h1 id='head_title'>
                  <span class='f_orange' id='head_title_pretext'>Country: </span><span>" . $values["head_title"] . "</span>
                </h1>
              </div>
            </div>
            <div id='head_plus' class='bg_grey'>
              <a id='head_plus_logo' class='icon icon-logo-healthpovertyaction' href='" . get_bloginfo("url") . "'></a>
              <div id='head_plus_menu'><div id='head_plus_plus'>PLUS</div>{$menu1}</div>
            </div>
          </div>
        </div>
        <div id='head_image'>" . $values["head_image"] . "</div>
      </div>
      </section>";
    $html .= "<div id='body' class='wrapper'>";
    $html .= "<section id='welcome'>
      <div id='head_side' class='bg_grey'>
        <div id='head_worker'>" . $values["head_worker"] . "</div>
        <div id='head_map'>" . $values["head_map"] . "</div>
      </div>
      <div id='head_welcome'>" . $values["head_welcome"] . "</div>
    </section>";
    $html .="<section id='main'>
      <div id='main_head'>
        <div id='main_picture'>" . $values["main_picture"] . "</div>
        <div id='main_summary'>" . $values["main_summary"] . "</div>
      </div>
      <div id='main_story'>" . $values["main_story"] . "</div>
    </section>";
    $html .="<section id='featured_health_worker' class='bg_grey'>
      <div id='fhw_header'>" . $values["fhw_header"] . "</div>
      <div id='fhw' class='clear'>
        <div id='fhw_image'>" . $values["fhw_image"] . "</div>
        <div id='fhw_story'>" . $values["fhw_story"] . "</div>
      </div>
      <div id='fhw_footer'>" . $values["fhw_footer"] . "</div>
    </section>";
    $html .="<section id='global_movement' class='bg_grey'>
      <h2 id='global_movement_title' class='bg_green'><span class='icon icon-iglobe'></span>The Global Movement for Health</h2><div  class='clear'></div>
      <div id='gm_main'>" . $values["gm_main"] . "</div>
    </section>";
    $html .="<section id='events_actions_news'>";
      $loop = array("events"=>"Events","actions"=>"Actions","news"=>"News");
      foreach($loop as $id=>$label){
        if (isset($meta[$id][0]) && "" != $meta[$id][0]){
          $html .= "<div id='{$id}'>";
          $html .= "<h2 class='block'>{$label}</h2>";
          foreach(unserialize($meta[$id][0]) as $index =>$value){
            foreach($value as $id => $e) {
              $fe = apply_filters('the_content',$e);
              $html .="<div class='item' id='{$id}_{$index}'>{$fe}</div>";
            }
          }
          $html .= "</div>";
        }
      }
      $html .= "</section>";
    $html .="<section id='foot'>
      <div id='legacy'>
        <h2>Leaving a legacy</h2>
    	  <p>As a supporter of Health Poverty Action, we know that you care passionately about improving the health of the world’s poorest people, and that you understand the need for long-term and community focused work to achieve this.</p>
        <p>It’s because of this that we hope you might consider leaving a gift in your Will, after your loved ones are taken care of, to ensure that this vital work can continue and that no-one is left behind.</p>
        <p><b>To find out more about leaving a legacy to Health Poverty Action, please contact Sarah on 020 7840 3766 or <a href='mailto:s.smith@healthpovertyaction.org&subject=legacy'>s.smith@healthpovertyaction.org</a></b></p>
      </div>
    </section>";
    $html .= "</div>";
    $html .= "<footer id='page-footer' class='bg_lgreen'>
      <p>Health Poverty Action works to strengthen poor and marginalised people in their struggle for health.</p>
      <ul>
      <li><span class='icon icon-iaddress'></span>31-33 Bondway, Vauxhall, London SW8 1SJ</li>
      <li><span class='icon icon-itel'></span>+44 20 7840 3777 </li>
      <li><span class='icon icon-iweb'></span><a href='http://healthpovertyaction.org'>healthpovertyaction.org</a></li>
      <li><span class='icon icon-iemail'></span><a href='mailto:fundraising@healthpovertyaction.org'>fundraising@healthpovertyaction.org</a></li>
      <li><span class='icon icon-ifacebook'></span><a href='https://www.facebook.com/HealthPovertyAction'>HealthPovertyAction</a></li>
      <li><span class='icon icon-itwitter'></span><a href='https://twitter.com/HealthPoverty'>@healthpoverty</a></li>
      </ul>
      <p>Registered charity no. 290535</p>
    </footer>";
    $html .= $waypoints;
    return $html;
  }

  # ADMIN
  function mce_buttons( $buttons ) { array_unshift( $buttons, 'styleselect' ); return $buttons; }
  function mce_formats( $init_array ) {
    // strangely does not accept p as block
    $style_formats = array(
      array('title' => 'Subtitle', 'block' => 'h2', 'classes' => '', 'wrapper' => true, ),
      array('title' => 'Pullquote', 'block' => 'div', 'classes' => 'pullquote', 'wrapper' => true, ),
    );
    $init_array['style_formats'] = json_encode( $style_formats );
    return $init_array;
  }
  function mce_styles() {add_editor_style( plugins_url( 'styles/editor-style.css', __FILE__ ) );}
}
class HPA_Newsletters0 {
  private $post_type = array(
      "type" => 'newsletter',
      "label" => 'Newsletters',
      "archive" => 'newsletters',
      "template" => "newsletter.php",
      "shortcode" => "newsletter");
  private $metaboxes = array(
    array("id"=>"head","label"=>"Head","type"=>"box","fields"=>
      array(
        array("id"=>"head_title","label"=>"Newsletter Title","type"=>"text"),
        array("id"=>"head_image","label"=>"Header Image","type"=>"image","before"=>"<div class='row'>"),
        array("id"=>"head_worker","label"=>"Featured Worker","type"=>"image"),
        array("id"=>"head_map","label"=>"Country Map","type"=>"image","after"=>"</div>"),
        array("id"=>"head_plus","label"=>"Plus text","type"=>"textarea"),
        array("id"=>"head_welcome","label"=>"Welcome text","type"=>"editor")
      )
    ),
    array("id"=>"main","label"=>"Main","type"=>"box","fields"=>
      array(
        array("id"=>"main_picture","label"=>"Header Image","type"=>"image","collapse"=>"true"),
        array("id"=>"main_summary","label"=>"Summary","type"=>"editor","collapse"=>"true"),
        array("id"=>"main_story","label"=>"Main Story","collapse"=>"true","type"=>"editor"),
      )
    ),
    array("id"=>"featured_health_worker","label"=>"Featured Health Worker","type"=>"box","fields"=>
      array(
        array("id"=>"fhw_image","label"=>"Header Image","type"=>"image","before"=>"<div class='row'>"),
        array("id"=>"fhw_header","label"=>"Header","type"=>"textarea"),
        array("id"=>"fhw_footer","label"=>"Footer","type"=>"textarea","after"=>"</div>"),
        array("id"=>"fhw_story","label"=>"Main Story","type"=>"editor","collapse"=>"true"),
      )
    ),
    array("id"=>"global_movement","label"=>"Global Movement for Health","type"=>"box","fields"=>
      array(
        array("id"=>"gm_main","label"=>"Main Story","type"=>"editor"),
      )
    ),
    array("id"=>"events_actions_news","label"=>"Events, Actions, News","type"=>"box","fields"=>
      array(
        array("id"=>"events","label"=>"Events","collapse"=>"true","type"=>"repeatable","fields"=>
          array(
            array("id"=>"event","label"=>"Event","collapse"=>"true","type"=>"editor")
          )
        ),
        array("id"=>"actions","label"=>"Actions","type"=>"repeatable","collapse"=>"true","fields"=>
          array(
            array("id"=>"action","label"=>"Action","type"=>"editor","collapse"=>"true")
          )
        ),
        array("id"=>"news","label"=>"News","type"=>"repeatable","collapse"=>"true","fields"=>
          array(
            array("id"=>"new","label"=>"News Piece","type"=>"editor","collapse"=>"true")
          )
        )
      )
    ));

  function __construct() {
    add_action( 'init', array($this,'register') );
    add_filter( 'template_include', array($this,'template'), 99 );
    add_shortcode( $this->post_type["shortcode"], array($this,'output'));
    # Dequeue all styles for newsletters
    add_action( 'wp_print_styles', array($this,'remove_styles'), 20 );
    add_action( 'wp_enqueue_scripts' , array($this,'add_styles'));

    new HPA_Meta($this->metaboxes, $this->post_type);

    # Admin: editor styles
    add_filter( 'mce_buttons_2', array($this,'mce_buttons'));
    add_filter( 'tiny_mce_before_init', array($this,'mce_formats' ));
    add_action( 'admin_init', array($this,'mce_styles' ));
  }

  function register() {
    register_post_type(
      $this->post_type["type"],
      array(
        'public' => true,
        'label'=> $this->post_type["label"],'has_archive'=>$this->post_type["archive"]));
  }
  function template( $template ) {
    global $post;
    if ($post->post_type == $this->post_type["type"] && is_single()){
      $template = plugin_dir_path( __FILE__ ) . $this->post_type["template"];
    }
    return $template;}
  function remove_styles(){
    if(is_single() && get_post_type() == $this->post_type["type"]) {
      global $wp_styles;
      foreach($wp_styles->queue as $id=>$style){
        if("admin-bar" !== $style && "newsletter-style" !== $style) {
          unset($wp_styles->queue[$id]);
        }
      }
    }
  }
  function add_styles(){
    wp_enqueue_style( "newsletter-style", plugins_url('/styles/newsletter0.css',__FILE__) , array(), '1.1', 'all');
  }
  function output(){
    global $post;
    $meta = get_post_meta($post->ID);
    $options = array("lightbox"=>"true","caption"=>"true","description"=>"true");
    $m = array();
    foreach($this->metaboxes as $mb){
      foreach($mb["fields"] as $f){
        $id = $f["id"];
        if("image" == $f["type"]){
          $x = new Responsive_Item($meta[$id][0],$options);
          $m[$id] = $x->output();
        }
        else {
          $m[$id] = apply_filters("the_content",$meta[$id][0]);
        }
      }
    }

    $html  ="<header id='head'>
      <div id='head_issue' class='pad bgorange'><span>" . $post->post_title . "</span></div>
      <div id='head_image'>" . $m["head_image"] . "</div>
      <h1 id='head_title'>" . $m["head_title"] . "</h1>
      <div id='head_plus'>" . $m["head_plus"] . "</div>
      <div id='head_side' class='clear'>
        <div id='head_worker'>" . $m["head_worker"] . "</div>
        <div id='head_map'>" . $m["head_map"] . "</div>
      </div>
      <div id='head_welcome'>" . $m["head_welcome"] . "</div>
    </header>";
    $html .="<article id='main'>
      <div id='main_head'>
        <div id='main_picture'>" . $m["main_picture"] . "</div>
        <div id='main_summary'>" . $m["main_summary"] . "</div>
      </div>
      <div id='main_story'>" . $m["main_story"] . "</div>
    </article>";
    $html .="<section id='featured_health_worker'>
      <div id='fhw_header'>" . $m["fhw_header"] . "</div>
      <div id='fhw' class='clear'>
        <div id='fhw_image'>" . $m["fhw_image"] . "</div>
        <div id='fhw_story'>" . $m["fhw_story"] . "</div>
      </div>
      <div id='fhw_footer'>" . $m["fhw_footer"] . "</div>
    </section>";
    $html .="<section id='global_movement'>
      <h2 id='global_movement_title'>The Global Movement for Health</h2><div  class='clear'></div>
      <div id='gm_main'>" . $m["gm_main"] . "</div>
    </section>";
    $html .="<section id='events_actions_news'>";
    $loop = array("events"=>"Events","actions"=>"Actions","news"=>"News");
    foreach($loop as $id=>$label){
      if (isset($meta[$id][0]) && "" != $meta[$id][0]){
        $html .= "<div id='{$id}'>";
        $html .= "<h2 class='block'>{$label}</h2>";
        foreach(unserialize($meta[$id][0]) as $index =>$value){
          foreach($value as $id => $e) {
            $fe = apply_filters('the_content',$e);
            $html .="<div class='item' id='{$id}_{$index}'>{$fe}</div>";
          }
        }
        $html .= "</div>";
      }
    }
    $html .= "</section>
    <footer>
      <div id='legacy'>
        <h2>Leaving a legacy</h2>
    	<p>As a supporter of Health Poverty Action, we know that you care passionately about improving the health of the world’s poorest people, and that you understand the need for long-term and community focused work to achieve this.</p>
        <p>It’s because of this that we hope you might consider leaving a gift in your Will, after your loved ones are taken care of, to ensure that this vital work can continue and that no-one is left behind.</p>
        <p><b>To find out more about leaving a legacy to Health Poverty Action, please
    contact Sarah on 020 7840 3766 or <a href='mailto:s.smith@healthpovertyaction.org&subject=legacy'>s.smith@healthpovertyaction.org</a></b></p>
      </div>
    </footer>";

    return $html;}

  # ADMIN
  function mce_buttons( $buttons ) { array_unshift( $buttons, 'styleselect' ); return $buttons; }
  function mce_formats( $init_array ) {
    // strangely does not accept p as block
    $style_formats = array(
      array('title' => 'Subtitle', 'block' => 'h2', 'classes' => '', 'wrapper' => true, ),
      array('title' => 'Pullquote', 'block' => 'div', 'classes' => 'pullquote', 'wrapper' => true, ),
    );
    $init_array['style_formats'] = json_encode( $style_formats );
    return $init_array;
  }
  function mce_styles() {add_editor_style( plugins_url( 'styles/editor-style.css', __FILE__ ) );}
}

class HPA_Meta {
  private $metaboxes, $post_type;
  function __construct($metaboxes,$post_type) {
    $this->metaboxes = $metaboxes;
    $this->post_type = $post_type;
    # Admin: print & save meta
    add_action( 'admin_menu', array($this,'meta_remove') );
    add_action( 'add_meta_boxes', array($this,'meta_register'), 51);
    add_action( 'wp_ajax_repeat', array($this,'ajax_repeat_field' ));
    add_action( 'save_post', array($this,'meta_save'));
    add_action( 'admin_footer',array($this,'scripts'));
    add_action( 'admin_enqueue_scripts',array($this,'admin_scripts'));
    # Admin: Styles
    add_action( 'admin_head', array($this,'admin_css') );
  }
  # Registering meta
  function meta_remove() {remove_post_type_support($this->post_type["type"], 'editor');}
  function meta_register() {
    add_meta_box( $this->post_type["type"], $this->post_type["label"],
      array($this,'meta_display'), $this->post_type["type"] );
  }
  # Printing meta
  public function ajax_repeat_field() {
    global $post;
    # Find Requested field
    $field = array();
    foreach($this->metaboxes as $mb){
      foreach($mb["fields"] as $f){
        if($f["id"]==$_POST["field_id"]) {$field = $f;}
      }
    }

    foreach($field["fields"] as $rf){
      $id = $field["id"] . "_" . $_POST["count"] . "_" . $rf["id"];
      $this->field_print($rf,null,array($field["id"],$_POST["count"]));
      if("editor" == $rf["type"]) {
        echo "<script>";
        #echo "tinymce.execCommand( 'mceAddEditor', true, '{$id}' );";

        echo "My_New_Global_Settings =  tinyMCEPreInit.mceInit; ";
        echo "My_New_Global_Settings.selector = '{$id}'; ";
        echo "tinymce.init(My_New_Global_Settings); ";
        echo "tinyMCE.execCommand('mceAddEditor', false, '{$id}'); ";
        echo "quicktags({id : '{$id}'});";
        echo "</script>";
      }
    }

    wp_die();
  }
  public function meta_display($post,$args){
    wp_nonce_field( plugin_basename( __FILE__ ), $post->ID . '_noncename' );
    if($this->post_type["type"] == $args["id"]){
      echo "<div id='hpa-tabs'><ul class='nav-tab-wrapper'>";
      foreach($this->metaboxes as $mb){
        echo "<li><a class='nav-tab' href='#" . $mb['id'] . "'>{$mb['label']}</a></li>";
      }
      echo "</ul>";
      foreach($this->metaboxes as $mb){
        echo "<div class='nada-admin-div' id='" . $mb['id'] . "'>";
        foreach($mb["fields"] as $f){
          $this->field_print($f);
        }
        echo "</div>";
      }
      echo "</div>";
    }
  }
  public function field_print($f,$meta = null,$rep = null) {
    $type = $f["type"];$id = $name = $f["id"];$label = $f["label"];
    $meta = ! (null == $meta) ? $meta : get_post_meta( get_the_ID(), $f["id"], true);
    $cllps = isset($f["collapse"]);
    if ( $rep ) {
      $name = "{$rep[0]}[{$rep[1]}][{$id}]";
      $id = "{$rep[0]}_{$rep[1]}_{$id}";
    }

    $before = isset($f["before"]) ? $f["before"] : "";
    $btag = (NULL !== $rep) ? "li" : "div";
    $before .= "<{$btag} id='{$id}-container' data-id='{$id}' class='postbox " . ($cllps ? "" : "transp") . "'>";
    if($cllps){
      $before .= "<button type='button' class='handlediv button-link' aria-expanded='true'><span class='toggle-indicator' aria-hidden='true'></span></button>";
      if("repeatable" == $type) {$before .= "<button type='button' class='button-link nada-repeatables meta_box_repeatable_add' data-count='" . count($meta) . "'>+</button>";}
      if(NULL !== $rep)         {$before .= "<span class='sort hndle'></span>";}
      if(NULL !== $rep )        {$before .= "<button type='button' class='button-link nada-repeatables meta_box_repeatable_remove'>-</button>";}
      $before .= "<h3><span>{$label}</span></h3>";
      $tag = ("repeatable" == $type ? "ul" : "div");
      $before .= "<{$tag} id='{$id}-sortable' class='inside " . ("repeatable" == $type ? "repeatdrag" : "") . "'>";
      $after = "</{$tag}>";
    }
    else {$before .= "<label for='{$id}'>{$label}</label>";}
    $after .= "</{$btag}>";
    $after .= isset($f["after"]) ? $f["after"] : "";

    echo $before;
    switch($type){
      case "group":
        if(isset($f["columns"])){ $a = "<table class='nada-admin-table'><tr>"; $b = "<td>"; $c= "</td>"; $d= "</tr></table>"; }
        echo $a; foreach($f["fields"] as $fld) {echo $b; $this->field_print($fld); echo $c;} echo $d;
        break;
      case "repeatable":
        if ( $meta == '' || $meta == array() ) {
          $keys = wp_list_pluck( $f["fields"], 'id' );
          $meta = array ( array_fill_keys( $keys, null ) );
        }
        elseif( ! is_array($meta) ) {
          $meta = array($meta);
        }
        $meta = array_values( $meta );
        $i = 0;
        foreach($meta as $m){
          foreach($f["fields"] as $fld) {
            $mt = isset($meta[$i][$fld['id']]) ? $meta[$i][$fld['id']] : null;
            $this->field_print($fld,$mt,array( $id, $i ) );
          }
          $i++;
        }
        break;
      case "text":$id = esc_attr($id); $val = esc_attr($meta);echo "<input type='{$type}' name='{$name}' id='{$id}' value='{$val}' class='regular-text nada' size='30' />";break;
      case 'textarea': echo "<textarea class='nada' name=" . esc_attr($name) . " id=" . esc_attr($id) . " cols='60' rows='4'>" . esc_textarea( $meta ) . "</textarea>"; break;
      case 'editor': echo wp_editor( $meta, $id); if(isset($_POST["is_ajax"])) {print_footer_scripts();} break;
      case 'image':
        echo "<div class='meta_box_image'>";
        if ( $meta ) {
          $image = wp_get_attachment_image_src( intval( $meta ), 'thumbnail' );
          $image = $image[0];
        }
        echo  '<input name="' . esc_attr( $id ) . '" type="hidden" class="meta_box_upload_image" value="' . intval( $meta ) . '" />
              <img style="width:150px;height:150px;" src="' . esc_attr( $image ) . '" class="meta_box_preview_image" alt="" /><br />
                <a href="#" class="meta_box_upload_image_button button" rel="' . get_the_ID() . '">Choose Image</a>
                <a href="#" class="meta_box_clear_image_button button">Remove Image</a></div>';
        break;
    }
    echo $after;
  }
  public function scripts(){
    ?>
      <script>
        jQuery(document).ready(function($){
          //$("#mytabs .hidden").removeClass('hidden');
          $("#hpa-tabs").tabs();

          var imageFrame;
          $('.meta_box_upload_image_button').click(function(event) {
            event.preventDefault();
            var options, attachment;
            $self = $(event.target);
            $div = $self.closest('div.meta_box_image');

            // if the frame already exists, open it
            if ( imageFrame ) {imageFrame.open(); return; }

            // set our settings
            imageFrame = wp.media({title:'Choose Image',multiple:false,library:{type:'image'},button:{text:'Use This Image'}});

            // set up our select handler
            imageFrame.on( 'select', function() {
              selection = imageFrame.state().get('selection');

              if ( ! selection )
              return;

              // loop through the selected files
              selection.each( function( attachment ) {
                var src = attachment.attributes.sizes.thumbnail.url;
                var id = attachment.id;

                $div.find('.meta_box_preview_image').attr('src', src);
                $div.find('.meta_box_upload_image').val(id);
              } );
            });

            // open the frame
            imageFrame.open();
          });
          $('.meta_box_clear_image_button').click(function() {
            var defaultImage = $(this).siblings('.meta_box_default_image').text();
            $(this).siblings('.meta_box_upload_image').val('');
            $(this).siblings('.meta_box_preview_image').attr('src', defaultImage);
            return false;
          });
          $('.meta_box_repeatable_add').click(function() {
              var data = {'action': 'repeat','is_ajax':true,'count': $(this).data("count"),'field_id': $(this).parent().data('id')  };
              var where = $(this).parent().find('.inside > .postbox').last();

              jQuery.post(ajaxurl, data, function(response) {where.after($(response));});
              $(this).data("count",$(this).data("count") + 1);
              return false;
          });
          $('.meta_box_repeatable_remove').click(function(){jQuery(this).parent().remove();return false;});

          $( '.repeatdrag' ).sortable({placeholder:'placeholder', handle: '.sort.hndle'});
          //$( '.repeatdrag' ).disableSelection();
        });
      </script>
    <?php
  }
  # Styling meta
  function admin_css() {
    ?>
    <style>
      .placeholder {border: dotted 4px grey; height:35px; background:rgba(0,0,0,0.3);}
      .placeholder::before{content:"Drop here";}
      #newsletter.postbox, .postbox.transp {border: 0 none;background: transparent ;box-shadow: none;}
      /* Hide meta Header */
      #newsletter.postbox > .inside {display: block !important;margin: -10px !important;}
      #newsletter.postbox > .hndle, #newsletter.postbox > .handlediv {display: none;}
      /* GROUPED FIELDS */
      .row {display:table;width:100%;}
      .row >div {display:table-cell;}

      /* TABS: opied a mixtures from ACF & JQUERY UI TABS */
      #newsletter .nav-tab-wrapper {
        border-top: 0 none;padding-left: 12px; border-color: #ccc;
        border-bottom: #DFDFDF solid 1px;
      }
      #newsletter .nav-tab-wrapper li a.nav-tab {
        padding: 6px 10px;display: block;color: #555555;font-size: 14px;font-weight: 700;
        line-height: 24px;border: #ccc solid 1px;border-bottom: 0 none;
        text-decoration: none;background: #F1F1F1;border-radius: 3px 3px 0 0;transition: none;
      }
      #newsletter .nav-tab-wrapper li.ui-tabs-active a.nav-tab,
      #newsletter .nav-tab-wrapper li.ui-state-active a.nav-tab {
        background: white;
        border-color: #ccc;
      }
      .nada-admin-div {}
      .nada-admin-div label {display:block;padding:7px;font-weight:bold;margin-top:5px;margin-bottom:5px;}
      .nada-admin-div h3 {background:rgb(240,240,240);}
      .nada-admin-div .nada {width:100%;}
      .nada-admin-div textarea.nada {height:150px;}
      .nada-repeatables {float:right;width:36px;height:36px;font-size:16px;font-weight:bold;}
      .sort.hndle {float:left;    cursor: pointer;padding:0 5px;font-size:20px;}
      .sort.hndle:after {content:"\f333";font-family:Dashicons;text-align:center;color#999;display:block;float:left;height:100%;line-height:40px;width:100%;}
      .nada-repeatables.meta_box_handle {


    </style>
    <?php
  }
  function admin_scripts(){wp_enqueue_script('jquery-ui-tabs');}
  # Saving meta
  public function meta_save( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {return;}
    if ( !isset( $_POST["{$post_id}_noncename"] ) ) {return;}
    if ( !wp_verify_nonce( $_POST["{$post_id}_noncename"], plugin_basename( __FILE__ ) ) ) {return;}
    if ( !current_user_can( 'edit_post', $post_id ) ) {return;}
    $this->save($post_id, $this->metaboxes);
  }
  function save($pid,$fields){
    foreach($fields as $f) {

      if( isset($f["fields"]) && ("box" == $f["type"] || "group" == $f["type"])) {
        $this->save($pid,$f["fields"]);
      }
      elseif("repeatable" == $f["type"]){ // repeater fields with editor will not come as array
        $fdata = isset($_POST[$f["id"]]) ? $_POST[$f["id"]] : array();
        foreach($_POST as $id => $val){
          if (0 === strpos($id, $f["id"] . "_")) {
            $id_arr = explode("_",$id);
            $fdata[$id_arr[1]][$id_arr[2]] = $val;
          }
        }
        remove_action( 'save_post', array($this,'meta_save' ));
        update_post_meta($pid, $f["id"], $fdata);
        add_action( 'save_post', array($this,'meta_save'));
      }
      elseif ( isset($_POST[$f["id"]]) ) {
        remove_action( 'save_post', array($this,'meta_save' ));
        update_post_meta($pid, $f["id"], $_POST[$f["id"]]);
        add_action( 'save_post', array($this,'meta_save'));
      }
    }
  }

}
?>
