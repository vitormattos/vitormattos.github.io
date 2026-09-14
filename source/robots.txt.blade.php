---
permalink: /robots.txt
---
User-agent: *
@if ($page->indexable ?? false)
Allow: /
Disallow: /pr-preview/
Sitemap: {{ $page->baseUrl }}/sitemap.xml
@else
Disallow: /
@endif
