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
        $value = json_decode($value, false);
        if (is_null($value)) {
            return null;
        }

        if (! is_object($value)) {
            return new PriceWithCurrency($value);
        }

        return new PriceWithCurrency($value->value, $value->currency);
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

        if ($value instanceof PriceWithCurrency) {
            if (empty($value->currency)) {
                return $value->value;
            }
            return json_encode($value->toArray());
        }

        if (ctype_digit((string)$value)) {
            return json_encode((int) $value);
        }

        return json_encode((float)$value);
    }
}
