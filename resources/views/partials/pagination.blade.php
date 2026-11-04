@if($paginator->hasPages())
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <span class="subtle">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data</span>
    <div class="actions">
        @if($paginator->onFirstPage())<span class="btn btn-secondary btn-sm" style="opacity:.5">←</span>@else<a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}">←</a>@endif
        @for($page=max(1,$paginator->currentPage()-2); $page<=min($paginator->lastPage(),$paginator->currentPage()+2);$page++)
            <a class="btn btn-sm {{ $page===$paginator->currentPage()?'btn-primary':'btn-secondary' }}" href="{{ $paginator->url($page) }}">{{ $page }}</a>
        @endfor
        @if($paginator->hasMorePages())<a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}">→</a>@else<span class="btn btn-secondary btn-sm" style="opacity:.5">→</span>@endif
    </div>
</div>
@endif
