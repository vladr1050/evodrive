<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::firstOrCreate([], [
            'logo_path' => null,
            'favicon_path' => null,
        ]);

        User::firstOrCreate(['email' => 'admin@evodrive.lv'], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $pages = [
            [
                'key' => 'google_landing',
                'title' => ['en' => 'Work with EvoDrive Fleet', 'ru' => 'Работайте в автопарке EvoDrive', 'lv' => 'Strādājiet ar EvoDrive floti'],
                'slug' => ['en' => 'g', 'ru' => 'g', 'lv' => 'g'],
                'meta_title' => ['en' => 'Work with EvoDrive Fleet | Taxi Jobs Riga', 'ru' => 'Работа в EvoDrive | Такси Рига', 'lv' => 'Darbs EvoDrive | Taksometrs Rīga'],
                'meta_description' => ['en' => 'Earn more with zero personal risk. Apply in 2 minutes.', 'ru' => 'Зарабатывайте больше без личного риска. Заявка за 2 минуты.', 'lv' => 'Nopelniet vairāk bez personīgā riska. Pieteikties 2 minūtēs.'],
                'is_active' => true,
                'sections' => [
                    ['key' => 'hero', 'sort_order' => 1, 'content' => [
                        'en' => ['h1' => 'Work with EvoDrive Fleet', 'subtitle' => 'Earn more with zero personal risk.', 'cta_primary' => 'Apply in 2 Minutes', 'cta_secondary' => 'Rent a Car Instead'],
                        'ru' => ['h1' => 'Работайте в автопарке EvoDrive', 'subtitle' => 'Зарабатывайте больше без личного риска.', 'cta_primary' => 'Подать заявку за 2 минуты', 'cta_secondary' => 'Арендовать авто вместо'],
                        'lv' => ['h1' => 'Strādājiet ar EvoDrive floti', 'subtitle' => 'Nopelniet vairāk bez personīgā riska.', 'cta_primary' => 'Pieteikties 2 minūtēs', 'cta_secondary' => 'Īrēt auto tā vietā'],
                    ]],
                    ['key' => 'benefits', 'sort_order' => 2, 'content' => [
                        'en' => ['items' => ['7–10 EUR/h bruto', 'Weekly payments', 'Tesla fleet', 'Maintenance included']],
                        'ru' => ['items' => ['7–10 EUR/час брутто', 'Еженедельные выплаты', 'Парк Tesla', 'Обслуживание включено']],
                        'lv' => ['items' => ['7–10 EUR/st bruto', 'Ikmēneša izmaksas', 'Tesla flote', 'Apkope iekļauta']],
                    ]],
                    ['key' => 'requirements', 'sort_order' => 3, 'content' => [
                        'en' => ['items' => ['B category license (3+ years)', 'Valid ATD taxi license card', 'Latvian language skills (B1)']],
                        'ru' => ['items' => ['Права категории B (3+ лет)', 'Действующая карта лицензии ATD', 'Знание латышского (B1)']],
                        'lv' => ['items' => ['B kategorijas tiesības (3+ gadi)', 'Derīga ATD taksometra licence', 'Latviešu valodas prasmes (B1)']],
                    ]],
                    ['key' => 'cta', 'sort_order' => 4, 'content' => [
                        'en' => ['cta_label' => 'Start Application'],
                        'ru' => ['cta_label' => 'Начать заявку'],
                        'lv' => ['cta_label' => 'Sākt pieteikumu'],
                    ]],
                ],
            ],
            [
                'key' => 'meta_landing',
                'title' => ['en' => 'Your Career Starts Here', 'ru' => 'Ваша карьера начинается здесь', 'lv' => 'Tava karjera sākas šeit'],
                'slug' => ['en' => 'm', 'ru' => 'm', 'lv' => 'm'],
                'meta_title' => ['en' => 'Your Career Starts Here | EvoDrive Taxi', 'ru' => 'Карьера в такси | EvoDrive', 'lv' => 'Karjera taksometrā | EvoDrive'],
                'meta_description' => ['en' => 'Join the best taxi fleet in Latvia. Zero debt. Zero maintenance.', 'ru' => 'Присоединяйтесь к лучшему таксопарку Латвии.', 'lv' => 'Pievienojies labākajam taksometru parkam Latvijā.'],
                'is_active' => true,
                'sections' => [
                    ['key' => 'hero', 'sort_order' => 1, 'content' => [
                        'en' => ['h1' => 'Your Career Starts Here', 'subtitle' => 'Join the best taxi fleet in Latvia today.', 'cta_primary' => 'Start Driving Now'],
                        'ru' => ['h1' => 'Ваша карьера начинается здесь', 'subtitle' => 'Присоединяйтесь к лучшему таксопарку Латвии.', 'cta_primary' => 'Начать вождение сейчас'],
                        'lv' => ['h1' => 'Tava karjera sākas šeit', 'subtitle' => 'Pievienojies labākajam taksometru parkam Latvijā.', 'cta_primary' => 'Sākt braukt tagad'],
                    ]],
                ],
            ],
            [
                'key' => 'privacy',
                'title' => ['en' => 'Privacy Policy', 'ru' => 'Политика конфиденциальности', 'lv' => 'Privātuma politika'],
                'slug' => ['en' => 'privacy', 'ru' => 'privacy', 'lv' => 'privacy'],
                'meta_title' => ['en' => 'Privacy Policy | EvoDrive.lv', 'ru' => 'Политика конфиденциальности | EvoDrive.lv', 'lv' => 'Privātuma politika | EvoDrive.lv'],
                'is_active' => true,
                'sections' => [],
            ],
            [
                'key' => 'terms',
                'title' => ['en' => 'Terms of Service', 'ru' => 'Условия использования', 'lv' => 'Lietošanas noteikumi'],
                'slug' => ['en' => 'terms', 'ru' => 'terms', 'lv' => 'terms'],
                'meta_title' => ['en' => 'Terms of Service | EvoDrive.lv', 'ru' => 'Условия использования | EvoDrive.lv', 'lv' => 'Lietošanas noteikumi | EvoDrive.lv'],
                'is_active' => true,
                'sections' => [],
            ],
        ];

        foreach ($pages as $pageData) {
            $sections = $pageData['sections'] ?? [];
            unset($pageData['sections']);

            $page = Page::firstOrCreate(['key' => $pageData['key']], $pageData);

            foreach ($sections as $sectionData) {
                PageSection::firstOrCreate(
                    ['page_id' => $page->id, 'key' => $sectionData['key']],
                    ['content' => $sectionData['content'], 'sort_order' => $sectionData['sort_order']]
                );
            }
        }

        $this->call([
            TranslationSeeder::class,
            FaqSeeder::class,
        ]);

        // Rental vehicles only from config on local/staging; prod manages them in admin
        if (! app()->environment('production')) {
            $this->call(RentalVehicleSeeder::class);
        }
    }
}
