@unless (empty($items))
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        @foreach ($items as $item)
            @if (!$loop->first)
                <span class="crumb-sep" aria-hidden="true">›</span>
            @endif
            @if ($loop->last || empty($item['url']))
                <span class="crumb-current" aria-current="page">{{ $item['label'] }}</span>
            @else
                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
            @endif
        @endforeach
    </nav>
@endunless
