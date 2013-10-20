<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name' => 'JSON',
	'pi_version' => '1.0.3',
	'pi_author' => 'Rob Sanchez',
	'pi_author_url' => 'https://github.com/rsanchez',
	'pi_description' => 'Output ExpressionEngine data in JSON format.',
	'pi_usage' => Json::usage()
);

class Json
{
	public $return_data = '';
	
	public $entries;
	public $entries_entry_ids;
	public $entries_custom_fields;
	protected $entries_matrix_rows;
	protected $entries_matrix_cols;
	protected $entries_relationship_data;

	public function Json()
	{
		$this->EE = get_instance();
	}
	
	protected function entries_initialize()
	{
		$this->entries = array();
		$this->entries_entry_ids = array();
		$this->entries_custom_fields = array();
		$this->entries_matrix_rows = NULL;
		$this->entries_relationship_data = NULL;
		//$this->entries_matrix_cols = NULL;
	}
	
	public function entries()
	{
		$this->entries_initialize();
		
		if ($this->EE->TMPL->fetch_param('xhr') == 'yes' && ! $this->EE->input->is_ajax_request())
		{
			return '';
		}
		
		if ($this->EE->TMPL->fetch_param('fields'))
		{
			$fields = explode('|', $this->EE->TMPL->fetch_param('fields'));
		}
		
		$sql = $this->entries_channel_sql();
		
		if (preg_match('/t\.entry_id IN \(([\d,]+)\)/', $sql, $match))
		{
			$this->entries_entry_ids = explode(',', $match[1]);
			
			$this->entries_custom_fields = $this->EE->db->select('channel_fields.*, channels.channel_id')
								    ->from('channel_fields')
								    ->join('channels', 'channel_fields.group_id = channels.field_group')
								    ->where_in('channels.channel_name', explode('|', $this->EE->TMPL->fetch_param('channel')))
								    ->get()
								    ->result_array();
			
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
			
			if ( ! empty($fields))
			{
				foreach ($default_fields as $field)
				{
					$key = substr($field, 2);
					
					if (in_array($key, $fields))
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
				if (empty($fields) || in_array($field['field_name'], $fields))
				{
					$select[] = 'wd.'.$this->EE->db->protect_identifiers('field_id_'.$field['field_id']).' AS '.$this->EE->db->protect_identifiers($field['field_name']);
				}
			}
			
			//we need entry_id, always grab it
			if ( ! in_array('t.entry_id', $select))
			{
				$select[] = 't.entry_id';
			}
			
			$this->EE->db->select(implode(', ', $select), FALSE)
				     ->from('channel_titles t')
				     ->join('channel_data wd', 't.entry_id = wd.entry_id')
				     ->where_in('t.entry_id', $this->entries_entry_ids);
			
			if (preg_match('/ORDER BY (.*)?/', $sql, $match))
			{
				if (strpos($match[1], 'w.') !== FALSE)
				{
					$this->EE->db->join('channels w', 't.channel_id = w.channel_id');
				}
				
				if (strpos($match[1], 'm.') !== FALSE)
				{
					$this->EE->db->join('members m', 'm.member_id = t.author_id');
				}
				
				if (strpos($match[1], 'md.') !== FALSE)
				{
					$this->EE->db->join('member_data md', 'm.member_id = md.member_id');
				}
				
				if ($this->channel->display_by === 'week' && strpos($match[1], 'yearweek') !== FALSE)
				{
					$yearweek = TRUE;
					
					$offset = $this->EE->localize->zones[$this->EE->config->item('server_timezone')] * 3600;
					
					$format = ($this->EE->TMPL->fetch_param('start_day') === 'Monday') ? '%x%v' : '%X%V';
					
					$this->EE->db->select("DATE_FORMAT(FROM_UNIXTIME(entry_date + $offset), '$format') AS yearweek", FALSE);
				}
				
				$this->EE->db->order_by($match[1]);
			}
			
			$this->entries = $this->EE->db->get()->result_array();
			
			foreach ($this->entries as &$entry)
			{
				if (isset($yearweek))
				{
					unset($entry['yearweek']);
				}
				
				if (isset($entry['entry_date']))
				{
					$entry['entry_date'] .= '000';
				}
				
				if (isset($entry['edit_date']))
				{
					$entry['edit_date'] = strtotime($entry['edit_date']).'000';
				}
				
				if (isset($entry['expiration_date']))
				{
					$entry['expiration_date'] = ($entry['expiration_date']) ? $entry['expiration_date'].'000' : '';
				}
				
				foreach ($this->entries_custom_fields as &$field)
				{
//					$entry[$field['field_name']] = $this->replace_tag($entry, $field, $entry['field_id_'.$field['field_id']]);

					if (isset($entry[$field['field_name']]) && is_callable(array($this, 'entries_'.$field['field_type'])))
					{
						$entry[$field['field_name']] = call_user_func(array($this, 'entries_'.$field['field_type']), $entry['entry_id'], $field, $entry[$field['field_name']]);
					}
				}
			}
		}
		
		$this->EE->load->library('javascript');
		
		$data = json_encode($this->entries);
		
		$this->EE->load->library('typography');
		
		$data = $this->EE->typography->parse_file_paths($data);
		
		if ($this->EE->TMPL->fetch_param('terminate') == 'yes')
		{
			if ($this->EE->config->item('send_headers') == 'y')
			{
				@header('Content-Type: application/json');
			}
			
			exit($data);
		}
		
		return $data;
	}
	
	private function entries_channel_sql()
	{
		if (empty($this->channel))
		{
			require_once PATH_MOD.'channel/mod.channel'.EXT;
			
			$this->channel = new Channel;
		}
		
		$this->channel->initialize();

		$this->channel->uri = ($this->channel->query_string != '') ? $this->channel->query_string : 'index.php';

		if ($this->channel->enable['custom_fields'] == TRUE)
		{
			$this->channel->fetch_custom_channel_fields();
		}

		$save_cache = FALSE;

		if ($this->EE->config->item('enable_sql_caching') == 'y')
		{
			if (FALSE == ($this->channel->sql = $this->channel->fetch_cache()))
			{
				$save_cache = TRUE;
			}
			else
			{
				if ($this->EE->TMPL->fetch_param('dynamic') != 'no')
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
		
		return $this->channel->sql;
	}
	
	protected function entries_matrix($entry_id, $field, $field_data)
	{
		if (is_null($this->entries_matrix_rows))
		{
			$this->entries_matrix_rows = $this->EE->db->where_in('entry_id', $this->entries_entry_ids)
								  ->order_by('row_order')
								  ->get('matrix_data')
								  ->result_array();
		}
		
		if (is_null($this->entries_matrix_cols))
		{
			$cols = $this->EE->db->get('matrix_cols')->result_array();
			
			foreach ($cols as $col)
			{
				$this->entries_matrix_cols[$col['col_id']] = $col;
			}
			
			unset($cols);
		}
		
		$data = array();
		
		foreach ($this->entries_matrix_rows as &$matrix_row)
		{
			if ($matrix_row['entry_id'] == $entry_id && $matrix_row['field_id'] == $field['field_id'])
			{
				$field_settings = unserialize(base64_decode($field['field_settings']));
				
				$row = array('row_id' => $matrix_row['row_id']);
				
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

	protected function entries_rel($entry_id, $field, $field_data)
	{
        $data = NULL;

        if (!is_null($field_data))
        {
            $this->entries_relationship_data = $this->EE->db->select('rel_child_id')
                                            ->where('rel_id', $field_data)
                                            ->get('relationships');
        }

        if ($this->entries_relationship_data->num_rows() > 0)
        {
            $data = $this->entries_relationship_data->row('rel_child_id');
        }

        return $data;

	}
	
	public function members()
	{
		if ($this->EE->TMPL->fetch_param('xhr') == 'yes' && ! $this->EE->input->is_ajax_request())
		{
			return '';
		}
		
		if ($this->EE->TMPL->fetch_param('fields'))
		{
			$fields = explode('|', $this->EE->TMPL->fetch_param('fields'));
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
			'm.url',
			'm.location',
			'm.occupation',
			'm.interests',
			'm.bio',
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
			'm.bday_d',
			'm.bday_m',
			'm.bday_y',
		);

		if (version_compare(APP_VER, '2.6', '<'))
		{
			$default_fields[] = 'm.daylight_savings';
		}
			
		$custom_fields = $this->EE->db->select('m_field_id, m_field_name')
						->from('member_fields')
						->get()
						->result_array();
		
		$select = array();
		
		if ( ! empty($fields))
		{
			foreach ($default_fields as $field)
			{
				$key = substr($field, 2);
				
				if (in_array($key, $fields))
				{
					$select[] = $field;
				}
			}
		}
		else
		{
			$select = $default_fields;
		}
		
		foreach ($custom_fields as &$field)
		{
			if (empty($fields) || in_array($field['m_field_name'], $fields))
			{
				$select[] = 'd.'.$this->EE->db->protect_identifiers('m_field_id_'.$field['m_field_id']).' AS '.$this->EE->db->protect_identifiers($field['m_field_name']);
			}
		}
		
		$this->EE->db->select(implode(', ', $select), FALSE)
					->from('members m')
					->join('member_data d', 'm.member_id = d.member_id');
		
		if ($this->EE->TMPL->fetch_param('member_id'))
		{
			$this->EE->db->where_in('m.member_id', explode('|', $this->EE->TMPL->fetch_param('member_id')));
		}
		else if ($this->EE->TMPL->fetch_param('username'))
		{
			$this->EE->db->where_in('m.member_id', explode('|', $this->EE->TMPL->fetch_param('member_id')));
		}
		
		if ($this->EE->TMPL->fetch_param('group_id'))
		{
			$this->EE->db->where_in('m.group_id', explode('|', $this->EE->TMPL->fetch_param('group_id')));
		}
		
		if ($this->EE->TMPL->fetch_param('limit'))
		{
			$this->EE->db->limit($this->EE->TMPL->fetch_param('limit'));
		}
		
		$members = $this->EE->db->get()->result_array();
		
		$date_fields = array(
			'join_date',
			'last_visit',
			'last_activity',
			'last_entry_date',
			'last_comment_date',
			'last_forum_post_date'
		);
		
		foreach ($members as &$member)
		{
			foreach ($date_fields as $field)
			{
				if (isset($member[$field]))
				{
					$member[$field] = ($member[$field]) ? $member[$field].'000' : '';
				}
			}
		}
		
		$this->EE->load->library('javascript');
		
		if ($this->EE->TMPL->fetch_param('terminate') == 'yes')
		{
			return $this->EE->output->send_ajax_response($members);
		}
		
		return json_encode($members);
	}
	
	public static function usage()
	{
		ob_start(); 
?>
{exp:json:entries channel="news"}

{exp:json:entries channel="products" search:product_size="10"}

{exp:json:members member_id="1"}
<?php
		$buffer = ob_get_contents();
		      
		ob_end_clean(); 
	      
		return $buffer;
	}
}
/* End of file pi.json.php */ 
/* Location: ./system/expressionengine/third_party/json/pi.json.php */ 
