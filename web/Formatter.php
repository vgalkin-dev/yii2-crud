<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use Yii;
use yii\base\InvalidParamException;
use yii\base\Model;
use yii\helpers\Html;

class Formatter extends \yii\i18n\Formatter
{
    /**
     * @var EnumCollection dictionaries used when formatting an enum value.
     */
    private $enums;

    /**
     * Returns the enum collection.
     * The enum collection contains the currently registered enums.
     * @return EnumCollection the enum collection
     */
    public function getEnums()
    {
        if ($this->enums === null) {
            $this->enums = new EnumCollection;
        }
        return $this->enums;
    }

    /**
     * Prepares format or filter methods params.
     * @param mixed $value
     * @param string|array $format
     * @return array two values: $format and $params
     */
    private function prepareFormat($value, $format)
    {
        if (!is_array($format)) {
            return [$format, [$value]];
        }
        if (!isset($format[0])) {
            throw new InvalidParamException('The $format array must contain at least one element.');
        }
        $f = $format[0];
        $format[0] = $value;
        $params = $format;
        $format = $f;
        return [$format, $params];
    }

    /**
     * @inheritdoc
     */
    public function format($value, $format)
    {
        list($format, $params) = $this->prepareFormat($value, $format);
        $method = 'as' . $format;
        if (!$this->hasMethod($method)) {
            throw new InvalidParamException("Unknown format type: $format");
        }
        return call_user_func_array([$this, $method], $params);
    }

