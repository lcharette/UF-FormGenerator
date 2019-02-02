<?php

/*
 * UF Form Generator.
 *
 * @link https://github.com/lcharette/UF_FormGenerator
 *
 * @copyright Copyright (c) 2017 Louis Charette
 * @license   https://github.com/lcharette/UF_FormGenerator/blob/master/LICENSE (MIT License)
 */

namespace UserFrosting\Sprinkle\FormGenerator\Element;

/**
 * Checkbox input type class.
 * Manage the default attributes required to display a checkbox input.
 *
 * @extends BaseInput
 */
class Checkbox extends BaseInput
{
    /**
     * {@inheritdoc}
     */
    protected function applyTransformations()
    {
        $this->element = array_merge([
            'class' => 'js-icheck',
            'name' => $this->name,
            'id' => 'field_' . str_replace(['[', ']'], ['_', ''], $this->name),
            'binary' => true,
        ], $this->element);

        // We add the check status instead of the value
        if ($this->element['binary'] !== false && $this->getValue() == 1) {
            $this->element['checked'] = 'checked';
        }
    }
}
