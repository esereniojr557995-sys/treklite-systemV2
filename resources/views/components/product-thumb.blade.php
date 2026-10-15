{{-- Product photo, or - when there is no photo - a generic picture of that kind of product with a tooltip.
     Use: <x-product-thumb :product="$p" :size="48" /> (load the product's category first to avoid extra queries) --}}
@props(['product', 'size' => 48])

@if ($product->image_url)
    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" width="{{ $size }}" height="{{ $size }}"
         class="rounded" style="object-fit:cover;width:{{ $size }}px;height:{{ $size }}px;flex-shrink:0">
@else
    @php $ph = $product->placeholder; @endphp
    <div class="ph-thumb" role="img" aria-label="{{ $ph['tip'] }}" data-tip="{{ $ph['tip'] }}"
         style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ (int) round($size * 0.5) }}px"><i class="bi bi-image"></i></div>
@endif
