<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Yasumi\Holiday;
use Yasumi\Yasumi;

/**
 * Statutory public holidays for the configured German state (via Yasumi).
 */
class HolidayProvider
{
    /** @var array<int, array<string, string>> year => [Y-m-d => name] */
    private array $cache = [];

    public function __construct(
        #[Autowire('%app.holiday_region%')]
        private readonly string $region = 'Germany/NorthRhineWestphalia',
    ) {
    }

    /** @return array<string, string> Y-m-d => localized name */
    public function forYear(int $year): array
    {
        if (!isset($this->cache[$year])) {
            $holidays = [];
            $provider = Yasumi::create($this->region, $year, 'de_DE');
            foreach ($provider->getHolidays() as $holiday) {
                if ($holiday->getType() === Holiday::TYPE_OFFICIAL) {
                    $holidays[$holiday->format('Y-m-d')] = $holiday->getName();
                }
            }
            $this->cache[$year] = $holidays;
        }

        return $this->cache[$year];
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
