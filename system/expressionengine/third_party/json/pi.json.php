<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
    'pi_name' => 'JSON',
    'pi_version' => '2.0.0',
    'pi_author' => 'Rob Sanchez',
    'pi_author_url' => 'https://github.com/rsanchez',
    'pi_description' => 'Output ExpressionEngine data in JSON format.',
    'pi_usage' => '
{exp:json:entries channel="news"}

{exp:json:entries channel="products" search:product_size="10"}
',
);

Phar::loadPhar(__DIR__.'/phar/deep.phar');

use rsanchez\Deep\Plugin\BasePlugin;
use rsanchez\Deep\Model\Title;
use Carbon\Carbon;

class Json extends BasePlugin
{
    /* settings */
    protected $content_type = 'application/json';
    protected $terminate = FALSE;
    protected $xhr = FALSE;
    protected $fields = array();
    protected $date_format = FALSE;
    protected $jsonp = FALSE;
    protected $callback;

    public function entries()
    {
        $this->initialize();

        //exit if ajax request is required and not found
        if ($this->check_xhr_required())
        {
            return '';
        }

        $callback = null;

        if ( ! ee()->TMPL->fetch_param('disable'))
        {
            ee()->TMPL->tagparams['disable'] = 'member_data|categories';
        }

        $hidden = [
            'view_count_one',
            'view_count_two',
            'view_count_three',
            'view_count_four',
            'allow_comments',
            'sticky',
            'year',
            'month',
            'day',
            'comment_expiration_date',
            'recent_comment_date',
            'comment_total',
            'author',
        ];

        if (ee()->TMPL->fetch_param('show_categories') === 'yes')
        {
            $callback = function ($query) {
                $query->withCategoryFields();
            };

            $hidden[] = 'categories';
        }

        $entries = $this->getEntries($callback);

        if ($this->date_format)
        {
            Carbon::setToStringFormat($this->date_format);
        }

        if ($this->fields)
        {
            $data = array();

            foreach ($entries as $entry)
            {
                $row = array();

                foreach ($this->fields as $field)
                {
                    $row[$field] = $entry->$field;
                }

                $data[] = $row;
            }
        }
        else
        {
            $entries->each(function ($entry) use ($hidden) {
                $entry->setHidden(array_merge($entry->getHidden(), $hidden));
            });

            $data = $entries->toArray();
        }

        return $this->respond($data);
    }

    protected function initialize($which = NULL)
    {
        $this->xhr = ee()->TMPL->fetch_param('xhr') === 'yes';

        $this->terminate = ee()->TMPL->fetch_param('terminate') === 'yes';

        $this->fields = (ee()->TMPL->fetch_param('fields')) ? explode('|', ee()->TMPL->fetch_param('fields')) : array();

        $this->date_format = ee()->TMPL->fetch_param('date_format');

        // get rid of EE formatted dates
        if ($this->date_format && strstr($this->date_format, '%'))
        {
            $this->date_format = str_replace('%', '', $this->date_format);
        }

        $this->jsonp = ee()->TMPL->fetch_param('jsonp') === 'yes';

        ee()->load->library('jsonp');

        $callback = ee()->TMPL->fetch_param('callback');

        if ($callback && ee()->jsonp->isValidCallback($callback))
        {
            $this->callback = $callback;
        }

        $this->content_type = ee()->TMPL->fetch_param('content_type', ($this->jsonp && $this->callback) ? 'application/javascript' : 'application/json');
    }

    protected function check_xhr_required()
    {
        return $this->xhr && ! ee()->input->is_ajax_request();
    }

    protected function respond(array $response, $callback = NULL)
    {
        ee()->load->library('javascript');

        $response = json_encode($response);

        ee()->load->library('typography');

        ee()->typography->parse_images = TRUE;

        $response = ee()->typography->parse_file_paths($response);

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
/* Location: ./system/expressionengine/third_party/json/pi.json.php */
