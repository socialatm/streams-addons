<?xml version="1.0" encoding="UTF-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
	<ShortName>{{$project}}@{{$nodename}}</ShortName>
	<Description>{{$search_project}}@{{$nodename}}</Description>
	<Contact>{{$repo}}</Contact>
	<Image height="16" width="16" type="image/x-icon">{{$favicon}}</Image>
	<Image height="64" width="64" type="image/png">{{$photo}}</Image>
	<Url type="text/html" template="{{$baseurl}}/search?search={searchTerms}"/>
	<Url type="application/opensearchdescription+xml" rel="self" template="{{$baseurl}}/opensearch" />        
</OpenSearchDescription>
