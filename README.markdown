# JSON #

Output ExpressionEngine data in JSON format.

## Installation

* Copy the /system/expressionengine/third_party/json/ folder to your /system/expressionengine/third_party/ folder

## Global Parameters

	xhr="yes"

Set xhr to yes to only output data when an XMLHttpRequest is detected. Do not set this to yes if you are using JSONP, as JSONP requests are not true XHMLHttpRequests.

	terminate="yes"

Set terminate to yes to terminate the template and output your json immediately, with Content-Type headers.

	fields="title|url_title"

Specify which fields you wish to have in the array. Separate multiple fields by a pipe character. If you do not specify fields, you will get all of the default fields' data.

	fields="entry_id=id|entry_date=start|title"

To output different fieldnames for the json keys (id instead of entry_id) specify the fieldname as fieldname=keyname.

	content_type="text/javascript"

Set a custom Content-Type header. The default is "application/json", or "application/javascript" if using JSONP. Headers are only sent when terminate is set to "yes". 

	jsonp="yes"

Set jsonp to yes to enable a JSONP response. You must also specify a valid callback. You are encouraged to set terminate to yes when using JSONP.

	callback="yourCallbackFunction"

Set a callback function for your JSONP request. Since query strings do not work out-of-the-box in EE, you may want to consider using a URL segment to specify your callback, ie. callback="{segment_3}", rather than the standard ?callback=foo method.

## Dates

Date fields are in unix timestamp format, accurate to milliseconds. Use the Javascript Date object in combination with date field data:

	for (i in data) {
		var entryDate = new Date(data[i].entry_date);
	}

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

## json:search

	{exp:json:search search_id="{segment_3}"}

json:search must be paired with {exp:search:simple_form} or {exp:search:advanced_form}.

#### json:search Parameters

See [channel:entries parameters](http://expressionengine.com/user_guide/modules/channel/parameters.html).

	search_id="{segment_3}"

The native search forms will append a search_id automatically to the result_page when you submit a form.

#### json:search Example

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

## json:members

	{exp:json:members member_id="1|2"}

json:members is a single tag, not a tag pair.

#### json:members Parameters

	member_id="1"

Specify which members, by member_id, to output. Separate multiple member_id's with a pipe character.

	username="admin"

Specify which members, by username, to output. Separate multiple usernames with a pipe character.

	group_id="1"

Specify which members, by group_id, to output. Separate multiple group_id's

	limit="1"

Set a limit for records to retrieve.


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