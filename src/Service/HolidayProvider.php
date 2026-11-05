<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\LocaleSwitcher;
use Yasumi\Holiday;
use Yasumi\Yasumi;

/**
 * Statutory public holidays for the configured German state (via Yasumi), names in the current language.
 */
class HolidayProvider
{
    private const YASUMI_LOCALES = ['de' => 'de_DE', 'en' => 'en_US', 'fr' => 'fr_FR'];

    /** @var array<string, array<string, string>> "year-locale" => [Y-m-d => name] */
    private array $cache = [];

    public function __construct(
        #[Autowire('%app.holiday_region%')]
        private readonly string $region = 'Germany/NorthRhineWestphalia',
        private readonly ?LocaleSwitcher $localeSwitcher = null,
    ) {
    }

    /** @return array<string, string> Y-m-d => localized name */
    public function forYear(int $year): array
    {
        $locale = self::YASUMI_LOCALES[$this->localeSwitcher?->getLocale() ?? 'de'] ?? 'de_DE';
        $key = $year.'-'.$locale;
        if (!isset($this->cache[$key])) {
            $holidays = [];
            $provider = Yasumi::create($this->region, $year, $locale);
            foreach ($provider->getHolidays() as $holiday) {
                if ($holiday->getType() === Holiday::TYPE_OFFICIAL) {
                    $holidays[$holiday->format('Y-m-d')] = $holiday->getName();
                }
            }
            $this->cache[$key] = $holidays;
        }

        return $this->cache[$key];
    }

    public function isHoliday(\DateTimeImmutable $day): bool
    {
        return isset($this->forYear((int) $day->format('Y'))[$day->format('Y-m-d')]);
    }

    public function nameOf(\DateTimeImmutable $day): ?string
    {
        return $this->forYear((int) $day->format('Y'))[$day->format('Y-m-d')] ?? null;
    }
}
