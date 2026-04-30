<?php

/**
 * Default CMS sections for legal pages (privacy / terms).
 * Used by migration (if missing) and DatabaseSeeder. Edit in Filament after deploy.
 *
 * Section fields per locale: optional `heading`, Markdown `body`.
 */
return [
    'privacy' => [
        [
            'key' => 'main',
            'sort_order' => 1,
            'content' => [
                'en' => [
                    'heading' => 'Overview',
                    'body' => <<<'MD'
Evo.drive processes personal data in accordance with applicable laws in Latvia and the European Union, including the GDPR.

**Categories of data** may include information you submit on the website or driver portal, account and shift-related data, vehicle telemetry where applicable, and communications with support.

**Purposes** include providing our services, fleet operations, safety, billing, legal compliance, and product improvement.

**Your rights** include access, rectification, erasure, restriction, portability, and objection where applicable, as well as lodging a complaint with the supervisory authority.

**Contact:** [support@evodrive.lv](mailto:support@evodrive.lv)

*This text is a starter template — replace with counsel-approved wording in Filament (Pages → Sections, Markdown in the `body` field).*
MD,
                ],
                'ru' => [
                    'heading' => 'Общие положения',
                    'body' => <<<'MD'
Evo.drive обрабатывает персональные данные в соответствии с применимым правом Латвии и ЕС, включая GDPR.

**Категории данных** могут включать сведения из заявок и портала водителя, данные учётной записи и смен, телематику транспорта (если применимо) и переписку с поддержкой.

**Цели** — оказание услуг, работа автопарка, безопасность, расчёты, соблюдение закона и развитие продукта.

**Права субъекта данных** — доступ, исправление, удаление, ограничение, переносимость, возражение и жалоба надзорному органу.

**Контакт:** [support@evodrive.lv](mailto:support@evodrive.lv)

*Это черновой шаблон — замените на утверждённый юристом текст в админке (Pages → Sections, поле `body` в Markdown).*
MD,
                ],
                'lv' => [
                    'heading' => 'Pārskats',
                    'body' => <<<'MD'
Evo.drive apstrādā personas datus saskaņā ar Latvijas un ES piemērojamiem tiesību aktiem, tostarp GDPR.

**Datu kategorijas** var ietvert informāciju, ko iesniedzat vietnē vai vadītāja portālā, konta un maiņu datus, transportlīdzekļu telemātiku (ja piemērojams) un saziņu ar atbalstu.

**Mērķi** — pakalpojumu sniegšana, flotes darbība, drošība, norēķini, tiesiskās atbilstības nodrošināšana un produkta uzlabošana.

**Jūsu tiesības** — piekļuve, labošana, dzēšana, ierobežošana, pārnesamība un iebildumi, kā arī sūdzība uzraudzības iestādei.

**Kontakts:** [support@evodrive.lv](mailto:support@evodrive.lv)

*Šis ir sākuma veidne — aizstājiet ar juristu apstiprinātu tekstu administrācijā (Pages → Sections, lauks `body` Markdown formātā).*
MD,
                ],
            ],
        ],
    ],
    'terms' => [
        [
            'key' => 'main',
            'sort_order' => 1,
            'content' => [
                'en' => [
                    'heading' => 'Agreement',
                    'body' => <<<'MD'
By using Evo.drive websites, portals, or related services, you agree to these terms and our Privacy Policy.

**Services** are provided as described at the time of use. Fleet, shift, and vehicle features may change with notice where required.

**Accounts** must be used lawfully. You are responsible for credentials and accurate information you provide.

**Liability** is limited to the maximum extent permitted by law. Nothing here excludes mandatory consumer or employment rights.

**Governing law:** Latvia, unless mandatory rules provide otherwise.

**Contact:** [support@evodrive.lv](mailto:support@evodrive.lv)

*Starter template — replace with counsel-approved terms in Filament.*
MD,
                ],
                'ru' => [
                    'heading' => 'Соглашение',
                    'body' => <<<'MD'
Используя сайты, порталы или связанные сервисы Evo.drive, вы соглашаетесь с настоящими условиями и Политикой конфиденциальности.

**Услуги** предоставляются в объёме, актуальном на момент использования. Функции автопарка, смен и транспорта могут меняться с уведомлением, если это требуется.

**Учётные записи** должны использоваться законно. Вы отвечаете за учётные данные и достоверность предоставленной информации.

**Ответственность** ограничивается в максимальной степени, допустимой законом. Ничто здесь не исключает императивных прав потребителя или работника.

**Применимое право:** Латвия, если иное не предписано императивными нормами.

**Контакт:** [support@evodrive.lv](mailto:support@evodrive.lv)

*Черновой шаблон — замените на утверждённые условия в админке.*
MD,
                ],
                'lv' => [
                    'heading' => 'Līgums',
                    'body' => <<<'MD'
Izmantojot Evo.drive vietnes, portālus vai saistītos pakalpojumus, jūs piekrītat šiem noteikumiem un mūsu Privātuma politikai.

**Pakalpojumi** tiek sniegti tādā apjomā, kāds ir spēkā izmantošanas brīdī. Flotes, maiņu un transportlīdzekļu funkcijas var mainīties ar paziņojumu, ja to pieprasa tiesību akti.

**Konti** jāizmanto likumīgi. Jūs esat atbildīgs par piekļuves datiem un sniegtās informācijas pareizību.

**Atbildība** ir ierobežota līdz likumā pieļautajam maksimumam. Nekas šeit neizslēdz obligātās patērētāja vai darba ņēmēja tiesības.

**Piemērojamās tiesības:** Latvija, ja imperatīvās normas nenosaka citādi.

**Kontakts:** [support@evodrive.lv](mailto:support@evodrive.lv)

*Sākuma veidne — aizstājiet ar juristu apstiprinātiem noteikumiem administrācijā.*
MD,
                ],
            ],
        ],
    ],
];
