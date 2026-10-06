@props(['status', 'label' => null])
<span {{ $attributes->merge(['class' => 'a-badge '.a_status($status)]) }}>{{ $label ?? a_label($status) }}</span>
