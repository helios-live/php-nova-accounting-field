<?php

declare(strict_types=1);

namespace HeliosLive\PhpNovaAccountingField;

use Brick\Math\RoundingMode;
use Laravel\Nova\Fields\Currency;
use Brick\Money\Context\CustomContext;
use Symfony\Polyfill\Intl\Icu\Currencies;
use Laravel\Nova\Fields\Filters\TextFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\Filters\NumberFilter;
use HeliosLive\PhpNovaAccountingField\PriceWithCurrency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Class Accounting
 *
 * A custom Laravel Nova field for handling accounting-related functionalities.
 *
 * @package HeliosLive\PhpNovaAccountingField
 */
class Accounting extends Currency
{
    /** @var callable|null */
    public static $globalCallback = null;

    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'php-nova-accounting-field';

    /**
     * The type callback.
     *
     * @var callable
     */
    protected $typeCallback;

    /**
     * Whether the value is stored in minor units.
     *
     * @var bool
     */
    public bool $inMinorUnits = false;

    /**
     * Create a new Accounting field instance.
     *
     * @param string        $name       The displayable name of the field.
     * @param string|null   $attribute  The underlying attribute for the field.
     * @param callable|null $cb         Optional callback for additional configuration.
     */
    public function __construct(string $name, ?string $attribute = null, ?callable $cb = null)
    {
        parent::__construct($name, $attribute, $cb);

        $this->typeCallback = $this->defaultTypeCallback();
        $this->initializeField();

        if (static::$globalCallback) {
            call_user_func(static::$globalCallback, $this);
        }
    }

    /**
     * Initialize the field with default configurations.
     *
     * @return void
     */
    protected function initializeField(): void
    {
        $this->step(0.01)
            ->currency('USD')
            ->asHtml()
            ->resolveUsing(function ($value) {
                return $this->resolveValue($value);
            })
            ->fillUsing(function (NovaRequest $request, $model, $attribute, string $requestAttribute) {
                $this->fillAttribute($request, $requestAttribute, $model, $attribute);
            })
            ->displayUsing(function ($value) {
                return $this->formatDisplay($value);
            });
    }

    /**
     * Resolve the value for display.
     *
     * @param mixed $value The raw value from the database.
     *
     * @return float|null The resolved float value or null.
     */
    protected function resolveValue($value): ?float
    {
        if (!is_scalar($value)) {
            Log::warning('Non-scalar value encountered in resolveValue', ['value' => $value]);
            return null;
        }

        $value = (string)$value;

        $price = PriceWithCurrency::parse($value);
        $this->currency = $price->currency ?? $this->currency;

        if ($this->minorUnits && !$this->isValidNullValue($price->value)) {
            return $price->value / (10 ** Currencies::getFractionDigits($this->currency));
        }
        return $price->value;
    }

    /**
     * Fill the model attribute with the processed value.
     *
     * @param NovaRequest $request           The Nova request instance.
     * @param string      $requestAttribute  The attribute name from the request.
     * @param mixed       $model             The Eloquent model instance.
     * @param string      $attribute         The model's attribute to be set.
     *
     * @return void
     */
    public function fillAttribute(NovaRequest $request, $requestAttribute, $model, $attribute)
    {
        if (!$model instanceof Model) {
            throw new \InvalidArgumentException('The $model parameter must be an instance of Illuminate\Database\Eloquent\Model.');
        }

        $value = $request->$requestAttribute;

        if (!is_scalar($value)) {
            Log::warning('Non-scalar value encountered in fillAttribute', ['value' => $value]);
            $model->setAttribute($attribute, null);
            return;
        }

        $price = PriceWithCurrency::parse((string)$value);

        if ($this->minorUnits && !$this->isValidNullValue($price->value)) {
            $model->setAttribute($attribute, (int)round($price->value * (10 ** Currencies::getFractionDigits($this->currency))));
        } else {
            $model->setAttribute($attribute, $price->value);
        }
    }

