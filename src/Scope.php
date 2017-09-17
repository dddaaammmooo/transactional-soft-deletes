<?php

namespace App\Cto\Database\TransactionalSoftDeletes;

use App\Models\SoftDeleteBaseModel;
use Illuminate\Database\Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Scope
 *
 * This class is responsible for adding the soft deletion methods onto Laravel's underlying query builder
 * class. This enables the use of `$model->where()->delete()` syntax, rather than having to retrieve the
 * collection and iteratively delete each item individually.
 *
 * @package App\Cto\Database\TransactionalSoftDeletes
 */
class Scope implements Eloquent\Scope
{
    /**
     * These are the functions that will be added to the Laravel query builder
     *
     * @var array $extensions
     */
    protected $extensions = ['Restore', 'WithTrashed', 'WithoutTrashed', 'OnlyTrashed'];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder $builder
     * @param Model   $model
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->whereNull($model->getQualifiedDeletedAtColumn());
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param Builder $builder
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }

        $builder->onDelete(function (Builder $builder) {
            foreach ($builder->getModels() as $model)
            {
                $model->delete();
            }
        });
    }

    /**
     * Get the "deleted at" column for the builder.
     *
     * @param Builder $builder
     * @return string
     */
    protected function getDeletedAtColumn(Builder $builder)
    {
        if (count((array)$builder->getQuery()->joins) > 0) {
            return $builder->getModel()->getQualifiedDeletedAtColumn();
        }

        return $builder->getModel()->getDeletedAtColumn();
    }

    /**
     * Add the restore extension to the builder.
     *
     * @param Builder $builder
     */
    protected function addRestore(Builder $builder)
    {
        $builder->macro('restore', function (Builder $builder) {
            $builder->withTrashed();

            /** @var SoftDeleteBaseModel $model */
            foreach ($builder->get() as $model) {
                $model->restore();
            }
        });
    }

    /**
     * Add the with-trashed extension to the builder.
     *
     * @param Builder $builder
     */
    protected function addWithTrashed(Builder $builder)
    {
        $builder->macro('withTrashed', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @param Builder $builder
     */
    protected function addWithoutTrashed(Builder $builder)
    {
        $builder->macro('withoutTrashed', function (Builder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNull(
                $model->getQualifiedDeletedAtColumn()
            );

            return $builder;
        });
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @param  Builder $builder
     */
    protected function addOnlyTrashed(Builder $builder)
    {
        $builder->macro('onlyTrashed', function (Builder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNotNull(
                $model->getQualifiedDeletedAtColumn()
            );

            return $builder;
        });
    }
}
