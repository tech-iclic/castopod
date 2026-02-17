<?php

declare(strict_types=1);

namespace App\Views\Components\Forms;

use Override;

class Select extends FormComponent
{
    protected array $props = ['options'];

    protected array $casts = [
        'options' => 'array',
    ];

    /**
     * @var array<array<string, string>>
     */
    protected array $options = [];

    #[Override]
    public function render(): string
    {
        $this->mergeClass('w-full focus:border-contrast border-3 rounded-lg bg-elevated border-contrast');
        $defaultAttributes = [
            'data-select-text'     => lang('Common.forms.multiSelect.selectText'),
            'data-loading-text'    => lang('Common.forms.multiSelect.loadingText'),
            'data-no-results-text' => lang('Common.forms.multiSelect.noResultsText'),
            'data-no-choices-text' => lang('Common.forms.multiSelect.noChoicesText'),
            'data-max-item-text'   => lang('Common.forms.multiSelect.maxItemText'),
        ];
        $this->attributes = [...$defaultAttributes, ...$this->attributes];

        $options = '';
        $selected = $this->getValue();
        foreach ($this->options as $option) {
            $optionValue = $option['value'] ?? '';
            $isSelected = is_scalar($optionValue)
                && is_scalar($selected)
                && (string) $optionValue === (string) $selected;

            $options .= '<option ' . (array_key_exists('description', $option) ? 'data-label-description="' . $option['description'] . '" ' : '') . 'value="' . $optionValue . '"' . ($isSelected ? ' selected' : '') . '>' . $option['label'] . '</option>';
        }

        $this->attributes['name'] = $this->name;

        return <<<HTML
        <select {$this->getStringifiedAttributes()}>{$options}</select>
        HTML;
    }
}
