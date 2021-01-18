# JSON #

Output ExpressionEngine data in JSON format.

## Requirements

- ExpressionEngine 6
- For ExpressionEngine 4.x and 5.x use JSON version [2.0.0](https://github.com/zignature/json/releases/tag/v2.0.0).
- For ExpressionEngine 2.6+ and 3.x use JSON version [1.1.9](https://github.com/zignature/json/releases/tag/v1.1.9).

## Warning

***Not tested with Assets, Matrix and Playa fieldtypes!***

I don't own Assets, Matrix and Playa modules, so if you use Assets, Matrix or Playa fields I recommend to verify whether changes to the code are required and to test this plugin on a local or development server before using it on a production/live server.
Since this plugin only outputs data I don't expect any damage but I will not accept any liability for any problems risen from using this plugin.

## Installation

* Copy the `/system/user/addons/json/` folder to your `/system/user/addons/` folder

## Global Parameters

### `xhr="yes"`

Set xhr to yes to only output data when an XMLHttpRequest is detected. Do not set this to yes if you are using JSONP, as JSONP requests are not true XHMLHttpRequests.

### `terminate="yes"`

Set terminate to yes to terminate the template and output your json immediately, with Content-Type headers.

### `fields="title|url_title"`

Specify which fields you wish to have in the array. Separate multiple fields by a pipe character. If you do not specify fields, you will get all of the default fields' data. The primary key (`entry_id` for entries, `member_id` for members) will always be present and cannot be suppressed by this parameter.

### `content_type="text/javascript"`

Set a custom Content-Type header. The default is "application/json", or "application/javascript" if using JSONP. Headers are only sent when terminate is set to "yes".

### `jsonp="yes"`

Set jsonp to yes to enable a JSONP response. You must also specify a valid callback. You are encouraged to set terminate to yes when using JSONP.

### `callback="yourCallbackFunction"`

Set a callback function for your JSONP request. Since query strings do not work out-of-the-box in EE, you may want to consider using a URL segment to specify your callback, ie. callback="{segment_3}", rather than the standard ?callback=foo method.

### `date_format="U"`

Use a different date format. Note: always returns dates as string.

### `root_node="items"`

By default, JSON will output a simple array of items. Use this parameter to make the response into a JSON object whose specified property is the array of items.

Using this parameter will turn this:

```
[
  {
    "title": "Foo",
    "entry_id": 1
  },
  {
    "title": "Bar",
    "entry_id": 2
  }
]
```

Into this:

```
{
  "items": [
    {
      "title": "Foo",
      "entry_id": 1
    },
    {
      "title": "Bar",
      "entry_id": 2
    }
  ]
}
```

### `item_root_node="item"`

By default, each item in the response array is a simple object. Using this parameter turns each item into a JSON object whose specified property is the item object.

Using this parameter will turn this:

```
[
  {
    "title": "Foo",
    "entry_id": 1
  },
  {
    "title": "Bar",
    "entry_id": 2
  },
]
```

Into this:

```
[
  {
    "item": {
      "title": "Foo",
      "entry_id": 1
    }
  },
  {
    "item": {
        "title": "Bar",
        "entry_id": 2
    }
  }
]
```

## Dates

By default, the date fields are in unix timestamp format, accurate to milliseconds. Use the Javascript Date object in combination with date field data:

```
for (i in data) {
  var entryDate = new Date(data[i].entry_date);
}
```

If you require a different output format for the date fields, set the date_format= parameter. This uses the php date() function. common formats include "U" (unix timestamp in seconds), "c" (ISO 8601) or "Y-m-d H:i" (2011-12-24 19:06).

## json:entries

```
{exp:json:entries channel="news"}
```

json:entries is a single tag, not a tag pair. Use channel:entries parameters to filter your entries.

#### json:entries Default Fields

```
title
url_title
entry_id
channel_id
author_id
status
entry_date
edit_date
expiration_date
Plus all of the custom fields associated with that channel
```

#### json:entries Parameters

See [channel:entries parameters](https://docs.expressionengine.com/latest/channels/entries.html#parameters).

##### `show_categories="yes"`

This will add categories to the entries response

##### `show_category_group="1|2"`

When paired with show_categories="yes", this will display only categories from the specified groups.

#### json:entries Custom Fields

Most custom fields will just return the raw column data from the `exp_channel_data` database table. The following fieldtypes will provide custom data. You *must* specify the `channel` parameter to get custom fields.

##### Matrix

The data will include an array of Matrix rows, including the row_id and the column names:

```
your_matrix_field: [
  {
    row_id: 1,
    my_col_name: "foo",
    other_col_name: "bar"
  },
  {
    row_id: 2,
    my_col_name: "baz",
    other_col_name: "qux"
  }
]
```

##### Grid

The data will include an array of Grid rows, including the row_id and the column names:

```
your_grid_field: [
  {
    row_id: 1,
    my_col_name: "foo",
    other_col_name: "bar"
  },
  {
    row_id: 2,
    my_col_name: "baz",
    other_col_name: "qux"
  }
]
```

##### Relationships

The data will include an array of related entry IDs:

```
your_relationships_field: [1, 2]
```

##### Playa

The data will include an array of related entry IDs:

```
your_playa_field: [1, 2]
```

##### Assets

```
your_assets_field: [
  {
    "file_id": 1,
    "url": "http://yoursite.com/uploads/flower.jpg",
    "subfolder": "",
    "filename": "flower",
    "extension": "jpg",
    "date_modified": 1389459034000,
    "kind": "image",
    "width": "300",
    "height": "300",
    "size": "65 KB",
    "title": null,
    "date": 1389459034000,
    "alt_text": null,
    "caption": null,
    "author": null,
    "desc": null,
    "location": null,
    "manipulations": {
      "medium": "http://yoursite.com/uploads/_medium/flower.jpg",
      "large": "http://yoursite.com/uploads/_large/flower.jpg"
    }
  },
  {
    "file_id": 2,
    "url": "http://yoursite.com/uploads/dog.jpg",
    "subfolder": "",
    "filename": "dog",
    "extension": "jpg",
    "date_modified": 1389466147000,
    "kind": "image",
    "width": "300",
    "height": "300",
    "size": "75 KB",
    "title": null,
    "date": 1389466147000,
    "alt_text": null,
    "caption": null,
    "author": null,
    "desc": null,
    "location": null,
    "manipulations": {
      "medium": "http://yoursite.com/uploads/_medium/dog.jpg",
      "large": "http://yoursite.com/uploads/_large/dog.jpg"
    }
  }
]
```

*NOTE: image manipulation urls are only available to Assets files store locally, not on Amazon S3 or Google Storage.*

##### Channel Files

```
your_channel_files_field: [
  {
    "file_id": 1,
    "url": "http://yoursite.com/uploads/flower.jpg",
    "filename": "flower.jpg",
    "extension": "jpg",
    "kind": "image\/jpeg",
    "size": "65 KB",
    "title": "flower",
    "date": 1389459034000,
    "author": 1,
    "desc": "Lorem ipsum",
    "primary": true,
    "downloads": 10,
    "custom1": null,
    "custom2": null,
    "custom3": null,
    "custom4": null,
    "custom5": null
  },
  {
    "file_id": 2,
    "url": "http://yoursite.com/uploads/dog.jpg",
    "filename": "dog.jpg",
    "extension": "jpg",
    "kind": "image\/jpeg",
    "size": "75 KB",
    "title": "dog",
    "date": 1389466147000,
    "author": 1,
    "desc": "Lorem ipsum",
    "primary": false,
    "downloads": 0,
    "custom1": null,
    "custom2": null,
    "custom3": null,
    "custom4": null,
    "custom5": null
  }
]
```

##### Date

The data will be the Unix timestamp, accurate to milliseconds. This is because the native JavaScript Date object accepts a millisecond-based timestamp in its constructor.

```
your_date_field: 1385661660000
```

## json:search

```
{exp:json:search search_id="{segment_3}"}
```

json:search must be paired with {exp:search:simple_form} or {exp:search:advanced_form}.

#### json:search Parameters

See [channel:entries parameters](https://docs.expressionengine.com/latest/channels/entries.html#parameters).

##### `search_id="{segment_3}"`

The native search forms will append a search_id automatically to the result_page when you submit a form.

#### json:search Example

```
{exp:search:simple_form channel="site" form_id="search" return_page="site/json"}
    <input type="text" name="keywords">
    <input type="submit" value="Submit">
{/exp:search:simple_form}

<script type="text/javascript">
jQuery(document).ready(function($){
  $("#search").submit(function(){
    $.post(
      $(this).attr("action"),
      $(this).serialize(),
      function(data) {
        console.log(data);
      },
      "json"
    );
    return false;
  });
});
</script>
```

## json:members

```
{exp:json:members member_id="1|2"}
```

json:members is a single tag, not a tag pair.

#### json:members Parameters

##### `member_id="1"`

Specify which members, by member_id, to output. Separate multiple member_id's with a pipe character. Use `member_id="CURRENT_USER"` to get member data for just the current user.

##### `username="admin"`

Specify which members, by username, to output. Separate multiple usernames with a pipe character.

##### `group_id="1"`

Specify which members, by group_id, to output. Separate multiple group_id's

##### `limit="1"`

Set a limit for records to retrieve.


## Advanced Examples

### JSONP

If you're doing cross-domain AJAX, you will probably want to use JSONP.

This is the JSON template:

```
{exp:json:entries channel="site" jsonp="yes" callback="{segment_3}"}
```

And the request itself:

```
$.ajax({
  url: "http://yoursite.com/group/template/yourCallbackFunction",
  dataType: "jsonp",
  jsonp: false
});
function yourCallbackFunction(data) {
  console.log(data);
}
```

You'll see here that we appended the callback function to the url as a segment, rather than use the traditional ?callback=function syntax. This is because query strings do not work out of the box with EE. If you have gotten query strings to work with EE you can use the traditional approach:

```
{exp:json:entries channel="site" jsonp="yes" callback="<?php echo $_GET['callback']; ?>"}
```

The request:

```
$.ajax({
  url: "http://yoursite.com/group/template",
  dataType: "jsonp",
  jsonpCallback: "yourCallbackFunction"
});
function yourCallbackFunction(data) {
  console.log(data);
}
```

## Changelog

### v3.0.0

- ExpressionEngine 6 required
- Several changes to the code due to database changes
- Added `/system/user/addons/json/icon.png` for the control panel
- Fluid fieldtype not supported
- **Note:** not tested with Assets, Matrix and Playa

### v2.0.0

- ExpressionEngine 4 or 5 required
- Several changes to the code due to database changes
- Fluid fieldtype not supported
- **Note:** not tested with Assets, Matrix and Playa

### v1.1.9

- EE3 compatibility
- Added relationships support for grids as per [ahebrank's commit](https://github.com/rsanchez/json/pull/65)
- Added `/system/user/addons/json/addon.setup.php` for EE3
- Added `/system/user/addons/json/README.md` for the add-on manual in the control panel (as of EE3)
- **Note:** not tested with Assets, Matrix and Playa

### v1.1.8

- Added `json_plugin_entries_end` and `json_plugin_members_end` hooks
- Improved Wygwam support
- Fixed intermittent disappearing `ee()->TMPL` object

### v1.1.7

- Added `offset` support for members

### v1.1.6

- Add Channel Files support.

### v1.1.5

- Add `root_node` and `item_root_node` parameters.

### v1.1.4

- Add manipulations to Assets fields

### v1.1.3

- Fix bug where show_categories parameter did not work

### v1.1.2

- Fix bug where `fields` parameter was not being honored
- Fix bug causing fatal MySQL error when using the `fixed_order` parameter

### v1.1.1

- Fix WSOD on Plugins page
- Fix PHP errors when an Assests field has no selection(s)

### v1.1.0

- Added support for the following fieldtypes: Assets, Grid, Playa, Relationships
- Change IDs (entry_id, author_id, etc.) and Dates to integers
- Added `show_categories` and `show_category_group` parameters to `{exp:json:entries}`
- Added `{exp:json:search}`
- Added JSONP support
- Added `date_format` parameter
- Added `content_type` parameter

## Upgrading from 1.0.x

- IDs (entry_id, author_id, etc.) and Dates are returned as integers
- The following fieldtypes have different output: Playa, Assets. Please see docs above for an example of their output.
