<?php
namespace App\Http\Controllers\Api\V1;

use App\Models\Advertisement;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdvertisementController extends Controller
{
    public function get_adds(Request $request)
    {
        $zone_ids= $request->header('zoneId');
        $zone_ids=  json_decode($zone_ids, true)?? [];
        $Advertisement= Advertisement::valid()
        ->when(config('module.current_module_data'), function($query){
            $query->where('module_id', config('module.current_module_data')['id']);
        })
        ->with('store')
        ->when(count($zone_ids) > 0, function($query) use($zone_ids) {
            $query->wherehas('store', function($query) use($zone_ids){
                $query->whereIn('zone_id',$zone_ids);
            });
        })
        ->orderByRaw('ISNULL(priority), priority ASC')
        ->get();

        try {
            $Advertisement->each(function ($advertisement) {
                $advertisement->reviews_comments_count = (int) $advertisement?->store?->reviews_comments()->count();
                $reviewsInfo = $advertisement?->store?->reviews()
                ->selectRaw('avg(reviews.rating) as average_rating, count(reviews.id) as total_reviews, items.store_id')
                ->groupBy('items.store_id')
                ->first();

                $advertisement->average_rating = (float)  $reviewsInfo?->average_rating ?? 0;
            });
        } catch (\Exception $e) {
            info($e->getMessage());
        }

        return response()->json($Advertisement, 200);
    }

}
