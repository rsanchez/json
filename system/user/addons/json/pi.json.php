<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Json
{
  /* settings */
  protected $content_type = 'application/json';
  protected $terminate = FALSE;
  protected $xhr = FALSE;
  protected $fields = array();
  protected $date_format = FALSE;
  protected $json_pretty_print = FALSE;
  protected $jsonp = FALSE;
  protected $callback;

  /* caches */
  public $entries;
  public $entries_entry_ids;
  public $entries_custom_fields;
  protected $entries_grid_rows;
  protected $entries_grid_cols;
  protected $entries_matrix_rows;
  protected $entries_matrix_cols;
  protected $entries_playa_data;
  protected $entries_channel_files_data;
  protected $image_manipulations = array();

  public function entries($entry_ids = null)
  {
    $this->initialize('entries');

    // exit if ajax request is required and not found
    if ($this->check_xhr_required())
    {
      return '';
    }

    // instantiate channel module object
    if (empty($this->channel))
    {
      require_once PATH_ADDONS.'channel/mod.channel.php';

      $this->channel = new Channel;
    }

    $this->channel->initialize();

    $order_by_string = '';

    if (is_array($entry_ids))
    {
      $this->entries_entry_ids = $entry_ids;
      $order_by_string = 'FIELD(t.entry_id,'.implode(',', $entry_ids).')';
    }
    else
    {
      // run through the channel module process to grab the entries
      $this->channel->uri = ($this->channel->query_string != '') ? $this->channel->query_string : 'index.php';

      if ($this->channel->enable['custom_fields'] === TRUE)
      {
        $this->channel->fetch_custom_channel_fields();
      }

      $save_cache = FALSE;

      if (ee()->config->item('enable_sql_caching') == 'y')
      {
        if (FALSE == ($this->channel->sql = $this->channel->fetch_cache()))
        {
          $save_cache = TRUE;
        }
        else
        {
          if (ee()->TMPL->fetch_param('dynamic') != 'no')
          {
            if (preg_match("#(^|\/)C(\d+)#", $this->channel->query_string, $match) OR in_array($this->channel->reserved_cat_segment, explode("/", $this->channel->query_string)))
            {
              $this->channel->cat_request = TRUE;
            }
          }
        }
      }

      if ( ! $this->channel->sql)
      {
        $this->channel->build_sql_query();
      }

      if (preg_match('/t\.entry_id IN \(([\d,]+)\)/', $this->channel->sql, $match))
      {
        $this->entries_entry_ids = explode(',', $match[1]);
      }

      if (preg_match('/ORDER BY (.*)?/', $this->channel->sql, $match))
      {
        $order_by_string = $match[1];
      }
    }

    if ($this->entries_entry_ids)
    {
      $get_grouped_custom_fields = ee()->db->select('channel_fields.*')
          ->from('channel_fields')
          ->join('channel_field_groups_fields', 'channel_fields.field_id = channel_field_groups_fields.field_id')
          ->join('channels_channel_field_groups', 'channel_field_groups_fields.group_id = channels_channel_field_groups.group_id')
          ->join('channels', 'channels_channel_field_groups.channel_id = channels.channel_id')
          ->where('channels.site_id', ee()->config->item('site_id'))
          ->where_in('channels.channel_name', explode('|', ee()->TMPL->fetch_param('channel')))
          ->get()
          ->result_array();

      $get_ungrouped_custom_fields = ee()->db->select('channel_fields.*')
          ->from('channel_fields')
          ->join('channels_channel_fields', 'channel_fields.field_id = channels_channel_fields.field_id')
          ->join('channels', 'channels_channel_fields.channel_id = channels.channel_id')
          ->where('channels.site_id', ee()->config->item('site_id'))
          ->where_in('channels.channel_name', explode('|', ee()->TMPL->fetch_param('channel')))
          ->get()
          ->result_array();

      $this->entries_custom_fields = array_merge($get_grouped_custom_fields, $get_ungrouped_custom_fields);

      $default_fields = array(
        't.title',
        't.url_title',
        't.entry_id',
        't.channel_id',
        't.author_id',
        't.status',
        't.entry_date',
        't.edit_date',
        't.expiration_date',
      );

      $select = array();

      if ( ! empty($this->fields))
      {
        foreach ($default_fields as $field)
        {
          $key = substr($field, 2);

          if (in_array($key, $this->fields))
          {
            $select[] = $field;
          }
        }
      }
      else
      {
        $select = $default_fields;
      }

      foreach ($this->entries_custom_fields as &$field)
      {
        if (empty($this->fields) || in_array($field['field_name'], $this->fields))
        {

          if ( ee()->db->table_exists('channel_data_field_'.$field['field_id']) AND $field['legacy_field_data'] === 'n' )
          {
            $select[] = 'cdf_'.$field['field_id'].'.'.ee()->db->protect_identifiers('field_id_'.$field['field_id']).' AS '.ee()->db->protect_identifiers($field['field_name']);
          }
          // We got legacy channel data, let's get it
          else
          {
            $select[] = 'lcd.'.ee()->db->protect_identifiers('field_id_'.$field['field_id']).' AS '.ee()->db->protect_identifiers($field['field_name']);
          }
        }
      }

      // we need entry_id, always grab it
      if ( ! in_array('t.entry_id', $select))
      {
        $select[] = 't.entry_id';
      }


      ee()->db->select(implode(', ', $select), FALSE)
          ->from('channel_titles t');
      foreach ($this->entries_custom_fields as &$field)
      {
        if ( ee()->db->table_exists('channel_data_field_'.$field['field_id']) AND $field['legacy_field_data'] === 'n' )
        {
          ee()->db->join('channel_data_field_'.$field['field_id'].' cdf_'.$field['field_id'], 't.entry_id = cdf_'.$field['field_id'].'.entry_id');
        }
        // We got legacy channel data, let's join it
        else
        {
          // Make sure the legacy channel data has not already been joined
          if ( ! isset($is_legacy_data_joined) ) {
            // join legacy channel data
            ee()->db->join('channel_data lcd', 't.entry_id = lcd.entry_id');
            // Set flag because we want this join only once!
            $is_legacy_data_joined = TRUE;
          }
        }
      }

      ee()->db->where_in('t.entry_id', $this->entries_entry_ids);


      if ($order_by_string)
      {
        if (strpos($order_by_string, 'w.') !== FALSE)
        {
          ee()->db->join('channels w', 't.channel_id = w.channel_id');
        }

        if (strpos($order_by_string, 'm.') !== FALSE)
        {
          ee()->db->join('members m', 'm.member_id = t.author_id');
        }

        if (strpos($order_by_string, 'md.') !== FALSE)
        {
          ee()->db->join('member_data md', 'm.member_id = md.member_id');
        }

        if (ee()->TMPL->fetch_param('display_by') === 'week' && strpos($order_by_string, 'yearweek') !== FALSE)
        {
          $yearweek = TRUE;

          $offset = ee()->localize->zones[ee()->config->item('server_timezone')] * 3600;

          $format = (ee()->TMPL->fetch_param('start_day') === 'Monday') ? '%x%v' : '%X%V';

          ee()->db->select("DATE_FORMAT(FROM_UNIXTIME(entry_date + $offset), '$format') AS yearweek", FALSE);
        }

        ee()->db->order_by($order_by_string, '', FALSE);
      }

      $query = $this->channel->query = ee()->db->get();

      $show_categories = ee()->TMPL->fetch_param('show_categories') === 'yes';

      if ($show_categories)
      {
        $this->channel->fetch_categories();

        if (ee()->TMPL->fetch_param('show_category_group'))
        {
          $show_category_group = explode('|', ee()->TMPL->fetch_param('show_category_group'));
        }
      }

      $this->entries = $query->result_array();

      $query->free_result();

      foreach ($this->entries as &$entry)
      {
        if (isset($yearweek))
        {
          unset($entry['yearweek']);
        }

        // format dates as javascript unix time (in microseconds!)
        if (isset($entry['entry_date']))
        {
          $entry['entry_date'] = $this->date_format($entry['entry_date']);
        }

        if (isset($entry['edit_date']))
        {
          $entry['edit_date'] = $this->date_format(strtotime($entry['edit_date']));
        }

        if (isset($entry['expiration_date']))
        {
          $entry['expiration_date'] = $this->date_format($entry['expiration_date']);
        }

        foreach ($this->entries_custom_fields as &$field)
        {
          // check for file grid fieldtype, if found "convert" it to grid fieldtype
          $field_type = $field['field_type'];
          if ( $field_type == "file_grid" ) $field_type = 'grid';

          // call our custom callback for this fieldtype if it exists
          if (is_callable(array($this, 'entries_'.$field_type)))
          {
            $entry[$field['field_name']] = call_user_func(array($this, 'entries_'.$field_type), FALSE, $entry['entry_id'], $field, $entry[$field['field_name']], $entry);
          }
        }

        if ($show_categories)
        {
          $entry['categories'] = array();

          if (isset($this->channel->categories[$entry['entry_id']]))
          {
            foreach ($this->channel->categories[$entry['entry_id']] as $raw_category)
            {
              if ( ! empty($show_category_group) && ! in_array($raw_category[5], $show_category_group))
              {
                continue;
              }

              $category = array(
                'category_id' => (int) $raw_category[0],
                'parent_id' => (int) $raw_category[1],
                'category_name' => $raw_category[2],
                'category_image' => $raw_category[3],
                'category_description' => $raw_category[4],
                'category_group' => $raw_category[5],
                'category_url_title' => $raw_category[6],
              );

              foreach ($this->channel->catfields as $cat_field)
              {
                $category[$cat_field['field_name']] = (isset($raw_category['field_id_'.$cat_field['field_id']])) ? $raw_category['field_id_'.$cat_field['field_id']] : '';
              }

              $entry['categories'][] = $category;
            }
          }
        }

        $entry['entry_id'] = (int) $entry['entry_id'];

        if (isset($entry['channel_id']))
        {
          $entry['channel_id'] = (int) $entry['channel_id'];
        }

        if (isset($entry['author_id']))
        {
          $entry['author_id'] = (int) $entry['author_id'];
        }
      }
    }

    ee()->load->library('javascript');

    ee()->load->library('typography');

    return $this->respond($this->entries, array(ee()->typography, 'parse_file_paths'));
  }


  protected function entries_fluid_field($in_fluid, $entry_id, $field, $field_data, $entry)
  {
    if ( ! function_exists('get_fluid_common_field') )
    {
      function get_fluid_common_field($field_id, $field_data, $field_data_id, $field_type)
      {
        $query = ee()->db->select('fluid_field_data.*, channel_data_field_'.$field_id.'.field_id_'.$field_id)
        ->from('fluid_field_data')
        ->join('channel_data_field_'.$field_id, 'fluid_field_data.field_data_id = channel_data_field_'.$field_id.'.id')
        ->where('fluid_field_data.field_id', $field_id)
        ->where('fluid_field_data.field_data_id', $field_data_id)
        ->get();

        $result = $query->result_array();
        $data = array_shift($result);
        $query->free_result();

        switch ($field_type)
        {
          case 'text':

            $field_settings = ee()->api_channel_fields->get_settings($field_id);

            if ($field_settings['field_content_type'] === 'numeric' || $field_settings['field_content_type'] === 'decimal')
            {
              return floatval($data['field_id_'.$field_id]);
            }

            if ($field_settings['field_content_type'] === 'integer')
            {
              return intval($data['field_id_'.$field_id]);
            }

            return $data['field_id_'.$field_id];

          break;


          case 'date':

            $date_format = ee()->TMPL->fetch_param('date_format');
            // get rid of EE formatted dates
            if ($date_format && strstr($date_format, '%'))
            {
              $date_format = str_replace('%', '', $date_format);
            }

            if ( ! isset($data['field_id_'.$field_id]))
            {
              return NULL;
            }

            return ($date_format) ? date($date_format, $data['field_id_'.$field_id]) : $data['field_id_'.$field_id] * 1000;

          break;


          default:

            return $data['field_id_'.$field_id];
        }
      }
    }

    $query = ee()->db->select('fluid_field_data.*, channel_fields.*')
                     ->from('fluid_field_data')
                     ->join('channel_fields', 'fluid_field_data.field_id = channel_fields.field_id')
                     ->where('entry_id', $entry_id)
                     ->where('fluid_field_id', $field['field_id'])
                     ->order_by('order', 'asc')
                     ->get();

    $data = array();

    $special_field_types = array('grid', 'file_grid', 'relationship', 'assets', 'channel_files', 'matrix', 'playa', 'wygwam');

    foreach ($query->result_array() AS $row)
    {
      $field_type = $row['field_type'];
      if ( $field_type == 'file_grid') $field_type = 'grid';

      // use dedicated functions for special fieldtypes
      if ( in_array($field_type, $special_field_types) )
      {
        if ( is_callable(array($this, 'entries_'.$field_type))  )
        {
          $data[$row['field_name']] = call_user_func(array($this, 'entries_'.$field_type), TRUE, $row['entry_id'], $row, $row['field_name'], $row['field_data_id']);
        }
      }
      else
      {
        $this_field_count = ee()->db->where('field_id', $row['field_id'])
                                    ->get('fluid_field_data')
                                    ->num_rows();

        // if there are multiple entries of the same field, make an array
        if ( $this_field_count > 1 )
        {
          $data[$row['field_name']][] = get_fluid_common_field($row['field_id'], $row['field_name'], $row['field_data_id'], $row['field_type']);
        }
        else
        {
          $data[$row['field_name']] = get_fluid_common_field($row['field_id'], $row['field_name'], $row['field_data_id'], $row['field_type']);
        }
      }

    }

    $query->free_result();

    return $data;
  }


  protected function entries_grid($in_fluid, $entry_id, $field, $field_data, $entry)
  {
    if ( ! isset($this->entries_grid_rows[$field['field_id']]))
    {
      $query = $in_fluid ? ee()->db->where('fluid_field_data_id !=', '0') : ee()->db->where('fluid_field_data_id', '0');
      $query = ee()->db->where_in('entry_id', $this->entries_entry_ids)
                       ->order_by('row_order')
                       ->get('channel_grid_field_'.$field['field_id']);

      foreach ($query->result_array() as $row)
      {
        if ( ! isset($this->entries_grid_rows[$field['field_id']][$row['entry_id']]))
        {
          $this->entries_grid_rows[$field['field_id']][$row['entry_id']] = array();
        }

        $this->entries_grid_rows[$field['field_id']][$row['entry_id']][] = $row;
      }

      $query->free_result();
    }

    if (is_null($this->entries_grid_cols))
    {
      $query = ee()->db->order_by('col_order', 'ASC')
                       ->get('grid_columns');

      foreach ($query->result_array() as $row)
      {
        if ( ! isset($this->entries_grid_cols[$row['field_id']]))
        {
          $this->entries_grid_cols[$row['field_id']] = array();
        }

        $this->entries_grid_cols[$row['field_id']][$row['col_id']] = $row;
      }

      $query->free_result();
    }

    $data = array();

    if (isset($this->entries_grid_rows[$field['field_id']][$entry_id]) && isset($this->entries_grid_cols[$field['field_id']]))
    {
      foreach ($this->entries_grid_rows[$field['field_id']][$entry_id] as $grid_row)
      {
        $row = array('row_id' => (int) $grid_row['row_id']);

        foreach ($this->entries_grid_cols[$field['field_id']] as $col_id => $col)
        {
          $val = $grid_row['col_id_' . $col_id];
          if ($col['col_type'] == 'relationship')
          {
            $val = $this->entries_grid_relationship($in_fluid, $col_id, $row['row_id'], $entry_id);
          }
          $row[$col['col_name']] = $val;
        }

        $data[] = $row;

      }
    }

    // Reset in case this field comes back inside a fluid field
    $this->entries_grid_rows[$field['field_id']] = NULL;

    return $data;
  }


  protected function entries_grid_relationship($in_fluid, $grid_col_id, $grid_row_id, $entry_id)
  {
    // First get the relationship ids
    $query = ee()->db->select('parent_id, child_id, grid_field_id, grid_col_id, grid_row_id')
                     ->where_in('parent_id', $this->entries_entry_ids)
                     ->where('grid_col_id', $grid_col_id)
                     ->order_by('order', 'asc')
                     ->get('relationships');

    $rel_ids = $query->result_array();
    $query->free_result();

    foreach ($rel_ids as $id)
    {
      if ( ! isset($entries_grid_relationship_data[$grid_col_id][$id['parent_id']][$id['grid_row_id']]))
      {
        $entries_grid_relationship_data[$grid_col_id][$id['parent_id']][$id['grid_row_id']] = array();
      }
      // Now get the related data
      $query = ee()->db->select('ct.entry_id, ct.channel_id, ct.title, ct.url_title, ct.author_id, m.username, c.channel_name')
                       ->from('channel_titles ct')
                       ->join('members m', 'ct.author_id = m.member_id')
                       ->join('channels c', 'ct.channel_id = c.channel_id')
                       ->where('entry_id', $id['child_id'])
                       ->get();

      $rel_data = $query->result_array();
      $query->free_result();

      foreach ($rel_data as $datum)
      {
        $entries_grid_relationship_data[$grid_col_id][$id['parent_id']][$id['grid_row_id']][] = array(
          'channel_id'   => (int) $datum['channel_id'],
          'channel_name' => $datum['channel_name'],
          'entry_id'     => (int) $datum['entry_id'],
          'title'        => $datum['title'],
          'url_title'    => $datum['url_title'],
          'author_id'    => (int) $datum['author_id'],
          'username'     => $datum['username']
        );
      }
    }

    if (isset($entries_grid_relationship_data[$grid_col_id][$entry_id][$grid_row_id]))
    {
      return $entries_grid_relationship_data[$grid_col_id][$entry_id][$grid_row_id];
    }

    return array();
  }


  protected function entries_relationship($in_fluid, $entry_id, $field, $field_data)
  {
    // First get the relationship ids
    $query = $in_fluid ? ee()->db->where('fluid_field_data_id !=', '0') : ee()->db->where('fluid_field_data_id', '0');
    $query = ee()->db->select('parent_id, child_id, field_id')
                     ->where_in('parent_id', $this->entries_entry_ids)
                     ->order_by('order', 'asc')
                     ->get('relationships');

    $rel_ids = $query->result_array();
    $query->free_result();

    foreach ($rel_ids as $id)
    {
      if ( ! isset($entries_relationship_data[$id['parent_id']]))
      {
        $entries_relationship_data[$id['parent_id']] = array();
      }

      if ( ! isset($entries_relationship_data[$id['parent_id']][$id['field_id']]))
      {
        $entries_relationship_data[$id['parent_id']][$id['field_id']] = array();
      }
      // Now get the related data
      $query = ee()->db->select('ct.entry_id, ct.channel_id, ct.title, ct.url_title, ct.author_id, m.username, c.channel_name')
                       ->from('channel_titles ct')
                       ->join('members m', 'ct.author_id = m.member_id')
                       ->join('channels c', 'ct.channel_id = c.channel_id')
                       ->where('entry_id', $id['child_id'])
                       ->get();

      $rel_data = $query->result_array();
      $query->free_result();

      foreach ($rel_data as $datum)
      {
        $entries_relationship_data[$id['parent_id']][$id['field_id']][] = array(
          'channel_id'   => (int) $datum['channel_id'],
          'channel_name' => $datum['channel_name'],
          'entry_id'     => (int) $datum['entry_id'],
          'title'        => $datum['title'],
          'url_title'    => $datum['url_title'],
          'author_id'    => (int) $datum['author_id'],
          'username'     => $datum['username']
        );
      }
    }

    if (isset($entries_relationship_data[$entry_id][$field['field_id']]))
    {
      return $entries_relationship_data[$entry_id][$field['field_id']];
    }

    return array();
  }


  protected function entries_date($in_fluid, $entry_id, $field, $field_data, $field_data_id)
  {
    if ( $in_fluid ) $field_data = call_user_func(array($this, 'get_fluid_channel_data_field'), $field['field_id'], $field_data_id);

    return $this->date_format($field_data);
  }


  protected function entries_text($in_fluid, $entry_id, $field, $field_data, $field_data_id)
  {
    $field_settings = ee()->api_channel_fields->get_settings($field['field_id']);

    if ( $in_fluid ) $field_data = call_user_func(array($this, 'get_fluid_channel_data_field'), $field['field_id'], $field_data_id);

    if ($field_settings['field_content_type'] === 'numeric' || $field_settings['field_content_type'] === 'decimal')
    {
      return floatval($field_data);
    }

    if ($field_settings['field_content_type'] === 'integer')
    {
      return intval($field_data);
    }

    return $field_data;
  }


  protected function entries_assets($in_fluid, $entry_id, $field, $field_data, $entry)
  {
    $field_data = $this->entries_custom_field($in_fluid, $entry_id, $field, $field_data, $entry);

    if ( ! is_array($field_data))
    {
      $field_data = array();
    }

    if (isset($field_data['absolute_total_files']) && $field_data['absolute_total_files'] === 0)
    {
      return array();
    }

    $fields = array(
      'file_id',
      'url',
      'subfolder',
      'filename',
      'extension',
      'date_modified',
      'kind',
      'width',
      'height',
      'size',
      'title',
      'date',
      'alt_text',
      'caption',
      'author',
      'desc',
      'location',
    );

    foreach ($field_data as &$row)
    {
      $source_type = $row['source_type'];
      $filedir_id = $row['filedir_id'];
      // excise any other fields from this row
      $row = array_intersect_key($row, array_flip($fields));
      $row['file_id'] = (int) $row['file_id'];
      $row['date'] = $this->date_format($row['date']);
      $row['date_modified'] = $this->date_format($row['date_modified']);

      $row['manipulations'] = array();

      if ($source_type === 'ee')
      {
        if ( ! isset($this->image_manipulations[$filedir_id]))
        {
          ee()->load->model('file_model');

          $query = ee()->file_model->get_dimensions_by_dir_id($filedir_id);

          $this->image_manipulations[$filedir_id] = $query->result();

          $query->free_result();
        }

        foreach ($this->image_manipulations[$filedir_id] as $manipulation)
        {
          $row['manipulations'][$manipulation->short_name] = pathinfo($row['url'], PATHINFO_DIRNAME).'/_'.$manipulation->short_name.'/'.basename($row['url']);
        }
      }
    }

    return $field_data;
  }


  protected function entries_channel_files($in_fluid, $entry_id, $field, $field_data, $entry)
  {
    $this->entries_channel_files_data = array();

    $field_settings = unserialize(base64_decode($field['field_settings']));
    $field_settings = $field_settings['channel_files'];

    $query = ee()->db->select()
        ->where('entry_id', $entry_id)
        ->where('field_id', $field['field_id'])
        ->order_by('file_order', 'asc')
        ->get('channel_files');

    foreach ($query->result_array() as $row)
    {
      $field_data = array(
        'file_id' => (int) $row['file_id'],
        'url' => $row['filename'],
        'filename' => $row['filename'],
        'extension' => $row['extension'],
        'kind' => $row['mime'],
        'size' => $row['filesize'],
        'title' => $row['title'],
        'date' => $this->date_format($row['date']),
        'author' => (int)$row['member_id'],
        'desc' => $row['description'],
        'primary' => (bool)$row['file_primary'],
        'downloads' => (int)$row['downloads'],
        'custom1' => (isset($row['cffield1']) ? $row['cffield1'] : null),
        'custom2' => (isset($row['cffield2']) ? $row['cffield2'] : null),
        'custom3' => (isset($row['cffield3']) ? $row['cffield3'] : null),
        'custom4' => (isset($row['cffield4']) ? $row['cffield4'] : null),
        'custom5' => (isset($row['cffield5']) ? $row['cffield5'] : null)
      );

      $fieldtype_specific_settings = $field_settings['locations'][$row['upload_service']];

      switch ($row['upload_service'])
      {
        case 'local':
          // get upload folder details from EE
          $query = ee()->db->select('url')
                           ->where('id', $fieldtype_specific_settings['location'])
                           ->get('exp_upload_prefs');

          $result = $query->row_array();
          $query->free_result();

          $base_url = $result['url'] . ($field_settings['entry_id_folder'] == 'yes' ? $entry_id . '/' : '');
          $field_data['url'] = $base_url . $field_data['url'];
          break;
        case 's3':
          if ($fieldtype_specific_settings['cloudfront_domain'] != '')
          {
            $domain = rtrim($fieldtype_specific_settings['cloudfront_domain'], '/');
            $domain = 'http://' . preg_replace('#https?://#', '', $domain);
          }
          else
          {
            $domain = "http://{$fieldtype_specific_settings['bucket']}.s3.amazonaws.com";
          }


          $dir = ($fieldtype_specific_settings['directory'] != '' ? rtrim($fieldtype_specific_settings['directory'], '/') . '/' : '');

          $base_url = "{$domain}/{$dir}{$entry_id}/";
          $field_data['url'] = $base_url . $field_data['url'];
          break;
        case 'cloudfiles':
        case 'ftp':
        case 'sftp':
          require_once PATH_THIRD.'channel_files/locations/cfile_location.php';
          require_once PATH_THIRD."channel_files/locations/{$row['upload_service']}/{$row['upload_service']}.php";

          $class_name = "CF_Location_{$row['upload_service']}";
          $cf = new $class_name($fieldtype_specific_settings);
          $dir = $entry_id;
          $entry_id_folder = (isset($fieldtype_specific_settings['entry_id_folder']) ? $fieldtype_specific_settings['entry_id_folder'] : null);;
          if (isset($entry_id_folder) && $fieldtype_specific_settings['entry_id_folder'] == 'no')
          {
            $dir = FALSE;
          }

          $field_data['url'] = $cf->parse_file_url($dir, $field_data['url']);
          break;
        default:
          break;
      }

      // make file size relevant
      $units = array('B', 'KB', 'MB', 'GB');
      $units_index = 0;
      while ($field_data['size'] >= 1024)
      {
        $field_data['size'] /= 1024;
        $units_index++;
      }
      $field_data['size'] = round($field_data['size']) . ' ' . $units[$units_index];

      $this->entries_channel_files_data[$row['field_id']][] = $field_data;
    }

    $query->free_result();

    if (isset($row['field_id'], $this->entries_channel_files_data[$row['field_id']]))
    {
      return $this->entries_channel_files_data[$row['field_id']];
    }

    return array();
  }


  protected function entries_matrix($in_fluid, $entry_id, $field, $field_data)
  {
    if (is_null($this->entries_matrix_rows))
    {
      $query = ee()->db->where_in('entry_id', $this->entries_entry_ids)
                       ->order_by('row_order')
                       ->get('matrix_data');

      foreach ($query->result_array() as $row)
      {
        if ( ! isset($this->entries_matrix_rows[$row['entry_id']]))
        {
          $this->entries_matrix_rows[$row['entry_id']] = array();
        }

        if ( ! isset($this->entries_matrix_rows[$row['entry_id']][$row['field_id']]))
        {
          $this->entries_matrix_rows[$row['entry_id']][$row['field_id']] = array();
        }

        $this->entries_matrix_rows[$row['entry_id']][$row['field_id']][] = $row;
      }

      $query->free_result();
    }

    if (is_null($this->entries_matrix_cols))
    {
      $query = ee()->db->get('matrix_cols');

      foreach ($query->result_array() as $row)
      {
        $this->entries_matrix_cols[$row['col_id']] = $row;
      }

      $query->free_result();
    }

    $data = array();

    if (isset($this->entries_matrix_rows[$entry_id][$field['field_id']]))
    {
      $field_settings = unserialize(base64_decode($field['field_settings']));

      foreach ($this->entries_matrix_rows[$entry_id][$field['field_id']] as $matrix_row)
      {
        $row = array('row_id' => (int) $matrix_row['row_id']);

        foreach ($field_settings['col_ids'] as $col_id)
        {
          if (isset($this->entries_matrix_cols[$col_id]))
          {
            $row[$this->entries_matrix_cols[$col_id]['col_name']] = $matrix_row['col_id_'.$col_id];
          }
        }

        $data[] = $row;
      }
    }

    return $data;
  }


  protected function entries_playa($in_fluid, $entry_id, $field, $field_data)
  {
    if (is_null($this->entries_playa_data))
    {
      $query = ee()->db->select('parent_entry_id, child_entry_id, parent_field_id')
                       ->where_in('parent_entry_id', $this->entries_entry_ids)
                       ->order_by('rel_order', 'asc')
                       ->get('playa_relationships');

      foreach ($query->result_array() as $row)
      {
        if ( ! isset($this->entries_playa_data[$row['parent_entry_id']]))
        {
          $this->entries_playa_data[$row['parent_entry_id']] = array();
        }

        if ( ! isset($this->entries_playa_data[$row['parent_entry_id']][$row['parent_field_id']]))
        {
          $this->entries_playa_data[$row['parent_entry_id']][$row['parent_field_id']] = array();
        }

        $this->entries_playa_data[$row['parent_entry_id']][$row['parent_field_id']][] = (int) $row['child_entry_id'];
      }

      $query->free_result();
    }

    if (isset($this->entries_playa_data[$entry_id][$field['field_id']]))
    {
      return $this->entries_playa_data[$entry_id][$field['field_id']];
    }

    return array();
  }


  protected function entries_wygwam($in_fluid, $entry_id, $field, $field_data, $entry)
  {
    return $this->entries_custom_field($in_fluid, $entry_id, $field, $field_data, $entry);
  }


  protected function entries_custom_field($in_fluid, $entry_id, $field, $field_data, $entry, $tagdata = ' ')
  {
    ee()->load->add_package_path(ee()->api_channel_fields->ft_paths[$field['field_type']], FALSE);

    ee()->api_channel_fields->setup_handler($field['field_id']);

    ee()->api_channel_fields->apply('_init', array(array(
      'row' => $entry,
      'content_id' => $entry['entry_id'],
      'content_type' => 'channel',
    )));

    $field_data = ee()->api_channel_fields->apply('pre_process', array($field_data));

    if (ee()->api_channel_fields->check_method_exists('replace_tag'))
    {
      require_once PATH_THIRD.'json/libraries/Json_Template.php';

      $ee_tmpl = ee()->TMPL;
      $template = new Json_Template();

      $field_data = ee()->api_channel_fields->apply('replace_tag', array($field_data, array(), $tagdata));

      if ($template->variables)
      {
        $field_data = $template->variables;
      }

      unset($template);
      ee()->TMPL = $ee_tmpl;
    }

    ee()->load->remove_package_path(ee()->api_channel_fields->ft_paths[$field['field_type']]);

    return $field_data;
  }

  public function search()
  {
    $search_id = ee()->TMPL->fetch_param('search_id');

    if ( ! $search_id)
    {
      $search_id = end(ee()->uri->segment_array());
    }

    if ($search_id)
    {
      $query = ee()->db->where('search_id', $search_id)
                       ->limit(1)
                       ->get('exp_search');

      if ($query->num_rows() > 0)
      {
        $search = $query->row_array();

        $query->free_result();

        if (preg_match('/IN \(([\d,]+)\)/', $query->row('query'), $match))
        {
          ee()->TMPL->tagparams['entry_id'] = (strpos($match[1], ',') !== FALSE) ? str_replace(',', '|', $match[1]) : $match[1];

          return $this->entries();
        }
      }
    }

    $this->initialize();

    return $this->respond(array());
  }

  /**
   * Categories
   *
   * @TODO a work in progress, does not work yet
   *
   * @param array|null $params
   *
   * @return string
   */
  public function categories($params = NULL)
  {
    $this->initialize();

    if (is_null($params))
    {
      $params = ee()->TMPL->tagparams;
    }

    ee()->load->helper('array');

    $channel = element('channel', $params);
    $group_id = element('group_id', $params, element('category_group', $params));
    $cat_id = element('cat_id', $params, element('category_id', $params, element('show', $params)));
    $status = element('status', $params);
    $parent_only = element('parent_only', $params);
    $show_empty = element('show_empty', $params, TRUE);
    $joins = array();

    if ($channel)
    {
      ee()->db->join('channel_titles', 'channel_titles.entry_id = category_posts.entry_id');
      ee()->db->join('channels', 'channels.channel_id = channel_titles.channel_id');
      ee()->db->where_in('channels.channel_name', explode('|', $channel));
      $joins[] = 'channels';
      $joins[] = 'channel_titles';
    }

    if ($group_id)
    {
      ee()->db->where_in('categories.group_id', explode('|', $group_id));
    }

    if ($cat_id)
    {
      ee()->db->where_in('categories.cat_id', explode('|', $cat_id));
    }

    if ($status)
    {
      if ( ! in_array('channel_titles', $joins))
      {
        ee()->db->join('channel_titles', 'channel_titles.entry_id = category_posts.entry_id');
      }

      ee()->db->where_in('channel_titles.status', explode('|', $status));
    }

    if ($parent_only)
    {
      ee()->db->where('categories.parent_id', 0);
    }

    if ($show_empty)
    {
      ee()->db->where('count >', 0);
    }
  }

  /**
   * Members
   *
   * @return string
   */
  public function members()
  {
    $this->initialize();

    if ($this->check_xhr_required())
    {
      return '';
    }

    $default_fields = array(
      'm.member_id',
      'm.group_id',
      'm.username',
      'm.screen_name',
      'm.email',
      'm.signature',
      'm.avatar_filename',
      'm.avatar_width',
      'm.avatar_height',
      'm.photo_filename',
      'm.photo_width',
      'm.photo_height',
      'm.join_date',
      'm.last_visit',
      'm.last_activity',
      'm.last_entry_date',
      'm.last_comment_date',
      'm.last_forum_post_date',
      'm.total_entries',
      'm.total_comments',
      'm.total_forum_topics',
      'm.total_forum_posts',
      'm.language',
      'm.timezone',
    );

    if (version_compare(APP_VER, '6', '>='))
    {
      $default_fields[1] = "m.role_id";
    }

    $select = array();

    if ( ! empty($this->fields))
    {
      foreach ($default_fields as $field)
      {
        $key = substr($field, 2);

        if (in_array($key, $this->fields))
        {
          $select[] = $field;
        }
      }
    }
    else
    {
      $select = $default_fields;
    }

		ee()->db->select(implode(', ', $select), FALSE)
            ->from('members m');

    if ($member_ids = ee()->TMPL->fetch_param('member_id'))
    {
      if ($member_ids === 'CURRENT_USER')
      {
        $member_ids = ee()->session->userdata('member_id');
      }

      ee()->db->where_in('m.member_id', explode('|', $member_ids));
    }
    else if (ee()->TMPL->fetch_param('username'))
    {
      ee()->db->where('m.username', ee()->TMPL->fetch_param('username'));
    }

    if (ee()->TMPL->fetch_param('group_id'))
    {
      $m_group_id = version_compare(APP_VER, '6.0.0', '>=') ? 'm.role_id' : 'm.group_id';
      ee()->db->where_in($m_group_id, explode('|', ee()->TMPL->fetch_param('group_id')));
    }

    if (ee()->TMPL->fetch_param('limit'))
    {
      ee()->db->limit(ee()->TMPL->fetch_param('limit'));
    }

    if (ee()->TMPL->fetch_param('offset'))
    {
      ee()->db->offset(ee()->TMPL->fetch_param('offset'));
    }

    $query = ee()->db->get();
    $members = $query->result_array();
    $query->free_result();

    $query = ee()->db->select('m_field_id, m_field_name, m_legacy_field_data')
                     ->get('member_fields');
    $custom_fields = $query->result_array();
    $query->free_result();

    foreach ($members as &$member)
    {
      foreach ($custom_fields as &$field)
      {
        $member[$field['m_field_name']] = call_user_func(array($this, 'members_custom_fields'), $field['m_field_id'], $member['member_id'], $field['m_legacy_field_data']);
      }
    }

    $date_fields = array(
      'join_date',
      'last_visit',
      'last_activity',
      'last_entry_date',
      'last_comment_date',
      'last_forum_post_date'
    );

    $query = ee()->db->select('m_field_name')->where('m_field_type', 'date')
                     ->get('member_fields');
    $custom_date_fields = $query->result_array();
    $query->free_result();

    foreach($custom_date_fields AS &$custom_date_field)
    {
      $date_fields[] = $custom_date_field['m_field_name'];
    }

    foreach ($members as &$member)
    {
      foreach ($date_fields as $field)
      {
        if (isset($member[$field]))
        {
          $member[$field] = $this->date_format($member[$field]);
        }
      }
    }

    return $this->respond($members);
  }


  protected function members_custom_fields($field_id, $member_id, $is_legacy_data)
  {
    $which_table = $is_legacy_data === 'y' ? 'member_data' : 'member_data_field_'.$field_id;

    if ( ee()->db->select('m_field_id_'.$field_id)->where('member_id', $member_id)->get($which_table)->num_rows() > 0 )
    {
      $query = ee()->db->select('m_field_id_'.$field_id)
                       ->where('member_id', $member_id)
                       ->get($which_table);

      $m_field_data = $query->result_array();
      $query->free_result();

      foreach ( $m_field_data AS $m_field_datum )
      {
        return $m_field_datum['m_field_id_'.$field_id];
      }
    }

    return NULL;
  }


  protected function initialize($which = NULL)
  {
    switch($which)
    {
      case 'entries':
        // initialize caches
        $this->entries = array();
        $this->entries_entry_ids = array();
        $this->entries_custom_fields = array();
        $this->entries_matrix_rows = NULL;
        $this->entries_playa_data = NULL;
        $this->entries_channel_files_data = NULL;
        break;
    }

    $this->xhr = ee()->TMPL->fetch_param('xhr') === 'yes';

    $this->terminate = ee()->TMPL->fetch_param('terminate') === 'yes';

    $this->json_pretty_print = ee()->TMPL->fetch_param('json_pretty_print') === 'yes';

    $this->fields = (ee()->TMPL->fetch_param('fields')) ? explode('|', ee()->TMPL->fetch_param('fields')) : array();

    $this->date_format = ee()->TMPL->fetch_param('date_format');

    // get rid of EE formatted dates
    if ($this->date_format && strstr($this->date_format, '%'))
    {
      $this->date_format = str_replace('%', '', $this->date_format);
    }

    $this->jsonp = ee()->TMPL->fetch_param('jsonp') === 'yes';

    ee()->load->library('jsonp');

    $this->callback = (ee()->TMPL->fetch_param('callback') && ee()->jsonp->isValidCallback(ee()->TMPL->fetch_param('callback')))
          ? ee()->TMPL->fetch_param('callback') : NULL;

    $this->content_type = ee()->TMPL->fetch_param('content_type', ($this->jsonp && $this->callback) ? 'application/javascript' : 'application/json');
  }


  protected function check_xhr_required()
  {
    return $this->xhr && ! ee()->input->is_ajax_request();
  }


  protected function date_format($date)
  {
    if ( ! $date)
    {
      return NULL;
    }

    return ($this->date_format) ? date($this->date_format, $date) : $date * 1000;
  }


  protected function respond(array $response, $callback = NULL)
  {
    ee()->load->library('javascript');

    if ($item_root_node = ee()->TMPL->fetch_param('item_root_node'))
    {
      $response_with_nodes = array();

      foreach($response as $item)
      {
        $response_with_nodes[] = array($item_root_node => $item);
      }

      $response = $response_with_nodes;
    }

    if ($root_node = ee()->TMPL->fetch_param('root_node'))
    {
      $response = array($root_node => $response);
    }

    $response = $this->json_pretty_print
    ? json_encode($response,JSON_PRETTY_PRINT)
    : json_encode($response);

    if ( ! is_null($callback))
    {
      $response = call_user_func($callback, $response);
    }

    if ($this->check_xhr_required())
    {
      $response = '';
    }
    else if ($this->jsonp && $this->callback)
    {
      $response = sprintf('%s(%s)', $this->callback, $response);
    }

    if ($this->terminate)
    {
      @header('Content-Type: '.$this->content_type);

      exit($response);
    }

    return $response;
  }
}

/* End of file pi.json.php */
/* Location: ./system/user/addons/json/pi.json.php */
