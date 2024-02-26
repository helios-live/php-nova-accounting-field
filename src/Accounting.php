<?php

namespace HeliosLive\PhpNovaAccountingField;

use Brick\Math\RoundingMode;
use Brick\Money\Context\CustomContext;
use Laravel\Nova\Fields\Currency;
use Symfony\Polyfill\Intl\Icu\Currencies;

class Accounting extends Currency
{
    protected $typeCallback;
    public $inMinorUnits = true;

    public function __construct($name, $attribute = null, $cb = null)
    {
        parent::__construct($name, $attribute, $cb);


        $this->typeCallback = $this->defaultTypeCallback();

        $this->step(0.01)
            ->currency('USD')
            ->asHtml()
            ->displayUsing(function ($value) {
                $this->context = new CustomContext(8);
                // try {
                if ($this->inMinorUnits && !$this->isValidNullValue($value)) {

                    $value = $this->toMoneyInstance(
                        $value / (10 ** Currencies::getFractionDigits($this->currency)),
                        $this->currency
                    )->getMinorAmount()->toScale(2, RoundingMode::HALF_UP)->toFloat();
                }


                $value = number_format(abs($value), 2);
                $class = "text-green-500";
                $res = ($this->typeCallback)($value);
                if ($res === true) {
                    $value = "(" . $value . ")";
                    $class = "text-red-500";
                } elseif (is_null($res)) {
                    $class = "";
                }
                $meta = $this->meta();
                return view('partials.field-accounting', compact('value', 'class', 'meta'))->render();
            })
            ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                if ($request->has($requestAttribute)) {
                    $value = $request->$requestAttribute;
                } else {
                    // we are in a flexible content
                    $key = $model->inUseKey();
                    $attribute = $key . '__' . $requestAttribute;
                    $value = $request->get($attribute);
                }

                if (($this->inMinorUnits || $this->minorUnits) && !$this->isValidNullValue($value)) {
                    $value = $this->toMoneyInstance(
                        $value * (10 ** Currencies::getFractionDigits($this->currency)),
                        $this->currency
                    )->getMinorAmount()->toInt();
                }
                $model->$attribute = $value;
            });

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
    /**
     * The value in database is store in minor units (cents for dollars).
     */
    public function storedInMinorUnits($yes = true):static
    {
        $this->inMinorUnits = $yes;

        return $this;
    }
}
