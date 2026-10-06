@props(['id', 'title', 'subtitle' => null, 'kicker' => null, 'size' => ''])
<dialog class="a-modal {{ $size }}" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
  <div class="a-modal-head">
    <div>
      @if($kicker)<p class="a-kicker">{{ $kicker }}</p>@endif
      <h3 id="{{ $id }}-title">{{ $title }}</h3>
      @if($subtitle)<p>{{ $subtitle }}</p>@endif
    </div>
    <button type="button" class="a-modal-close" data-modal-close aria-label="Close dialog">&times;</button>
  </div>
  {{ $slot }}
</dialog>
