@php
    $car = $car ?? [];
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => ($car['make'] ?? '') . ' ' . ($car['model'] ?? ''),
        'description' => \Illuminate\Support\Str::limit(strip_tags($car['description'] ?? ''), 500),
        'brand' => [
            '@type' => 'Brand',
            'name' => $car['make'] ?? 'EvoDrive',
        ],
        'offers' => [
            '@type' => 'Offer',
            'price' => $car['price'] ?? 0,
            'priceCurrency' => 'EUR',
            'priceValidUntil' => now()->addYear()->format('Y-m-d'),
            'availability' => 'https://schema.org/InStock',
        ],
    ];
    if (! empty($car['image'])) {
        $schema['image'] = str_starts_with($car['image'], 'http') ? $car['image'] : url($car['image']);
    }
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
