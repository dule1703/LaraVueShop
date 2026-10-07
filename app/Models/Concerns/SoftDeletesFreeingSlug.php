<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * SoftDeletes za modele sa `unique` slug kolonom (products, categories).
 *
 * - Soft delete preimenuje slug u `slug-deleted-{id}` (id je jedinstven, a
 *   varchar(255) kolona je daleko iznad dužine), pa isti slug može ponovo da
 *   se iskoristi. forceDelete() slug ne dira (red nestaje).
 * - delete() ide u transakciji, tako da `deleted` listeneri (npr.
 *   ProductObserver koji briše knjigu) i sam soft delete uspevaju ili se
 *   poništavaju zajedno.
 */
trait SoftDeletesFreeingSlug
{
    use SoftDeletes;

    public static function bootSoftDeletesFreeingSlug(): void
    {
        static::deleted(function ($model) {
            if ($model->isForceDeleting()) {
                return;
            }

            $slug = 'slug-deleted-'.$model->getKey();

            $model->newQueryWithoutScopes()->whereKey($model->getKey())->toBase()->update(['slug' => $slug]);
            $model->setAttribute('slug', $slug);
            $model->syncOriginalAttribute('slug');
        });
    }

    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }
}
