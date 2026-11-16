<?php

namespace App\Support\Reports;

use App\Models\Parameter;

/**
 * Decides which aggregations are meaningful for a parameter.
 *
 * A single "average" for every parameter would be wrong in both directions:
 * summing temperatures or streamflow produces a number with no physical
 * meaning, and averaging daily rainfall destroys the total that a water
 * balance actually needs. The rule is derived from the parameter's stored
 * `value_type`, so a new accumulation parameter (evaporation, sunshine hours)
 * behaves correctly without touching report code.
 */
final class ParameterProfile
{
    public const TOTAL = 'total';

    public const MEAN = 'mean';

    public const MIN = 'min';

    public const MAX = 'max';

    private function __construct(private readonly ?Parameter $parameter) {}

    public static function for(?Parameter $parameter): self
    {
        return new self($parameter);
    }

    /**
     * True when the parameter records an amount accumulated over its period,
     * which makes a total the primary statistic and a mean a secondary one.
     */
    public function accumulates(): bool
    {
        if (! $this->parameter) {
            return false;
        }

        return in_array($this->parameter->value_type, config('reports.accumulating_value_types', []), true);
    }

    /**
     * @return list<string>
     */
    public function metrics(): array
    {
        $metrics = $this->accumulates()
            ? [self::TOTAL, self::MEAN, self::MIN, self::MAX]
            : [self::MEAN, self::MIN, self::MAX];

        return $metrics;
    }

    public function primaryMetric(): string
    {
        return $this->accumulates() ? self::TOTAL : self::MEAN;
    }

    public function isPrecipitation(): bool
    {
        if (! $this->parameter || ! $this->accumulates()) {
            return false;
        }

        return $this->unitDimension() === 'length';
    }

    public function label(string $metric): string
    {
        return match ($metric) {
            self::TOTAL => 'Total',
            self::MIN => 'Minimum',
            self::MAX => 'Maximum',
            default => $this->accumulates() ? 'Period average' : 'Average',
        };
    }

    public function chartLabel(string $metric): string
    {
        return match ($metric) {
            self::TOTAL => 'Total',
            self::MIN => 'Min',
            self::MAX => 'Max',
            default => 'Average',
        };
    }

    public function decimals(): int
    {
        return (int) ($this->parameter?->decimals ?? 2);
    }

    public function name(): string
    {
        return $this->parameter?->name ?? 'Parameter';
    }

    public function unitSymbol(): ?string
    {
        return $this->parameter?->defaultUnit?->symbol;
    }

    public function unitDimension(): ?string
    {
        return $this->parameter?->defaultUnit?->dimension;
    }

    public function code(): ?string
    {
        return $this->parameter?->code;
    }

    public function parameterId(): ?int
    {
        return $this->parameter?->id;
    }

    /**
     * Display value rounded to the precision the parameter declares.
     */
    public function format(?float $value, ?string $metric = null): ?float
    {
        if ($value === null) {
            return null;
        }

        return round($value, $this->decimals() + ($metric === self::TOTAL ? 1 : 0));
    }

    /**
     * Compact description used in report headers and chart captions.
     */
    public function descriptor(): string
    {
        $unit = $this->unitSymbol();
        $metric = $this->label($this->primaryMetric());

        return trim(sprintf('%s · %s%s', $this->name(), $metric, $unit ? ' ('.$unit.')' : ''));
    }
}
