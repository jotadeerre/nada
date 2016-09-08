<?php
class Responsive_Item {
  private static $wp_sizes = array(), $in_use = false;
  private $id,$image,$post,$meta,$alt,$caption,$description,$aspect_ratio,$pic_sizes = array();
  private $options = array(
    "mode"=>"image", # image or background
    "link"=>"false",
    "share"=>"false",
    "caption"=>"false",
    "description"=>"false",
    "lightbox"=>"false");
  private $classes = array(
    "wrap_item"=>"gallery-item",
    "wrap_video"=>"video",
    "wrap_image"=>"imageholder",
    "caption"=>"gallery-caption",
    "description"=>"gallery-description",
    "image"=>"gallery-image",
    "bgimage"=>"gallery-image-bg");
  public function __construct($id,$options = array()) {
    # SIZE VARS
    if ( empty(self::$wp_sizes) ) {self::$wp_sizes = $this->get_sizes();}
    foreach (self::$wp_sizes as $name => $width) { $this->pic_sizes[$name] = wp_get_attachment_image_src($id,$name); }

    # SCRIPTS
    if (false === self::$in_use) {self::$in_use = true; add_action( 'wp_footer' , array($this,'script')); }

    # OPTIONS
    $this->options = array_merge($this->options,$options);

    # OBJECT
    $this->id = $id;
    $this->post = get_post($id);
    if("attachment" == $this->post->post_type) {
      $this->image = $this->post;
    }
    elseif(has_post_thumbnail($id)){
      $this->image = get_post(get_post_thumbnail($id));
    }
    else { $this->image = NULL;}

    # INFO
    $this->meta = get_post_meta($this->image->ID);
    $full = $this->pic_sizes["full"];
    $this->aspect_ratio = (! isset($full[1])) ? 1 : ($full[2]/$full[1]);

    if($this->is_video()){
      $this->options["lightbox"] = "false";
      $this->options["link"] = "false";
      $this->aspect_ratio = 0.57;
    }
  }
  private function get_sizes(){
      $sizes = array();
      global $_wp_additional_image_sizes;
      foreach ( get_intermediate_image_sizes() as $_size ) {
        if ( in_array( $_size, array('thumbnail', 'medium', 'medium_large', 'large') ) ) {
          if ( false === (bool) get_option( "{$_size}_crop" ) ) { $sizes[ $_size ] = get_option( "{$_size}_size_w" ); }
        }
        elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
          if ( false === $_wp_additional_image_sizes[ $_size ]['crop'] ) {
             $sizes[ $_size ] = $_wp_additional_image_sizes[ $_size ]['width'];
           }
        }
      }
      $sizes["full"] = "10000";
      asort($sizes);
      return $sizes;
  }

  # INFO
  public function post() {return $this->post;}
  public function post_id(){return $this->post->ID;}
  public function is_video() {return (bool)(NULL !== $this->video_link());}
  public function is_postrait() {return (bool)($this->orientation() == "portrait");}
  public function is_landscape() {return (bool)($this->orientation() == "landscape");}
  public function orientation() {return $this->aspect_ratio >=1 ? "portrait" : "landscape";}
  public function ratio() {return $this->aspect_ratio;}
  public function video_link() {return isset($this->meta["_video_link"][0]) ? $this->meta["_video_link"][0] : NULL ;}
  public function picture_url($size = "full") { return $this->pic_sizes[$size][0];}

  # FILLERS
  public function add_class($value) {$this->calsses["wrap_item"] .= " {$value}";}
  public function set_text($text,$val) {$this->$text = $val;}
  #public function add_attr(){}
  public function output(){
    $pre = "nada-filter_responsiveitem";
    $url = get_permalink($this->id);
    $src = $this->pic_sizes["full"][0];
    $is_bg = "background" == $this->options["mode"];

    $alt = (NULL == $this->alt) ? get_post_meta($this->image->ID,"_wp_attachment_image_alt",true) : $this->alt;
    $alt = apply_filters($pre . "alt",$alt, $this);

    $wrap_item_classes = array(
      $this->classes["wrap_item"],
      $this->orientation(),
      $this->is_video() ? $this->classes["wrap_video"] : ""
      );
    $wrap_item_classes = apply_filters($pre . "class",$wrap_item_classes, $this);
    $wrap_item_classes = implode(" ",$wrap_item_classes);

    $tag = $this->options["link"] == "false" ? "span" : "a";
    $rel = $this->options["lightbox"] == "lightbox" ? "rel='lightbox'" : "";
    $link = $this->options["link"] == "false" ? "" : "href='{$url}'";
    $ratio = "data-ratio='" . ($this->aspect_ratio * 100) . "%'";
    $wrap_image_classes = "class='" . $this->classes["wrap_image"] . "'";
    $stl = ! $is_bg? "" : "style='height:100%'";

    $out = "<div id='wrapper_{$this->id}' data-id='{$this->id}' class='{$wrap_item_classes}' {$stl}>";
    $out .= "<{$tag} {$wrap_image_classes} {$link} {$rel} {$ratio}  {$stl}>";
    if($this->is_video()) {
      $out .= "<iframe src='" . $this->video_link() . "' frameborder='0' webkitallowfullscreen='' mozallowfullscreen='' allowfullscreen=''></iframe>";
    }
    else{
      $out .= "<noscript data-alt={$alt}' data-sizes='" . json_encode($this->pic_sizes) . "'>";
      if($is_bg){$out .= "<div class='" . $this->classes["bgimage"] . "' style='height:100%;background-image:url({$src});background-position:center;-webkit-background-size:cover;-moz-background-size:cover;-o-background-size:cover;background-size:cover;'>";      }
      $out .= "<img class='" . $this->classes["image"] . "' src='{$src}' title='{$alt}' alt='{$alt}' />";
      if($is_bg){$out .= "</div>";}
      $out .= "</noscript>";
    }
    if($this->options["caption"] !== "false") {
      $caption = (NULL == $this->caption) ? $this->post->post_excerpt : $this->caption;
      $caption = apply_filters('the_content',$caption);
      $caption = apply_filters($pre . "caption",$caption, $this);
      if("" !== $caption) {$out .= "<div class='" . $this->classes["caption"] . "'>{$caption}</div>";}
    }
    if($this->options["description"] !== "false") {
      $description = (NULL == $this->description) ? $this->post->post_content : $this->description;
      $description = apply_filters('the_content',$description);
      $description = apply_filters($pre . "description",$description, $this);
      if("" !== $description) {$out .= "<div class='" . $this->classes["description"] . "'>{$description}</div>";}
    }
    if($this->options["share"] !== "false") {
      if(shortcode_exists('share')) {
        $out .= do_shortcode("[share url='{$url}' title='{$alt}' image='{$src}']");
      }
    }
    $out .= "</{$tag}></div>";
    return $out;
  }
  public function script() {
    ?>
    <script>
      var sizes = <?= json_encode(self::$wp_sizes) ?>;
      jQuery(document)
      //.on("jd:imagesloaded",function($){lightbox($);})
      .ready(function($){
        image_replacer($);
      });
      function image_replacer($) {
        // preserve ratio before loading image
        var imgclass = "<?= $this->classes["wrap_image"]; ?>";
        var imgs = $("." + imgclass + "[data-ratio]"); var count = imgs.length; var i = 0;
        imgs.each(function(){
          $(this).css("padding-bottom",$(this).data("ratio"));
          i++; if ( i == count ) {$(document).trigger("jd:imagesloaded");}
        });

        // Replace image
        $('noscript[data-sizes]').each(function(){
           var me = $(this); var a = me.parent(); var div = a.parent(); a.addClass('wait');

           var  target_size_for_this_image = "full";
           $.each(sizes,function(i,x){
              if ( (parseInt(x) / div.width()) >= 1 ) {
                target_size_for_this_image = i;
                return false;
              }
           });

           var opts = me.data("sizes");
           var oldsource = opts["full"][0];
           var source = opts[target_size_for_this_image][0];

           // Load Image
           var img = new Image(); img.src = source; img.onload = function() {
             var newimg = me.html().replace(oldsource,source).replace('&gt;',">").replace('&lt;',"<");
             $(newimg).insertAfter(me).fadeIn(200); a.removeClass("wait");
           }
        });

        // restore original aspect
        imgs.each(function(){
          $(this).css("padding-bottom","");
        });

      }
      function lightbox($) {
        <?php if("fancybox" == $this->options["lightbox"]) { ?>
          var lb = $("[rel^=lightbox]");
          if(lb.length){ lb.fancybox({helpers:{overlay : {css : {'background' : 'rgba(0, 0, 0, 0.95)'}}, title: {type:'over'}}});}
        <?php } else { ?>
          $('[rel*=lightbox]').on('click',function(){
            if (! $("#jdbox").length ) {
            var overlay = $("<div id='jdoverlay'></div>");
            var bttns = "<div class='icon icon-close' id='jd-lb-close' style='position:absolute;right:0;top:0;'></div>" +
                  "<div class='icon icon-prev' id='jd-lb-prev' style='position:absolute;left:0;top:50%;'></div>" +
                  "<div class='icon icon-next' id='jd-lb-next' style='position:absolute;right:0;top:50%;'></div>";
            var lb = $("<div id='jdbox'>" + bttns + "<div class='jdbox'><img class='wait' /></div></div>");

            overlay.append(lb);
            $("#wbody").append(overlay);
            $("body").addClass("frozen");
            $("#jd-lb-close").on("click",function(){ $("#jdoverlay").remove(); $("body").removeClass("frozen"); });
            $(document).keydown(function(e){
                if (e.keyCode == 37) { $("#jd-lb-prev").first().trigger( "click" ); return false; }
                if (e.keyCode == 39) { $("#jd-lb-next").first().trigger( "click" ); return false; }
            });
          }
          var lbid = $(this).parent().attr("data-id");
          jdslide(lbid);
          var prev, next;
          function jdslide(id){
            var item = $(".gallery-item[data-id=" + id + "]");
            var lbimg = item.find("noscript").data(jd.target_size);
            var lbshr = item.find(".shares").clone(true);

            $(".jdbox img").attr("src",lbimg);
            $(".jdbox").attr("style","background-image:url(" + lbimg + ");");
            $(".jdbox .shares").remove();
            $(".jdbox").append(lbshr);
            //console.log("data Loaded");
            var i = item.index(),
                items = item.parent().find(".gallery-item"),
                c = items.length - 1;
            var iprev = (i == 0 ? c : i - 1 ),
                inext = (i == c ? 0 : i + 1 );
            var prev = items.eq(iprev).attr("data-id"),
              next = items.eq(inext).attr("data-id");

            //console.log("Prev Next Calc");
            $("#jd-lb-prev").off("click").on("click",function(){jdslide(prev);});
            $("#jd-lb-next").off("click").on("click",function(){jdslide(next);});
            $("#jdoverlay").off("swiperight").on("swiperight",function(){jdslide(prev);});
            $("#jdoverlay").off("swipeleft").on("swipeleft",function(){jdslide(next);});
          }

          return false;
          });
          <?php } ?>
      }
    </script>
    <?php
  }
}
class Gallery {
   private static $instance = 0; protected static $settings; protected $atts;
   function __construct() {
     if ( self::$instance == 0 ) {
    	 $instance = 1;
   	   Script::register($this);
       add_filter('body_class', array($this,'body_classes'));
       remove_shortcode('gallery', 'gallery_shortcode');
       add_filter( 'admin_print_footer_scripts', array($this,'gallery_options'));
       #add_filter( 'print_media_templates', array($this,'gallery_options'));
       add_shortcode('gallery', array($this,'output'));
     }
   }
   function gallery_options(){
     #global $pagenow;
     #if ($pagenow == "upload.php" ) {
       ?>
       <script type="text/html" id="tmpl-my-custom-gallery-setting">
         <?php
         if (isset(self::$settings)) {
           foreach(self::$settings as $field){
             $text = "<span>{$field->label}</span>";
             $input = $field->display();
             $output = "checkbox" == $field->type ? $input . $text : $text . $input;
             echo "<label class='setting'>{$output}</label>";
           }
         }
         ?>
       </script>
       <script type="text/javascript">
         // Inject media gallery options
         if (wp.media.view.Settings.Gallery) {
           _.extend(wp.media.gallery.defaults, {
             <?php
             if (isset(self::$settings)) {
               foreach(self::$settings as $field){
                 if(isset($field->attributes["default"])) {
                    echo "{$field->field}: '" . $field->attributes["default"] . "',";
                 }
               }
             }
             ?>
           });
           wp.media.view.Settings.Gallery = wp.media.view.Settings.Gallery.extend({
             template: function(view){
               return wp.media.template('my-custom-gallery-setting')(view);
             }
           });
         }

       </script>
       <?php
      #}
   }
   function body_classes($classes) {
     global $post;
     $dat = array(); preg_match("/\[gallery(.*?)\]/", $post->post_content, $dat);
     if(!empty($dat) || !empty($pro)){ $classes[] = 'hasgallery'; }
     return $classes;
   }
   function output($atts){
     // ATTRIBUTES
     global $post; self::$instance++;
     $atts = shortcode_atts( array('style'=>'default','effect'=>'fade','type'=>'attachment','classes'=>'',
 								  'show_share'=>true,'show_text'=>false,'show_lightbox'=>true,
 								  'id'=>$post ? $post->ID : 0,'ids'=>''), $atts, 'gallery' );

     $bshare = filter_var($atts["show_share"], FILTER_VALIDATE_BOOLEAN);
     $btext = filter_var($atts["show_text"], FILTER_VALIDATE_BOOLEAN);
     $bbox = filter_var($atts["show_lightbox"], FILTER_VALIDATE_BOOLEAN);

     // TYPE, STYLE, CLASSES
     $type = $atts["type"];
     $style = $atts["style"];
     $classes = array_merge(array("gallery",$style),explode(" ",$atts["classes"]));
     if ( startsWith($style,"columns-") ) { $classes[] = "columns"; $style = "columns"; }
     if ( $atts["show_text"] !== "false" ) { $classes[] = "hascaption"; }
     $this->atts = $atts;
     $classa = "carousel-animated";
     $isslider = "carousel" == $style;
     if ($isslider) { $classes[] = $classa;}

     // ATTACHMENTS
     $attachments;
     $args = array('posts_per_page'=>-1,'post_type'=>$type,'post_status'=>'inherit');
     if ( $atts["ids"] !== "" ) {
         $args["orderby"] = "post__in";
         $args["post__in"] = explode(",",$atts["ids"]);
     }
     else { $args["post_parent"] = $atts["id"]; }
     $attachments = get_posts($args);

     if(!empty($attachments)){
       $images = array();
       foreach($attachments as $a){
         $options = array("mode"=>"image", # image or background
         "link"=>"true",
         "share"=>$bshare,
         "caption"=>$text,
         "description"=>"false",
         "lightbox"=>$bbox);
         $gitem = new Responsive_Item($a->ID,$options);
         $images[] = apply_filters('nada-filter_galleryitem',$gitem);
       }
       $images = apply_filters('nada-filter_galleryitems',$images);

       $classes= apply_filters('nada-filter_galleryclasses',$classes,$atts);
       $gclass = implode(" ",$classes);
       $html = "<div id='gallery-" . self::$instance . "' class='{$gclass}'>";
       if($isslider){
          $animduration = 3; $animfade = 0.3;
          $cssanim = true; $jsanim = true;
          $effect = $atts["effect"];
          $num = count($attachments);
          $classb = "carousel-js";
          $sel_gallery = "#gallery-" . self::$instance;
          $sel_item = ".gallery-item"; $sel_img = ".gallery-image"; $sel_p = ".gallery-image-caption";

          $csss = $this->slider_style($effect,$sel_gallery,$sel_item,$sel_img,$sel_p);
          $animcss = ($num>1 && $cssanim) ? $this->slider_anim($num,$effect,$animfade,$animduration,$sel_gallery,$sel_item,$classa) : "";
          $animjs = ($num>1 && $jsanim) ? $this->slider_animjs($effect,$animfade,$animduration,$sel_gallery,$classa,$classb) : "";

          $html .= "<script>{$animjs}</script><style>{$csss}{$animcss}</style>";
       }
       foreach($images as $gitem){ $html .= $gitem->output(); }
       $html .= "</div>";
     }
     return apply_filters('nada-filter_gallery',$html,$images,$atts);
   }
   function script_init() { ?> $(document).on("jd:imagesloaded",this.gallery); <?php }
   function script_gallery() {  }
   private function slider_style($effect,$g,$gi,$gii,$git){
     $xss = ($effect == "slide" ? "position:relative;" : "position:absolute;top:0;left:0;" );
     $css = "
       {$g} {margin:auto;width:100%;height:100%;overflow:hidden;white-space:nowrap;font-size:0;position:relative;}
       {$g} {$gi} {width:100%;height:100%;" . $xss . "overflow:hidden;display:inline-block;*display:inline;zoom:1;font-size:16px;}
       {$g} {$gi} {$gii} {height:auto; width:100%;display:block;  display:none;}
       {$g} {$gi} {$git} {position:absolute; text-align:left;bottom:10px; left:10px;background:rgb(0,0,0);padding:5px 10px;}";
     return $css;
   }
   private function slider_anim($num,$effect,$animfade,$animduration,$g,$gi,$classa){
    $sel = "{g}.{$classa} {$gi}";
 	  $t = $num * $animduration;
 	  $prefixes = array("-webkit-","-moz-","-o-","");  $keyframes = "";	  $animation = "";

 	  if ($effect == "slide") {
 		  $animation0 = "{$sel}:first-child { ";
 		  $animation1 = "animation: {$effect} {$t}s 0s infinite; ";
 		  $animation2 = "}";
 		  foreach($prefixes as $pf){ $animation = $pf . $animation1;  }
 		  $animation = $animation0 . $animation . $animation2;

 		  $keyframes0 = "keyframes {$effect} {";
 		  for($i=0;$i<=$num;$i++){
 			  $p = 100/$t; $s = $i*$animduration;
 			  if($i != 0) {$keyframes0 .= " " . ($p * ($s-$animfade)) . "% {margin-left:-" . (($i-1)*100) . "%;} "; }
 			  if($i != $num) {$keyframes0 .= " " . ($p * $s) . "% {margin-left:-" . ($i*100) . "%;} "; }
 		  }
 		  $keyframes0 .=  " 100% {margin-left:0;} \n";
 		  foreach($prefixes as $pf){ $keyframes .= " @{$pf}{$keyframes0} "; }
 	  }
    else {
 		  $animation = "{$sel}:not(:first-child) {opacity:0;}";
 		  for($i=1;$i<=$num;$i++){
 			$p = 100/$t; $s = ($i-1)*$animduration;
 			$animation .= "{$sel}:nth-child({$i}) {animation: {$effect} {$t}s " . (($i-1) * $animduration) . "s infinite }";
 		  }

 		  $dur = 100/$num;
 		  $fad = $dur * $animfade;

 		  $keyframes0  = "keyframes {$effect} {";
 		  $keyframes0 .= "0% {opacity:1;} ";
 		  $keyframes0 .= ($dur-$fad) . "% {opacity:1;} ";
 		  $keyframes0 .= $dur . "% {opacity:0;} ";
 		  $keyframes0 .= (100 - $fad) . "% {opacity:0;} ";
 		  $keyframes0 .= "100% {opacity:1;}} ";

 		  foreach($prefixes as $pf){ $keyframes .= " @{$pf}{$keyframes0} "; }
 		}
    $nojs = ".js {$g} *  {-webkit-transition: none !important;-moz-transition: none !important;-ms-transition: none !important;-o-transition: none !important;transition: none !important;}";
 	  return $nojs . $animation . $keyframes;
   }
   private function slider_animjs($effect,$animfade,$animduration,$g,$classa,$classb){
      $js = "\n jQuery(document).ready(function(){\n\t jQuery('{$g}.{$classa}').removeClass('{$classa}').addClass('{$classb}');";
   	  $js .= ($effect == "slide" ? "" : "\n\t\t jQuery('{$g} > div:gt(0)').hide();");
   	  $js .= "\n\t\t setInterval(function(){";
   	  $fade = $animduration * $animfade * 1000;
   	  if ($effect == "slide") {
   		$js .= "\n\t\t\t jQuery('{$g} > div:first').animate({marginLeft:'-100%'}," . $fade . ", function(){
   					jQuery(this).appendTo('{$g}').animate({marginLeft:'0'},0);
   				});";
   	  } else { $js .= "\n\t\t\t jQuery('{$g} > div:first').fadeOut(" . $fade . ").next().fadeIn(" . $fade . ").end().appendTo('{$g}');"; }
   	  $js .= "}, " . $animduration * 1000 . ")";
   	  $js .=  "});";
      return $js;
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
    $item->set_text("alt","{$this->post->post_title} by Justine Roland-Cal - {$item->post()->post_title}");

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


?>