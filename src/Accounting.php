<?php

namespace HeliosLive\PhpNovaAccountingField;

use Brick\Math\RoundingMode;
use Laravel\Nova\Fields\Currency;
use Brick\Money\Context\CustomContext;
use Symfony\Polyfill\Intl\Icu\Currencies;
use Laravel\Nova\Fields\Filters\TextFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\Filters\NumberFilter;
use HeliosLive\PhpNovaAccountingField\PriceWithCurrency;

class Accounting extends Currency
{

    public static $globalCallback = null;

    /**
     * The field's component.
     *
     * @var string
     */

    public $component = 'php-nova-accounting-field';
    protected $typeCallback;
    public $inMinorUnits = false;

    public function __construct($name, $attribute = null, $cb = null)
    {
        parent::__construct($name, $attribute, $cb);


        $this->typeCallback = $this->defaultTypeCallback();

        $this->step(0.01)
            ->currency('USD')
            ->asHtml()
            ->resolveUsing(function ($value) {
                $price = PriceWithCurrency::parse($value);
                if (! is_null($price->currency)) {
                    $this->currency = $price->currency;
                }
                return json_encode($price);
            })
            ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                $value = $request->$requestAttribute;

                $price = PriceWithCurrency::parse($value);

                $model->{$attribute} = $price;
            })
            ->displayUsing(function ($value) {

                $this->withMeta(['justify' => $this->transformAlign()]);

                $price = PriceWithCurrency::parse($value);
                ray(compact('price', 'value'));
                $this->context = new CustomContext(8);


                $value = $price->value;

                $this->currency = $price->currency ?? $this->currency;

                if ($this->inMinorUnits && !$this->isValidNullValue($price->value)) {

                    $value = $this->toMoneyInstance(
                        $price / (10 ** Currencies::getFractionDigits($this->currency)),
                        $this->currency
                    )->getMinorAmount()->toScale(2, RoundingMode::HALF_UP)->toFloat();
                }

                $decimals = strlen(explode(".", $this->step)[1]);


                $formatted = number_format(abs($value), $decimals);
                if ($price->value == 0) {
                    return null;
                }

                if ($price->value < 0) {
                    $this->withMeta(['class' => 'text-red-500']);

                    $formatted = "(" . $formatted . ")";
                    return $formatted;
                }

                $this->withMeta(['class' => 'text-green-500']);
                return $formatted;
            });
        if (static::$globalCallback) {
            call_user_func(static::$globalCallback, $this);
        }
    }


    public function currencies(array $list)
    {
        $this->withMeta(['currencies' => $list]);
        return $this;
    }

    public function type(callable $typeCallback)
    {
        $this->typeCallback = $typeCallback;
        return $this;
    }
    protected function defaultTypeCallback()
    {
        return function ($value) {
            if ($value == 0) return null;
            return $value < 0;
        };
    }
    /*
     *
     */
    public function justify($direction)
    {
        if (!in_array($direction, ['start', 'end',  'center'])) {
            return $this;
        }
        $revMap = [
            'start' => 'left',
            'end' => 'right',
            'center' => 'center',
        ];
        $this->withMeta(['justify' => $direction, 'textAlign' => $revMap[$direction]]);
        return $this;
    }

    public function transformAlign()
    {
        $align = $this->meta['textAlign'] ?? 'right';
        $map = [
            'right' => 'end',
            'left' => 'start',
            'center' => 'center',
        ];
        return $map[$align];
    }
    /**
     * The value in database is store in minor units (cents for dollars).
     */
    public function storedInMinorUnits($yes = true): static
    {
        $this->inMinorUnits = $yes;

        return $this;
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        // ray(array_merge(parent::jsonSerialize(), [
        //     'currency' => $this->currency,
        //     'symbol' => $this->resolveCurrencySymbol(),
        // ]));
        return array_merge(parent::jsonSerialize(), [
            'currency' => $this->currency,
            'symbol' => $this->resolveCurrencySymbol(),
        ]);
    }
    /**
     * Make the field filter.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return \Laravel\Nova\Fields\Filters\Filter
     */
    protected function makeFilter(NovaRequest $request)
    {
        return new NumberFilter($this);
    }

    /**
     * Define the default filterable callback.
     *
     * @return callable(\Laravel\Nova\Http\Requests\NovaRequest, \Illuminate\Database\Eloquent\Builder, mixed, string):\Illuminate\Database\Eloquent\Builder
     */
    protected function defaultFilterableCallback()
    {
        return function (NovaRequest $request, $query, $value, $attribute) {
            [$min, $max] = $value;

            if (! is_null($min) && ! is_null($max)) {
                return $query->whereBetween($attribute, [$min, $max]);
            } elseif (! is_null($min)) {
                return $query->where($attribute, '>=', $min);
            }

            return $query->where($attribute, '<=', $max);
        };
    }
    public static function global($callback)
    {
        static::$globalCallback = $callback;
    }
}