    /**
     * Formats the value as a time interval.
     * @param mixed $value the value to be formatted, in ISO8601 format.
     * @return string the formatted result.
     */
    public function asInterval($value)
    {
        if ($value === null) {
            return $this->nullDisplay;
        }
        if ($value instanceof \DateInterval) {
            $negative = $value->invert;
            $interval = $value;
        } else {
            if (strpos($value, '-') !== false) {
                $negative = true;
                $interval = new \DateInterval(str_replace('-', '', $value));
            } else {
                $negative = false;
                $interval = new \DateInterval($value);
            }
        }
        if ($interval->y > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{a year} other{# years}}', [
                'delta' => $interval->y,
            ], $this->locale);
        }
        if ($interval->m > 0) {
            $parts[] = Yii::t('yii', '{delta, plural, =1{a month} other{# months}}', [
                'delta' => $interval->m,
            ], $this->locale);
        }
        if ($interval->d > 0) {
            $parts[] = Yii::t('yii', '{delta, plural, =1{a day} other{# days}}', [
                'delta' => $interval->d,
            ], $this->locale);
        }
        if ($interval->h > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{an hour} other{# hours}}', [
                'delta' => $interval->h,
            ], $this->locale);
        }
        if ($interval->i > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{a minute} other{# minutes}}', [
                'delta' => $interval->i,
            ], $this->locale);
        }
        if ($interval->s > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{a second} other{# seconds}}', [
                'delta' => $interval->s,
            ], $this->locale);
        }

        return empty($parts) ? $this->nullDisplay : (($negative ? '-' : '') . implode(', ', $parts));
    }

    /**
     * Formats the value as an enum value.
     * @param mixed $value the value to be formatted
     * @param string $enumName
     * @return string the formatted result.
     */
    public function asEnum($value, $enumName)
    {
        if (!isset($this->enums[$enumName])) {
            throw new InvalidParamException("The '$enumName' enum has not been registered in the current formatter");
        }
        if ($value === null) {
            return $this->nullDisplay;
        }
        return isset($this->enums[$enumName][$value]) ? $this->enums[$enumName][$value] : $value;
    }

    /**
     * Formats the value as a link to a matching controller.
     * @param mixed $value the value to be formatted.
     * @param array $options the tag options in terms of name-value pairs. See [[Html::a()]].
     * @return string the formatted result.
     */
    public function asCrudLink(Model $value, $options = [])
    {
        if ($value === null) {
            return $this->nullDisplay;
        }
        $route = Yii::$app->crudModelsMap[$value::className()];
        $value = Html::encode((string)$value);

        if ($route === null || !Yii::$app->user->can($value::className().'.read', ['model' => $value])) {
            return $value;
        }

        return Html::a($value, $route, $options);
    }
    
    /**
     * Method checks if value is (-;+)infinity; If so returns text, else return parent.
     * 
     * @param integer|string|DateTime $value @see parent
     * @param string $format @see parent
     * @return string || string the formatted datetime
     */
    public function asDatetime($value, $format = null)
    {        
        if(($label = $this->isInfinity($value)) !== null) {
            return $label;
        }
        return parent::asDatetime($value, $format);
    }
    
    protected function isInfinity($value)
    {
        $value = strtolower($value);
        switch($value) {
            case '-infinity': return Yii::t('app', 'From infinity');
            case 'infinity': return Yii::t('app', 'To infinity');
            default: return null;
        }
    }

    /**
     * Filters the value based on the given format type.
     * This method will call one of the "filter" methods available in this class to do the filtering.
     * For type "xyz", the method "filterXyz" will be used. For example, if the format is "html",
     * then [[filterHtml()]] will be used. Format names are case insensitive.
     * @param mixed $value the value to be filtered.
     * @param string|array $format the format of the value, e.g., "html", "text". To specify additional
     * parameters of the filtering method, you may use an array. The first element of the array
     * specifies the format name, while the rest of the elements will be used as the parameters to the filtering
     * method. For example, a format of `['date', 'Y-m-d']` will cause the invocation of `filterDate($value, 'Y-m-d')`.
     * @return string the filtering result.
     * @throws InvalidParamException if the format type is not supported by this class.
     */
    public function filter($value, $format)
    {
        list($format, $params) = $this->prepareFormat($value, $format);
        $method = 'filter' . $format;
        if (!$this->hasMethod($method)) {
            throw new InvalidParamException("Unknown format type: $format");
        }
        return call_user_func_array([$this, $method], $params);
    }

    /**
     * Parses boolean format strings, true/false and 0/1 as strings.
     * @param string $value the value to be filtered
     * @return integer 0 or 1
     */
    public function filterBoolean($value)
    {
        if ($value === null) {
            return null;
        }
        $booleanFormat = [
            $this->booleanFormat[0] => false, $this->booleanFormat[1] => true,
            'false'                 => false, 'true' => true,
            '0'                     => false, '1' => true,
            ''                      => false,
        ];
        $map = [];
        foreach ($booleanFormat as $label => $key) {
            $label = mb_strtolower($label, 'UTF-8');
            $map[$label] = $key;
            if (mb_strlen($label, 'UTF-8') > 1) {
                $map[mb_substr($label, 0, 1, 'UTF-8')] = $key;
            }
        }
        $value = mb_strtolower($value, 'UTF-8');

        return !isset($map[$value]) ? null : (int)$map[$value];
    }

    /**
     * @param string $value the value to be filtered
     * @param int $scale not used
     * @param int $precision number of digits after the decimal separator
     * @param int $multiplier if set to match the precision, returned value will be an integer
     * @return int|double
     */
    public function filterDecimal($value, $scale = null, $precision = 2, $multiplier = null)
    {
        if ($value === null) {
            return null;
        }
        if ($scale !== null) {
            throw new InvalidParamException('netis\utils\web\Formatter::filterDecimal does not support setting scale');
        }
        $value = $this->str2dec($value, $precision);
        $defaultMultiplier = pow(10, $precision);
        if ($multiplier === null) {
            $multiplier = $defaultMultiplier;
        } elseif ($multiplier === $defaultMultiplier) {
            return $value;
        }

        return (double)$value / $multiplier;
    }

    /**
     * Filters the value to the ones contained in the enum and returns its key.
     * Note, the value is searched using exact comparison so it may need to be trimmed.
     * @param string $value the value to be filtered
     * @param string $enumName
     * @return string if the value is not found, returns null
     */
    public function filterEnum($value, $enumName)
    {
        if (!isset($this->enums[$enumName])) {
            throw new InvalidParamException("The '$enumName' enum has not been registered in the current formatter");
        }
        if ($value === null) {
            return null;
        }
        return ($key = array_search($value, $this->enums[$enumName])) !== null ? $key : null;
    }

    /**
     * @param array $value the value to be filtered
     * @return int
     */
    public function filterFlags($value)
    {
        if ($value === null) {
            return null;
        }
        return is_array($value) ? array_sum($value) : 0;
    }

    /**
     * Filters the date using strtotime() and returns it in Y-m-d format.
     * @param string $value the value to be filtered
     * @return string
     */
    public function filterDate($value)
    {
        if ($value === null || (($ts = strtotime($value)) === false)) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    /**
     * Filters the date using strtotime() and returns it in Y-m-d H:i:s format.
     * @param string $value the value to be filtered
     * @return string
     */
    public function filterDatetime($value)
    {
        if ($value === null || (($ts = strtotime($value)) === false)) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Filters the time using strtotime() and returns it in H:i:s format.
     * @param string $value the value to be filtered
     * @return string
     */
    public function filterTime($value)
    {
        if ($value === null || (($ts = strtotime($value)) === false)) {
            return null;
        }

        return date('H:i:s', $ts);
    }

    /**
     * Maps polish specification of a time interval to PHP DateInterval's format.
     * Ex. 1 miesiąc, 3 dni
     * Returns null if a valid format cannot be determined.
     * @param  string $value the value to be filtered
     * @return string
     */
    public function filterInterval($value)
    {
        if ($value === null) {
            return null;
        }
        /**
         * For Polish language:
         * Y - rok, lata, lat, roku
         * M - miesiąc, miesiące, miesięcy, miesiąca
         * D - dzień, dni, dni, dnia
         * W - tydzień, tygodnie, tygodni, tygodnia
         * H - godzina, godziny, godzin, godziny
         * M - minuta, minuty, minut, minuty
         * S - sekunda, sekundy, sekund, sekundy
         */
        $units    = [
            'rok' => ['symbol' => 'Y', 'type' => 'date'],
            'lat' => ['symbol' => 'Y', 'type' => 'date'],
            'mie' => ['symbol' => 'M', 'type' => 'date'],
            'dz'  => ['symbol' => 'D', 'type' => 'date'],
            'd'   => ['symbol' => 'D', 'type' => 'date'],
            'dn'  => ['symbol' => 'D', 'type' => 'date'],
            'ty'  => ['symbol' => 'W', 'type' => 'date'],
            'h'   => ['symbol' => 'H', 'type' => 'time'],
            'g'   => ['symbol' => 'H', 'type' => 'time'],
            'm'   => ['symbol' => 'M', 'type' => 'time'],
            's'   => ['symbol' => 'S', 'type' => 'time'],
        ];
        $result   = '';
        $negative = false;
        preg_match_all('/([\d,\.-]+)\s*(\pL*)/u', $value, $matches);
        $appended_date = false;
        $appended_time = false;
        foreach ($matches[1] as $key => $quantity) {
            // cast quantity to integer, skip whole part if not valid
            $q = intval($quantity);
            if ($q < 0) {
                $negative = true;
            }
            // map unit
            $unit = mb_strtolower($matches[2][$key], 'UTF-8');
            foreach ($units as $short => $opts) {
                // if can be mapped
                if (substr($unit, 0, strlen($short)) == $short) {
                    // if this is a date unit, remember it
                    if ($opts['type'] == 'date') {
                        $appended_date = true;
                    }
                    // if this is a first time unit after date units
                    if ($opts['type'] == 'time' && !$appended_time) {
                        $result .= 'T';
                        $appended_time = true;
                    }
                    $result .= $q . $opts['symbol'];
                    // stop checking other units
                    break;
                }
            }
        }
        if (empty($result)) {
            return null;
        }
        try {
            new \DateInterval('P' . $result);
        } catch (\Exception $e) {
            return null;
        }

        return 'P' . ($negative ? '-' : '') . $result;
    }

    /**
     * Converts a decimal number as string to a 32 bit integer.
     * @param string $value
     * @param int $precision
     * @return int
     */
    public function str2dec($value, $precision = 2)
    {
        $value = preg_replace('/[^\d,\.\-]+/', '', $value);
        if ($value === '') {
            return null;
        }
        if (($pos = strpos($value, ',')) === false && ($pos = strpos($value, '.')) === false) {
            $value = $value . str_pad('', $precision, '0');
            return min(max((int)$value, -0x80000000), 0xFFFFFFFF);
        }
        $distance = strlen($value) - $pos;
        if ($distance > $precision) {
            $value = substr($value, 0, $pos + $precision + 1);
        } else {
            do {
                $value .= '0';
            } while ($distance !== $precision-- && $precision > 0);
        }
        $value = str_replace([',', '.'], '', $value);

        return min(max((int)$value, -0x80000000), 0xFFFFFFFF);
    }
}
