<?php

declare(strict_types=1);

namespace LiteAdmin\Metric;

/**
 * KPI Metric card for LiteAdmin dashboard.
 */
class AdminMetric
{
    public function __construct(
        public readonly string $label,
        public readonly string|int|float $value,
        public readonly ?string $prefix = null,
        public readonly ?string $suffix = null,
        public readonly ?string $icon = null,
        public readonly ?string $description = null,
    ) {}

    public static function make(string $label, string|int|float $value): self
    {
        return new self($label, $value);
    }

    public function prefix(string $prefix): self
    {
        return new self($this->label, $this->value, $prefix, $this->suffix, $this->icon, $this->description);
    }

    public function suffix(string $suffix): self
    {
        return new self($this->label, $this->value, $this->prefix, $suffix, $this->icon, $this->description);
    }

    public function icon(string $icon): self
    {
        return new self($this->label, $this->value, $this->prefix, $this->suffix, $icon, $this->description);
    }

    public function description(string $description): self
    {
        return new self($this->label, $this->value, $this->prefix, $this->suffix, $this->icon, $description);
    }

    public function render(): string
    {
        $valFormatted = is_numeric($this->value) ? number_format((float)$this->value) : (string)$this->value;
        $displayVal = ($this->prefix ?? '') . $valFormatted . ($this->suffix ?? '');
        $iconHtml = $this->icon ? "<span class=\"metric-icon\">{$this->icon}</span>" : '';
        $descHtml = $this->description ? "<div class=\"metric-desc\">{$this->description}</div>" : '';

        return <<<HTML
<div class="metric-card">
    <div class="metric-header">
        <span class="metric-label">{$this->label}</span>
        {$iconHtml}
    </div>
    <div class="metric-value">{$displayVal}</div>
    {$descHtml}
</div>
HTML;
    }
}
