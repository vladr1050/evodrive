@php
    $categories = $categories ?? collect();
    $mainEntity = [];
    foreach ($categories as $cat) {
        foreach ($cat->items as $item) {
            $q = $item->getTranslatedQuestion();
            $a = $item->getTranslatedAnswer();
            if ($q && $a) {
                $mainEntity[] = [
                    '@type' => 'Question',
                    'name' => $q,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $a,
                    ],
                ];
            }
        }
    }
    if (empty($mainEntity)) {
        $mainEntity = [
            ['@type' => 'Question', 'name' => 'FAQ', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Frequently asked questions.']],
        ];
    }
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $mainEntity,
    ];
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
