<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name' => 'JSON',
	'pi_version' => '1.0.3',
	'pi_author' => 'Rob Sanchez',
	'pi_author_url' => 'http://github.com/rsanchez',
	'pi_description' => 'Output ExpressionEngine data in JSON format.',
	'pi_usage' => '
{exp:json:entries channel="news"}

{exp:json:entries channel="products" search:product_size="10"}

{exp:json:members member_id="1"}',
);

class Json
{
	/* settings */
	protected $content_type = 'application/json';
	protected $terminate = FALSE;
	protected $xhr = FALSE;
	protected $fields = array();
	protected $json_keys = array();
	protected $date_format = FALSE;
	protected $jsonp = FALSE;
	protected $callback;
	
	/* caches */
	public $entries;
	public $entries_entry_ids;
	public $entries_custom_fields;
	protected $entries_matrix_rows;
	protected $entries_matrix_cols;
	protected $entries_relationship_data;

	public function __construct()
	{
		$this->EE =& get_instance();
	}
	
	public function entries()
	{
		$this->initialize('entries');
		
		//exit if ajax request is required and not found
		if ($this->check_xhr_required())
		{
			return '';
		}
		
		//instantiate channel module object
		if (empty($this->channel))
		{
			require_once PATH_MOD.'channel/mod.channel'.EXT;
			
			$this->channel = new Channel;
		}
		
		//run through the channel module process to grab the entries
		$this->channel->initialize();

		$this->channel->uri = ($this->channel->query_string != '') ? $this->channel->query_string : 'index.php';

		if ($this->channel->enable['custom_fields'] === TRUE)
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
		
		if (preg_match('/t\.entry_id IN \(([\d,]+)\)/', $this->channel->sql, $match))
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
			
			if (preg_match('/ORDER BY (.*)?/', $this->channel->sql, $match))
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
				
				$this->EE->db->order_by($match[1]);
			}
			
			$query = $this->channel->query = $this->EE->db->get();
			
			if ($this->EE->TMPL->fetch_param('show_categories') === 'yes')
			{
				$this->channel->fetch_categories();
				
				if ($this->EE->TMPL->fetch_param('show_category_group'))
				{
					$show_category_group = explode('|', $this->EE->TMPL->fetch_param('show_category_group'));
				}
			}
			
			$this->entries = $query->result_array();
			
			$query->free_result();
			
