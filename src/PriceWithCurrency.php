<?php

namespace HeliosLive\PhpNovaAccountingField;

use App\Models\Model;
use App\Interfaces\CurrencyConverter;

class PriceWithCurrency implements \JsonSerializable
{
    public ?float $value;
    protected ?string $currency;
    public static $default_currency = 'USD';
    public static $converter;

    protected $attribute = '';
    protected $model = null;

    public function __construct(
        ?float $value = null,
        ?string $currency = null,
        Model $model = null,
        string $attribute = '',
        array $extraValues = []
    ) {
        $this->value = $value;
        $this->currency = $currency;
        $this->model = $model;
        $this->attribute = $attribute;

        foreach ($extraValues as $key => $value) {
            $this->$key = $value;
        }
    }


    public static function parse(mixed $text)
    {
        if (is_null($text)) {
            return new PriceWithCurrency(null);
        }

        if ($text instanceof PriceWithCurrency) {
            return $text;
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
            'currency' => $this->currency ?? static::$default_currency,
        ];
    }

    public function __toString()
    {
        $data = $this->toArray();
        return $data['currency'] . ' ' . number_format($data['value'], 2);
    }
    public function filled(): bool
    {
        return !is_null($this->value) && !is_null($this->currency);
    }

    public function setModel(Model $model, string $attribute)
    {
        $this->model = $model;
        $this->attribute = $attribute;
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

    public function in($currency, $date, $save = true)
    {
        $from_currency = $this->currency ?? static::$default_currency;
        if (is_null($from_currency) || $from_currency === $currency) {
            return $this->value;
        }

        if (is_null(static::$converter)) {
            throw new \Exception('No converter set');
        }

        if (isset($this->$currency)) {
            return $this->$currency;
        }

        $rate =  static::$converter->rate($from_currency, $currency, $date) * $this->value;

        if ($save) {
            $this->$currency = $rate;

            $attr = $this->attribute;
            $model = $this->model;
            $model->$attr = $this;
            $model->saveQuietly();
            $model->fresh();
        }
        return $rate;
    }
    public function __set($name, $value)
    {
        $this->$name = $value;
    }
    public function __get($name)
    {
        if ($name === 'currency') {
            return $this->$name ?? static::$default_currency;
        }
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }
    public function jsonSerialize(): mixed
    {
        $list =  get_object_vars($this);
        unset($list['model'], $list['attribute']);
        $list['currency'] = $this->currency ?? static::$default_currency;
        return $list;
    }
}
