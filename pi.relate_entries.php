<?php
// define the old-style EE object
if (!function_exists('ee')) {
  function ee() {
    static $EE;
    if (! $EE) {
      $EE = get_instance();
    }
    return $EE;
  }
}

$plugin_info = array(
  'pi_name' => 'Relate_entries',
  'pi_version' =>'0.1',
  'pi_author' =>'Andy Hebrank',
  'pi_author_url' => 'http://insidenewcity.com/',
  'pi_description' => 'Custom category functions',
  'pi_usage' => Relate_entries::usage()
  );

class Relate_entries {

  function __construct() {
    $fields = ee()->TMPL->fetch_param('fields', null);
    $ids = ee()->TMPL->fetch_param('ids', null);

    if (is_null($fields) || is_null($ids)) {
      $this->return_data = "Need ids and fields";
      return;
    }

    $fields = explode("|", $fields);
    $ids = explode("|", $ids);

    if (count($fields) != count($ids)) {
      $this->return_data = "Number of elements in fields and ids must match";
      return;
    }

    // lookup the field ids
    $fieldmap = array();
    for ($i = 0; $i < count($fields); $i++) {
      $f = $fields[$i];
      $search = $ids[$i];

      $result = ee()->db->select('field_id')
        ->from('channel_fields')
        ->where('field_name', $f)
        ->get()->row();
      if (empty($result)) continue;
      $fieldmap[$result->field_id] = $search;
    }

    if (count($fieldmap) != count($fields)) {
      $this->return_data = "Could not find all the fields";
      return;
    }

    // search for all entries that match every relationship
    $entries = null;
    foreach ($fieldmap as $fid => $search) {
      $relationship_entries = array();
      $results = ee()->db->select('parent_id')
        ->from('relationships')
        ->where('field_id', $fid)
        ->where('child_id', $search)
        ->get();
      //echo ee()->db->last_query();
      if ($results->num_rows() == 0) {
        // no matches; we can stop here
        $this->return_data = "0";
        return;
      }

      foreach ($results->result() as $row) {
        $relationship_entries[] = $row->parent_id;
      }
      $relationship_entries = array_unique($relationship_entries);
      if (is_null($entries)) {
        $entries = $relationship_entries;
      }
      else {
        $entries = array_intersect($entries, $relationship_entries);
      }
    }

    $this->return_data = (count($entries) > 0)? implode("|", $entries) : "0";
  }

  // do any entries meet the criteria
  function has_any() {
    if (empty($this->return_data) || $this->return_data == "0") {
      return "0";
    }
    return "1";
  }

  // return a simple list of links
  function link_list() {
    if (empty($this->return_data) || $this->return_data == "0") {
      return "";
    }

    // decide how to do the URLs
    $title_permalink = ee()->TMPL->fetch_param('title_permalink', null);

    $ids = explode("|", $this->return_data);

    $entries = ee()->db->select('*')
      ->from('channel_titles')
      ->where_in('entry_id', $ids)
      ->get();

    if ($entries->num_rows() == 0) {
      return "";    
    }

    // from http://stackoverflow.com/questions/8245405/expressionengine-how-to-get-the-path-of-a-page-given-its-entry-id-with-the-str
    // lookup the URL from the crazy EE page hash
    $site_id = ee()->config->item('site_id'); // Get site id (MSM safety)
    $site_pages = ee()->config->item('site_pages'); // Get pages array

    $output = '<ul class="relate-entries-list">' . "\n";
    foreach ($entries->result() as $e) {
      if (!is_null($title_permalink)) {
        $page_url = ee()->functions->create_url($title_permalink . '/' . $e->url_title);
      }
      elseif (isset($site_pages[$site_id]['uris'][$e->entry_id])) {
        // I think this is for Pages
        $page_url = $site_pages[$site_id]['uris'][$e->entry_id];
      }
      else {
        // hmm
        $page_url = $e->url_title;
      }
      $output .= '<li><a href="'. $page_url . '">' . $e->title . "</a></li>\n";
    }
    $output .= "</ul>\n";

    return $output;
  }

  function usage() {
    ob_start();
?>
{exp:relate_entries fields="program_degree_level|program_area_of_study" ids="{embed:entry_id}|{entry_id}"}
<?php
    return ob_get_clean();
  }

}