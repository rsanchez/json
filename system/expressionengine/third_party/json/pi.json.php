<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name' => 'JSON',
	'pi_version' => '1.0.1',
	'pi_author' => 'Rob Sanchez',
	'pi_author_url' => 'http://barrettnewton.com/',
	'pi_description' => 'Output ExpressionEngine data in JSON format.',
	'pi_usage' => Json::usage()
);

class Json
{
	public $return_data = '';

	public function Json()
	{
		$this->EE = get_instance();
	}
	
	public function entries()
	{
		if ($this->EE->TMPL->fetch_param('xhr') == 'yes' && ! $this->EE->input->is_ajax_request())
		{
			return '';
		}
		
		$entries = array();
		
		if ($this->EE->TMPL->fetch_param('fields'))
		{
			$fields = explode('|', $this->EE->TMPL->fetch_param('fields'));
		}
		
		if (preg_match('/t\.entry_id IN \(([\d,]+)\)/', $this->channel_sql(), $match))
		{
			$entry_ids = explode(',', $match[1]);
			
			$custom_fields = $this->EE->db->select('channel_fields.*, channels.channel_id')
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
			
			foreach ($custom_fields as &$field)
			{
				if (empty($fields) || in_array($field['field_name'], $fields))
				{
					$select[] = 'd.'.$this->EE->db->protect_identifiers('field_id_'.$field['field_id']).' AS '.$this->EE->db->protect_identifiers($field['field_name']);
				}
			}
			
			$entries = $this->EE->db->select(implode(', ', $select), FALSE)
						->from('channel_titles t')
						->join('channel_data d', 't.entry_id = d.entry_id')
						->where_in('t.entry_id', $entry_ids)
						->get()
						->result_array();
			
			foreach ($entries as &$entry)
			{
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
				
				/*
				foreach ($custom_fields as &$field)
				{
					$entry[$field['field_name']] = $this->replace_tag($entry, $field, $entry['field_id_'.$field['field_id']]);
					
					unset($entry['field_id_'.$field['field_id']]);
				}
				*/
			}
		}
		
		$this->EE->load->library('javascript');
		
		if ($this->EE->TMPL->fetch_param('terminate') == 'yes')
		{
			return $this->EE->output->send_ajax_response($entries);
		}
		
		return $this->EE->javascript->generate_json($entries, TRUE);
	}
	
	private function channel_sql()
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
			'm.daylight_savings',
			'm.bday_d',
			'm.bday_m',
			'm.bday_y',
		);
			
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
		
		return $this->EE->javascript->generate_json($members, TRUE);
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
/* End of file pi.plugin.php */ 
/* Location: ./system/expressionengine/third_party/plugin/pi.plugin.php */ 