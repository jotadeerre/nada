<?php // Common Classes
if (!defined('ABSPATH')) die();

class Responsive_Item {

  private static $wp_sizes = array(), $in_use = false;
  private $id,$image,$post,$meta,$alt,$caption,$description,$aspect_ratio,$pic_sizes = array();
  private $options = array(
    "mode"=>"image", # image or background
    "link"=>"false",
    "share"=>"false",
    "caption"=>"false",
    "desription"=>"false",
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

    $out = "<div id='wrapper_{$this->id}' data-id='{$this->id}' class='{$wrap_item_classes}'>";
    $out .= "<{$tag} {$wrap_image_classes} {$link} {$rel} {$ratio}>";
    if($this->is_video()) {
      $out .= "<iframe src='" . $this->video_link() . "' frameborder='0' webkitallowfullscreen='' mozallowfullscreen='' allowfullscreen=''></iframe>";
    }
    else{
      $out .= "<noscript data-alt={$alt}' data-sizes='" . json_encode($this->pic_sizes) . "'>";
      if("background" == $this->options["mode"]){
        $out .= "<div class='" . $this->classes["bgimage"] . "' style='background-image:url({$src});background-position:center;-webkit-background-size:cover;-moz-background-size:cover;-o-background-size:cover;background-size:cover;'>";
      }
      $out .= "<img class='" . $this->classes["image"] . "' src='{$src}' title='{$alt}' alt='{$alt}' />";
      if("background" == $this->options["mode"]){
        $out .= "</div>";
      }
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
