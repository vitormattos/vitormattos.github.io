---
permalink: /robots.txt
---
User-agent: *
@if ($page->indexable ?? false)
Allow: /
Disallow: /pr-preview/
Disallow: /presentations/
Sitemap: {{ rtrim($page->siteUrl, '/') }}/sitemap.xml
@else
Disallow: /
@endif
