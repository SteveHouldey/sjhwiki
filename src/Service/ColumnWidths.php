<?php
declare(strict_types=1);

namespace App\Service;

use HTMLPurifier_AttrDef;

class ColumnWidths extends HTMLPurifier_AttrDef
{
    /**
     * Validate the pixel widths used by the editor's table cells.
     *
     * @param mixed $string Attribute value.
     * @param mixed $config Purifier configuration.
     * @param mixed $context Purifier context.
     * @return string|false
     */
    public function validate(mixed $string, mixed $config, mixed $context): string|false
    {
        if (!is_string($string)) {
            return false;
        }
        $value = trim($string);
        if (!preg_match('/\A(?:[0-9]{1,4}|10000)(?:,(?:[0-9]{1,4}|10000)){0,49}\z/D', $value)) {
            return false;
        }

        return $value;
    }
}