			foreach ($this->entries as &$entry)
			{
				//format dates as javascript unix time (in microseconds!)
				if (isset($entry['entry_date']))
				{
					$entry['entry_date'] = ($this->date_format) ? date($this->date_format, $entry['entry_date']) : (int) ($entry['entry_date'].'000');
				}
				
				if (isset($entry['edit_date']))
				{
					$entry['edit_date'] = strtotime($entry['edit_date']);
					$entry['edit_date'] = ($this->date_format) ? date($this->date_format, $entry['edit_date']) : (int) ($entry['edit_date'].'000');
				}
				
				if (isset($entry['expiration_date']))
				{
					if($entry['expiration_date'])
					{
						$entry['expiration_date'] = ($this->date_format) ? date($this->date_format, $entry['expiration_date']) : (int) ($entry['expiration_date'].'000');
					}
					else $entry['expiration_date'] = NULL;
					
				}
				
				foreach ($this->entries_custom_fields as &$field)
				{
					//call our custom callback for this fieldtype if it exists
					if (isset($entry[$field['field_name']]) && is_callable(array($this, 'entries_'.$field['field_type'])))
					{
						$entry[$field['field_name']] = call_user_func(array($this, 'entries_'.$field['field_type']), $entry['entry_id'], $field, $entry[$field['field_name']]);
					}
				}
				
				if ($this->EE->TMPL->fetch_param('show_categories') === 'yes')
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
								'category_id' => $raw_category[0],
								'parent_id' => $raw_category[1],
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

				foreach($this->json_keys as $field_name => $json_key)
				{
					if($field_name != $json_key)
					{
						$entry[$json_key] = $entry[$field_name];
						unset($entry[$field_name]);
					}
				}
				
			}
		}
		
		$this->EE->load->library('javascript');
		
		$data = $this->EE->javascript->generate_json($this->entries, TRUE);
		
		$this->EE->load->library('typography');
		
		$data = $this->EE->typography->parse_file_paths($data);
		
		return $this->respond($data);
	}
	
	protected function entries_matrix($entry_id, $field, $field_data)
	{
		if (is_null($this->entries_matrix_rows))
		{
			$query = $this->EE->db->where_in('entry_id', $this->entries_entry_ids)
					      ->order_by('row_order')
					      ->get('matrix_data');
			
			$this->entries_matrix_rows = $query->result_array();
			
			$query->free_result();
		}
		
		if (is_null($this->entries_matrix_cols))
		{
			$query = $this->EE->db->get('matrix_cols');
			
			foreach ($query->result_array() as $row)
			{
				$this->entries_matrix_cols[$row->col_id] = $row;
			}
			
			$query->free_result();
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
						$row[$this->entries_matrix_cols[$col_id]->col_name] = $matrix_row['col_id_'.$col_id];
					}
				}
				
				$data[] = $row;
			}
		}
		
		return $data;
	}

	protected function entries_rel($entry_id, $field, $field_data)
	{
		if (is_null($this->entries_relationship_data))
		{
			$query = $this->EE->db->select('rel_child_id, rel_id')
					      ->where('rel_parent_id', $entry_id)
					      ->get('relationships');
			
			$this->entries_relationship_data = array();
			
			foreach ($query->result() as $row)
			{
				$this->entries_relationship_data[$row->rel_id] = $row->rel_child_id;
			}
			
			$query->free_result();
		}
		
		if ( ! isset($this->entries_relationship_data[$field_data]))
		{
			return NULL;
		}
		
		return $this->entries_relationship_data[$field_data];
	}
	
	public function search()
	{
		$search_id = $this->EE->TMPL->fetch_param('search_id');
		
		if ( ! $search_id)
		{
			$search_id = end($this->EE->uri->segment_array());
		}
        
		if ($search_id)
		{
			$query = $this->EE->db->where('search_id', $search_id)
					      ->limit(1)
					      ->get('exp_search');
			
			if ($query->num_rows() > 0)
			{
				$search = $query->row_array();
				
				$query->free_result();
				
				if (preg_match('/IN \(([\d,]+)\)/', $query->row('query'), $match))
				{
					$this->EE->TMPL->tagparams['entry_id'] = (strpos($match[1], ',') !== FALSE) ? str_replace(',', '|', $match[1]) : $match[1];
					
					return $this->entries();
				}
			}
		}
		
		$this->initialize();
		
		return $this->response(array());
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
			$params = $this->EE->TMPL->tagparams;
		}
		
		$this->EE->load->helper('array');
		
		$channel = element('channel', $params);
		$group_id = element('group_id', $params, element('category_group', $params));
		$cat_id = element('cat_id', $params, element('category_id', $params, element('show', $params)));
		$status = element('status', $params);
		$parent_only = element('parent_only', $params);
		$show_empty = element('show_empty', $params, TRUE);
		$joins = array();
		
		if ($channel)
		{
			$this->EE->db->join('channel_titles', 'channel_titles.entry_id = category_posts.entry_id');
			$this->EE->db->join('channels', 'channels.channel_id = channel_titles.channel_id');
			$this->EE->db->where_in('channels.channel_name', explode('|', $channel));
			$joins[] = 'channels';
			$joins[] = 'channel_titles';
		}
		
		if ($group_id)
		{
			$this->EE->db->where_in('categories.group_id', explode('|', $group_id));
		}
		
		if ($cat_id)
		{
			$this->EE->db->where_in('categories.cat_id', explode('|', $cat_id));
		}
		
		if ($status)
		{
			if ( ! in_array('channel_titles', $joins))
			{
				$this->EE->db->join('channel_titles', 'channel_titles.entry_id = category_posts.entry_id');
			}
			
			$this->EE->db->where_in('channel_titles.status', explode('|', $status));
		}
		
		if ($parent_only)
		{
			$this->EE->db->where('categories.parent_id', 0);
		}
		
		if ($show_empty)
		{
			$this->EE->db->where('count >', 0);
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
			'm.daylight_savings',
			'm.bday_d',
			'm.bday_m',
			'm.bday_y',
		);
			
		$query = $this->EE->db->select('m_field_id, m_field_name')
				      ->get('member_fields');
		
		$custom_fields = $query->result_array();
		
		$query->free_result();
		
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
		
		foreach ($custom_fields as &$field)
		{
			if (empty($this->fields) || in_array($field['m_field_name'], $this->fields))
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
		
		$query = $this->EE->db->get();
		
		$members = $query->result_array();
		
		$query->free_result();
		
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
		
		return $this->respond($members);
	}
	
	protected function initialize($which = NULL)
	{
		switch($which)
		{
			case 'entries':
				//initialize caches
				$this->entries = array();
				$this->entries_entry_ids = array();
				$this->entries_custom_fields = array();
				$this->entries_matrix_rows = NULL;
				$this->entries_relationship_data = NULL;
				break;
		}
		
		$this->xhr = $this->EE->TMPL->fetch_param('xhr') === 'yes';
		
		$this->terminate = $this->EE->TMPL->fetch_param('terminate') === 'yes';
		
		$this->fields = ($this->EE->TMPL->fetch_param('fields')) ? explode('|', $this->EE->TMPL->fetch_param('fields')) : array();
		foreach($this->fields as $field)
		{
			$name = explode('=',$field);
			$this->json_keys[$name[0]] = isset($name[1]) ? $name[1] : $name[0];
		}
		$this->fields = array_keys($this->json_keys);
		
		$this->date_format = $this->EE->TMPL->fetch_param('date_format', false);

		$this->jsonp = $this->EE->TMPL->fetch_param('jsonp') === 'yes';
		
		$this->EE->load->library('jsonp');
		
		$this->callback = ($this->EE->TMPL->fetch_param('callback') && $this->EE->jsonp->isValidCallback($this->EE->TMPL->fetch_param('callback')))
				  ? $this->EE->TMPL->fetch_param('callback') : NULL;
		
		$this->content_type = $this->EE->TMPL->fetch_param('content_type', ($this->jsonp && $this->callback) ? 'application/javascript' : 'application/json');
	}
	
	protected function check_xhr_required()
	{
		return $this->xhr && ! $this->EE->input->is_ajax_request();
	}
	
	protected function respond($response)
	{
		$response = ( ! is_string($response)) ? $this->EE->javascript->generate_json($response, TRUE) : $response;
		
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
/* Location: ./system/expressionengine/third_party/json/pi.json.php */ 