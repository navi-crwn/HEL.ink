<?php

namespace Hop\Safety;

/**
 * The answer of LinkVerdict::evaluate(): what the app should do with a link, the score behind it, the reasons as
 * translatable keys (the signal names: blacklisted, brand_spoof, guest, ...), the address the link finally lands on
 * and how many redirects it took to get there.
 */
final readonly class Verdict
{
    /** Send the visitor straight to the destination. */
    public const ALLOW = 'allow';

    /** Show a warning page first and let the visitor decide. */
    public const INTERSTITIAL = 'interstitial';

    /** Refuse the link. */
    public const BLOCK = 'block';

    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(
        public string $verdict,
        public int $score,
        public array $reasons,
        public string $finalUrl,
        public int $hops,
    ) {}

    public function allows(): bool
    {
        return $this->verdict === self::ALLOW;
    }

    public function needsInterstitial(): bool
    {
        return $this->verdict === self::INTERSTITIAL;
    }

    public function blocks(): bool
    {
        return $this->verdict === self::BLOCK;
    }

    /**
     * @return array{verdict: string, score: int, reasons: array<int, string>, final_url: string, hops: int}
     */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict,
            'score' => $this->score,
            'reasons' => $this->reasons,
            'final_url' => $this->finalUrl,
            'hops' => $this->hops,
        ];
    }
}
