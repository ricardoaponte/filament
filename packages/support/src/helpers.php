<?php

namespace Filament\Support;

use ErrorException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Illuminate\Translation\MessageSelector;
use Illuminate\View\ComponentAttributeBag;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

if (!function_exists('Filament\Support\get_model_label')) {
    function get_model_label(string $model): string
    {
        return (string)Str::of(class_basename($model))
            ->kebab()
            ->replace('-', ' ');
    }
}

if (!function_exists('Filament\Support\locale_has_pluralization')) {
    function locale_has_pluralization(): bool
    {
        return (new MessageSelector())->getPluralIndex(app()->getLocale(), 10) > 0;
    }
}

if (!function_exists('Filament\Support\prepare_inherited_attributes')) {
    function prepare_inherited_attributes(ComponentAttributeBag $attributes): ComponentAttributeBag
    {
        $originalAttributes = $attributes->getAttributes();

        $attributes->setAttributes(
            collect($originalAttributes)
                ->filter(fn($value, string $name): bool => !Str::of($name)->startsWith('x-'))
                ->mapWithKeys(fn($value, string $name): array => [Str::camel($name) => $value])
                ->merge($originalAttributes)
                ->all(),
        );

        return $attributes;
    }
}

if (!function_exists('Filament\Support\get_column_property')) {
    function get_column_property($column, $property)
    {
        $properties = json_decode($column->getComment());
        if ($properties && property_exists($properties, $property) && $properties->{$property} === true) {
            return $properties;
        }
        return null;
    }
}

if (!function_exists('Filament\Support\get_model_relationships')) {
    /**
     * @throws ReflectionException
     */
    function get_model_relationships($model): array
    {
        $relationships = [];
        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class != get_parent_class($model)) {
                continue;
            }

            try {
                $return = $method->invoke($model);

                if ($return instanceof Relation) {
                    $relationships[$method->getName()] = [
                        'type' => (new ReflectionClass($return))->getShortName(),
                        'model' => (new ReflectionClass($return->getRelated()))->getName()
                    ];
                }
            } catch (ErrorException $e) {
            }
        }

        return $relationships;
    }
}


