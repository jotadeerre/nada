<?php // Common Classes
if (!defined('ABSPATH')) die();

class Field {
  private static $count = 0;
  public $id, $field, $label, $type, $action,$what, $where,$whatelse,$attributes;
  private  $listcallback, $list;
  private function __construct() {}

  # GET really just stores generic values but BUILD generates actual field instances
  public static function get($field,$label=null,$type=null,$attributes=null){
    self::$count++;
    $me = new self();
    $me->field = $field;
    $me->ofield = $field;
    $me->label = (null==$label) ? ucwords(str_replace("_"," ",$field)) : $label;
    $me->id    = "{$field}_" . self::$count;
    $me->type  = (null==$type) ? "post" : $type;
    $me->attributes = $attributes;
    # PARSE ATTRIBUTES
    if ( null !== $attributes) {
      #only callback provided
      if ( isset($attributes[0]) && is_object($attributes[0])) { $me->listcallback = $attributes;}
      else {
        if (isset($attributes["callback"])) {$me->listcallback = $attributes["callback"]; unset($attributes["callback"]);}
        if (isset($attributes["list"])) { $me->list = $attributes["list"]; unset($attributes["list"]);}
        # Other accepted attributes: type, class, default
        $me->attributes = $attributes;
      }
    }
    return $me;
  }
  public static function build($fields,$action,$what,$where) {
    $arr = array();
    if(!is_array($fields)) {$fields = array($fields);}

    foreach($fields as $f){
      if (is_string($f)) { $f = self::get($f);}
      else {$f = clone $f;}
      $f->what = $what;
      $f->where = $where;
      $f->action = $action;
      $f->whatelse = $what;
      if ("meta" == $what) {
        if ("attachment" == $where) { $f->whatelse .= "_att"; $f->type = "meta"; }
        elseif(startsWith($where,"taxonomy_")) { $f->whatelse .= "_tax"; $f->type = "option"; }
        else{ $f->whatelse .= "_cpt"; }
      }
      if (("remove" == $action ) && ("taxonomy"==$f->type) && ("meta_cpt" == $f->whatelse)) {
        $f->field = "tagsdiv-" . $f->field;
      }
      elseif("column" == $what && "taxonomy" == $f->type){
        $f->field = "taxonomy-" . $f->field;
      }
      $arr[$f->id] = $f;
    }
    return $arr;
  }

  public function getlist() {
    if (! isset($this->list) && isset($this->listcallback)) {
      $o = $this->listcallback[0];
      $f = $this->listcallback[1];
      $a = isset($this->listcallback[2]) ? $this->listcallback[2] : NULL;
      $this->list = call_user_func(array($o,$f),$a);
      unset($this->listcallback);
    }
    return $this->list;
  }
  public function display($id=null) {
    $ignore = ("custom" == $this->type);
    $isinput = ("filter" == $this->what) || ("meta_cpt" == $this->whatelse)  || ("meta_tax" == $this->whatelse);
    $isid = (in_array($this->field,array("ID","post_parent")) && $this->type == "post");
    if ("custom" == $this->type) { $raw = $value = null; }
    elseif ("filter" == $this->what) { $raw = $value = $this->input($id); }
    elseif ("panel" == $this->type) { $raw = $value = $this->input(); }
    else {
      $raw = $this->raw($id); $value = $raw;
      if ("meta_tax" == $this->whatelse) { $value = $this->input($raw); }
      elseif ("meta_cpt" == $this->whatelse) { $value = $this->input($raw);}
      elseif ("column" == $this->whatelse && $isid)    { $value = $this->editlink($raw);}
      elseif ("column" == $this->whatelse ) { $value = $this->lookup(null,$raw); }
      if ($this->field == "gallery") { $this->gscript();}
    }
    $value = apply_filters("nada-filter-admin",$value,$raw,$id,$this);
    return $value;
  }
  public function save($id,$value) {
    #$eol = PHP_EOL; $pfield =  print_r($this,true); $lookup =  $this->lookup($value,null,"id");
    #fput("POST ID: {$id}{$eol}FIELD VALUE: {$value}{$eol}LOOKUP: {$LOOKUP}{$eol}FIELD: {$pfield}");
    $field = $this->field;
    # when saving a field from a dropdown we don't get the actual id, but tthe list id.
    $value = $this->lookup($value,null,"id");
    if     ($this->type == "post")     {wp_update_post(array('ID' => $id,$field=>$value));}
    elseif ($this->type == "meta")     {update_post_meta($id, $field, $value);}
    elseif ($this->type == "taxonomy") {wp_set_object_terms($id, ( $value < 1 ? NULL : (int) $value ) , $field,false);}
    elseif ($this->type == "option")   {$meta = get_option( $field ); $meta[$id] = $value; update_option( $field, $meta); }
  }

  private function editlink($id){
    if (0 == $id) { return "&#8212";}
    else { return sprintf("<a class='{$this->what} {$this->field}' href='%s'>%s</a>",get_edit_post_link($id),get_the_title($id));}
  }
  private function lookup($list_id=null,$value_id=null,$return="title") {
    $list= $this->getlist();
    if (isset($list)) {
      foreach($list as $liid => $li) {
        if ($value_id === $li["id"] || $list_id === $liid) {return $li[$return]; }
      }
    }

    return $value_id == null ? $list_id : $value_id;
  }
  private function input($value=null) {
    $ispanel = ("panel" == $this->type);
    $val = null == $value ? "" : "value='{$value}'";
    $type = (isset($this->attributes["type"]) ? $this->attributes["type"] : "text" );
    $id = $ispanel ? "settings_{$this->field}" : $this->id;
    $dataatt = $ispanel ? "data-setting='{$this->field}'" : "";
    $class =  (isset($this->attributes["class"]) ? "value='" . $this->attributes["default"] . "'" : "" );
    $s = "id='{$id}' name='{$id}' {$class} {$dataatt}";
    $list = $this->getlist();

    if (null == $list) {
      $input = "<input {$val} type='{$type}' {$s} />";
    }
    else {
      $input = "<select {$s}>";
      if ( ! $ispanel ) { $input .= "<option value='-1'>(Filter by {$this->label})</option>";}
      foreach($list as $liid => $li){
        $name = $ispanel ? $li : $li["title"];
        $selected = ($ispanel && $liid == "default") ||
                    ("filter" == $this->what && $liid == $value) ||
                    ("meta_cpt" == $this->whatelse && $li["id"] == $value );
        $selected = $selected ? "selected" : "";
        $input .= "<option value='{$liid}' {$selected}>{$name}</option>";
      }
      $input .= "</select>";
    }
    return $input;
  }
  private function raw($id){
    $value = "";
    if     ($this->type == "post")     { $value = get_post($id)->{$this->field};}
    elseif ($this->type == "meta")     { $value = get_post_meta($id, $this->field, true); }
    elseif ($this->type == "taxonomy") { $value = wp_get_post_terms( $id, $this->ofield); $value = empty($value) ? 0 : $value[0]->term_id; }
    elseif ($this->type == "option")   { $meta = get_option( $this->field ); $value = $meta[$id]; }
    return $value;
  }
  private function gscript() {
    ?><script>
    jQuery(document).ready(function($){
        var iframe; // Instantiates the variable that holds the media library frame.
    	  var btn = $('#<?= $this->id   ?>');
        btn.click(function(e){ // Runs when the image button is clicked.
    		    var ids = btn.val();
    		    var selectie = loadImages(ids);
            e.preventDefault();
            if ( iframe ) { iframe.open(); return; } // If the frame already exists, re-open it.

            // Sets up the media library frame
            iframe = wp.media.frames.iframe = wp.media({frame:'post',state:'gallery-edit',editing:true,multiple:true,class:'xxx',selection:selectie});
    		    iframe.on("update",function(){
    			       var controller = iframe.states.get('gallery-edit');
        		     var library = controller.get('library');
    		         var ids = library.pluck('id');
        	       btn.val(ids.join());
    		    });
            iframe.open();
    		    jQuery(".media-modal").addClass("hidethesettings"); // add class to hide gallery settings
        });
     });

    function loadImages(images){
        if (images){
            var shortcode = new wp.shortcode({
                tag:      'gallery',
                attrs:    { ids: images },
                type:     'single'
            });

            var attachments = wp.media.gallery.attachments( shortcode );

            var selection = new wp.media.model.Selection( attachments.models, {
                props:    attachments.props.toJSON(),
                multiple: true
            });

            selection.gallery = attachments.gallery;

            selection.more().done( function() {
                // Break ties with the query.
                selection.props.set({ query: false });
                selection.unmirror();
                selection.props.unset('orderby');
            });

            return selection;
        }
        return false;
     }
     </script>
    <?php
  }
}
class Admin {
  private static $sep = ",";
  private static $add, $remove;
  private static function countwhat($what){
    $count = 0;
    if (isset(self::$add[$what])) { $count += count(self::$add[$what]); }
    if (isset(self::$remove[$what])) { $count += count(self::$remove[$what]); }
    return $count;
  }
  private static function countwhere($what,$where) {
    $count = 0;
    if (isset(self::$add[$what][$where])) { $count += count(self::$add[$what][$where]); }
    if (isset(self::$remove[$what][$where])) { $count += count(self::$remove[$what][$where]); }
    return $count;
  }
  public static function columns($where,$add=null,$remove=null){
    $what = "column";
    $where = ("attachment" == $where)  ? "media" : $where;
    if ( 0 == self::countwhere($what,$where) ) {
      add_filter( "manage_{$where}_posts_columns", array(__CLASS__,'column_register') );
      add_action( "manage_{$where}_posts_custom_column" , array(__CLASS__,'column_fill'), 10, 2 );
      add_filter( "manage_edit-{$where}_sortable_columns", array(__CLASS__,'column_sort') );
    }

    self::field("add","column",$where,$add);
    self::field("remove","column",$where,$remove);
  }
  public static function filters($where,$add){
    $what = "filters";
    if ( 0 == self::countwhat($what) ){ # FOR ALL
      add_action( 'restrict_manage_posts',array(__CLASS__,'filter_print'));
      add_action( 'pre_get_posts', array(__CLASS__,'filter_apply'));
    }
    if ( "attachment" == $where && 0 == self::countwhere($what,$where) ){ # FILTERS FOR MEDIA/BACKBONE
      add_action( 'admin_head', array(__CLASS__,'filter_styles'));
      add_filter( 'ajax_query_attachments_args', array(__CLASS__,'filter_backbone_enable_arrays'), 10, 1 );
      add_filter( 'media_view_settings', array(__CLASS__,'filter_backbone_register'), 10, 2);
      add_action( 'admin_print_footer_scripts', array(__CLASS__,'filter_backbone_proper'), 52);
    }

    self::field("add","filter",$where,$add);
  }
  public static function metaatt($add) {
    $what = "meta"; $where = "attachment";
    if ( 0 == self::countwhere($what,$where)) {
      add_filter( 'attachment_fields_to_edit' ,array(__CLASS__,'meta_att_edit'), 10, 2);
      add_filter( 'attachment_fields_to_save' ,array(__CLASS__,'meta_att_save'), 10, 2);
    }
    self::field("add","meta",$where,$add);
  }
  public static function metatax($where,$add) {
    $what = "meta";
    if ( 0 == self::countwhere($what,$where)) {
      self::meta_tax_hook($where);
    }
    self::field("add","meta",$where,$add);
  }
  public static function meta($where,$add=null,$remove=null){
    $what = "meta";
    if ( 0 == self::countwhere($what,$where)) {
      add_action( 'add_meta_boxes', array(__CLASS__,'meta_register'), 51);
      add_action( 'save_post', array(__CLASS__,'meta_save'));
      add_filter( 'default_content', array(__CLASS__,'meta_vars'), 10, 2 );
    }
    if (!(null == $remove)) {self::field("remove",$what,$where,$remove);}
    if (!(null == $add)) {
      foreach($add as $meta_title => $fields) {
        $boxcount = 0;
        if(isset(self::$add["boxes"])) {$boxcount += count(self::$add["boxes"]);}
        $ids= array(); $id="box_{$boxcount}";
        foreach($fields as $f) {
           $f = is_string($f) ? Field::get($f) : $f ;
           $ids[] = $f->id;
         }

        $ids = implode(self::$sep,$ids);
        self::field("add","boxes",$where,Field::get("box",$meta_title,"custom",array("list"=>$ids)));
        self::field("add",$what,$where,$fields);
      }
    }
  }
  public static function tabs($tablist) {
    if ( count(self::$remove["tab"]) == 0 ) {
        add_action( 'admin_menu', array(__CLASS__,'hide_admin_tabs'));
    }
    self::$remove["tab"] = $tablist;
  }
  private static function field($how,$what,$where,$fields) {
    if(isset($fields)){
      $fields = Field::build($fields,$how,$what,$where);
      foreach($fields as $f ){
        if ("add" == $how) { self::$add[$what][$where][$f->id] = $f; }
        else{self::$remove[$what][$where][$f->id] = $f;}
      }
    }
  }

