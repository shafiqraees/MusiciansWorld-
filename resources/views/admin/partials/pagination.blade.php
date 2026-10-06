@if ($paginator->hasPages() || $paginator->total() > 0)
<nav class="a-pagination" aria-label="Pagination">
  <span>Showing {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }} of {{ number_format($paginator->total()) }}</span>
  @if ($paginator->hasPages())
  <ul>
    @if ($paginator->onFirstPage())
      <li><span class="page is-disabled">‹</span></li>
    @else
      <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a></li>
    @endif
    @foreach ($elements as $element)
      @if (is_string($element))<li><span class="page is-disabled">{{ $element }}</span></li>@endif
      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <li><span class="page is-current" aria-current="page">{{ $page }}</span></li>
          @else
            <li><a href="{{ $url }}">{{ $page }}</a></li>
          @endif
        @endforeach
      @endif
    @endforeach
    @if ($paginator->hasMorePages())
      <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">›</a></li>
    @else
      <li><span class="page is-disabled">›</span></li>
    @endif
  </ul>
  @endif
</nav>
@endif
