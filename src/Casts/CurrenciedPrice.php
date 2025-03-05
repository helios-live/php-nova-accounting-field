<?php

namespace HeliosLive\PhpNovaAccountingField\Casts;

use Illuminate\Database\Eloquent\Model;
use HeliosLive\PhpNovaAccountingField\PriceWithCurrency;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class CurrenciedPrice implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $extra_currencies = json_decode($value, true);
        $storedPlainInt = !is_array($extra_currencies);
        if ($storedPlainInt) {
            $extra_currencies = [];
        } else {
            unset($extra_currencies['value'], $extra_currencies['currency']);
        }

        $value = json_decode($value, false);

        if (is_null($value)) {
            return null;
        }
        if (! is_object($value)) {
            return new PriceWithCurrency($value, null, $model, $key, $extra_currencies);
        }

        return new PriceWithCurrency($value->value, $value->currency, $model, $key, $extra_currencies);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (ctype_digit((string)$value)) {
            return json_encode((int) $value);
        }

        if (filter_var((string)$value, FILTER_VALIDATE_FLOAT) !== false) {
            return json_encode((float) $value);
        }
        if ($value instanceof PriceWithCurrency) {
            return json_encode($value);
        }
        throw new \Exception("Not Implemented", 1);
    }
}
