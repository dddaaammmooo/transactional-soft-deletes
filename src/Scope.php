<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

use Illuminate\Database\Eloquent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class Scope
 *
 * Adds the soft deletion methods onto the Illuminate\Database\Eloquent\Builder class
 *
 * @package Dddaaammmooo\TransactionalSoftDeletes
 */
class Scope implements Eloquent\Scope
{
    /**
     * These are the functions that will be added to the Laravel query builder
     *
     * @var array $extensions
     */
    protected $extensions = [
        'Restore',
        'WithTrashed',
        'WithoutTrashed',
        'OnlyTrashed',
    ];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder        $builder
     * @param Eloquent\Model $model
     */
    public function apply(Builder $builder, Eloquent\Model $model)
    {
        /** @var Model $model */
        $builder->whereNull($model->getQualifiedDeletedAtColumn());
    }

    /**
     * Extend the query builder with the needed functions
     *
     * @param Builder $builder
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension)
        {
            $this->{"add{$extension}"}($builder);
        }

        $builder->onDelete(
            function (Builder $builder)
            {
                foreach ($builder->getModels() as $model)
                {
                    $model->delete();
                }
            }
        );
    }

    /**
     * Get the "delete_transaction_id" column for the builder
     *
     * @param Builder $builder
     * @return string
     */
    protected function getDeletedAtColumn(Builder $builder)
    {
        /** @var Model $model */
        $model = $builder->getModel();

        return $model->getQualifiedDeletedAtColumn();
    }

    /**
     * Add the restore extension to the builder
     *
     * @param Builder $builder
     */
    protected function addRestore(Builder $builder)
    {
        $builder->macro(
            'restore', function (Builder $builder)
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $builder->withTrashed();

            /** @var Model $model */
            foreach ($builder->get() as $model)
            {
                $model->restore();
            }
        }
        );
    }

    /**
     * Add the with-trashed extension to the builder
     *
     * @param Builder $builder
     */
    protected function addWithTrashed(Builder $builder)
    {
        $builder->macro(
            'withTrashed', function (Builder $builder)
        {
            return $builder->withoutGlobalScope($this);
        }
        );
    }

    /**
     * Add the without-trashed extension to the builder
     *
     * @param Builder $builder
     */
    protected function addWithoutTrashed(Builder $builder)
    {
        $builder->macro(
            'withoutTrashed', function (Builder $builder)
        {
            /** @var Model $model */
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNull(
                $model->getQualifiedDeletedAtColumn()
            );

            return $builder;
        }
        );
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @param Builder $builder
     */
    protected function addOnlyTrashed(Builder $builder)
    {
        $builder->macro(
            'onlyTrashed', function (Builder $builder)
        {
            /** @var Model $model */
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNotNull(
                $model->getQualifiedDeletedAtColumn()
            );

            return $builder;
        }
        );
    }
}