  # Tabs
  public static function hide_admin_tabs(){
    foreach (self::$remove["tab"] as $tab) {  remove_menu_page($tab);  }
  }

  # Filters
  public static function filter_print() {
    global $wp_query; $post_type = $wp_query->query["post_type"];
    if (isset(self::$add["filter"][$post_type])) {
      foreach (self::$add["filter"][$post_type] as $field_id => $field) {
        $value_id =  isset($_REQUEST[$field_id]) ? $_REQUEST[$field_id] : -1;
        echo $field->display($value_id);
      }
    }
  }
  public static function filter_apply($query){
    #TRANSLATE url?field_id=value_id to url?field=value
    #TRANSLATE comma separated id lists to arrays
    #TRANSLATE taxonomy term ids to taxonomy term slugs
    #fput("FIELD: "  . PHP_EOL .  print_r($field,true) . PHP_EOL . "VALUE: "  . PHP_EOL . print_r($value,true));
    if ( is_admin() && $query->is_main_query() ) {
      $post_type = $query->query_vars["post_type"];
      if ( isset(self::$add["filter"][$post_type]) ) {
        foreach (self::$add["filter"][$post_type] as $field_id => $field) {
          if ( isset($_REQUEST[$field_id]) ) {
            $value_id = $_REQUEST[$field_id];
            $list = $field->getlist();
            if ( isset($list[$value_id]["id"]) ) {
              $value = $list[$value_id]["id"];
              if (  ! ( ( null === $value ) || ( '' === $value ) || ( -1 === $value ) ) ) {
                if ( endsWith($field->field,"__in") ) {
                  if (contains($value,",")) { $value = explode(",",$value); }
                  else { $value = array($value); }
                }
                elseif("taxonomy" == $field->type) {
                  $term = get_term_by('id',$value,$field->field);
                  $value = $term->slug;
                }
                $query->set($field->field, $value);
              }
            }
          }
        }
      }
    }
  }

