<?php

declare(strict_types=1);

namespace App\Twig;

use App\Demo\DemoMode;
use App\Http\CspNonce;
use App\Http\Preferences;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final readonly class AppExtension
{
    public function __construct(
        private CspNonce $nonce,
        private RequestStack $requests,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
        private DemoMode $demo,
    ) {
    }

    #[AsTwigFunction('csp_nonce')]
    public function cspNonce(): string
    {
        return $this->nonce->get();
    }

    #[AsTwigFunction('demo_mode')]
    public function demoMode(): bool
    {
        return $this->demo->isEnabled();
    }

    #[AsTwigFunction('ui_theme')]
    public function theme(): ?string
    {
        $request = $this->requests->getCurrentRequest();

        return null === $request ? null : Preferences::theme($request);
    }

    /**
     * @param list<int> $values
     */
    #[AsTwigFunction('sparkline')]
    public function sparkline(array $values, int $width = 120, int $height = 24): string
    {
        return Sparkline::points($values, $width, $height);
    }

    /**
     * @param list<array<string, mixed>> $frames
     *
     * @return list<array{in_app: bool, frames: list<array<string, mixed>>}>
     */
    #[AsTwigFunction('frame_groups')]
    public function frameGroups(array $frames): array
    {
        $groups = [];
        foreach (array_reverse($frames) as $frame) {
            $inApp = true === ($frame['in_app'] ?? null);
            $last = array_key_last($groups);
            if (null !== $last && $groups[$last]['in_app'] === $inApp) {
                $groups[$last]['frames'][] = $frame;
            } else {
                $groups[] = ['in_app' => $inApp, 'frames' => [$frame]];
            }
        }

        if (1 === \count($groups) && !$groups[0]['in_app']) {
            $groups[0]['in_app'] = true;
        }

        return $groups;
    }

    #[AsTwigFilter('ago')]
    public function ago(\DateTimeInterface $moment): string
    {
        $seconds = max(0, $this->clock->now()->getTimestamp() - $moment->getTimestamp());

        return match (true) {
            $seconds < 60 => $this->translator->trans('time.just_now'),
            $seconds < 3600 => $this->translator->trans('time.minutes_ago', ['count' => intdiv($seconds, 60)]),
            $seconds < 86400 => $this->translator->trans('time.hours_ago', ['count' => intdiv($seconds, 3600)]),
            default => $this->translator->trans('time.days_ago', ['count' => intdiv($seconds, 86400)]),
        };
    }
}
