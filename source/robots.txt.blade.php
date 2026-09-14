---
permalink: /robots.txt
---
User-agent: *
@if ($page->indexable ?? false)
Allow: /
Sitemap: {{ $page->baseUrl }}/sitemap.xml
@else
Disallow: /
@endif
