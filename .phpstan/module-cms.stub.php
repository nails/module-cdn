<?php

/**
 * Declarations of the module-cms classes this module extends, for static analysis only. module-cms requires this
 * module, so it cannot be a dependency here. Keep the signatures in step with module-cms.
 */

namespace Nails\Cms\WidgetEditor\Interfaces {

    interface Widget
    {
    }

    interface Field
    {
        public static function getType(): string;

        public static function getAssets(): array;

        public function getKey(): string;

        public function getDefault(): mixed;

        public function fill(mixed $value): mixed;

        public function getCanvasEdit(): ?string;

        public function validate(): void;

        public function toArray(): array;
    }
}

namespace Nails\Cms\WidgetEditor\Widget {

    abstract class Base implements \Nails\Cms\WidgetEditor\Interfaces\Widget
    {
        public function getFields(): ?array
        {
        }

        abstract public function render(array $data): string;

        protected function isPreview(): bool
        {
        }

        protected function editable(string $field, string $tag, mixed $value, array $attrs = [], ?string $edit = null): string
        {
        }
    }
}

namespace Nails\Cms\WidgetEditor\Field {

    abstract class Base implements \Nails\Cms\WidgetEditor\Interfaces\Field
    {
        protected string $label = '';
        protected string $tip = '';
        protected bool $required = false;
        protected bool $wide = false;
        protected array $visibleWhen = [];

        final public function __construct(protected readonly string $key)
        {
        }

        public static function make(string $key): static
        {
        }

        public static function getAssets(): array
        {
        }

        public function label(string $label): static
        {
        }

        public function tip(string $tip): static
        {
        }

        public function required(bool $required = true): static
        {
        }

        public function wide(bool $wide = true): static
        {
        }

        public function visibleWhen(array $conditions): static
        {
        }

        public function default(mixed $default): static
        {
        }

        public function getKey(): string
        {
        }

        public function getDefault(): mixed
        {
        }

        public function fill(mixed $value): mixed
        {
        }

        public function getCanvasEdit(): ?string
        {
        }

        public function validate(): void
        {
        }

        public function toArray(): array
        {
        }

        protected function defaultValue(): mixed
        {
        }

        protected function config(): array
        {
        }
    }
}

namespace Nails\Cms\Cms\Fields {

    class Text extends \Nails\Cms\WidgetEditor\Field\Base
    {
        public static function getType(): string
        {
        }

        public function maxLength(?int $maxLength): static
        {
        }
    }

    class Select extends \Nails\Cms\WidgetEditor\Field\Base
    {
        public static function getType(): string
        {
        }

        public function options(array $options): static
        {
        }
    }
}
