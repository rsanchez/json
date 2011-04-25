# JSON #

Output ExpressionEngine data in JSON format.

## Installation

* Copy the /system/expressionengine/third_party/json/ folder to your /system/expressionengine/third_party/ folder

## Global Parameters

	xhr="yes"

Set xhr to yes to only output data when an XMLHttpRequest is detected.

	terminate="yes"

Set terminate to yes to terminate the template and output your json immediately, with application/json content-type headers.

	fields="title|url_title"

Specify which fields you wish to have in the array. Separate multiple fields by a pipe character. If you do not specify fields, you will get all of the default fields' data.

## Dates

Date fields are in unix timestamp format, accurate to milliseconds. Use the Javascript Date object in combination with date field data:

	for (i in data) {
		var entryDate = new Date(data[i].entry_date);
	}

## Tags

### json:entries

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
	
	output="images/output.json"

Save the contents to an external file. Remember to set the permissions. Path is relative to the main index.php file and you do not need a slash at the start

	refresh="240"

Number in seconds on how often to refresh the output file. 

### json:members

	{exp:json:members member_id="1|2"}

json:entries is a single tag, not a tag pair. Use channel:entries parameters to filter your entries.

#### json:members Parameters

	member_id="1"

Specify which members, by member_id, to output. Separate multiple member_id's with a pipe character.

	username="admin"

Specify which members, by username, to output. Separate multiple usernames with a pipe character.

	group_id="1"

Specify which members, by group_id, to output. Separate multiple group_id's

	limit="1"

Set a limit for records to retrieve.
