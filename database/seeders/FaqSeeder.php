<?php

namespace Database\Seeders;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'slug' => 'general',
                'sort_order' => 1,
                'title' => ['en' => 'General', 'ru' => 'Общее', 'lv' => 'Vispārīgi'],
                'items' => [
                    [
                        'question' => ['en' => 'What is EvoDrive.lv?'],
                        'answer' => ['en' => 'EvoDrive is Riga\'s leading mobility infrastructure platform. We provide professional drivers with everything they need to succeed: from premium Tesla fleet employment to taxi-licensed vehicle rentals for independent work.'],
                    ],
                    [
                        'question' => ['en' => 'Where are you located?'],
                        'answer' => ['en' => 'Our main office and fleet hub are located in Riga, Latvia. We serve drivers operating across the entire Riga region and surrounding areas.'],
                    ],
                ],
            ],
            [
                'slug' => 'fleet',
                'sort_order' => 2,
                'title' => ['en' => 'Work with Fleet', 'ru' => 'Работа с парком', 'lv' => 'Darbs ar autoparku'],
                'items' => [
                    [
                        'question' => ['en' => 'What cars do you provide for fleet drivers?'],
                        'answer' => ['en' => 'Our primary fleet consists of modern Tesla Model 3 and Model Y vehicles. We also utilize high-efficiency Toyota hybrids for specific service tiers.'],
                    ],
                    [
                        'question' => ['en' => 'Do I need my own car to work in the fleet?'],
                        'answer' => ['en' => 'No. The "Work with Fleet" path is designed specifically for drivers who want to use a company-provided vehicle. We handle all costs including insurance, maintenance, and charging.'],
                    ],
                    [
                        'question' => ['en' => 'What are the requirements to join?'],
                        'answer' => ['en' => 'You must have a valid B-category driving license with at least 3 years of experience, a valid ATD taxi driver card, and a clean driving record.'],
                    ],
                ],
            ],
            [
                'slug' => 'rental',
                'sort_order' => 3,
                'title' => ['en' => 'Taxi Rental', 'ru' => 'Аренда такси', 'lv' => 'Taksometru noma'],
                'items' => [
                    [
                        'question' => ['en' => 'How does the weekly rental work?'],
                        'answer' => ['en' => 'You rent a taxi-licensed vehicle on a weekly basis. This gives you full independence to work on platforms like Bolt or Forus whenever you want. Maintenance and insurance are included in the weekly price.'],
                    ],
                    [
                        'question' => ['en' => 'Is there a security deposit?'],
                        'answer' => ['en' => 'Yes, we require a standard security deposit (usually starting from €200) which is fully refundable upon return of the vehicle in good condition.'],
                    ],
                    [
                        'question' => ['en' => 'Can I use the rental car for personal needs?'],
                        'answer' => ['en' => 'Yes, as long as it fits within the agreed mileage limits. Our rental cars are your professional tool and personal transport combined.'],
                    ],
                ],
            ],
            [
                'slug' => 'payments',
                'sort_order' => 4,
                'title' => ['en' => 'Payments & Legal', 'ru' => 'Оплата и юридическое', 'lv' => 'Maksājumi un tiesības'],
                'items' => [
                    [
                        'question' => ['en' => 'When do I get paid?'],
                        'answer' => ['en' => 'For fleet drivers, payouts are processed every Monday for the previous week\'s work. Rental drivers keep all their earnings from platforms and only pay the fixed weekly rental fee.'],
                    ],
                    [
                        'question' => ['en' => 'Are taxes included in the pricing?'],
                        'answer' => ['en' => 'Our listed prices for rentals usually include VAT. For fleet employment, we provide official contracts and handle all necessary social tax contributions.'],
                    ],
                ],
            ],
        ];

        foreach ($data as $idx => $cat) {
            $items = $cat['items'];
            unset($cat['items']);
            $category = FaqCategory::create($cat);
            foreach ($items as $i => $item) {
                FaqItem::create([
                    'faq_category_id' => $category->id,
                    'sort_order' => $i,
                    'question' => $item['question'],
                    'answer' => $item['answer'],
                ]);
            }
        }
    }
}
