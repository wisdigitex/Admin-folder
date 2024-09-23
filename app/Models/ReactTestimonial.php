<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;

class ReactTestimonial extends Model
{
    use HasFactory;
    protected $appends = ['reviewer_image_full_url','company_image_full_url'];
    public function getReviewerImageFullUrlAttribute(){
        $value = $this->reviewer_image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'reviewer_image') {
                    return Helpers::get_full_url('reviewer_image',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('reviewer_image',$value,'public');
    }
    public function getCompanyImageFullUrlAttribute(){
        $value = $this->company_image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'company_image') {
                    return Helpers::get_full_url('reviewer_company_image',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('reviewer_company_image',$value,'public');
    }
    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }
    protected static function booted()
    {
        static::addGlobalScope('storage', function ($builder) {
            $builder->with('storage');
        });
    }
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            if($model->isDirty('reviewer_image')){
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'reviewer_image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if($model->isDirty('company_image')){
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'company_image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

    }
}
