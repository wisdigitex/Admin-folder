<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use App\CentralLogics\CategoryLogic;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    public function get_searched_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone_id = $request->header('zoneId');

        $key = explode(' ', $request['name']);

        $limit = $request['limit']??10;
        $offset = $request['offset']??1;
        $category_ids = $request['category_ids']?(is_array($request['category_ids'])?$request['category_ids']:json_decode($request['category_ids'])):'';
        $brand_ids = $request['brand_ids']?(is_array($request['brand_ids'])?$request['brand_ids']:json_decode($request['brand_ids'])):'';
        $filter = $request['filter']?(is_array($request['filter'])?$request['filter']:str_getcsv(trim($request['filter'], "[]"), ',')):'';
        $type = $request->query('type', 'all');
        $min = $request->query('min_price');
        $min = ($min == 0) ? 0.0001 : $min;
        $max = $request->query('max_price');
        $rating_count = $request->query('rating_count');

        // dd($filter&&in_array('high',$filter));

        $items = Item::active()->type($type)
        ->with('store', function($query){
            $query->withCount(['campaigns'=> function($query){
                $query->Running();
            }]);
        })
        ->when($request->category_id, function($query)use($request){
            $query->whereHas('category',function($q)use($request){
                return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
            });
        })
        ->when($category_ids && (count($category_ids)>0), function($query)use($category_ids){
            $query->whereHas('category',function($q)use($category_ids){
                return $q->whereIn('id',$category_ids)->orWhereIn('parent_id', $category_ids);
            });
        })
        ->when(isset($brand_ids) && (count($brand_ids)>0), function($query)use($brand_ids){
            $query->whereHas('ecommerce_item_details',function($q)use($brand_ids){
                return $q->whereHas('brand',function($q)use($brand_ids){
                    return $q->whereIn('id',$brand_ids);
                });
            });
        })
        ->when($request->store_id, function($query) use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
            $q->orWhereHas('translations',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('value', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('tags',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('tag', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category.parent',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });

        })
        ->when($rating_count, function($query) use ($rating_count){
            $query->where('avg_rating', '>=' , $rating_count);
        })
        ->when($min && $max, function($query)use($min,$max){
            $query->whereBetween('price',[$min,$max]);
        })
        ->orderByRaw("FIELD(name, ?) DESC", [$request['name']])
        ->when($filter&&in_array('top_rated',$filter),function ($qurey){
            $qurey->withCount('reviews')->orderBy('reviews_count','desc');
        })
        ->when($filter&&in_array('popular',$filter),function ($qurey){
            $qurey->popular();
        })
        ->when($filter&&in_array('high',$filter),function ($qurey){
            $qurey->orderBy('price', 'DESC');
        })
        ->when($filter&&in_array('low',$filter),function ($qurey){
            $qurey->orderBy('price', 'asc');
        })
        ->when($filter&&in_array('discounted',$filter),function ($qurey){
            $qurey->Discounted();
        })
        ->paginate($limit, ['*'], 'page', $offset);


        $item_categories = Item::active()->type($type)
        ->with('store', function($query){
            $query->withCount(['campaigns'=> function($query){
                $query->Running();
            }]);
        })
        ->when($request->category_id, function($query)use($request){
            $query->whereHas('category',function($q)use($request){
                return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
            });
        })
        ->when($category_ids && (count($category_ids)>0), function($query)use($category_ids){
            $query->whereHas('category',function($q)use($category_ids){
                return $q->whereIn('id',$category_ids)->orWhereIn('parent_id', $category_ids);
            });
        })
        ->when(isset($brand_ids) && (count($brand_ids)>0), function($query)use($brand_ids){
            $query->whereHas('ecommerce_item_details',function($q)use($brand_ids){
                return $q->whereHas('brand',function($q)use($brand_ids){
                    return $q->whereIn('id',$brand_ids);
                });
            });
        })
        ->when($request->store_id, function($query) use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
            $q->orWhereHas('translations',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('value', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('tags',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('tag', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category.parent',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });

        })
        ->when($rating_count, function($query) use ($rating_count){
            $query->where('avg_rating', '>=' , $rating_count);
        })
        ->when($min && $max, function($query)use($min,$max){
            $query->whereBetween('price',[$min,$max]);
        })
        ->pluck('category_id')->toArray();

        $item_categories = array_unique($item_categories);

        $categories = Category::withCount(['products','childes'])->with(['childes' => function($query)  {
            $query->withCount(['products','childes']);
        }])
        ->where(['position'=>0,'status'=>1])
        ->when(config('module.current_module_data'), function($query){
            $query->module(config('module.current_module_data')['id']);
        })
        ->whereIn('id',$item_categories)
        ->orderBy('priority','desc')->get();

        $data =  [
            'total_size' => $items->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $items->items(),
            'categories'=>$categories
        ];

        $data['products'] = Helpers::product_data_formatting($data['products'], true, false, app()->getLocale());
        return response()->json($data, 200);
    }

    public function get_combined_data(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'list_type' => 'required|in:item,store',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if (!$request->hasHeader('zoneId')) {
            $errors = [['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]];
            return response()->json(['errors' => $errors], 403);
        }

        if($request->list_type == 'item'){
            $data_type = $request->query('data_type', 'all');

            $zone_id = $request->header('zoneId');
            $type = $request->query('type', 'all');
            $filter = $request->query('filter', '');
            $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
            $category_ids = $request->query('category_ids', '');
            $brand_ids = $request->query('brand_ids', '');

            // Common parameters for all product types
            $limit = $request->query('limit', 10);
            $offset = $request->query('offset', 1);
            $min_price = $request->query('min_price');
            $min_price = ($min_price == 0) ? 0.0001 : $min_price;
            $max_price = $request->query('max_price');
            $rating_count = $request->query('rating_count');
            $product_id = $request->query('product_id');

            switch ($data_type) {
                case 'searched':
                    return $this->get_searched_products($request);
                    break;
                case 'discounted':
                    $items = ProductLogic::discounted_products($zone_id, $limit, $offset, $type, $category_ids, $filter, $min_price, $max_price, $rating_count,$brand_ids);
                    break;
                case 'brand':
                    $validator = Validator::make($request->all(), [
                        'brand_ids' => 'required',
                    ]);

                    if ($validator->fails()) {
                        return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                    }
                    $items = ProductLogic::brand_products($zone_id, $limit, $offset, $type, $category_ids, $filter, $min_price, $max_price, $rating_count,$brand_ids);
                    break;
                case 'new':
                    $items = ProductLogic::get_new_products($zone_id, $type, $min_price, $max_price, $product_id, $limit, $offset, $filter, $rating_count, $category_ids,$brand_ids);
                    break;
                case 'category':
                    $validator = Validator::make($request->all(), [
                        'category_ids' => 'required',
                    ]);

                    if ($validator->fails()) {
                        return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                    }

                    $items = CategoryLogic::category_products($category_ids, $zone_id, $limit, $offset, $type, $filter, $min_price, $max_price, $rating_count,$brand_ids);
                    break;
                default:
                    $items =  [
                        'total_size' => 0,
                        'limit' => $limit,
                        'offset' => $offset,
                        'products' => [],
                        'categories' => [],
                    ];
            }

            $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
            return response()->json($items, 200);
        }
        $zone_id = $request->header('zoneId');
        $data_type = $request->query('data_type', 'all');
        $type = $request->query('type', 'all');
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 1);
        $longitude = (float) $request->header('longitude') ?? 0;
        $latitude = (float) $request->header('latitude') ?? 0;
        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $rating_count = $request->query('rating_count');

        switch ($data_type) {
            case 'searched':
                $validator = Validator::make($request->all(), ['name' => 'required']);
                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }
                $name = $request->input('name');

                $paginator = StoreLogic::search_stores($name, $zone_id, $request->category_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count);
                break;

            case 'discounted':

                $paginator = StoreLogic::get_discounted_stores($zone_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count);
                break;

            case 'category':
                $validator = Validator::make($request->all(), [
                    'category_ids' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }

                $category_ids = $request->input('category_ids');

                $paginator = CategoryLogic::category_stores($category_ids, $zone_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count);
                break;

            default:
                $filter_data = $request->query('filter_data', 'all');
                $store_type = $request->query('store_type', 'all');
                $featured = $request->query('featured');
                $paginator = StoreLogic::get_stores($zone_id, $filter_data, $type, $store_type, $limit, $offset, $featured, $longitude, $latitude, $filter, $rating_count);
                break;
        }

        $paginator['stores'] = Helpers::store_data_formatting($paginator['stores'], true);
        return response()->json($paginator, 200);

    }


}