  # Backbone Filters
  public static function filter_styles() {
    $width = 100 / (count(self::$add["filter"]["attachment"]) + 2) -5;
    ?> <style>.attachment-filters {width:<?= $width ?>% !important;}</style>  <?php
  }
  public static function filter_backbone_register($settings, $post){
      if ( isset(self::$add["filter"]["attachment"]) ) {
      foreach (self::$add["filter"]["attachment"] as $field_id =>$field) {
        $list = $field->getlist();
        if ( isset($list) ) {
          foreach ($list as $list_item_id => $list_item) {
            $settings[$field_id][$list_item_id] = $list_item;
          }
        }
      }
    }
    return $settings;
  }
  public static function filter_backbone_proper(){
    ?><script type="text/javascript"><?php
    foreach(self::$add["filter"]["attachment"] as $field_id => $field) {
      $id = $field_id;
      $name = $field->label;
      $key =  ( $field->field == "post__in" ? "include" : "uploadedTo" );
      ?>

      var <?= $id ?> = wp.media.view.AttachmentFilters.extend({
        className: "attachment-filters",
        id: "media-attachment-filters-<?= $id ?>",
        createFilters: function() {
          var filters = {};
          filters.all = { text:"All <?= $name ?>",props:{ <?= $key ?>:null, orderby: 'date', order: 'DESC' },priority: 10};
          _.each( wp.media.view.settings.<?= $id ?> || {}, function( arr, key ) {
            filters[key] = { text:arr["title"],props:{<?= $key ?>:arr["id"], orderby: 'date', order: 'DESC' }};
          });
          this.filters = filters;
        }
      });
      <?php } ?>
      var myNewDropDown = wp.media.view.AttachmentsBrowser;
      wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
        createToolbar: function() {
        myNewDropDown.prototype.createToolbar.apply(this,arguments);
        if ( this.model.id!="gallery-edit" ){
          <?php foreach(self::$add["filter"]["attachment"] as $field_id=>$field) { $id = $field_id;
        echo "this.toolbar.set('{$id}', new {$id}({controller:this.controller,model:this.collection.props,priority:-75}).render() );";
        }?>
        }
      }
      });
    </script>
  <?php  }
  public static function filter_backbone_enable_arrays( $query = array()) {
    # backbone does not use "pre_get_posts"
    foreach(array("post_parent__in","post__in") as $param) {
      if ( isset($query[$param]) ) {
        if ( !is_array($query[$param][0]) && contains($query[$param][0],",") ) {
          $query[$param] = explode(",",$query[$param][0]);
        }
      }
    }

      if ( isset($query["post_parent"]) ) {
        if ( !is_array($query["post_parent"]) && contains($query["post_parent"],",") ) {
          $query["post_parent__in"] = explode(",",$query["post_parent"]);
          unset($query["post_parent"]);
        }
      }
    return $query;
  }

  # Column Functions
  public static function column_register( $columns ) {
    if (isset(self::$remove["column"][$_REQUEST["post_type"]])){
      foreach (self::$remove["column"][$_REQUEST["post_type"]] as $field_id=>$field) {
        unset($columns[$field->field]);
      }
    }
    if (isset(self::$add["column"][$_REQUEST["post_type"]])){
      foreach (self::$add["column"][$_REQUEST["post_type"]] as $field_id => $field) {
        $columns[$field->field] = $field->label;
      }
    }
    return $columns;
  }
  public static function column_fill( $column_name, $post_id ) {
    $post_type = $_REQUEST["post_type"];
    foreach(self::$add["column"][$post_type] as $field_id=>$field) {
      if($field->field == $column_name) {
        echo $field->display($post_id);
      }
    }
  }
  public static function column_sort( $columns ) {
    foreach (self::$add["column"][$_REQUEST["post_type"]] as $field_id => $field) {
      if (isset($field->field) ){
        $columns[$field->field] = $field->field;
      }
    }
    return $columns;
  }

  # Meta
  public static function meta_register(){
    $context = array("normal","advanced","side");
    if (isset(self::$remove["meta"])) {
      foreach(self::$remove["meta"] as $screen => $boxes ) {
        foreach($boxes as $box_id=>$box) {
          foreach($context as $c){
            remove_meta_box( $box->field,$box->where,$c);
          }
        }
      }
    }
    if (isset(self::$add["meta"])) {
      foreach(self::$add["boxes"] as $screen => $boxes) {
       foreach($boxes as $box_id=>$box) {
          add_meta_box($box_id,$box->label,array(__CLASS__,'meta_display'), $box->where, 'side', 'high');
        }
      }
    }
  }
  public static function meta_display($post,$args){
    wp_nonce_field( plugin_basename( __FILE__ ), $post->ID . '_noncename' );
    ?><div class='vas-custom-meta wp-core-ui' id='vas-meta-portfolio'>
          <style scoped>
          .vas-custom-meta-row {display:table-row;}
          .vas-custom-meta-cell {display:table-cell; padding:10px 0;}
          label.vas-custom-meta-cell {width:110px; }
          .vas-custom-meta-row .button, .vas-custom-meta-row select {width:100%;}
          </style>
    <?php
    $ids = self::$add["boxes"][$post->post_type][$args["id"]]->getlist();
    $ids = explode(self::$sep,$ids);
    foreach(self::$add["meta"][$post->post_type] as $field_id=>$field) {
      echo "";
      if (in_array($field_id,$ids)) {
        echo "<div class='vas-custom-meta-row'>";
        echo "<label class='vas-custom-meta-cell' for='{$field_id}'>" . $field->label . "</label>";
        echo "<span class='vas-custom-meta-cell' >" . $field->display($post->ID) . "</span>";
        echo "</div>";
      }
    }
    ?></div><?php
  }
  public static function meta_save( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {return;}
    if ( !isset( $_POST["{$post_id}_noncename"] ) ) {return;}
    if ( !wp_verify_nonce( $_POST["{$post_id}_noncename"], plugin_basename( __FILE__ ) ) ) {return;}
    if ( !current_user_can( 'edit_post', $post_id ) ) {return;}
    foreach(self::$add["meta"][$_POST['post_type']] as $field_id=>$field) {
      if ( isset($_POST[$field_id]) ) {
        remove_action( 'save_post', array(__CLASS__,'meta_save' ));
        $field->save($post_id,$_POST[$field_id]);
        add_action( 'save_post', array(__CLASS__,'meta_save'));
      }
    }
  }
  public static function meta_vars($content, $post) {
    # Ability to pass parameters to new portfolios
    if (basename($_SERVER['PHP_SELF']) == 'post-new.php') {
      if (isset(self::$add["meta"][$post->post_type])){
        foreach(self::$add["meta"][$post->post_type] as $field_id=>$field){
          if (isset($_REQUEST[$field->field])) {
            $field->save($post->ID,$_REQUEST[$field->field]);
          }
        }
      }
    }
    return $content;
  }

  # Attachment Meta
  public static function meta_att_edit($form_fields, $post) {
    foreach(self::$add["meta"]["attachment"] as $id => $field) {
      $label = $field->label; $helps = $field->helps;
      $value = $field->display($post->ID);

      $id = "ID_{$id}"; # id field cannot start with underscore or it is hidden
      $form_fields[$id] = array("label"=>$label,"value"=>$value,"helps" =>$helps,);
    }
    return $form_fields;
  }
  public static function meta_att_save($post, $attachment) {
    foreach(self::$add["meta"]["attachment"] as $field_id => $field) {
      if( isset($attachment["ID_{$field_id}"]) ){
        # curiously, the $post object here is and array, not an object
        $field->save($post["ID"],$attachment["ID_{$field_id}"]);
      }
    }
    return $post;
  }

  # Category Meta functions
  public static function meta_tax_hook($taxo,$hook= null){
    $taxes = array();

    # called by addField
    if (null == $hook) {
      # add hook to this same function for taxonomies registered later in code
      add_action('registered_taxonomy', array(__CLASS__,'meta_tax_hook'),10,2);
      # but for now just hook the one taxonomy
      if (!contains($taxo,"*")) {
        $taxes[] = str_replace("taxonomy_","",$taxo);
      }
      # Look for taxonomies matching pattern amongst already registered taxonomies
      else {
        foreach(get_taxonomies() as $tax) {
          if (fnmatch($taxo,"taxonomy_{$tax}")){
            $taxes[] = $tax ;
          }
        }
      }
    }
    # called by hook: seek tax match pattern amongst added fields
    else {
      foreach(self::$add["meta"] as $tax=>$fields) {
        if (fnmatch($tax,"taxonomy_{$taxo}")){ $taxes[] = $taxo ; }
      }
    }

    # Attach the actiual display and save hooks
    foreach($taxes as $t) {
      add_action( $t . '_edit_form_fields', array(__CLASS__,'meta_tax_box'));
      add_action( $t . '_add_form_fields', array(__CLASS__,'meta_tax_box'));
      add_action('created_' . $t, array(__CLASS__,'meta_tax_save'),10 , 3);
      add_action('edited_' . $t, array(__CLASS__,'meta_tax_save'),10, 3);
    }
  }
  public static function meta_tax_box($tag) {
    foreach(self::$add["meta"] as $tax=>$fields) {
      if ( fnmatch($tax,"taxonomy_" . $tag->taxonomy) ) {
        foreach ($fields as $field_id => $field) {
          echo "<tr class='form-field'>
                <th scope='row' valign='top'><label for='{$field_id}'>{$field->label}</label></th>
                <td>" . $field->display($tag->term_id) . "</td>
                </tr>";
        }
      }
    }
  }
  public static function meta_tax_save ($term_id, $tt_id, $tax) {
    $tag = get_term($term_id,$tax);
    foreach(self::$add["meta"] as $tax_pat=>$fields) {
      if ( startsWith($tax_pat,"taxonomy_") && fnmatch($tax_pat, "taxonomy_" . $tag->taxonomy) ) {
        foreach ($fields as $field_id => $field) {
          if ( isset($_POST[$field_id]) ) {
            $field->save($term_id,$_POST[$field_id]);
          }
        }
      }
    }
  }
}
class Script {  # add nojs class + jquery mobile
  private static $in_use = false, $classes = array();  private $nojs = "nojs"; private $yesjs = "js";
  public static function register($class = NULL) {
    if ( NULL !== $class ) { $name = get_class($class);
  	  if ( ! isset(self::$classes[$name]) ) { self::$classes[$name] = $class;}
  	}
  	new self();
  }
  private function __construct(){
    if ( false === self::$in_use ) {
  	  self::$in_use = true;
  	  add_filter( 'body_class', array($this,'body_class'));
  	  add_filter( 'wp_head', array($this,'head'));
  	  add_action( 'wp_footer' , array($this,'script'));
  	}
  }
  function head(){?>
   <?php $image_folder = apply_filters('nada-filter_imagefolder',"images"); ?>
   <?php $root = get_stylesheet_directory_uri() . "/{$image_folder}/" ; ?>
  	<!-- ****** Grumpicon ****** -->
      <noscript><link href="<?= get_template_directory_uri(); ?>/grumpicons/icons.fallback.css" rel="stylesheet"></noscript>
      <!-- ****** faviconit.com favicons ****** -->
  	<link rel="shortcut icon" href="<?= $root; ?>favicon.ico">
  	<link rel="icon" sizes="16x16 32x32 64x64" href="<?= $root; ?>favicon.ico">
  	<link rel="icon" type="image/png" sizes="196x196" href="<?= $root; ?>favicon-192.png">
  	<link rel="icon" type="image/png" sizes="160x160" href="<?= $root; ?>favicon-160.png">
  	<link rel="icon" type="image/png" sizes="96x96" href="<?= $root; ?>favicon-96.png">
  	<link rel="icon" type="image/png" sizes="64x64" href="<?= $root; ?>favicon-64.png">
  	<link rel="icon" type="image/png" sizes="32x32" href="<?= $root; ?>favicon-32.png">
  	<link rel="icon" type="image/png" sizes="16x16" href="<?= $root; ?>favicon-16.png">
  	<link rel="apple-touch-icon" href="<?= $root; ?>favicon-57.png">
  	<link rel="apple-touch-icon" sizes="114x114" href="<?= $root; ?>favicon-114.png">
  	<link rel="apple-touch-icon" sizes="72x72" href="<?= $root; ?>favicon-72.png">
  	<link rel="apple-touch-icon" sizes="144x144" href="<?= $root; ?>favicon-144.png">
  	<link rel="apple-touch-icon" sizes="60x60" href="<?= $root; ?>favicon-60.png">
  	<link rel="apple-touch-icon" sizes="120x120" href="<?= $root; ?>favicon-120.png">
  	<link rel="apple-touch-icon" sizes="76x76" href="<?= $root; ?>favicon-76.png">
  	<link rel="apple-touch-icon" sizes="152x152" href="<?= $root; ?>favicon-152.png">
  	<link rel="apple-touch-icon" sizes="180x180" href="<?= $root; ?>favicon-180.png">
  	<meta name="msapplication-TileColor" content="#FFFFFF">
  	<meta name="msapplication-TileImage" content="<?= $root; ?>favicon-144.png">
  	<meta name="msapplication-config" content="<?= $root; ?>browserconfig.xml">
  	<!-- ****** faviconit.com favicons ****** -->
    <?php }
  function body_class($classes) {
    	global $post;
  	  $classes[] = 'page-id-' . $post->ID;
  	  $classes[] = $post->post_title;
    	$classes[] = $post->post_name;
    	$classes[] = $this->nojs;
    	return $classes;
  }
  function script() {
    ?>
    <script>var urls = {
	    ajaxurl: "<?= admin_url( 'admin-ajax.php' )?>",
  		plugin_url: "<?= plugin_dir_url( __FILE__ )?>",
  		parent_theme_url: "<?= get_template_directory_uri()?>",
  		theme_url: "<?= get_stylesheet_directory_uri() ?>"
  	  }</script>
  	<script>/*! jQuery Mobile v1.4.0 | Copyright 2010, 2013 jQuery Foundation, Inc. | jquery.org/license */
      (function(e,t,n){typeof define=="function"&&define.amd?define(["jquery"],function(r){return n(r,e,t),r.mobile}):n(e.jQuery,e,t)})(this,document,function(e,t,n,r){(function(e,t,n,r){function T(e){while(e&&typeof e.originalEvent!="undefined")e=e.originalEvent;return e}function N(t,n){var i=t.type,s,o,a,l,c,h,p,d,v;t=e.Event(t),t.type=n,s=t.originalEvent,o=e.event.props,i.search(/^(mouse|click)/)>-1&&(o=f);if(s)for(p=o.length,l;p;)l=o[--p],t[l]=s[l];i.search(/mouse(down|up)|click/)>-1&&!t.which&&(t.which=1);if(i.search(/^touch/)!==-1){a=T(s),i=a.touches,c=a.changedTouches,h=i&&i.length?i[0]:c&&c.length?c[0]:r;if(h)for(d=0,v=u.length;d<v;d++)l=u[d],t[l]=h[l]}return t}function C(t){var n={},r,s;while(t){r=e.data(t,i);for(s in r)r[s]&&(n[s]=n.hasVirtualBinding=!0);t=t.parentNode}return n}function k(t,n){var r;while(t){r=e.data(t,i);if(r&&(!n||r[n]))return t;t=t.parentNode}return null}function L(){g=!1}function A(){g=!0}function O(){E=0,v.length=0,m=!1,A()}function M(){L()}function _(){D(),c=setTimeout(function(){c=0,O()},e.vmouse.resetTimerDuration)}function D(){c&&(clearTimeout(c),c=0)}function P(t,n,r){var i;if(r&&r[t]||!r&&k(n.target,t))i=N(n,t),e(n.target).trigger(i);return i}function H(t){var n=e.data(t.target,s),r;!m&&(!E||E!==n)&&(r=P("v"+t.type,t),r&&(r.isDefaultPrevented()&&t.preventDefault(),r.isPropagationStopped()&&t.stopPropagation(),r.isImmediatePropagationStopped()&&t.stopImmediatePropagation()))}function B(t){var n=T(t).touches,r,i,o;n&&n.length===1&&(r=t.target,i=C(r),i.hasVirtualBinding&&(E=w++,e.data(r,s,E),D(),M(),d=!1,o=T(t).touches[0],h=o.pageX,p=o.pageY,P("vmouseover",t,i),P("vmousedown",t,i)))}function j(e){if(g)return;d||P("vmousecancel",e,C(e.target)),d=!0,_()}function F(t){if(g)return;var n=T(t).touches[0],r=d,i=e.vmouse.moveDistanceThreshold,s=C(t.target);d=d||Math.abs(n.pageX-h)>i||Math.abs(n.pageY-p)>i,d&&!r&&P("vmousecancel",t,s),P("vmousemove",t,s),_()}function I(e){if(g)return;A();var t=C(e.target),n,r;P("vmouseup",e,t),d||(n=P("vclick",e,t),n&&n.isDefaultPrevented()&&(r=T(e).changedTouches[0],v.push({touchID:E,x:r.clientX,y:r.clientY}),m=!0)),P("vmouseout",e,t),d=!1,_()}function q(t){var n=e.data(t,i),r;if(n)for(r in n)if(n[r])return!0;return!1}function R(){}function U(t){var n=t.substr(1);return{setup:function(){q(this)||e.data(this,i,{});var r=e.data(this,i);r[t]=!0,l[t]=(l[t]||0)+1,l[t]===1&&b.bind(n,H),e(this).bind(n,R),y&&(l.touchstart=(l.touchstart||0)+1,l.touchstart===1&&b.bind("touchstart",B).bind("touchend",I).bind("touchmove",F).bind("scroll",j))},teardown:function(){--l[t],l[t]||b.unbind(n,H),y&&(--l.touchstart,l.touchstart||b.unbind("touchstart",B).unbind("touchmove",F).unbind("touchend",I).unbind("scroll",j));var r=e(this),s=e.data(this,i);s&&(s[t]=!1),r.unbind(n,R),q(this)||r.removeData(i)}}}var i="virtualMouseBindings",s="virtualTouchID",o="vmouseover vmousedown vmousemove vmouseup vclick vmouseout vmousecancel".split(" "),u="clientX clientY pageX pageY screenX screenY".split(" "),a=e.event.mouseHooks?e.event.mouseHooks.props:[],f=e.event.props.concat(a),l={},c=0,h=0,p=0,d=!1,v=[],m=!1,g=!1,y="addEventListener"in n,b=e(n),w=1,E=0,S,x;e.vmouse={moveDistanceThreshold:10,clickDistanceThreshold:10,resetTimerDuration:1500};for(x=0;x<o.length;x++)e.event.special[o[x]]=U(o[x]);y&&n.addEventListener("click",function(t){var n=v.length,r=t.target,i,o,u,a,f,l;if(n){i=t.clientX,o=t.clientY,S=e.vmouse.clickDistanceThreshold,u=r;while(u){for(a=0;a<n;a++){f=v[a],l=0;if(u===r&&Math.abs(f.x-i)<S&&Math.abs(f.y-o)<S||e.data(u,s)===f.touchID){t.preventDefault(),t.stopPropagation();return}}u=u.parentNode}}},!0)})(e,t,n),function(e){e.mobile={}}(e),function(e,t){var r={touch:"ontouchend"in n};e.mobile.support=e.mobile.support||{},e.extend(e.support,r),e.extend(e.mobile.support,r)}(e),function(e,t,r){function l(t,n,r){var i=r.type;r.type=n,e.event.dispatch.call(t,r),r.type=i}var i=e(n),s=e.mobile.support.touch,o="touchmove scroll",u=s?"touchstart":"mousedown",a=s?"touchend":"mouseup",f=s?"touchmove":"mousemove";e.each("touchstart touchmove touchend tap taphold swipe swipeleft swiperight scrollstart scrollstop".split(" "),function(t,n){e.fn[n]=function(e){return e?this.bind(n,e):this.trigger(n)},e.attrFn&&(e.attrFn[n]=!0)}),e.event.special.scrollstart={enabled:!0,setup:function(){function s(e,n){r=n,l(t,r?"scrollstart":"scrollstop",e)}var t=this,n=e(t),r,i;n.bind(o,function(t){if(!e.event.special.scrollstart.enabled)return;r||s(t,!0),clearTimeout(i),i=setTimeout(function(){s(t,!1)},50)})},teardown:function(){e(this).unbind(o)}},e.event.special.tap={tapholdThreshold:750,emitTapOnTaphold:!0,setup:function(){var t=this,n=e(t),r=!1;n.bind("vmousedown",function(s){function a(){clearTimeout(u)}function f(){a(),n.unbind("vclick",c).unbind("vmouseup",a),i.unbind("vmousecancel",f)}function c(e){f(),!r&&o===e.target?l(t,"tap",e):r&&e.stopPropagation()}r=!1;if(s.which&&s.which!==1)return!1;var o=s.target,u;n.bind("vmouseup",a).bind("vclick",c),i.bind("vmousecancel",f),u=setTimeout(function(){e.event.special.tap.emitTapOnTaphold||(r=!0),l(t,"taphold",e.Event("taphold",{target:o}))},e.event.special.tap.tapholdThreshold)})},teardown:function(){e(this).unbind("vmousedown").unbind("vclick").unbind("vmouseup"),i.unbind("vmousecancel")}},e.event.special.swipe={scrollSupressionThreshold:30,durationThreshold:1e3,horizontalDistanceThreshold:30,verticalDistanceThreshold:75,start:function(t){var n=t.originalEvent.touches?t.originalEvent.touches[0]:t;return{time:(new Date).getTime(),coords:[n.pageX,n.pageY],origin:e(t.target)}},stop:function(e){var t=e.originalEvent.touches?e.originalEvent.touches[0]:e;return{time:(new Date).getTime(),coords:[t.pageX,t.pageY]}},handleSwipe:function(t,n,r,i){if(n.time-t.time<e.event.special.swipe.durationThreshold&&Math.abs(t.coords[0]-n.coords[0])>e.event.special.swipe.horizontalDistanceThreshold&&Math.abs(t.coords[1]-n.coords[1])<e.event.special.swipe.verticalDistanceThreshold){var s=t.coords[0]>n.coords[0]?"swipeleft":"swiperight";return l(r,"swipe",e.Event("swipe",{target:i,swipestart:t,swipestop:n})),l(r,s,e.Event(s,{target:i,swipestart:t,swipestop:n})),!0}return!1},setup:function(){var t=this,n=e(t);n.bind(u,function(r){function l(n){if(!s)return;i=e.event.special.swipe.stop(n),u||(u=e.event.special.swipe.handleSwipe(s,i,t,o)),Math.abs(s.coords[0]-i.coords[0])>e.event.special.swipe.scrollSupressionThreshold&&n.preventDefault()}var i,s=e.event.special.swipe.start(r),o=r.target,u=!1;n.bind(f,l).one(a,function(){u=!0,n.unbind(f,l)})})},teardown:function(){e(this).unbind(u).unbind(f).unbind(a)}},e.each({scrollstop:"scrollstart",taphold:"tap",swipeleft:"swipe",swiperight:"swipe"},function(t,n){e.event.special[t]={setup:function(){e(this).bind(n,e.noop)},teardown:function(){e(this).unbind(n)}}})}(e,this)});
    </script>
  	<script>/*! grunt-grunticon Stylesheet Loader - v2.1.2 | https://github.com/filamentgroup/grunticon | (c) 2015 Scott Jehl, Filament Group, Inc. | MIT license. */
      (function(e){function t(t,n,r,o){"use strict";function a(){for(var e,n=0;u.length>n;n++)u[n].href&&u[n].href.indexOf(t)>-1&&(e=!0);e?i.media=r||"all":setTimeout(a)}var i=e.document.createElement("link"),l=n||e.document.getElementsByTagName("script")[0],u=e.document.styleSheets;return i.rel="stylesheet",i.href=t,i.media="only x",i.onload=o||null,l.parentNode.insertBefore(i,l),a(),i}var n=function(r,o){"use strict";if(r&&3===r.length){var a=e.navigator,i=e.Image,l=!(!document.createElementNS||!document.createElementNS("http://www.w3.org/2000/svg","svg").createSVGRect||!document.implementation.hasFeature("http://www.w3.org/TR/SVG11/feature#Image","1.1")||e.opera&&-1===a.userAgent.indexOf("Chrome")||-1!==a.userAgent.indexOf("Series40")),u=new i;u.onerror=function(){n.method="png",n.href=r[2],t(r[2])},u.onload=function(){var e=1===u.width&&1===u.height,a=r[e&&l?0:e?1:2];n.method=e&&l?"svg":e?"datapng":"png",n.href=a,t(a,null,null,o)},u.src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==",document.documentElement.className+=" grunticon"}};n.loadCSS=t,e.grunticon=n})(this);(function(e,t){"use strict";var n=t.document,r="grunticon:",o=function(e){if(n.attachEvent?"complete"===n.readyState:"loading"!==n.readyState)e();else{var t=!1;n.addEventListener("readystatechange",function(){t||(t=!0,e())},!1)}},a=function(e){return t.document.querySelector('link[href$="'+e+'"]')},c=function(e){var t,n,o,a,c,i,u={};if(t=e.sheet,!t)return u;n=t.cssRules?t.cssRules:t.rules;for(var l=0;n.length>l;l++)o=n[l].cssText,a=r+n[l].selectorText,c=o.split(");")[0].match(/US\-ASCII\,([^"']+)/),c&&c[1]&&(i=decodeURIComponent(c[1]),u[a]=i);return u},i=function(e){var t,o,a;o="data-grunticon-embed";for(var c in e)if(a=c.slice(r.length),t=n.querySelectorAll(a+"["+o+"]"),t.length)for(var i=0;t.length>i;i++)t[i].innerHTML=e[c],t[i].style.backgroundImage="none",t[i].removeAttribute(o);return t},u=function(t){"svg"===e.method&&o(function(){i(c(a(e.href))),"function"==typeof t&&t()})};e.embedIcons=i,e.getCSS=a,e.getIcons=c,e.ready=o,e.svgLoadedCallback=u,e.embedSVG=u})(grunticon,this);
      grunticon([urls.parent_theme_url + "/grumpicons/icons.data.svg.css", urls.parent_theme_url + "/grumpicons/icons.data.png.css", urls.parent_theme_url + "/grumpicons/icons.fallback.css"]);</script>
      <?php
      if ( wp_script_is( 'jquery' , 'done' ) ) {
  	  ?><script>jQuery(document).ready(function($){
  	  $("body").removeClass("<?= $this->nojs ?>").addClass("<?= $this->yesjs ?>");
  	  var jd = { target_size: "", init : function(){
  	    <?php
  		foreach(self::$classes as $n=>$o){
  		  if (method_exists($o,"script_init")){ $o->script_init(); }
  		  if (method_exists($o,"script")){ echo "this.{$n}();";  }
  		} ?>
  		}
  	    <?php
  		foreach(self::$classes as $n=>$o){
  		  foreach( get_class_methods($o) as $meth) {
  		    if ( startsWith($meth,"script") && $meth !== "script_init" ) {
  			  if ("script" == $meth) { $js = $n; }  else { $x = explode("_",$meth,2); $js = $x[1]; }
  		      echo ",\n {$js} : function (){ \n"; $o->$meth(); echo "\n }";
  			}
  	      }
  		}
  		?>
  	  };<?php echo "\n\n jd.init();"; ?>
  	  });</script><?php
  	}
  }
 }
class Share {
  private $shortcode = "share", $has_wpseo_image = false;
  var $page_title, $page_url, $page_image, $page_description, $poster;
  var $socials = array("facebook","twitter","pinterest","tumblr","weheartit","linkedin");
  var $links = array(
    "facebook"  => "https://www.facebook.com/sharer/sharer.php?u={url}",
	  "twitter"   => "https://twitter.com/intent/tweet?text={text} {url}",
	  "pinterest" => "http://pinterest.com/pin/create/button/?url={url}&amp;description={text}&amp;media={icon}",
	  "tumblr"    => "http://www.tumblr.com/share/photo?source={icon}&amp;caption={text}&amp;click_thru={url}",
	  "weheartit" => "http://weheartit.com/heart-it/new_entry?encoding=UTF-8&hearting_method=heart_it_button&image_url={icon}&popup=1&source_url={url}",
	  "linkedin"  => "https://www.linkedin.com/shareArticle?mini=true&url={url}&title={text}"
  );
  function __construct($socials = array(),$poster ="") {
    if ( ! empty($socials) ) { $this->socials = array_intersect($socials,$this->socials); }
	  $this->poster = $poster;

    add_action("wp_head",array($this,"opengraph"));
	  add_shortcode( $this->shortcode,array($this,'output'));
    add_action('wpseo_opengraph_image',array($this,'has_wpseo_image'));
  }
  function has_wpseo_image() {$this->has_wpseo_image = true;}
  function opengraph() {
    global $post; $pageimage=array();
    $pagetitle =  wp_title( '●', false, 'right' );  #	&#9679;	&#x25CF;
    $url = get_url(true);
    $pagedescription = get_bloginfo('description');
    $blogtitle = get_bloginfo('name');

    if (! $this->has_wpseo_image ) {
      if (is_attachment()) {
        #get attached image
        $img = wp_get_attachment_image_src( $post->ID, "full" );
  	    if($img) {
          $pageimage[$post->ID] = $img[0];
        }
      }
      elseif ( has_post_thumbnail($post)) {
        #get thumbnail image
        $thumb_id = get_post_thumbnail_id($post->ID);
        $thumb_url_array = wp_get_attachment_image_src($thumb_id, 'thumbnail-size', true);
        $pageimage[$thumb_id] = $thumb_url_array[0];
      }
      else {
        #if there's a gallery with ids, get the first image
        $galleries = nada_short_attributes("gallery",$post->post_content);
        if ( isset($galleries[0]["ids"])) {
          $ids = explode(",",$galleries[0]["ids"]);
          $id = $ids[0];
          $img = wp_get_attachment_image_src( $id, "full" );
          $pageimage[$id] = $img[0];
        }
        # or the first attached image
        elseif(isset($galleries[0])) {
          $imgs = get_attached_media( "image", $post->ID );
          if(!empty($imgs)){
            foreach($imgs as $i){
              $img = wp_get_attachment_image_src( $i->ID, "full" );
              $pageimage[] = $img[0];
              break;  # just grab the first
            }
          }
        }
      }
      #if all else fails get cover image
      if (count($pageimage) == 0) {
        $pageimage[] = resolve_theme_path($this->poster,true);
      }
      else {
        foreach($pageimage as $id=>$src){
          $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
          if (strlen($alt) != 0) {$pagedescription = $alt;}
        }
      }

      if (! has_action('wpseo_opengraph') ) {
        # print og:general
        echo "<meta prefix='og: http://ogp.me/ns#' property='og:site_name' content='{$blogtitle}'>";
        echo "<meta prefix='og: http://ogp.me/ns#' property='og:title' content='{$pagetitle}'>";
        echo "<meta prefix='og: http://ogp.me/ns#' property='og:url' content='{$url}'>";
        echo "<meta prefix='og: http://ogp.me/ns#' property='og:description' content='{$pagedescription}'>";
        echo "<meta prefix='og: http://ogp.me/ns#' property='og:type' content='website' />";
      }
      else{ echo "<meta prefix='og: http://ogp.me/ns#' property='og:description' content='{$pagedescription}'>";}
      # print og:image
      foreach ($pageimage as $i) {
        echo "<meta prefix='og: http://ogp.me/ns#' property='og:image' content='{$i}'>";
      }
    }

    $this->page_title = $pagetitle;
	  $this->page_url = $url;
    if(!empty($pageimage)) {
      $a = array_values($pageimage);
      $this->page_image = $a[0];
    }
	  $this->page_description = $pagedescription;
  }
  function output($atts) {
    Script::register($this);
  	$a = shortcode_atts(array("url" => null, "title" => null, "image" => null), $atts);
  	$url = $a["url"] == null ? urlencode($this->page_url) : $a["url"];
  	$text = $a["title"] == null ? $this->page_title : $a["title"];
  	$icon = $a["image"] == null ? urlencode($this->page_image) : $a["image"];

    $html  = "<div class='shares side-nav' onclick='return true' aria-haspopup='true' tabindex='0'>
      				<div class='icon icon-share' title='share'><span class='alttext'>Share</span></div>
      				<div class='side-nav-pop'>";
  	foreach($this->socials as $nm) {
  	    $link = $this->links[$nm];
        $link = str_replace("{url}",$url,$link);
        $link = str_replace("{icon}",$icon,$link);
        $link = str_replace("{text}",$text,$link);

  	  $html .= "<a class='socials icon icon-{$nm}' title='{$nm}' target='_blank' href='{$link}'><span class='alttext'>{$nm}</span></a>";
  	}

    $html .= "</div></div>";
    return $html;
  }
  function script_share(){?> $('body').on('click','a.socials',function(){window.open($(this).attr("href"), '', 'width=626,height=436'); return false; }); <?php  }
  function script_init() {?> this.share(); <?php }
 }
class Instagram {
  # uses the following external functions: get_url, resolve_theme_path, contains, global share
  var $post, $iuser, $islugs, $ikey, $ivars = array('page'=>'after','item'=>'xid','user'=>'uid');
  private $posts_per_page = 20, $show_overlay = false, $show_comments = false, $show_share = false;
  var $shortcode = "instagram";

  const CACHE_EXPIRY = 1000;
  const CACHE_FLUSH = false;
  const CACHE_FOLDER = "blog";

  var $loader = "/images/load.gif";
  var $cover = "/images/cover.jpg";

  function __construct($pagename,$key,$user, $options = array()){
    $this->iuser = $user;
  	$this->islugs = is_array($pagename) ? $pagename : array($pagename);
  	$this->ikey = $key;
  	$this->loader = resolve_theme_path($this->loader,true);
  	$this->cover = resolve_theme_path($this->cover,true);
  	$this->posts_per_page = isset($options{"posts_per_page"}) ? $options{"posts_per_page"} : 20;
  	$this->show_overlay = isset($options{"show_overlay"}) ? $options{"show_overlay"} : false;
  	$this->show_comments = isset($options{"show_comments"}) ? $options{"show_comments"} : false;
  	$this->show_share = isset($options{"show_share"}) ? $options{"show_share"} : false;
    add_filter( 'init', array($this,'rewrite_rules'));
  	add_filter( 'query_vars' , array($this,'query_vars'));
  	add_action( 'wp', array($this,"set_post"));
    add_action( 'wp_ajax_getblog', array($this,'get_instagram_ajax') );
    add_action( 'wp_ajax_nopriv_getblog', array($this,'get_instagram_ajax') );
    add_filter( 'the_content', array($this,'auto_insert_instagram'));
	  add_filter( 'body_class', array($this,'body_classes'));
	  add_shortcode ( $this->shortcode, array($this,'output'));
  }
  function set_post() { global $post; $this->post = $post;  }
  function body_classes($classes) {
  	if (has_shortcode($this->post->post_content,$this->shortcode) || in_array($this->post->post_name,$this->islugs)) {
        $classes[] = 'hasgallery';
  	}
  	return $classes;
  }
  function get_instagram_ajax() { echo $this->output(); die(); }
  function build_url($var,$val) { return get_url(false, $this->ivars[$var], $val); }
  function query_vars($vars){ $q = array_values($this->ivars); return $q + $vars; }
  function auto_insert_instagram($content) {
    if(!has_shortcode($content,$this->shortcode) && in_array($this->post->post_name,$this->islugs)) {
  	  $content .= do_shortcode("[" . $this->shortcode . "]");
  	}
  	return $content;
  }
  function rewrite_rules() {
    $pslugs = implode("|",array_values($this->islugs));
	  $qvars = implode("|",array_values($this->ivars));
    $rew = "^({$pslugs})/({$qvars})/([^/]+)/({$qvars})/([^/]+)/?$";
    add_rewrite_rule($rew,'index.php?pagename=matches[1]&matches[2]=$matches[3]&matches[4]=$matches[5]','top');
    $rew = "^({$pslugs})/({$qvars})/([^/]+)/?$";
    add_rewrite_rule($rew,'index.php?pagename=matches[1]&matches[2]=$matches[3]','top');
  }
  function nz($var, $short_atts, $default = null ) { # return the 1st value of 3: A) shortcode, B) URL, C) default
    if (isset($short_atts[$var])) {$val = $short_atts[$var];}
  	if (!isset($val) || empty($val)) {$val = get_query_var($this->ivars[$var]);}
  	if (!isset($val) || empty($val)) {$val = $default;}
  	return $val;
  }
  function output($a = array()) {
    Script::register($this);
    $page = $this->nz("page",$a,1);
  	$item = $this->nz("item",$a,null);
  	$user = $this->nz("user",$a,$this->iuser);
  	return $this->resolve_cache($user,$page,$item);
  }
  private function resolve_cache($user,$page,$item) {
    $cache = resolve_theme_path("/" . self::CACHE_FOLDER . "/");
  	$local = $cache . "instagram" . $user . ($item == null ? $page : $item) . ".html";
  	$fetch = false;
  	if (isset($_REQUEST["refresh"])) { $fetch = true; }
  	elseif ( self::CACHE_FLUSH ) { $fetch = true; }
  	elseif ( ! file_exists($local) ) { $fetch = true; }
  	elseif ( time() - filemtime($local) > self::CACHE_EXPIRY ) { $fetch = true; }
  	if (!$fetch) { $html = file_get_contents($local); }
  	else { $html = $this->get_data($user,$page,$item); file_put_contents($local,$html); }
  	return $html;
  }
  private function get_data($user,$page,$item){
    $key = $this->ikey;
  	if ($item !== null) { $remote = "https://api.instagram.com/v1/media/{$item}?access_token={$key}"; }
  	else { $count = $this->posts_per_page;
  	       $remote = "https://api.instagram.com/v1/users/{$user}/media/recent/?access_token={$key}&count={$count}" . ($page !== 1 ? "&max_id={$page}" : ""); }
    $json = @file_get_contents($remote);
  	return $this->transform_data($json);
  }
  private function transform_data($json) {
    $data = json_decode($json,true);
    $html = "<div class='winstagram'>";
  	if ($data["meta"]["code"] == 200) {
        $items = $data["data"]; if (count($items) <2) { $items = array($items); }
  	  foreach ($items as $v) { $html .= $this->print_item($v); }
  	  if ( isset( $data["pagination"]["next_max_id"] ) ){
  	    $newpage = $data["pagination"]["next_max_id"];
  		$next = $this->build_url("page",$newpage);
  		$user = $data["user"]["id"];
  		$loader = $this->loader;
  		$html .= "<div class='post loadmore'><a href='{$next}' id='blogloader' data-page='{$newpage}' data-user='{$user}' data-loader='{$loader}'>Load More</a></div>";
  	  }
  	}
  	$html .= "</div>";
  	return $html;
  }
  private function print_item($item) {
  	$post = "";
  	$title = $this->post->post_title;
  	$itype =  $item["type"];
  	$image = $item["images"]["standard_resolution"]["url"];
  	if( $itype == "image") {
  	  $post = "<img src='{$image}' alt='{$title}' title='{$title}' class='blogimg' />";
  	} elseif ($itype == "video") {
  	  $video = $item["videos"]["standard_resolution"]["url"];
  	  $post = "<video controls poster='{$image}'><source src='{$video}' type='video/mp4'>Your browser does not support the video tag.</video>";
  	}
  	$image = $image == "" ? $this->cover : $image;

  	$link = $item["link"];
  	$id = $item["id"];

  	$html = "<div class='post'><a href='{$link}' data-instagram-id='{$id}' rel='nofollow' target='_blank'>{$post}";
    if ($this->show_overlay) {
      $html .= "<span class='ig-overlay'>";
      $html .= "<span class='icon icon-iglike'></span><span class='icon ig'>" . $item["likes"]["count"] . "</span>";
      $html .= "<span class='icon icon-igcomment'></span><span class='icon ig'>" . $item["comments"]["count"] . "</span>";
      $html .= "</span>";
    }
    if ($this->show_overlay) { $html .= "<span class='ig-comment'>" . $item["caption"]["text"] . "</span>";  }
    if ($this->show_share) {
  	  if ( shortcode_exists( 'share' ) ) {
  	    $html .= do_shortcode("[share url='" . $this->build_url("item",$id) . "' title='{$title}' image='{$image}']");
  	  }
  	}
  	$html .= "</a></div>";

  	return $html;
  }
  function script_instagram(){
	   ?>if($("a[data-instagram-id]").length){
        if(navigator.userAgent.match(/(iPad|iPhone|iPod)/g)){
	      $(document).on("click","a[data-instagram-id]",function(){
		    setTimeout(function(){if(window.document.hasFocus()){return true;}},1620);
		    window.location = "instagram://media?id=" + $(this).data("instagram-id"); return false;
	      });
	    }
      }<?php
  }
  function script_loadblog() {
	   ?>var tblr = $("#blogloader");
        if ( tblr.length != 0 ) {
		  tblr.on("click",function(){
		    tblr.html('<img src="' + tblr.attr("data-loader") + '" style="width:auto;height:auto;" />');

    	    var data = {'action': 'getblog','page': tblr.attr("data-page") , 'user': tblr.attr("data-user")};
	        $.post(urls.ajaxurl, data, function(info) {
			  tblr.parent().parent().append(info);
			  tblr.parent().remove();
			  loadblog($);
		    });
		    return false;
		  });
	  }<?php
    }
  function script_init() {?> this.instagram(); this.loadblog();  <?php }
 }
class Unscroll {
   private $el, $par;
   function __construct($el,$par) {$this->el = $el; $this->par = $par; Script::register($this);}
   function script() { ?>
     $("#menu .empty>a").on("click",function(){
 	     var toggle = $(this).siblings("ul");
 	     var hide = $(this).closest("li").siblings("li").find("ul");
 	     toggle.css("white-space","nowrap").toggle(500,"swing");
 	     hide.hide(0);
 	     return false;
     });    // Menu navigation empty items
     $("#menu").on("click","#menuicon",function(){
 	     var nav = $(this).parent().find(".nav-menu");
 	     nav.toggle(300,"swing",function(){$("body").toggleClass("frozen");});
     }); // Menu mobile navigator

     function unscroll(el,size){
       if (el.length) {
         var menu_top, menu_height, screen_height, scroll_top, block;
 	       checkUnscroll();
 	       $(window).scroll(positionUnscroll).resize(checkUnscroll);
 	     }
       function positionUnscroll(){
 	       if (block) {
           if((menu_top + menu_height) <= screen_height) {
             el.css("position","fixed").css("top",menu_top);
           }
           else {
             scroll_top = $(document).scrollTop();
             var a = menu_top - scroll_top;
             var b = screen_height - menu_height + menu_top;
             var new_menu_top = Math.max(a,b);
             console.log("menu_top: " + menu_top +
                       "; menu_height: " + menu_height +
                       "; screen_height: " + screen_height +
                       "; scroll_top: " + scroll_top +
                       "; menu minus sroll: " + a +
                       "; screen minus menu: " + b +
                       "; new top: " + new_menu_top + ";");
             el.css("position","fixed").css("top",new_menu_top);
           }
         }
       }
       function checkUnscroll(){
         menu_top = el.offset().top;
         menu_height = el.height();
         screen_height = $(window).height();
 	       block = el.css("display") !== "none";
       }
     }
     function Xunscroll(el,size){
       if (el.length) {
         var top, height, bottom, block;
 	       checkUnscroll();
 	       $(window).scroll(positionUnscroll).resize(checkUnscroll);
 	     }
       function positionUnscroll(){
 	       if (block) {
 		        var h = el.height(), sctop = $(document).scrollTop();
 		        if (h <= height) { el.css("top",top).css("bottom","").css("position", "fixed"); }
 		        else if (h - sctop <= height) { el.css("top","").css("bottom",bottom + "px").css("position", "fixed"); }
 		        else { el.css("top","").css("bottom","").css("position",""); }
         }
       }
       function checkUnscroll(){
         top = el.offset().top;
 	       height = size.height();
         bottom = $(window).height() - height - top;
 	       block = el.css("display") !== "none";
       }
     }
     unscroll($('<?= $this->el ?>'),$('<?= $this->par ?>'));
  <?php }
  }
class Email_Hider {
  private $shortcode = "email";
  function __construct(){
    add_shortcode($this->shortcode,array($this,'output'));
  	add_action( 'admin_print_footer_scripts', array($this,'add_button_qtg'), 51);
  	add_action( 'init', array($this,'add_button_tmce'));
  	add_filter( 'tiny_mce_version', array($this,'vas_refresh_mce'));
  }
  function output($atts, $content = "" ) {
    $hascontent = isset($atts["content"]) ? "true" : "false";
    $atts = shortcode_atts(
        array(
          'class'=>'', 'tag'=>'div',
          'content'=> '<noscript>Please turn on javascript for email</noscript>'
        ), $atts, 'email' );

    Script::register($this);
    $eml = explode("@",$content,2); $x = $eml[0];
    $eml = explode(".",$eml[1],2); $y = $eml[0];
    $eml = explode("?",$eml[1],2); $z = $eml[0];
    $w = isset($eml[1]) ? $eml[1] : "";

    $tag = $atts["tag"];
    $class = $atts["class"];
    $data = "data-content='{$hascontent}' data-x='{$x}' data-y='{$y}' data-z='{$z}' data-w='{$w}'";
    return "<{$tag} class='xyz {$class}' {$data}>" . $atts["content"] . "</{$tag}>";
    # alternative encoding
    #$output = '';
    #for ($i = 0; $i < strlen($content); $i++) {$output .= '&#'.ord($content[$i]).';';}
    #return $output;
  }
  function script_email_hider(){
    ?>
    	$('[data-x][data-y][data-z]').each(function(){
    		 var x = $(this).data('x');
    		 var y = $(this).data('y');
    		 var z = $(this).data('z');
         var w = $(this).data('w');
         if($(this).data("content") === false) {
           $("<span class='xyz-x'>" + x + "</span><span class='xyz-y'>" + y + "</span><span class='xyz-z'>" + z + "</span>").appendTo(this);
         }
    		 $(this).on('click',function(){document.location = 'mailto:' + x + '@' + y + '.' + z + '?' + w; });
    		});
    <?php
  }
  function script_init() {?> this.email_hider();<?php }
  # Shortcode button on Qtips
  function add_button_qtg () {
    if ( wp_script_is( 'quicktags' ) ) { ?>
      <script>QTags.addButton("email", "@", "[email]","[/email]","m","Insert email address");</script>
    <?php }
  }
  # Shortcode button on MCE
  function add_button_tmce () {
    if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') ) { return; }
    if ( get_user_option('rich_editing') == 'true') {
      add_filter('mce_external_plugins', array($this,'add_tinymce_plugin'));
      add_filter('mce_buttons',array($this,'register_button'));
    }
  }
  function register_button ($buttons) {
    array_push($buttons, "|", "email");
    return $buttons;
  }
  function add_tinymce_plugin ($plugin_array) {
    $path = get_bloginfo('template_url').'/js/editor_plugin.js';
    if ( file_exists($path) ) { $plugin_array['vasmail'] = $path; }
    return $plugin_array;
  }
  function refresh_mce($ver) { $ver += 3;  return $ver; }
 }
class Emojicon_Killer {
  private static $been_here = false;
  function __construct() {
    if ( false === self::$been_here ) {
	  self::$been_here = true;
	  add_action( 'init', array($this,'disable_wp_emojicons' ));

	}
  }
  # Disable Emoji Crap
  function disable_wp_emojicons() {
    // all actions related to emojis
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    // filter to remove TinyMCE emojis
    add_filter( 'tiny_mce_plugins', array($this,'disable_emojicons_tinymce' ));
  }
  function disable_emojicons_tinymce( $plugins ) {
    if ( is_array( $plugins ) ) { return array_diff( $plugins, array( 'wpemoji' ) ); }
	else { return array();}
  }
 }
class Responsive_Image {
  # Class accepts an image id and a customised alt a_attribute
  # outputs noscript tag for responsive image and corresponding script
  private $id, $pic_sizes = array(), $default_size = "large", $image_class = "gallery-image", $default_gallery = "favicon-310.png";
  private static $wp_sizes = array();

  function __construct($id,$default = "") {
    $this->id = $id; if ("" !== $default) {$this->default_gallery = $default;}
    if ( empty(self::$wp_sizes) ) {
	     self::$wp_sizes = $this->get_sizes();
       Script::register($this);
	  }
	  foreach (self::$wp_sizes as $name => $width) { $this->pic_sizes[$name] = wp_get_attachment_image_src($id,$name); }
  }
  function get_sizes(){
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
  function get_picture($size = ""){ $size = $size == "" ? $this->default_size : $size ;return $this->pic_sizes[$size][0];  }
  function is_portrait(){ return (bool)($this->orientation() == "portrait"); }
  function orientation(){ return ($this->pic_sizes["full"][1] <= $this->pic_sizes["full"][2]) ? "portrait": "landscape";  }
  function ratio(){
    if ( ! isset($this->pic_sizes["full"][1]) ) { return 1; }
    else { return ($this->pic_sizes["full"][2] / $this->pic_sizes["full"][1]); }
  }
  function output(){
    if ( $this->id == 0 ) {
  	  global $default_gallery; $src = "/images/" . $this->default_gallery;
  	  $src = resolve_theme_path($src,true);
  	  return "<img src='{$src}' alt='{$alt}' />";
  	}
    else {
  	  $data = array(); $src= "";
  	  foreach($this->pic_sizes as $name => $s){
  	    if ($name == $this->default_size) ($src = $s[0]);
  	    $data[] = "data-{$name}='" . $s[0] . "'";
  	  }
  	  $data = implode(" ",$data);
  	  $alt = get_post_meta($this->id, '_wp_attachment_image_alt', true);
  	  return "<noscript data-alt='{$alt}' {$data}><img src='{$src}' alt='{$alt}' title='{$alt}' class='" . $this->image_class . "'></noscript>";
  	}
  }
  function script_image_replacer() {
     $size_atts = "[data-" . implode("][data-",array_keys(self::$wp_sizes)) . "]";
	   ?>var sizes = <?= json_encode(self::$wp_sizes) ?>;
       var imgs = $(".imageholder[data-ratio]"); var count = imgs.length; var i = 0;
       imgs.each(function(){
         $(this).css("padding-bottom",$(this).data("ratio"));
         i++; if ( i == count ) {$(document).trigger("jd:imagesloaded");}
       });

       $.each(sizes,function(i,x){ if ( x / screen.width >= 1 ) { jd.target_size = i; return false; } } );
       $('noscript<?= $size_atts ?>').each(function(){
           var me = $(this); var a = me.parent(); var div = a.parent();
           a.addClass('wait'); // Display spinner and restore original aspect ratio
           var source = me.data(jd.target_size); // which image to show
      	   var alt = me.data('alt');
      	   // Load Image
      	   var img = new Image(); img.src = source; img.onload = function() {
      	     $('<img />').addClass('<?= $this->image_class ?>').attr('src',source).attr('alt',alt).attr('title',alt).hide().insertAfter(me).fadeIn(200);
      	   }
         });
		 <?php
  }
  function script_init() {?> this.image_replacer(); <?php  }
 }
class Gallery_Item extends Responsive_Image {
  private $image_id, $video_link, $style, $show_text = false, $show_share = false, $show_lightbox = false;
  public $post_id, $alt, $caption, $post, $lightbox;
  private $atts = array("div"=>array("class"=>"gallery-item"),
                        "a"  =>array("class"=>"imageholder"));

  function __construct($id,$show_lightbox = false,$show_text = false,$show_share = false,$style = "") {
    Script::register($this);
    $post = get_post($id);
    $this->post = $post;
    $this->style = $style;
  	$this->post_id = $id;
  	$this->show_lightbox = $show_lightbox;
  	$this->show_text = $show_text;
  	$this->show_share = $show_share;

    if ($post->post_type == "attachment"){
      $this->image_id = $id;
  	  $this->video_link = get_post_meta( $id, "_video_link" );
  	  $this->video_link = empty($this->video_link) ? $this->video_link : $this->video_link[0];
  	}
    elseif (has_post_thumbnail($id)) { $this->image_id = get_post_thumbnail_id($id);}
    else {$this->image_id = 0;}
    parent::__construct($this->image_id);
  	$this->set_attributes();
  }
  public function add_class($value){ $this->atts["div"]["class"] .= " " . $value; }
  public function add_attr($tag, $attr, $val) { $this->atts[$tag][$attr] = $val; }
  public function is_video(){ return !empty($this->video_link); }
  public function is_portrait_image() { return ! $this->is_video() && $this->is_portrait(); }
  private function set_attributes() {
    $this->atts["div"]["data-id"] = $this->post_id;
  	$this->atts["a"]["rel"] = "lightbox";
    $this->atts["a"]["href"] = get_the_permalink($this->post_id);
  	$this->add_class($this->orientation());
  	if ( $this->is_video() ) {
  	  $this->add_class("video");
  	  $this->atts["a"]["data-ratio"] = "57%";
  	}
    else {
  	  $this->atts["a"]["data-ratio"] = $this->ratio() * 100 . "%";
  	}
    if ( "carousel" == $this->style){
      $src = wp_get_attachment_image_src ( $this->image_id, "large"); $src = $src[0];
      $this->add_class("usesbg");
      $this->atts["div"]["style"] = "background-image:url({$src});background-position:center;-webkit-background-size:cover;-moz-background-size:cover;-o-background-size:cover;background-size:cover;";
    }
  }
  function output(){
    $alt =     isset($this->alt)     ? $this->alt     : get_post_meta($this->post_id, '_wp_attachment_image_alt', true);
    $caption = isset($this->caption) ? $this->caption : get_post_field("post_title",$this->post_id);;
    $a = $this->show_lightbox ? "a" : "span";
    $arg = $this->atts["a"];
    if ( ! $this->show_lightbox ) { unset($arg["href"]); unset($arg["rel"]);}

    $html = "<div " . array_short($this->atts["div"]) . ">";
    $html .= "<" . $a . array_short($arg) . ">";
    if ( $this->is_video() ) {
      $html .= "<iframe src='" . $this->video_link . "' frameborder='0' webkitallowfullscreen='' mozallowfullscreen='' allowfullscreen=''></iframe>";
    }
	  else { $html .= parent::output();}
    $html .= "</{$a}>";
    if ($this->show_text ){
      $html .= "<div class='gallery-image-caption'>{$caption}</div>";
    }
    if ($this->show_share ){
  	  if ( shortcode_exists('share')) {
  	    $url = $this->atts["a"]["href"]; $image = $this->get_picture();
  		  $html .= do_shortcode("[share url='{$url}' title='{$alt}' image='{$image}']");
  	  }
    }
    return $html . "</div>";
  }
  function script_lightbox() {
    if("fancybox" == $this->lightbox) { ?>
      var lb = $("[rel^=lightbox]");
      if(lb.length){ lb.fancybox({helpers:{overlay : {css : {'background' : 'rgba(0, 0, 0, 0.95)'}}, title: {type:'over'}}});}
    <?php }
    else { ?>
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
      }); <?php
    }
  }
  function script_init() { ?> $(document).on("jd:imagesloaded",this.lightbox); this.image_replacer(); <?php }
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
         $gitem = new Gallery_Item($a->ID,$bbox,$btext,$bshare,$style);
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
?>