    /**
     * Format the value for display.
     *
     * @param mixed $value The raw value.
     *
     * @return string|null The formatted string or null.
     */
    protected function formatDisplay($value): ?string
    {
        if (!is_scalar($value)) {
            Log::warning('Non-scalar value encountered in formatDisplay', ['value' => $value]);
            return null;
        }

        $value = (string)$value;

        $price = PriceWithCurrency::parse($value);
        $this->currency = $price->currency ?? $this->currency;

        if ($this->minorUnits && !$this->isValidNullValue($price->value)) {
            $value = (string)((float)$price->value / (10 ** Currencies::getFractionDigits($this->currency)));
        }

        $formatted = $this->formatNumber($this->step, $value);

        if ($price->value === 0) {
            return null;
        }

        if ($price->value < 0) {
            $this->withMeta(['class' => 'text-red-500']);
            return "(" . $formatted . ")";
        }

        $this->withMeta(['class' => 'text-green-500']);
        return $formatted;
    }

    /**
     * Format the number based on step and value.
     *
     * @param mixed  $step  The step value.
     * @param string $value The value to format.
     *
     * @return string The formatted number.
     */
    protected function formatNumber($step, string $value): string
    {
        if (!is_scalar($step)) {
            $step = 0.01; // Default step if invalid
        }

        $stepParts = explode(".", (string)$step);
        $decimals = isset($stepParts[1]) ? strlen($stepParts[1]) : 2;
        return number_format(abs((float)$value), $decimals);
    }

    /**
     * Add currencies to the field.
     *
     * @param array<string, string> $list List of currency codes and their symbols.
     *
     * @return static
     */
    public function currencies(array $list): static
    {
        $this->withMeta(['currencies' => $list]);
        return $this;
    }

    /**
     * Set the type callback.
     *
     * @param callable $typeCallback A callable that determines the type based on the value.
     *
     * @return static
     */
    public function type(callable $typeCallback): static
    {
        $this->typeCallback = $typeCallback;
        return $this;
    }

    /**
     * Get the default type callback.
     *
     * @return callable(mixed): ?bool A callable that returns null or a boolean based on the value.
     */
    protected function defaultTypeCallback(): callable
    {
        return function ($value): ?bool {
            if ($value == 0) {
                return null;
            }
            return $value < 0;
        };
    }

    /**
     * Justify the text alignment.
     *
     * @param string $direction The direction to justify ('start', 'end', 'center').
     *
     * @return static
     */
    public function justify(string $direction): static
    {
        if (!in_array($direction, ['start', 'end', 'center'], true)) {
            return $this;
        }
        $revMap = [
            'start'  => 'left',
            'end'    => 'right',
            'center' => 'center',
        ];
        $this->withMeta(['justify' => $direction, 'textAlign' => $revMap[$direction]]);
        return $this;
    }

    /**
     * Transform the alignment for display.
     *
     * @return string The transformed alignment value.
     */
    public function transformAlign(): string
    {
        $align = $this->meta['textAlign'] ?? 'right';
        $map = [
            'right'  => 'end',
            'left'   => 'start',
            'center' => 'center',
        ];
        return $map[$align];
    }

    /**
     * Specify if the value is stored in minor units.
     *
     * @param bool $yes Whether to store the value in minor units.
     *
     * @return static
     */
    public function storedminorUnits(bool $yes = true): static
    {
        $this->minorUnits = $yes;

        return $this;
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array<string, mixed> The serialized field data.
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'currency' => $this->currency,
            'symbol'   => $this->resolveCurrencySymbol(),
        ]);
    }

    /**
     * Make the field filter.
     *
     * @param NovaRequest $request The Nova request instance.
     *
     * @return NumberFilter The number filter instance.
     */
    protected function makeFilter(NovaRequest $request): NumberFilter
    {
        return new NumberFilter($this);
    }

    /**
     * Define the default filterable callback.
     *
     * @return callable(\Laravel\Nova\Http\Requests\NovaRequest, \Illuminate\Database\Eloquent\Builder<Model>, mixed, string): \Illuminate\Database\Eloquent\Builder<Model>
     */
    protected function defaultFilterableCallback(): callable
    {
        return function (NovaRequest $request, Builder $query, $value, string $attribute): Builder {
            if (!is_array($value) || count($value) !== 2) {
                return $query;
            }

            [$min, $max] = $value;

            if (!is_null($min) && !is_null($max)) {
                return $query->whereBetween($attribute, [$min, $max]);
            } elseif (!is_null($min)) {
                return $query->where($attribute, '>=', $min);
            }

            return $query->where($attribute, '<=', $max);
        };
    }

    /**
     * Set a global callback for all Accounting instances.
     *
     * @param callable $callback A callable to apply globally.
     *
     * @return void
     */
    public static function global(callable $callback): void
    {
        static::$globalCallback = $callback;
    }
}
