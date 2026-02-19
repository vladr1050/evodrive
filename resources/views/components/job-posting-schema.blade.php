@php
    $sections = $sections ?? collect();
    $hero = $sections['hero'] ?? null;
    $heroContent = $hero?->getContentForLocale() ?? [];
    $h1 = $heroContent['h1'] ?? __('ui.emp_hero_h1_1') . ' ' . __('ui.emp_hero_h1_2');
    $description = $heroContent['subtitle'] ?? __('ui.emp_hero_sub');

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'JobPosting',
        'title' => $h1,
        'description' => $description,
        'employmentType' => 'FULL_TIME',
        'datePosted' => config('schema.job_posting.date_posted') ?? now()->format('Y-m-d'),
        'hiringOrganization' => [
            '@type' => 'Organization',
            'name' => config('schema.organization.name'),
            'sameAs' => config('schema.organization.url', url('/')),
        ],
        'jobLocation' => [
            '@type' => 'Place',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => config('schema.job_location.address_locality'),
                'addressCountry' => config('schema.job_location.address_country'),
            ],
        ],
        'baseSalary' => [
            '@type' => 'MonetaryAmount',
            'currency' => config('schema.job_posting.base_salary.currency', 'EUR'),
            'value' => [
                '@type' => 'QuantitativeValue',
                'minValue' => config('schema.job_posting.base_salary.value.min', 12),
                'maxValue' => config('schema.job_posting.base_salary.value.max', 15),
                'unitText' => config('schema.job_posting.base_salary.value.unit', 'HOUR'),
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
