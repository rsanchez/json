<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name' => 'JSON',
	'pi_version' => '1.0.0',
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
		require_once PATH_MOD.'channel/mod.channel'.EXT;
		
		$channel = new Channel;
		
		$channel->initialize();

		$channel->uri = ($channel->query_string != '') ? $channel->query_string : 'index.php';

		if ($channel->enable['custom_fields'] == TRUE)
		{
			$channel->fetch_custom_channel_fields();
		}

		$save_cache = FALSE;

		if ($this->EE->config->item('enable_sql_caching') == 'y')
		{
			if (FALSE == ($channel->sql = $channel->fetch_cache()))
			{
				$save_cache = TRUE;
			}
			else
			{
				if ($this->EE->TMPL->fetch_param('dynamic') != 'no')
				{
					if (preg_match("#(^|\/)C(\d+)#", $channel->query_string, $match) OR in_array($channel->reserved_cat_segment, explode("/", $channel->query_string)))
					{
						$channel->cat_request = TRUE;
					}
				}
			}
		}
		
		if ( ! $channel->sql)
		{
			$channel->build_sql_query();
		}
		
		$data = array();
		
		if (preg_match('/t\.entry_id IN \(([\d,]+)\)/', $channel->sql, $match))
		{
			$entry_ids = explode(',', $match[1]);
			
			$this->EE->db->select('channel_fields.*, channels.channel_id')
					->from('channel_fields')
					->join('channels', 'channel_fields.group_id = channels.field_group')
					->where_in('channels.channel_name', explode('|', $this->EE->TMPL->fetch_param('channel')));
			
			$query = $this->EE->db->get();
			
			$select = array(
				't.title',
				't.url_title',
				't.entry_id',
				't.channel_id',
				//'c.channel_name',
				't.author_id',
				't.status',
				't.entry_date',
				't.edit_date',
				't.expiration_date',
				//'d.*',
			);
			
			foreach ($query->result() as $field)
			{
				$select[] = 'd.'.$this->EE->db->protect_identifiers('field_id_'.$field->field_id).' AS '.$this->EE->db->protect_identifiers($field->field_name);
				//$select[] = 'd.field_id_'.$this->EE->db->protect_identifiers('field_id_'.$field->field_id);
			}
			
			$this->EE->db->select(implode(', ', $select), FALSE)
					->from('channel_titles t')
					//->join('channels c', 't.channel_id = c.channel_id')
					->join('channel_data d', 't.entry_id = d.entry_id')
					->where_in('t.entry_id', $entry_ids);
					
			$data = $this->EE->db->get()->result_array();
			
			/*
			foreach ($data as $i => $row)
			{
				foreach ($query->result_array() as $field)
				{
					$data[$i][$field['field_name']] = $this->replace_tag($row, $field, $row['field_id_'.$field['field_id']]);
					
					unset($data[$i]['field_id_'.$field['field_id']]);
				}
			}
			*/
		}
		
		$this->EE->load->library('javascript');
		
		$this->EE->output->send_ajax_response($data);
	}
	
	public static function usage()
	{
		ob_start(); 
?>
{exp:json:entries channel="news"}

{exp:json:entries channel="products" search:product_size="10"}
<?php
		$buffer = ob_get_contents();
		      
		ob_end_clean(); 
	      
		return $buffer;
	}
}
/* End of file pi.plugin.php */ 
/* Location: ./system/expressionengine/third_party/plugin/pi.plugin.php */ 