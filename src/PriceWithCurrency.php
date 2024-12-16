<?php

namespace HeliosLive\PhpNovaAccountingField;

use stdClass;
use App\Models\ExchangeRate;
use App\Interfaces\CurrencyConverter;
use Illuminate\Database\Eloquent\Model;

class PriceWithCurrency
{
    public ?float $value;
    public ?string $currency;
    public static $default_currency = 'USD';
    public static $converter;

    public function __construct(
        ?float $value = null,
        ?string $currency = null,
    ) {
        $this->value = $value;
        $this->currency = $currency;
    }


    public static function parse(?string $text)
    {
        if (is_null($text)) {
            return new PriceWithCurrency(null);
        }

        $data = json_decode($text);
        if (is_object($data)) {
            return new PriceWithCurrency($data->value, $data->currency);
        }
        return new PriceWithCurrency($data);
    }
    public function toArray()
    {
        return [
            'value' => $this->value,
            'currency' => $this->currency,
        ];
    }

    public function __toString()
    {
        if (is_null($this->currency)) {
            return $this->value;
        }
        return json_encode($this->toArray());
    }
    public function filled(): bool
    {
        return !is_null($this->value) && !is_null($this->currency);
    }

    public static function useCurrency($currency, $default = false)
    {
        static::$default_currency = $currency;
    }
    public function default($date)
    {
        return $this->in(static::$default_currency, $date);
    }

    public static function useConverter(CurrencyConverter $converter)
    {
        static::$converter = $converter;
    }

    public function in($currency, $date)
    {
        $from_currency = $this->currency ?? static::$default_currency;
        if (is_null($from_currency) || $from_currency === $currency) {
            return $this->value;
        }

        if (is_null(static::$converter)) {
            throw new \Exception('No converter set');
        }

        return static::$converter->rate($from_currency, $currency, $date) * $this->value;
    }
}
