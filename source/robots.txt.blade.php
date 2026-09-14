---
permalink: /robots.txt
---
User-agent: *
@if ($page->indexable ?? false)
Allow: /
Disallow: /pr-preview/
Sitemap: {{ rtrim($page->siteUrl, '/') }}/sitemap.xml
@else
Disallow: /
@endif
