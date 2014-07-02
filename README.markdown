# JSON #

Output ExpressionEngine data in JSON format.

## Requirements

- ExpressionEngine 2.6+
- PHP 5.4+

## Installation

* Copy the /system/expressionengine/third_party/json/ folder to your /system/expressionengine/third_party/ folder

## Global Parameters

	xhr="yes"

Set xhr to yes to only output data when an XMLHttpRequest is detected. Do not set this to yes if you are using JSONP, as JSONP requests are not true XHMLHttpRequests.

	terminate="yes"

Set terminate to yes to terminate the template and output your json immediately, with Content-Type headers.

	fields="title|url_title"

Specify which fields you wish to have in the array. Separate multiple fields by a pipe character. If you do not specify fields, you will get all of the default fields' data. The primary key (`entry_id` for entries, `member_id` for members) will always be present and cannot be suppressed by this parameter.

	content_type="text/javascript"

Set a custom Content-Type header. The default is "application/json", or "application/javascript" if using JSONP. Headers are only sent when terminate is set to "yes". 

	jsonp="yes"

Set jsonp to yes to enable a JSONP response. You must also specify a valid callback. You are encouraged to set terminate to yes when using JSONP.

	callback="yourCallbackFunction"

Set a callback function for your JSONP request. Since query strings do not work out-of-the-box in EE, you may want to consider using a URL segment to specify your callback, ie. callback="{segment_3}", rather than the standard ?callback=foo method.

	date_format="U"

Use a different date format. Note: always returns dates as string.

## Dates

By default, the date fields are in ISO-8601 (`Y-m-d\TH:i:sO`) format. You may use ISO-8601-formatted strings in the the Javascript Date object constructor:

	for (i in data) {
		var entryDate = new Date(data[i].entry_date);
	}

If you require a different output format for the date fields, set the date_format= parameter. This uses the php date() function.

## json:entries

	{exp:json:entries channel="news"}

json:entries is a single tag, not a tag pair. Use channel:entries parameters to filter your entries.

#### json:entries Default Fields
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

#### json:entries Parameters

See [channel:entries parameters](http://expressionengine.com/user_guide/modules/channel/parameters.html).

	show_categories="yes"

This will add categories to the entries response

	show_category_group="1|2"

When paired with show_categories="yes", this will display only categories from the specified groups.

#### json:entries Custom Fields

Most custom fields will just return the raw column data from the `exp_channel_data` database table. The following fieldtypes will provide custom data.

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
		"location": null
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
		"location": null
	}
]
```

##### Date

The data will be the Unix timestamp, accurate to milliseconds. This is because the native JavaScript Date object accepts a millisecond-based timestamp in its constructor.

```
your_date_field: 1385661660000
```

## Advanced Examples

### JSONP

If you're doing cross-domain AJAX, you will probably want to use JSONP.

This is the JSON template:

	{exp:json:entries channel="site" jsonp="yes" callback="{segment_3}"}

And the request itself:

	$.ajax({
		url: "http://yoursite.com/group/template/yourCallbackFunction",
		dataType: "jsonp",
		jsonp: false
	});
	function yourCallbackFunction(data) {
		console.log(data);
	}

You'll see here that we appended the callback function to the url as a segment, rather than use the traditional ?callback=function syntax. This is because query strings do not work out of the box with EE. If you have gotten query strings to work with EE you can use the traditional approach:

	{exp:json:entries channel="site" jsonp="yes" callback="<?php echo $_GET['callback']; ?>"}

The request:

	$.ajax({
		url: "http://yoursite.com/group/template",
		dataType: "jsonp",
		jsonpCallback: "yourCallbackFunction"
	});
	function yourCallbackFunction(data) {
		console.log(data);
	}
