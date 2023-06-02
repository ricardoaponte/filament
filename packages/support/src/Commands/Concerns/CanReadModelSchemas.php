<?php

namespace Filament\Support\Commands\Concerns;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionException;
use Throwable;

trait CanReadModelSchemas
{
    protected function getModel(string $model): ?string
    {
        if (!class_exists($model)) {
            return null;
        }

        return $model;
    }

    protected function getModelTable(string $model): ?Table
    {
        $modelClass = $model;
        $model = app($model);

        try {
            return $model
                ->getConnection()
                ->getDoctrineSchemaManager()
                ->listTableDetails($model->getTable());
        } catch (Throwable $exception) {
            $this->warn("Unable to read table schema for model [{$modelClass}]: {$exception->getMessage()}");

            return null;
        }
    }

    protected function guessBelongsToRelationshipName(AbstractAsset $column, string $model): ?string
    {
        $modelReflection = invade(app($model));
        $guessedRelationshipName = Str::of($column->getName())->beforeLast('_id');
        $hasRelationship = $modelReflection->reflected->hasMethod($guessedRelationshipName);

        if (!$hasRelationship) {
            $guessedRelationshipName = $guessedRelationshipName->camel();
            $hasRelationship = $modelReflection->reflected->hasMethod($guessedRelationshipName);
        }

        if (!$hasRelationship) {
            return null;
        }

        try {

            $method = $modelReflection->reflected->getMethod($guessedRelationshipName);
            $methodReturnType = $method->getReturnType();
            $columnTypeClass = $column->getType()::class;

            if (!$method->hasReturnType() && ($columnTypeClass === Types\IntegerType::class || $columnTypeClass === Types\BigIntType::class)) {
                return $guessedRelationshipName;
            }

            if (!$methodReturnType || !method_exists($methodReturnType, 'getName') || $methodReturnType->getName() !== BelongsTo::class) {
                return null;
            }

            return $guessedRelationshipName;


        } catch (ReflectionException $exception) {
            return null;
        }

    }

    protected function guessBelongsToRelationshipTableName(AbstractAsset $column): ?string
    {
        $tableName = Str::of($column->getName())->beforeLast('_id');

        if (Schema::hasTable(Str::plural($tableName))) {
            return Str::plural($tableName);
        }

        if (!Schema::hasTable($tableName)) {
            return null;
        }

        return $tableName;
    }

    protected function guessBelongsToRelationshipTitleColumnName(AbstractAsset $column, string $model): string
    {
        $schema = $this->getModelTable($model);

        if ($schema === null) {
            return 'id';
        }

        $columns = collect(array_keys($schema->getColumns()));

        if ($columns->contains('name')) {
            return 'name';
        }

        if ($columns->contains('title')) {
            return 'title';
        }

        return $schema->getPrimaryKey()->getColumns()[0];
    }
}
