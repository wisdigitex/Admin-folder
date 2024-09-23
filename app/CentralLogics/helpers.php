<?php

namespace App\CentralLogics;

use App\Models\ExternalConfiguration;
use DateTime;
use App\Models\Item;
use App\Models\User;
use App\Models\Zone;
use App\Models\AddOn;
use App\Models\Order;
use App\Models\Store;
use App\Library\Payer;
use App\Models\Module;
use App\Models\Review;
use App\Models\Expense;
use App\Traits\Payment;
use App\Mail\PlaceOrder;
use App\Models\CashBack;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DMReview;
use App\Library\Receiver;
use App\Models\DataSetting;
use App\Models\StoreWallet;
use App\Models\Translation;
use Illuminate\Support\Str;
use PayPal\Api\Transaction;
use App\Models\FlashSaleItem;
use Illuminate\Support\Carbon;
use App\Models\BusinessSetting;
use App\CentralLogics\StoreLogic;
use App\Models\StoreSubscription;
use Illuminate\Support\Facades\DB;
use App\Mail\OrderVerificationMail;
use App\Models\NotificationMessage;
use App\Models\NotificationSetting;
use App\Models\SubscriptionPackage;
use Illuminate\Support\Facades\App;
use App\Mail\SubscriptionSuccessful;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Mail;
use App\Mail\SubscriptionRenewOrShift;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use App\Library\Payment as PaymentInfo;
use App\Models\SubscriptionTransaction;
use Illuminate\Support\Facades\Storage;
use App\Models\StoreNotificationSetting;
use Illuminate\Database\Eloquent\Collection;
use MatanYadaev\EloquentSpatial\Objects\Point;
use App\Models\SubscriptionBillingAndRefundHistory;
use Laravelpkg\Laravelchk\Http\Controllers\LaravelchkController;
use App\Traits\PaymentGatewayTrait;
use App\Traits\NotificationDataSetUpTrait;

class Helpers
{
    use PaymentGatewayTrait , NotificationDataSetUpTrait;
    public static function error_processor($validator)
    {
        $err_keeper = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            array_push($err_keeper, ['code' => $index, 'message' => $error[0]]);
        }
        return $err_keeper;
    }

    public static function schedule_order()
    {
        return (bool)BusinessSetting::where(['key' => 'schedule_order'])->first()->value;
    }


    public static function combinations($arrays)
    {
        $result = [[]];
        foreach ($arrays as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }
        return $result;
    }

    public static function variation_price($product, $variation)
    {
        $match = json_decode($variation, true)[0];
        $result = ['price' => 0, 'stock' => 0];
        foreach (json_decode($product['variations'], true) as $property => $value) {
            if ($value['type'] == $match['type']) {
                $result = ['price' => $value['price'], 'stock' => $value['stock'] ?? 0];
            }
        }
        return $result;
    }

    public static function address_data_formatting($data)
    {
        foreach ($data as $key=>$item) {
            $data[$key]['zone_ids'] = array_column(Zone::query()->whereContains('coordinates', new Point($item->latitude, $item->longitude, POINT_SRID))->latest()->get(['id'])->toArray(), 'id');
        }
        return $data;
    }

    public static function cart_product_data_formatting($data, $selected_variation, $selected_addons,
                                                        $selected_addon_quantity,$trans = false, $local = 'en')
    {
        $variations = [];
        $categories = [];
        $category_ids = gettype($data['category_ids']) == 'array' ? $data['category_ids'] : json_decode($data['category_ids'],true);
        foreach ($category_ids as $value) {
            $category_name = Category::where('id',$value['id'])->pluck('name');
            $categories[] = ['id' => (string)$value['id'], 'position' => $value['position'], 'name'=>data_get($category_name,'0','NA')];
        }
        $data['category_ids'] = $categories;
        $attributes = gettype($data['attributes']) == 'array' ? $data['attributes'] : json_decode($data['attributes'],true);
        $data['attributes'] = $attributes;
        $choice_options = gettype($data['choice_options']) == 'array' ? $data['choice_options'] : json_decode($data['choice_options'],true);
        $data['choice_options'] = $choice_options;
        $add_ons = gettype($data['add_ons']) == 'array' ? $data['add_ons'] : json_decode($data['add_ons'],true);
        $data_addons = self::addon_data_formatting(AddOn::whereIn('id', $add_ons)->active()->get(), true, $trans, $local);
        $selected_data = array_combine($selected_addons, $selected_addon_quantity);
        foreach ($data_addons as $addon) {
            $addon_id = $addon['id'];
            if (in_array($addon_id, $selected_addons)) {
                $addon['isChecked'] = true;
                $addon['quantity'] = $selected_data[$addon_id];
            } else {
                $addon['isChecked'] = false;
                $addon['quantity'] = 0;
            }
        }
        $data['addons'] = $data_addons;
        $data_variations = gettype($data['variations']) == 'array' ? $data['variations'] : json_decode($data['variations'],true);
        foreach ($data_variations as $var) {
            array_push($variations, [
                'type' => $var['type'],
                'price' => (float)$var['price'],
                'stock' => (int)($var['stock'] ?? 0)
            ]);
        }
        if ($data->title) {
            $data['name'] = $data->title;
            unset($data['title']);
        }
        if ($data->start_time) {
            $data['available_time_starts'] = $data->start_time->format('H:i');
            unset($data['start_time']);
        }
        if ($data->end_time) {
            $data['available_time_ends'] = $data->end_time->format('H:i');
            unset($data['end_time']);
        }
        if ($data->start_date) {
            $data['available_date_starts'] = $data->start_date->format('Y-m-d');
            unset($data['start_date']);
        }
        if ($data->end_date) {
            $data['available_date_ends'] = $data->end_date->format('Y-m-d');
            unset($data['end_date']);
        }
        $data['variations'] = $variations;
        $data_variation = $data['food_variations']?(gettype($data['food_variations']) == 'array' ? $data['food_variations'] : json_decode($data['food_variations'],true)):[];
        if($data->module->module_type == 'food'){
            foreach ($selected_variation as $selected_item) {
                foreach ($data_variation as &$all_item) {
                    if ($selected_item["name"] === $all_item["name"]) {
                        foreach ($all_item["values"] as &$value) {
                            if (in_array($value["label"], $selected_item["values"]["label"])) {
                                $value["isSelected"] = true;
                            }else{
                                $value["isSelected"] = false;
                            }
                        }
                    }
                }
            }
        }
        $data['food_variations'] = $data_variation;
        $data['store_name'] = $data->store->name;
        $data['is_campaign'] = $data->store?->campaigns_count>0?1:0;
        $data['module_type'] = $data->module->module_type;
        $data['zone_id'] = $data->store->zone_id;
        $running_flash_sale = FlashSaleItem::Active()->whereHas('flashSale', function ($query) {
            $query->Active()->Running();
        })
            ->where(['item_id' => $data['id']])->first();
        $data['flash_sale'] =(int) (($running_flash_sale) ? 1 :0);
        $data['stock'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->available_stock : $data['stock'];
        $data['discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount : $data['discount'];
        $data['discount_type'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount_type : $data['discount_type'];
        $data['store_discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? 0 : (self::get_store_discount($data->store) ? $data->store?->discount->discount : 0);
        $data['schedule_order'] = $data->store->schedule_order;
        $data['rating_count'] = (int)($data->rating ? array_sum(json_decode($data->rating, true)) : 0);
        $data['avg_rating'] = (float)($data->avg_rating ? $data->avg_rating : 0);
        $data['min_delivery_time'] =  (int) explode('-',$data->store->delivery_time)[0] ?? 0;
        $data['max_delivery_time'] =  (int) explode('-',$data->store->delivery_time)[1] ?? 0;
        $data['common_condition_id'] =  (int) $data->pharmacy_item_details?->common_condition_id ?? 0;
        $data['brand_id'] =  (int) $data->ecommerce_item_details?->brand_id ?? 0;
        $data['is_basic'] =  (int) $data->pharmacy_item_details?->is_basic ?? 0;
        $data['is_prescription_required'] =  (int) $data->pharmacy_item_details?->is_prescription_required ?? 0;
        $data['halal_tag_status'] =  (int) $data->store->storeConfig?->halal_tag_status??0;

        unset($data['pharmacy_item_details']);
        unset($data['store']);
        unset($data['rating']);


        return $data;
    }

    public static function product_data_formatting($data, $multi_data = false, $trans = false, $local = 'en' , $temp_product=false)
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                $variations = [];
                if ($item->title) {
                    $item['name'] = $item->title;
                    unset($item['title']);
                }
                if ($item->start_time) {
                    $item['available_time_starts'] = $item->start_time->format('H:i');
                    unset($item['start_time']);
                }
                if ($item->end_time) {
                    $item['available_time_ends'] = $item->end_time->format('H:i');
                    unset($item['end_time']);
                }

                if ($item->start_date) {
                    $item['available_date_starts'] = $item->start_date->format('Y-m-d');
                    unset($item['start_date']);
                }
                if ($item->end_date) {
                    $item['available_date_ends'] = $item->end_date->format('Y-m-d');
                    unset($item['end_date']);
                }
                $item['recommended'] =(int) $item->recommended;
                $categories = [];
                foreach (json_decode($item['category_ids']) as $value) {
                    $category_name = Category::where('id',$value->id)->pluck('name');
                    $categories[] = ['id' => (string)$value->id, 'position' => $value->position, 'name'=>data_get($category_name,'0','NA')];
                }
                $item['category_ids'] = $categories;
                $item['attributes'] = json_decode($item['attributes']);
                $item['choice_options'] = json_decode($item['choice_options']);
                $item['add_ons'] = self::addon_data_formatting(AddOn::whereIn('id', json_decode($item['add_ons'], true))->active()->get(), true, $trans, $local);
                foreach (json_decode($item['variations'], true) as $var) {
                    array_push($variations, [
                        'type' => $var['type'],
                        'price' => (float)$var['price'],
                        'stock' => (int)($var['stock'] ?? 0)
                    ]);
                }
                $item['variations'] = $variations;
                $item['food_variations'] = $item['food_variations']?json_decode($item['food_variations'], true):'';
                $item['module_type'] = $item->module->module_type;
                $item['store_name'] = $item->store?->name;
                $item['is_campaign'] = $item->store?->campaigns_count>0?1:0;
                $item['zone_id'] = $item->store?->zone_id;
                $running_flash_sale = FlashSaleItem::Active()->whereHas('flashSale', function ($query) {
                    $query->Active()->Running();
                })
                    ->where(['item_id' => $item['id']])->first();
                $item['flash_sale'] =(int) ((($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? 1 :0));
                $item['stock'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->available_stock : $item['stock'];
                $item['discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount : $item['discount'];
                $item['discount_type'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount_type : $item['discount_type'];
                $item['store_discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? 0 : (self::get_store_discount($item->store) ? $item->store?->discount->discount : 0);
                $item['schedule_order'] = $item->store?->schedule_order;
                $item['delivery_time'] = $item->store?->delivery_time;
                $item['free_delivery'] = $item->store?->free_delivery;
                $item['tax'] = $item->store?->tax;
                $item['unit'] = $item->unit;
                $item['rating_count'] = (int)($item->rating ? array_sum(json_decode($item->rating, true)) : 0);
                $item['avg_rating'] = (float)($item->avg_rating ? $item->avg_rating : 0);
                $item['recommended'] =(int) $item->recommended;
                $item['min_delivery_time'] =  (int) explode('-',$item->store?->delivery_time)[0] ?? 0;
                $item['max_delivery_time'] =  (int) explode('-',$item->store?->delivery_time)[1] ?? 0;
                $item['common_condition_id'] =  (int) $item->pharmacy_item_details?->common_condition_id ?? 0;
                $item['brand_id'] =  (int) $item->ecommerce_item_details?->brand_id ?? 0;
                $item['is_basic'] =  (int) $item->pharmacy_item_details?->is_basic ?? 0;
                $item['is_prescription_required'] =  (int) $item->pharmacy_item_details?->is_prescription_required ?? 0;
                $item['halal_tag_status'] =  (int) $item->store->storeConfig?->halal_tag_status??0;

                $item->store['self_delivery_system'] = (int) $item->store->sub_self_delivery;

                unset($item['pharmacy_item_details']);
                unset($item['store']);
                unset($item['rating']);
                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            $variations = [];
            $categories = [];
            foreach (json_decode($data['category_ids']) as $value) {
                $category_name = Category::where('id',$value->id)->pluck('name');
                $categories[] = ['id' => (string)$value->id, 'position' => $value->position, 'name'=>data_get($category_name,'0','NA')];
            }
            $data['category_ids'] = $categories;

            $data['attributes'] = json_decode($data['attributes']);
            $data['choice_options'] = json_decode($data['choice_options']);
            $data['add_ons'] = self::addon_data_formatting(AddOn::whereIn('id', json_decode($data['add_ons']))->active()->get(), true, $trans, $local);
            foreach (json_decode($data['variations'], true) as $var) {
                array_push($variations, [
                    'type' => $var['type'],
                    'price' => (float)$var['price'],
                    'stock' => (int)($var['stock'] ?? 0)
                ]);
            }
            if ($data->title) {
                $data['name'] = $data->title;
                unset($data['title']);
            }
            if ($data->start_time) {
                $data['available_time_starts'] = $data->start_time->format('H:i');
                unset($data['start_time']);
            }
            if ($data->end_time) {
                $data['available_time_ends'] = $data->end_time->format('H:i');
                unset($data['end_time']);
            }
            if ($data->start_date) {
                $data['available_date_starts'] = $data->start_date->format('Y-m-d');
                unset($data['start_date']);
            }
            if ($data->end_date) {
                $data['available_date_ends'] = $data->end_date->format('Y-m-d');
                unset($data['end_date']);
            }
            $data['variations'] = $variations;
            $data['food_variations'] = $data['food_variations']?json_decode($data['food_variations'], true):'';
            $data['store_name'] = $data->store->name;
            $data['is_campaign'] = $data->store?->campaigns_count>0?1:0;
            $data['module_type'] = $data->module->module_type;
            $data['zone_id'] = $data->store->zone_id;
            $running_flash_sale = FlashSaleItem::Active()->whereHas('flashSale', function ($query) {
                $query->Active()->Running();
            })
                ->where(['item_id' => $data['id']])->first();
            $data['flash_sale'] =(int) (($running_flash_sale) ? 1 :0);
            $data['stock'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->available_stock : $data['stock'];
            $data['discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount : $data['discount'];
            $data['discount_type'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount_type : $data['discount_type'];
            $data['store_discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? 0 : (self::get_store_discount($data->store) ? $data->store?->discount->discount : 0);
            $data['schedule_order'] = $data->store->schedule_order;
            $data['rating_count'] = (int)($data->rating ? array_sum(json_decode($data->rating, true)) : 0);
            $data['avg_rating'] = (float)($data->avg_rating ? $data->avg_rating : 0);
            $data['min_delivery_time'] =  (int) explode('-',$data->store->delivery_time)[0] ?? 0;
            $data['max_delivery_time'] =  (int) explode('-',$data->store->delivery_time)[1] ?? 0;
            $data['common_condition_id'] =  (int) $data->pharmacy_item_details?->common_condition_id ?? 0;
            $data['brand_id'] =  (int) $data->ecommerce_item_details?->brand_id ?? 0;
            $data['is_basic'] =  (int) $data->pharmacy_item_details?->is_basic ?? 0;
            $data['is_prescription_required'] =  (int) $data->pharmacy_item_details?->is_prescription_required ?? 0;
            $data['halal_tag_status'] =  (int) $data->store->storeConfig?->halal_tag_status??0;
            if($temp_product == true){
                $data['tags']=\App\Models\Tag::whereIn('id',json_decode($data?->tag_ids) )->get(['tag','id']);
            }
            $data->store['self_delivery_system'] = (int) $data->store->sub_self_delivery;

            unset($data['pharmacy_item_details']);
            unset($data['store']);
            unset($data['rating']);
        }

        return $data;
    }

    public static function product_data_formatting_translate($data, $multi_data = false, $trans = false, $local = 'en')
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                $variations = [];
                if ($item->title) {
                    $item['name'] = $item->title;
                    unset($item['title']);
                }
                if ($item->start_time) {
                    $item['available_time_starts'] = $item->start_time->format('H:i');
                    unset($item['start_time']);
                }
                if ($item->end_time) {
                    $item['available_time_ends'] = $item->end_time->format('H:i');
                    unset($item['end_time']);
                }

                if ($item->start_date) {
                    $item['available_date_starts'] = $item->start_date->format('Y-m-d');
                    unset($item['start_date']);
                }
                if ($item->end_date) {
                    $item['available_date_ends'] = $item->end_date->format('Y-m-d');
                    unset($item['end_date']);
                }
                $item['recommended'] =(int) $item->recommended;
                $categories = [];
                foreach (json_decode($item['category_ids']) as $value) {
                    $categories[] = ['id' => (string)$value->id, 'position' => $value->position];
                }
                $item['category_ids'] = $categories;
                $item['attributes'] = json_decode($item['attributes']);
                $item['choice_options'] = json_decode($item['choice_options']);
                $item['add_ons'] = self::addon_data_formatting(AddOn::withoutGlobalScope('translate')->whereIn('id', json_decode($item['add_ons'], true))->active()->get(), true, $trans, $local);
                foreach (json_decode($item['variations'], true) as $var) {
                    array_push($variations, [
                        'type' => $var['type'],
                        'price' => (float)$var['price'],
                        'stock' => (int)($var['stock'] ?? 0)
                    ]);
                }
                $item['variations'] = $variations;
                $item['food_variations'] = $item['food_variations']?json_decode($item['food_variations'], true):'';
                $item['module_type'] = $item->module->module_type;
                $item['store_name'] = $item->store->name;
                $item['zone_id'] = $item->store->zone_id;
                $running_flash_sale = FlashSaleItem::Active()->whereHas('flashSale', function ($query) {
                    $query->Active()->Running();
                })
                    ->where(['item_id' => $item['id']])->first();
                $item['flash_sale'] =(int) (($running_flash_sale) ? 1 :0);
                $item['stock'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->available_stock : $item['stock'];
                $item['discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount : $item['discount'];
                $item['discount_type'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount_type : $item['discount_type'];
                $item['store_discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? 0 : (self::get_store_discount($item->store) ? $item->store?->discount->discount : 0);
                $item['schedule_order'] = $item->store->schedule_order;
                $item['tax'] = $item->store->tax;
                $item['rating_count'] = (int)($item->rating ? array_sum(json_decode($item->rating, true)) : 0);
                $item['avg_rating'] = (float)($item->avg_rating ? $item->avg_rating : 0);
                $item['recommended'] =(int) $item->recommended;

                $item['common_condition_id'] =  (int) $item->pharmacy_item_details?->common_condition_id ?? 0;
                $item['brand_id'] =  (int) $item->ecommerce_item_details?->brand_id ?? 0;
                $item['is_basic'] =  (int) $item->pharmacy_item_details?->is_basic ?? 0;
                $item['is_prescription_required'] =  (int) $item->pharmacy_item_details?->is_prescription_required ?? 0;
                $item['halal_tag_status'] =  (int) $item->store->storeConfig?->halal_tag_status??0;

                if ($trans) {
                    $item['translations'][] = [
                        'translationable_type' => 'App\Models\Item',
                        'translationable_id' => $item->id,
                        'locale' => 'en',
                        'key' => 'name',
                        'value' => $item->name
                    ];

                    $item['translations'][] = [
                        'translationable_type' => 'App\Models\Item',
                        'translationable_id' => $item->id,
                        'locale' => 'en',
                        'key' => 'description',
                        'value' => $item->description
                    ];
                }

                if (count($item['translations']) > 0) {
                    foreach ($item['translations'] as $translation) {
                        if ($translation['locale'] == $local) {
                            if ($translation['key'] == 'name') {
                                $item['name'] = $translation['value'];
                            }

                            if ($translation['key'] == 'title') {
                                $item['name'] = $translation['value'];
                            }

                            if ($translation['key'] == 'description') {
                                $item['description'] = $translation['value'];
                            }
                        }
                    }
                }
                if (!$trans) {
                    unset($item['translations']);
                }
                unset($item['ecommerce_item_details']);
                unset($item['pharmacy_item_details']);
                unset($item['store']);
                unset($item['rating']);
                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            $variations = [];
            $categories = [];
            foreach (json_decode($data['category_ids']) as $value) {
                $categories[] = ['id' => (string)$value->id, 'position' => $value->position];
            }
            $data['category_ids'] = $categories;

            $data['attributes'] = json_decode($data['attributes']);
            $data['choice_options'] = json_decode($data['choice_options']);
            $data['add_ons'] = self::addon_data_formatting(AddOn::whereIn('id', json_decode($data['add_ons']))->active()->get(), true, $trans, $local);
            foreach (json_decode($data['variations'], true) as $var) {
                array_push($variations, [
                    'type' => $var['type'],
                    'price' => (float)$var['price'],
                    'stock' => (int)($var['stock'] ?? 0)
                ]);
            }
            if ($data->title) {
                $data['name'] = $data->title;
                unset($data['title']);
            }
            if ($data->start_time) {
                $data['available_time_starts'] = $data->start_time->format('H:i');
                unset($data['start_time']);
            }
            if ($data->end_time) {
                $data['available_time_ends'] = $data->end_time->format('H:i');
                unset($data['end_time']);
            }
            if ($data->start_date) {
                $data['available_date_starts'] = $data->start_date->format('Y-m-d');
                unset($data['start_date']);
            }
            if ($data->end_date) {
                $data['available_date_ends'] = $data->end_date->format('Y-m-d');
                unset($data['end_date']);
            }
            $data['variations'] = $variations;
            $data['food_variations'] = $data['food_variations']?json_decode($data['food_variations'], true):'';
            $data['store_name'] = $data->store->name;
            $data['module_type'] = $data->module->module_type;
            $data['zone_id'] = $data->store->zone_id;
            $running_flash_sale = FlashSaleItem::Active()->whereHas('flashSale', function ($query) {
                $query->Active()->Running();
            })
                ->where(['item_id' => $data['id']])->first();
            $data['flash_sale'] =(int) (($running_flash_sale) ? 1 :0);
            $data['stock'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->available_stock : $data['stock'];
            $data['discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount : $data['discount'];
            $data['discount_type'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? $running_flash_sale->discount_type : $data['discount_type'];
            $data['store_discount'] = ($running_flash_sale && ($running_flash_sale->available_stock > 0)) ? 0 : (self::get_store_discount($data->store) ? $data->store?->discount->discount : 0);
            $data['schedule_order'] = $data->store->schedule_order;
            $data['rating_count'] = (int)($data->rating ? array_sum(json_decode($data->rating, true)) : 0);
            $data['avg_rating'] = (float)($data->avg_rating ? $data->avg_rating : 0);

            $data['common_condition_id'] =  (int) $data->pharmacy_item_details?->common_condition_id ?? 0;
            $data['brand_id'] =  (int) $data->ecommerce_item_details?->brand_id ?? 0;
            $data['is_basic'] =  (int) $data->pharmacy_item_details?->is_basic ?? 0;
            $data['is_prescription_required'] =  (int) $data->pharmacy_item_details?->is_prescription_required ?? 0;
            $data['halal_tag_status'] =  (int) $data->store->storeConfig?->halal_tag_status??0;

            if ($trans) {
                $data['translations'][] = [
                    'translationable_type' => 'App\Models\Item',
                    'translationable_id' => $data->id,
                    'locale' => 'en',
                    'key' => 'name',
                    'value' => $data->name
                ];

                $data['translations'][] = [
                    'translationable_type' => 'App\Models\Item',
                    'translationable_id' => $data->id,
                    'locale' => 'en',
                    'key' => 'description',
                    'value' => $data->description
                ];
            }

            if (count($data['translations']) > 0) {
                foreach ($data['translations'] as $translation) {
                    if ($translation['locale'] == $local) {
                        if ($translation['key'] == 'name') {
                            $data['name'] = $translation['value'];
                        }

                        if ($translation['key'] == 'title') {
                            $item['name'] = $translation['value'];
                        }

                        if ($translation['key'] == 'description') {
                            $data['description'] = $translation['value'];
                        }
                    }
                }
            }
            if (!$trans) {
                unset($data['translations']);
            }
            unset($data['ecommerce_item_details']);
            unset($data['pharmacy_item_details']);
            unset($data['store']);
            unset($data['rating']);
        }

        return $data;
    }

    public static function addon_data_formatting($data, $multi_data = false, $trans = false, $local = 'en')
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                // if ($trans) {
                //     $item['translations'][] = [
                //         'translationable_type' => 'App\Models\AddOn',
                //         'translationable_id' => $item->id,
                //         'locale' => 'en',
                //         'key' => 'name',
                //         'value' => $item->name
                //     ];
                // }
                // if (count($item->translations) > 0) {
                //     foreach ($item['translations'] as $translation) {
                //         if ($translation['locale'] == $local && $translation['key'] == 'name') {
                //             $item['name'] = $translation['value'];
                //         }
                //     }
                // }

                // if (!$trans) {
                //     unset($item['translations']);
                // }

                $storage[] = $item;
            }
            $data = $storage;
        } else if (isset($data)) {
            // if ($trans) {
            //     $data['translations'][] = [
            //         'translationable_type' => 'App\Models\AddOn',
            //         'translationable_id' => $data->id,
            //         'locale' => 'en',
            //         'key' => 'name',
            //         'value' => $data->name
            //     ];
            // }

            // if (count($data->translations) > 0) {
            //     foreach ($data['translations'] as $translation) {
            //         if ($translation['locale'] == $local && $translation['key'] == 'name') {
            //             $data['name'] = $translation['value'];
            //         }
            //     }
            // }

            // if (!$trans) {
            //     unset($data['translations']);
            // }
        }
        return $data;
    }

    public static function category_data_formatting($data, $multi_data = false, $trans = false)
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                if (count($item->translations) > 0) {
                    $item->name = $item->translations[0]['value'];
                }

                if (!$trans) {
                    unset($item['translations']);
                }

                $storage[] = $item;
            }
            $data = $storage;
        } else if (isset($data)) {
            if (count($data->translations) > 0) {
                $data->name = $data->translations[0]['value'];
            }

            if (!$trans) {
                unset($data['translations']);
            }
        }
        return $data;
    }

    public static function parcel_category_data_formatting($data, $multi_data = false)
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                // if (count($item['translations']) > 0) {
                //     $translate = array_column($item['translations']->toArray(), 'value', 'key');
                //     $item['name'] = $translate['name'];
                //     $item['description'] = $translate['description'];
                //     unset($item['translations']);
                // }
                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            // if (count($data['translations']) > 0) {
            //     $translate = array_column($data['translations']->toArray(), 'value', 'key');
            //     $data['title'] = $translate['title'];
            //     $data['description'] = $translate['description'];
            //     unset($data['translations']);
            // }
        }
        return $data;
    }

    public static function basic_campaign_data_formatting($data, $multi_data = false)
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                $variations = [];

                if ($item->start_date) {
                    $item['available_date_starts'] = $item->start_date->format('Y-m-d');
                    unset($item['start_date']);
                }
                if ($item->end_date) {
                    $item['available_date_ends'] = $item->end_date->format('Y-m-d');
                    unset($item['end_date']);
                }

                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            if ($data->start_date) {
                $data['available_date_starts'] = $data->start_date->format('Y-m-d');
                unset($data['start_date']);
            }
            if ($data->end_date) {
                $data['available_date_ends'] = $data->end_date->format('Y-m-d');
                unset($data['end_date']);
            }
        }

        return $data;
    }

    public static function store_data_formatting($data, $multi_data = false)
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {

                $item->load('storeConfig');
                $ratings = StoreLogic::calculate_store_rating($item['rating']);
                $item['ratings'] = $item?->rating ?? [];
                unset($item['rating']);
                $item['avg_rating'] = $ratings['rating'];
                $item['rating_count'] = $ratings['total'];
                $item['positive_rating'] = $ratings['positive_rating'];
                $item['total_items'] = $item['items_count'];
                $item['total_campaigns'] = $item['campaigns_count'];
                $item['is_recommended'] = false;
                $item['halal_tag_status'] =   (bool) $item?->storeConfig?->halal_tag_status;
                $extra_packaging_data = \App\Models\BusinessSetting::where('key', 'extra_packaging_data')->first()?->value ?? '';
                $extra_packaging_data =json_decode($extra_packaging_data , true);
                $item['extra_packaging_status'] =   (bool) (!empty($extra_packaging_data) && $extra_packaging_data[$item->module->module_type]=='1')?$item?->storeConfig?->extra_packaging_status:false;
                $item['extra_packaging_amount'] =   (float) (!empty($extra_packaging_data) && ($extra_packaging_data[$item->module->module_type]=='1') && ($item?->storeConfig?->extra_packaging_status == '1'))?$item?->storeConfig?->extra_packaging_amount:0;
                if($item->storeConfig && $item->storeConfig->is_recommended_deleted == 0 ){
                    $item['is_recommended'] = $item->storeConfig->is_recommended;
                }
                $item['self_delivery_system'] = (int) $item->sub_self_delivery;
                $item['current_opening_time'] = self::getNextOpeningTime($item['schedules']) ?? 'closed';
                unset($item['items_count']);
                unset($item['campaigns_count']);
                unset($item['storeConfig']);
                unset($item['campaigns']);
                unset($item['pivot']);
                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            $data->load('storeConfig');
            $data['is_recommended'] = false;
            $data['halal_tag_status'] =   (bool) $data?->storeConfig?->halal_tag_status;
            $extra_packaging_data = \App\Models\BusinessSetting::where('key', 'extra_packaging_data')->first()?->value ?? '';
            $extra_packaging_data =json_decode($extra_packaging_data , true);
            $data['extra_packaging_status'] =   (bool) (!empty($extra_packaging_data) && $extra_packaging_data[$data->module->module_type]=='1')?$data?->storeConfig?->extra_packaging_status:false;
            $data['extra_packaging_amount'] =   (float) (!empty($extra_packaging_data) && ($extra_packaging_data[$data->module->module_type]=='1') && ($data?->storeConfig?->extra_packaging_status == '1'))?$data?->storeConfig?->extra_packaging_amount:0;
            if($data->storeConfig && $data->storeConfig->is_recommended_deleted == 0 ){
                $data['is_recommended'] = $data->storeConfig->is_recommended;
            }
            $data['self_delivery_system'] = (int) $data->sub_self_delivery;
            $ratings = StoreLogic::calculate_store_rating($data['rating']);
            $data['ratings'] = $data?->rating ?? [];
            unset($data['rating']);
            $data['avg_rating'] = $ratings['rating'];
            $data['rating_count'] = $ratings['total'];
            $data['positive_rating'] = $ratings['positive_rating'];
            $data['total_items'] = $data['items_count'];
            $data['total_campaigns'] = $data['campaigns_count'];
            $data['current_opening_time'] = self::getNextOpeningTime($data['schedules']) ?? 'closed';
            unset($data['items_count']);
            unset($data['campaigns_count']);
            unset($data['campaigns']);
            unset($data['storeConfig']);
            unset($data['pivot']);
        }

        return $data;
    }

    public static function wishlist_data_formatting($data, $multi_data = false)
    {
        $items = [];
        $stores = [];
        if ($multi_data == true) {

            foreach ($data as $temp) {
                if ($temp->item) {
                    $items[] = self::product_data_formatting($temp->item, false, false, app()->getLocale());
                }
                if ($temp->store) {
                    $stores[] = self::store_data_formatting($temp->store);
                }
            }
        } else {
            if ($data->item) {
                $items[] = self::product_data_formatting($data->item, false, false, app()->getLocale());
            }
            if ($data->store) {
                $stores[] = self::store_data_formatting($data->store);
            }
        }

        return ['item' => $items, 'store' => $stores];
    }

    public static function order_data_formatting($data, $multi_data = false)
    {
        $storage = [];
        if ($multi_data) {
            foreach ($data as $item) {
                if (isset($item['store'])) {
                    $item['store_name'] = $item['store']['name'];
                    $item['store_address'] = $item['store']['address'];
                    $item['store_phone'] = $item['store']['phone'];
                    $item['store_lat'] = $item['store']['latitude'];
                    $item['store_lng'] = $item['store']['longitude'];
                    $item['store_logo'] = $item['store']['logo'];
                    $item['store_logo_full_url'] = $item['store']['logo_full_url'];
                    $item['min_delivery_time'] =  (int) explode('-',$item['store']['delivery_time'])[0] ?? 0;
                    $item['max_delivery_time'] =  (int) explode('-',$item['store']['delivery_time'])[1] ?? 0;

                    $item['vendor_id'] = $item['store']['vendor_id'];
                    $item['chat_permission'] = $item['store']['chat_permission']?? 0;
                    $item['review_permission'] = $item['store']['review_permission'] ?? 0;
                    $item['store_business_model'] = $item['store']['store_business_model'];

                    unset($item['store']);
                } else {
                    $item['store_name'] = null;
                    $item['store_address'] = null;
                    $item['store_phone'] = null;
                    $item['store_lat'] = null;
                    $item['store_lng'] = null;
                    $item['store_logo'] = null;
                    $item['store_logo_full_url'] = null;
                    $item['min_delivery_time'] = null;
                    $item['max_delivery_time'] = null;
                    $item['vendor_id'] = null;
                    $item['chat_permission'] = null;
                    $item['review_permission'] = null;
                    $item['store_business_model'] = null;
                }
                $item['item_campaign'] = 0;
                foreach ($item->details as $d) {
                    if ($d->item_campaign_id != null) {
                        $item['item_campaign'] = 1;
                    }
                }

                $item['delivery_address'] = $item->delivery_address ? json_decode($item->delivery_address, true) : null;
                $item['details_count'] = (int)$item->details->count();
                $item['min_delivery_time'] =  $item->store ? (int)explode('-',$item->store?->delivery_time)[0] ?? 0:0;
                $item['max_delivery_time'] =  $item->store ? (int)explode('-',$item->store?->delivery_time)[1] ?? 0:0;

                unset($item['details']);
                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            if (isset($data['store'])) {
                $data['store_name'] = $data['store']['name'];
                $data['store_address'] = $data['store']['address'];
                $data['store_phone'] = $data['store']['phone'];
                $data['store_lat'] = $data['store']['latitude'];
                $data['store_lng'] = $data['store']['longitude'];
                $data['store_logo'] = $data['store']['logo'];
                $data['store_logo_full_url'] = $data['store']['logo_full_url'];
                $data['min_delivery_time'] =  $data['store']?(int) explode('-',$data['store']['delivery_time'])[0] ?? 0:0;
                $data['max_delivery_time'] =  $data['store']?(int) explode('-',$data['store']['delivery_time'])[1] ?? 0:0;
                $data['vendor_id'] = $data['store']['vendor_id'];
                $data['chat_permission'] = $data['store']['chat_permission']?? 0;
                $data['review_permission'] = $data['store']['review_permission'] ?? 0;
                $data['store_business_model'] = $data['store']['store_business_model'];

                unset($data['store']);
            } else {
                $data['store_name'] = null;
                $data['store_address'] = null;
                $data['store_phone'] = null;
                $data['store_lat'] = null;
                $data['store_lng'] = null;
                $data['store_logo'] = null;
                $data['store_logo_full_url'] = null;
                $data['min_delivery_time'] = null;
                $data['max_delivery_time'] = null;
                $item['vendor_id'] = null;
                $item['chat_permission'] = null;
                $item['review_permission'] = null;
                $item['store_business_model'] = null;
            }

            $data['item_campaign'] = 0;
            foreach ($data->details as $d) {
                if ($d->item_campaign_id != null) {
                    $data['item_campaign'] = 1;
                }
            }
            $data['delivery_address'] = $data->delivery_address ? json_decode($data->delivery_address, true) : null;
            $data['details_count'] = (int)$data->details->count();

            unset($data['details']);
        }
        return $data;
    }

    public static function order_details_data_formatting($data)
    {
        $storage = [];
        foreach ($data as $item) {
            $item['add_ons'] = json_decode($item['add_ons']);
            $item['variation'] = json_decode($item['variation'], true);
            $item['item_details'] = json_decode($item['item_details'], true);
            if ($item['item_id']){
                $product = \App\Models\Item::where(['id' => $item['item_details']['id']])->first();
                $item['image_full_url'] = $product->image_full_url;
                $item['images_full_url'] = $product->images_full_url;
            }else{
               $product = \App\Models\ItemCampaign::where(['id' => $item['item_details']['id']])->first();
                $item['image_full_url'] = $product->image_full_url;
                $item['images_full_url'] = [];
            }
            array_push($storage, $item);
        }
        $data = $storage;

        return $data;
    }

    public static function deliverymen_list_formatting($data)
    {
        $storage = [];
        foreach ($data as $item) {
            $storage_type = 'public';
            if ($item->storage && count($item->storage) > 0) {
                foreach ($item->storage as $value) {
                    if ($value['key'] == 'image') {
                        $storage_type = $value['value'];
                    }
                }
            }
            $storage[] = [
                'id' => $item['id'],
                'name' => $item['f_name'] . ' ' . $item['l_name'],
                'image' => $item['image'],
                'assigned_order_count' => $item['assigned_order_count'],
                'lat' => $item->last_location ? $item->last_location->latitude : false,
                'lng' => $item->last_location ? $item->last_location->longitude : false,
                'location' => $item->last_location ? $item->last_location->location : '',
                'storage' => $storage_type,
                'image_link' => $item['image_full_url'],
                'image_full_url' => $item['image_full_url'],
            ];
        }
        $data = $storage;

        return $data;
    }

    public static function deliverymen_data_formatting($data)
    {
        $storage = [];
        foreach ($data as $item) {
            $item['avg_rating'] = (float)(count($item->rating) ? (float)$item->rating[0]->average : 0);
            $item['rating_count'] = (int)(count($item->rating) ? $item->rating[0]->rating_count : 0);
            $item['lat'] = $item->last_location ? $item->last_location->latitude : null;
            $item['lng'] = $item->last_location ? $item->last_location->longitude : null;
            $item['location'] = $item->last_location ? $item->last_location->location : null;
            if ($item['rating']) {
                unset($item['rating']);
            }
            if ($item['last_location']) {
                unset($item['last_location']);
            }
            $storage[] = $item;
        }
        $data = $storage;

        return $data;
    }

    public static function get_business_settings($name)
    {
        $config = null;

        $paymentmethod = BusinessSetting::where('key', $name)->first();

        if ($paymentmethod) {
            $config = json_decode($paymentmethod->value, true);
        }

        return $config;
    }

    public static function get_business_data($name)
    {
        $config = null;

        $paymentmethod = BusinessSetting::where('key', $name)->first();

        if ($paymentmethod) {
            $config = $paymentmethod->value;
        }

        return $config;
    }
    public static function get_external_data($name)
    {
        $config = null;

        $paymentmethod = ExternalConfiguration::where('key', $name)->first();

        if ($paymentmethod) {
            $config = $paymentmethod?->value;
        }

        return $config;
    }

    public static function currency_code()
    {
        if (!config('currency') ){
            $currency = BusinessSetting::where(['key' => 'currency'])->first()?->value;
            Config::set('currency', $currency );
        }
        else{
            $currency = config('currency');
        }

        return $currency;
    }


    public static function currency_symbol()
    {
        if (!config('currency_symbol') ){
            $currency_symbol = Currency::where(['currency_code' => Helpers::currency_code()])->first()?->currency_symbol;
            Config::set('currency_symbol', $currency_symbol );
        }
        else{
            $currency_symbol =config('currency_symbol');
        }
        return $currency_symbol ;
    }



    public static function format_currency($value)
    {
        if (!config('currency_symbol_position') ){
            $currency_symbol_position = BusinessSetting::where(['key' => 'currency_symbol_position'])->first()?->value;
            Config::set('currency_symbol_position', $currency_symbol_position );
        }
        else{
            $currency_symbol_position =config('currency_symbol_position');
        }

        return $currency_symbol_position == 'right' ? number_format($value, config('round_up_to_digit')) . ' ' . self::currency_symbol() : self::currency_symbol() . ' ' . number_format($value, config('round_up_to_digit'));
    }

    public static function sendNotificationToHttp(array|null $data)
    {
        $config = self::get_business_settings('push_notification_service_file_content');
        $key = (array)$config;
        if($key['project_id']){
            $url = 'https://fcm.googleapis.com/v1/projects/'.$key['project_id'].'/messages:send';
            $headers = [
                'Authorization' => 'Bearer ' . self::getAccessToken($key),
                'Content-Type' => 'application/json',
            ];
            try {
                Http::withHeaders($headers)->post($url, $data);
            }catch (\Exception $exception){
                return false;
            }
        }
        return false;
    }

    public static function getAccessToken($key)
    {
        $jwtToken = [
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 3600,
            'iat' => time(),
        ];
        $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtPayload = base64_encode(json_encode($jwtToken));
        $unsignedJwt = $jwtHeader . '.' . $jwtPayload;
        openssl_sign($unsignedJwt, $signature, $key['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = $unsignedJwt . '.' . base64_encode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);
        return $response->json('access_token');
    }

    public static function send_push_notif_to_device($fcm_token, $data, $web_push_link = null)
    {
//        if(isset($data['message'])){
//            $message = $data['message'];
//        }else{
//            $message = '';
//        }
        if(isset($data['conversation_id'])){
            $conversation_id = $data['conversation_id'];
        }else{
            $conversation_id = '';
        }
        if(isset($data['sender_type'])){
            $sender_type = $data['sender_type'];
        }else{
            $sender_type = '';
        }
        if(isset($data['module_id'])){
            $module_id = $data['module_id'];
        }else{
            $module_id = '';
        }
        if(isset($data['order_type'])){
            $order_type = $data['order_type'];
        }else{
            $order_type = '';
        }

//        $click_action = "";
//        if($web_push_link){
//            $click_action = ',
//            "click_action": "'.$web_push_link.'"';
//        }
        $postData = [
            'message' => [
                "token" => $fcm_token,
                "data" => [
                    "title" => (string)$data['title'],
                    "body" => (string)$data['description'],
                    "image" => (string)$data['image'],
                    "order_id" => (string)$data['order_id'],
                    "type" => (string)$data['type'],
                    "conversation_id" => (string)$conversation_id,
                    "module_id" => (string)$module_id,
                    "sender_type" => (string)$sender_type,
                    "order_type" => (string)$order_type,
                    "click_action" => $web_push_link?(string)$web_push_link:'',
                    "sound" => "notification.wav",
                ],
                "notification" => [
                    'title' => (string)$data['title'],
                    'body' => (string)$data['description'],
                    "image" => (string)$data['image'],
                ],
                "android" => [
                    "notification" => [
                        "channelId" => '6ammart',
                    ]
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "notification.wav"
                        ]
                    ]
                ]
            ]
        ];

        return self::sendNotificationToHttp($postData);
    }

    public static function send_push_notif_to_topic($data, $topic, $type,$web_push_link = null)
    {
        if(isset($data['module_id'])){
            $module_id = $data['module_id'];
        }else{
            $module_id = '';
        }
        if(isset($data['order_type'])){
            $order_type = $data['order_type'];
        }else{
            $order_type = '';
        }
        if(isset($data['zone_id'])){
            $zone_id = $data['zone_id'];
        }else{
            $zone_id = '';
        }

//        $click_action = "";
//        if($web_push_link){
//            $click_action = ',
//            "click_action": "'.$web_push_link.'"';
//        }

        if (isset($data['order_id'])) {
            $postData = [
                'message' => [
                    "topic" => $topic,
                    "data" => [
                        "title" => (string)$data['title'],
                        "body" => (string)$data['description'],
                        "order_id" => (string)$data['order_id'],
                        "order_type" => (string)$order_type,
                        "type" => (string)$type,
                        "image" => (string)$data['image'],
                        "module_id" => (string)$module_id,
                        "zone_id" => (string)$zone_id,
                        "title_loc_key" => (string)$data['order_id'],
                        "body_loc_key" => (string)$type,
                        "click_action" => $web_push_link?(string)$web_push_link:'',
                        "sound" => "notification.wav",
                    ],
                    "notification" => [
                        "title" => (string)$data['title'],
                        "body" => (string)$data['description'],
                        "image" => (string)$data['image'],
                    ],
                    "android" => [
                        "notification" => [
                            "channelId" => '6ammart',
                        ]
                    ],
                    "apns" => [
                        "payload" => [
                            "aps" => [
                                "sound" => "notification.wav"
                            ]
                        ]
                    ]
                ]
            ];
        } else {
            $postData = [
                'message' => [
                    "topic" => $topic,
                    "data" => [
                        "title" => (string)$data['title'],
                        "body" => (string)$data['description'],
                        "type" => (string)$type,
                        "image" => (string)$data['image'],
                        "body_loc_key" => (string)$type,
                        "click_action" => $web_push_link?(string)$web_push_link:'',
                        "sound" => "notification.wav",
                    ],
                    "notification" => [
                        "title" => (string)$data['title'],
                        "body" => (string)$data['description'],
                        "image" => (string)$data['image'],
                    ],
                    "android" => [
                        "notification" => [
                            "channelId" => '6ammart',
                        ]
                    ],
                    "apns" => [
                        "payload" => [
                            "aps" => [
                                "sound" => "notification.wav"
                            ]
                        ]
                    ]
                ]
            ];
        }
        return self::sendNotificationToHttp($postData);
    }


    public static function rating_count($item_id, $rating)
    {
        return Review::where(['item_id' => $item_id, 'rating' => $rating])->count();
    }

    public static function dm_rating_count($deliveryman_id, $rating)
    {
        return DMReview::where(['delivery_man_id' => $deliveryman_id, 'rating' => $rating])->count();
    }

    public static function tax_calculate($item, $price)
    {
        if ($item['tax_type'] == 'percent') {
            $price_tax = ($price / 100) * $item['tax'];
        } else {
            $price_tax = $item['tax'];
        }
        return $price_tax;
    }

    public static function discount_calculate($product, $price)
    {
        if ($product['store_discount']) {
            $price_discount = ($price / 100) * $product['store_discount'];
        } else if ($product['discount_type'] == 'percent') {
            $price_discount = ($price / 100) * $product['discount'];
        } else {
            $price_discount = $product['discount'];
        }
        return $price_discount;
    }

    public static function get_product_discount($product)
    {
        $store_discount = self::get_store_discount($product->store);
        if ($store_discount) {
            $discount = $store_discount['discount'] . ' %';
        } else if ($product['discount_type'] == 'percent') {
            $discount = $product['discount'] . ' %';
        } else {
            $discount = self::format_currency($product['discount']);
        }
        return $discount;
    }

    public static function product_discount_calculate($product, $price, $store)
    {
        $running_flash_sale = FlashSaleItem::Active()->whereHas('flashSale', function ($query) {
            $query->Active()->Running();
        })
            ->where(['item_id' => $product->id])->first();

        if($running_flash_sale){
            if ($running_flash_sale['discount_type'] == 'percent') {
                $price_discount = ($price / 100) * $running_flash_sale['discount'];
            } else {
                $price_discount = $running_flash_sale['discount'];
            }
            return [
                'discount_type'=>'flash_sale',
                'discount_amount'=> $price_discount,
                'admin_discount_amount'=> ($price_discount*$running_flash_sale->flashSale->admin_discount_percentage)/100,
                'vendor_discount_amount'=> ($price_discount*$running_flash_sale->flashSale->vendor_discount_percentage)/100,
            ];
        }

        $store_discount = self::get_store_discount($store);
        if (isset($store_discount)) {
            $price_discount = ($price / 100) * $store_discount['discount'];
        } else if ($product['discount_type'] == 'percent') {
            $price_discount = ($price / 100) * $product['discount'];
        } else {
            $price_discount = $product['discount'];
        }

        return [
            'discount_type'=>isset($store_discount)?'store_discount':'product_discount',
            'discount_amount'=> $price_discount
        ];
    }

    public static function get_price_range($product, $discount = false)
    {
        $lowest_price = $product->price;
        $highest_price = $product->price;
        if ($product->variations && is_array(json_decode($product['variations'], true))) {
            foreach (json_decode($product->variations) as $key => $variation) {
                if ($lowest_price > $variation->price) {
                    $lowest_price = round($variation->price, 2);
                }
                if ($highest_price < $variation->price) {
                    $highest_price = round($variation->price, 2);
                }
            }
        }

        if ($discount) {
            $lowest_price -= self::product_discount_calculate($product, $lowest_price, $product->store)['discount_amount'];
            $highest_price -= self::product_discount_calculate($product, $highest_price, $product->store)['discount_amount'];
        }
        $lowest_price = self::format_currency($lowest_price);
        $highest_price = self::format_currency($highest_price);

        if ($lowest_price == $highest_price) {
            return $lowest_price;
        }
        return $lowest_price . ' - ' . $highest_price;
    }
    public static function get_food_price_range($product, $discount = false)
    {
        $lowest_price = $product->price;


        if ($discount) {
            $lowest_price -= self::product_discount_calculate($product, $lowest_price, $product->store)['discount_amount'];

        }
        $lowest_price = self::format_currency($lowest_price);
        return $lowest_price;
    }

    public static function get_store_discount($store)
    {
        if ($store->discount) {
            if (date('Y-m-d', strtotime($store->discount->start_date)) <= now()->format('Y-m-d') && date('Y-m-d', strtotime($store->discount->end_date)) >= now()->format('Y-m-d') && date('H:i', strtotime($store->discount->start_time)) <= now()->format('H:i') && date('H:i', strtotime($store->discount->end_time)) >= now()->format('H:i')) {
                return [
                    'discount' => $store->discount->discount,
                    'min_purchase' => $store->discount->min_purchase,
                    'max_discount' => $store->discount->max_discount
                ];
            }
        }
        return null;
    }

    public static function max_earning()
    {
        $data = Order::where(['order_status' => 'delivered'])->select('id', 'created_at', 'order_amount')
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->format('m');
            });

        $max = 0;
        foreach ($data as $month) {
            $count = 0;
            foreach ($month as $order) {
                $count += $order['order_amount'];
            }
            if ($count > $max) {
                $max = $count;
            }
        }
        return $max;
    }

    public static function max_orders()
    {
        $data = Order::select('id', 'created_at')
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->format('m');
            });

        $max = 0;
        foreach ($data as $month) {
            $count = 0;
            foreach ($month as $order) {
                $count += 1;
            }
            if ($count > $max) {
                $max = $count;
            }
        }
        return $max;
    }

    public static function order_status_update_message($status,$module_type, $lang='en')
    {
        if ($status == 'pending') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'order_pending_message')->first();
        } elseif ($status == 'confirmed') {
            $data =  NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'order_confirmation_msg')->first();
        } elseif ($status == 'processing') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'order_processing_message')->first();
        } elseif ($status == 'picked_up') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'out_for_delivery_message')->first();
        } elseif ($status == 'handover') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'order_handover_message')->first();
        } elseif ($status == 'delivered') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'order_delivered_message')->first();
        } elseif ($status == 'delivery_boy_delivered') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'delivery_boy_delivered_message')->first();
        } elseif ($status == 'accepted') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'delivery_boy_assign_message')->first();
        } elseif ($status == 'canceled') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'order_cancled_message')->first();
        } elseif ($status == 'refunded') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'order_refunded_message')->first();
        } elseif ($status == 'refund_request_canceled') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'refund_request_canceled')->first();
        } elseif ($status == 'offline_verified') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'offline_order_accept_message')->first();
        } elseif ($status == 'offline_denied') {
            $data = NotificationMessage::with(['translations'=>function($query)use($lang){
                $query->where('locale', $lang);
            }])->where('module_type',$module_type)->where('key', 'offline_order_deny_message')->first();
        } else {
            $data = ["status"=>"0","message"=>"",'translations'=>[]];
        }

        if($data){
            if ($data['status'] == 0) {
                return 0;
            }
            return count($data->translations) > 0 ? $data->translations[0]->value : $data['message'];
        }else{
            return false;
        }
    }

    public static function send_order_notification($order)
    {
        $push_notification_status=self::getNotificationStatusData('store','store_order_notification','push_notification_status', $order?->store?->id);

        try {


            if((in_array($order->payment_method, ['cash_on_delivery', 'offline_payment'])  && $order->order_status == 'pending' )||(!in_array($order->payment_method, ['cash_on_delivery', 'offline_payment']) && $order->order_status == 'confirmed' )){
                $data = [
                    'title' => translate('Order_Notification'),
                    'description' => translate('messages.new_order_push_description'),
                    'order_id' => $order->id,
                    'image' => '',
                    'module_id' => $order->module_id,
                    'order_type' => $order->order_type,
                    'zone_id' => $order->zone_id,
                    'type' => 'new_order',
                ];
                self::send_push_notif_to_topic($data, 'admin_message', 'order_request', url('/').'/admin/order/list/all');
            }

            $status = ($order->order_status == 'delivered' && $order->delivery_man) ? 'delivery_boy_delivered' : $order->order_status;


            if($order->is_guest){
                $customer_details = json_decode($order['delivery_address'],true);
                $value = self::order_status_update_message($status,$order->module->module_type,'en');
                $value = self::text_variable_data_format(value:$value,store_name:$order->store?->name,order_id:$order->id,user_name:"{$customer_details['contact_person_name']}",delivery_man_name:"{$order->delivery_man?->f_name} {$order->delivery_man?->l_name}");
                $user_fcm = $order->guest->fcm_token;

            }else{

                $value = self::order_status_update_message($status,$order->module->module_type,$order->customer?
                    $order->customer->current_language_key:'en');
                $value = self::text_variable_data_format(value:$value,store_name:$order->store?->name,order_id:$order->id,user_name:"{$order->customer?->f_name} {$order->customer?->l_name}",delivery_man_name:"{$order->delivery_man?->f_name} {$order->delivery_man?->l_name}");
                $user_fcm = $order?->customer?->cm_firebase_token;
            }

            if (self::getNotificationStatusData('customer','customer_order_notification','push_notification_status') &&  $value && $user_fcm) {
                $data = [
                    'title' => translate('Order_Notification'),
                    'description' => $value,
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ];
                self::send_push_notif_to_device($user_fcm, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'user_id' => $order->user_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            if ($status == 'picked_up') {
                $data = [
                    'title' => translate('Order_Notification'),
                    'description' => $value,
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ];
                if($order->store && $order->store->vendor && $push_notification_status){
                    self::send_push_notif_to_device($order->store->vendor->firebase_token, $data);
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'vendor_id' => $order->store->vendor_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            if ($order->order_type == 'delivery' && !$order->scheduled && $status == 'pending' && $order->payment_method == 'cash_on_delivery' && config('order_confirmation_model') == 'deliveryman') {
                if ($order->store->sub_self_delivery && $push_notification_status) {
                    $data = [
                        'title' => translate('Order_Notification'),
                        'description' => translate('messages.new_order_push_description'),
                        'order_id' => $order->id,
                        'module_id' => $order->module_id,
                        'order_type' => $order->order_type,
                        'image' => '',
                        'type' => 'new_order',
                    ];
                    if($order->store && $order->store->vendor && $push_notification_status){
                        self::send_push_notif_to_device($order->store->vendor->firebase_token, $data);
                        $web_push_link = url('/').'/store-panel/order/list/all';
                        self::send_push_notif_to_topic($data, "store_panel_{$order->store_id}_message", 'new_order', $web_push_link);
                        DB::table('user_notifications')->insert([
                            'data' => json_encode($data),
                            'vendor_id' => $order->store->vendor_id,
                            // 'module_id' => $order->module_id,
                            'order_type' => $order->order_type,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                } else {
                    $data = [
                        'title' => translate('Order_Notification'),
                        'description' => translate('messages.new_order_push_description'),
                        'order_id' => $order->id,
                        'module_id' => $order->module_id,
                        'order_type' => $order->order_type,
                        'image' => '',
                    ];
                    if($order->zone){
                        if($order->dm_vehicle_id){

                            $topic = 'delivery_man_'.$order->zone_id.'_'.$order->dm_vehicle_id;
                            self::send_push_notif_to_topic($data, $topic, 'order_request');
                        }
                        self::send_push_notif_to_topic($data, $order->zone->deliveryman_wise_topic, 'order_request');


                    }
                }
                // self::send_push_notif_to_topic($data, 'admin_message', 'order_request', url('/').'/admin/order/list/all');
            }

            if ($order->order_type == 'parcel' && in_array($order->order_status, ['pending', 'confirmed'])) {
                $data = [
                    'title' => translate('Order_Notification'),
                    'description' => translate('messages.new_order_push_description'),
                    'order_id' => $order->id,
                    'module_id' => $order->module_id,
                    'order_type' => 'parcel_order',
                    'image' => '',
                ];
                if($order->zone){
                    if($order->dm_vehicle_id){

                        $topic = 'delivery_man_'.$order->zone_id.'_'.$order->dm_vehicle_id;
                        self::send_push_notif_to_topic($data, $topic, 'order_request');
                    }
                    self::send_push_notif_to_topic($data, $order->zone->deliveryman_wise_topic, 'order_request');

                }
                // self::send_push_notif_to_topic($data, 'admin_message', 'order_request');
            }

            if ($order->order_type == 'delivery' && !$order->scheduled && $order->order_status == 'pending' && $order->payment_method == 'cash_on_delivery' && config('order_confirmation_model') == 'store') {
                $data = [
                    'title' => translate('Order_Notification'),
                    'description' => translate('messages.new_order_push_description'),
                    'order_id' => $order->id,
                    'module_id' => $order->module_id,
                    'order_type' => $order->order_type,
                    'image' => '',
                    'type' => 'new_order',
                ];
                if($order->store && $order->store->vendor && $push_notification_status){
                    self::send_push_notif_to_device($order->store->vendor->firebase_token, $data);
                    $web_push_link = url('/').'/store-panel/order/list/all';
                    self::send_push_notif_to_topic($data, "store_panel_{$order->store_id}_message", 'new_order', $web_push_link);
                    // self::send_push_notif_to_topic($data, 'admin_message', 'order_request');
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'vendor_id' => $order->store->vendor_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            if (!$order->scheduled && (($order->order_type == 'take_away' && $order->order_status == 'pending') || ($order->payment_method != 'cash_on_delivery' && $order->order_status == 'confirmed'))) {
                $data = [
                    'title' => translate('Order_Notification'),
                    'description' => translate('messages.new_order_push_description'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'new_order',
                ];
                if($order->store && $order->store->vendor && $push_notification_status){
                    self::send_push_notif_to_device($order->store->vendor->firebase_token, $data);
                    $web_push_link = url('/').'/store-panel/order/list/all';
                    self::send_push_notif_to_topic($data, "store_panel_{$order->store_id}_message", 'new_order', $web_push_link);
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'vendor_id' => $order->store->vendor_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            if ($order->order_status == 'confirmed' && $order->order_type != 'take_away' && config('order_confirmation_model') == 'deliveryman' && $order->payment_method == 'cash_on_delivery') {
                if ($order->store->sub_self_delivery) {
                    $data = [
                        'title' => translate('Order_Notification'),
                        'description' => translate('messages.new_order_push_description'),
                        'order_id' => $order->id,
                        'module_id' => $order->module_id,
                        'order_type' => $order->order_type,
                        'image' => '',
                    ];

                    self::send_push_notif_to_topic($data, "restaurant_dm_" . $order->store_id, 'new_order',null);
                } else {
                    $data = [
                        'title' => translate('Order_Notification'),
                        'description' => translate('messages.new_order_push_description'),
                        'order_id' => $order->id,
                        'module_id' => $order->module_id,
                        'order_type' => $order->order_type,
                        'image' => '',
                        'type' => 'new_order',
                    ];
                    if($order->store && $order->store->vendor && $push_notification_status){
                        self::send_push_notif_to_device($order->store->vendor->firebase_token, $data);
                        $web_push_link = url('/').'/store-panel/order/list/all';
                        self::send_push_notif_to_topic($data, "store_panel_{$order->store_id}_message", 'new_order', $web_push_link);
                        DB::table('user_notifications')->insert([
                            'data' => json_encode($data),
                            'vendor_id' => $order->store->vendor_id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }

            if ($order->order_type == 'delivery' && !$order->scheduled && $order->order_status == 'confirmed'  && ($order->payment_method != 'cash_on_delivery' || config('order_confirmation_model') == 'store')) {
                $data = [
                    'title' => translate('Order_Notification'),
                    'description' => translate('messages.new_order_push_description'),
                    'order_id' => $order->id,
                    'module_id' => $order->module_id,
                    'order_type' => $order->order_type,
                    'image' => '',
                ];
                if ($order->store->sub_self_delivery) {
                    self::send_push_notif_to_topic($data, "restaurant_dm_" . $order->store_id, 'order_request',null);
                } else
                {if($order->zone){
                    if($order->dm_vehicle_id){

                        $topic = 'delivery_man_'.$order->zone_id.'_'.$order->dm_vehicle_id;
                        self::send_push_notif_to_topic($data, $topic, 'order_request');
                    }
                    self::send_push_notif_to_topic($data, $order->zone->deliveryman_wise_topic, 'order_request');
                }
                }
            }

            if (in_array($order->order_status, ['processing', 'handover']) && $order->delivery_man && self::getNotificationStatusData('deliveryman','deliveryman_order_notification','push_notification_status')) {
                $data = [
                    'title' => translate('Order_Notification'),
                    'description' => $order->order_status == 'processing' ? translate('messages.Proceed_for_cooking') : translate('messages.ready_for_delivery'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status'
                ];
                self::send_push_notif_to_device($order->delivery_man->fcm_token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'delivery_man_id' => $order->delivery_man->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            try {
                if ($order->order_status == 'confirmed' && $order->payment_method != 'cash_on_delivery' && config('mail.status') && Helpers::get_mail_status('place_order_mail_status_user') == '1' && $order->is_guest == 0 && Helpers::getNotificationStatusData('customer','customer_order_notification','mail_status')) {
                    Mail::to($order->customer->email)->send(new PlaceOrder($order->id));
                }
                $order_verification_mail_status = Helpers::get_mail_status('order_verification_mail_status_user');
                if ($order->order_status == 'pending' && config('order_delivery_verification') == 1  && config('mail.status') && $order_verification_mail_status == '1' && $order->is_guest == 0 && Helpers::getNotificationStatusData('customer','customer_delivery_verification','mail_status')) {
                    Mail::to($order->customer->email)->send(new OrderVerificationMail($order->otp,$order->customer->f_name));
                }
            } catch (\Exception $ex) {
                info($ex->getMessage());
            }
            return true;
        } catch (\Exception $e) {
            info($e->getMessage());
        }
        return false;
    }

    public static function day_part()
    {
        $part = "";
        $morning_start = date("h:i:s", strtotime("5:00:00"));
        $afternoon_start = date("h:i:s", strtotime("12:01:00"));
        $evening_start = date("h:i:s", strtotime("17:01:00"));
        $evening_end = date("h:i:s", strtotime("21:00:00"));

        if (time() >= $morning_start && time() < $afternoon_start) {
            $part = "morning";
        } elseif (time() >= $afternoon_start && time() < $evening_start) {
            $part = "afternoon";
        } elseif (time() >= $evening_start && time() <= $evening_end) {
            $part = "evening";
        } else {
            $part = "night";
        }

        return $part;
    }

    public static function env_update($key, $value)
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            file_put_contents($path, str_replace(
                $key . '=' . env($key),
                $key . '=' . $value,
                file_get_contents($path)
            ));
        }
    }

    public static function env_key_replace($key_from, $key_to, $value)
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            file_put_contents($path, str_replace(
                $key_from . '=' . env($key_from),
                $key_to . '=' . $value,
                file_get_contents($path)
            ));
        }
    }

    public static  function remove_dir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") Helpers::remove_dir($dir . "/" . $object);
                    else unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    public static function get_store_id()
    {
        if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()->store->id;
        }
        return auth('vendor')->user()->stores[0]->id;
    }

    public static function get_vendor_id()
    {
        if (auth('vendor')->check()) {
            return auth('vendor')->id();
        } else if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()->vendor_id;
        }
        return 0;
    }

    public static function get_vendor_data()
    {
        if (auth('vendor')->check()) {
            return auth('vendor')->user();
        } else if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()->vendor;
        }
        return 0;
    }

    public static function get_loggedin_user()
    {
        if (auth('vendor')->check()) {
            return auth('vendor')->user();
        } else if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user();
        }
        return 0;
    }

    public static function get_store_data()
    {
        if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()->store;
        }
        return auth('vendor')->user()->stores[0];
    }

    public static function getDisk()
    {
        $config=\App\CentralLogics\Helpers::get_business_settings('local_storage');

        return isset($config)?($config==0?'s3':'public'):'public';
    }

    public static function upload(string $dir, string $format, $image = null)
    {
        try {
            if ($image != null) {
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
                if (!Storage::disk(self::getDisk())->exists($dir)) {
                    Storage::disk(self::getDisk())->makeDirectory($dir);
                }
                Storage::disk(self::getDisk())->putFileAs($dir, $image, $imageName);
            } else {
                $imageName = 'def.png';
            }
        } catch (\Exception $e) {
        }
        return $imageName;
    }

    public static function update(string $dir, $old_image, string $format, $image = null)
    {
        if ($image == null) {
            return $old_image;
        }
        try {
            if (Storage::disk(self::getDisk())->exists($dir . $old_image)) {
                Storage::disk(self::getDisk())->delete($dir . $old_image);
            }
        } catch (\Exception $e) {
        }
        $imageName = Helpers::upload($dir, $format, $image);
        return $imageName;
    }

    public static function check_and_delete(string $dir, $old_image)
    {

        try {
            if (Storage::disk('public')->exists($dir . $old_image)) {
                Storage::disk('public')->delete($dir . $old_image);
            }
            if (Storage::disk('s3')->exists($dir . $old_image)) {
                Storage::disk('s3')->delete($dir . $old_image);
            }
        } catch (\Exception $e) {
        }

        return true;
    }

    public static function format_coordiantes($coordinates)
    {
        $data = [];
        foreach ($coordinates as $coord) {
            $data[] = (object)['lat' => $coord[1], 'lng' => $coord[0]];
        }
        return $data;
    }

    public static function module_permission_check($mod_name)
    {
        if (!auth('admin')->user()->role) {
            return false;
        }

        if ($mod_name == 'zone' && auth('admin')->user()->zone_id) {
            return false;
        }

        $permission = auth('admin')->user()->role->modules;
        if (isset($permission) && in_array($mod_name, (array)json_decode($permission)) == true) {
            return true;
        }

        if (auth('admin')->user()->role_id == 1) {
            return true;
        }
        return false;
    }

    public static function employee_module_permission_check($mod_name)
    {
        if (auth('vendor')->check()) {
            if ($mod_name == 'reviews') {
                return auth('vendor')->user()->stores[0]->reviews_section;
            } else if ($mod_name == 'deliveryman') {
                return auth('vendor')->user()->stores[0]->self_delivery_system;
            } else if ($mod_name == 'pos') {
                return auth('vendor')->user()->stores[0]->pos_system;
            } else if ($mod_name == 'addon') {
                return config('module.' . auth('vendor')->user()->stores[0]->module->module_type)['add_on'];
            }
            return true;
        } else if (auth('vendor_employee')->check()) {
            $permission = auth('vendor_employee')->user()->role->modules;
            if (isset($permission) && in_array($mod_name, (array)json_decode($permission)) == true) {
                if ($mod_name == 'reviews') {
                    return auth('vendor_employee')->user()->store->reviews_section;
                } else if ($mod_name == 'deliveryman') {
                    return auth('vendor_employee')->user()->store->self_delivery_system;
                } else if ($mod_name == 'pos') {
                    return auth('vendor_employee')->user()->store->pos_system;
                } else if ($mod_name == 'addon') {
                    return config('module.' . auth('vendor_employee')->user()->store->module->module_type)['add_on'];
                }
                return true;
            }
        }

        return false;
    }
    public static function calculate_addon_price($addons, $add_on_qtys)
    {
        $add_ons_cost = 0;
        $data = [];
        if ($addons) {
            foreach ($addons as $key2 => $addon) {
                if ($add_on_qtys == null) {
                    $add_on_qty = 1;
                } else {
                    $add_on_qty = $add_on_qtys[$key2];
                }
                $data[] = ['id' => $addon->id, 'name' => $addon->name, 'price' => $addon->price, 'quantity' => $add_on_qty];
                $add_ons_cost += $addon['price'] * $add_on_qty;
            }
            return ['addons' => $data, 'total_add_on_price' => $add_ons_cost];
        }
        return null;
    }

    public static function get_settings($name)
    {
        $config = null;
        $data = BusinessSetting::where(['key' => $name])->first();
        if (isset($data)) {
            $config = json_decode($data['value'], true);
            if (is_null($config)) {
                $config = $data['value'];
            }
        }
        return $config;
    }

    public static function setEnvironmentValue($envKey, $envValue)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        $oldValue = env($envKey);
        if (strpos($str, $envKey) !== false) {
            $str = str_replace("{$envKey}={$oldValue}", "{$envKey}={$envValue}", $str);
        } else {
            $str .= "{$envKey}={$envValue}\n";
        }
        $fp = fopen($envFile, 'w');
        fwrite($fp, $str);
        fclose($fp);
        return $envValue;
    }

    public static function requestSender()
    {
        $class = new LaravelchkController();
        $response = $class->actch();
        return json_decode($response->getContent(), true);
    }

    public static function insert_business_settings_key($key, $value = null)
    {
        $data =  BusinessSetting::where('key', $key)->first();
        if (!$data) {
            DB::table('business_settings')->updateOrInsert(['key' => $key], [
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return true;
    }

    public static function insert_data_settings_key($key,$type, $value = null)
    {
        $data =  DataSetting::where('key', $key)->where('type', $type)->first();
        if (!$data) {
            DataSetting::updateOrCreate(['key' => $key,'type' => $type ], [
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return true;
    }

    public static function get_language_name($key)
    {
        $languages = array(
            "af" => "Afrikaans",
            "sq" => "Albanian - shqip",
            "am" => "Amharic - አማርኛ",
            "ar" => "Arabic - العربية",
            "an" => "Aragonese - aragonés",
            "hy" => "Armenian - հայերեն",
            "ast" => "Asturian - asturianu",
            "az" => "Azerbaijani - azərbaycan dili",
            "eu" => "Basque - euskara",
            "be" => "Belarusian - беларуская",
            "bn" => "Bengali - বাংলা",
            "bs" => "Bosnian - bosanski",
            "br" => "Breton - brezhoneg",
            "bg" => "Bulgarian - български",
            "ca" => "Catalan - català",
            "ckb" => "Central Kurdish - کوردی (دەستنوسی عەرەبی)",
            "zh" => "Chinese - 中文",
            "zh-HK" => "Chinese (Hong Kong) - 中文（香港）",
            "zh-CN" => "Chinese (Simplified) - 中文（简体）",
            "zh-TW" => "Chinese (Traditional) - 中文（繁體）",
            "co" => "Corsican",
            "hr" => "Croatian - hrvatski",
            "cs" => "Czech - čeština",
            "da" => "Danish - dansk",
            "nl" => "Dutch - Nederlands",
            "en" => "English",
            "en-AU" => "English (Australia)",
            "en-CA" => "English (Canada)",
            "en-IN" => "English (India)",
            "en-NZ" => "English (New Zealand)",
            "en-ZA" => "English (South Africa)",
            "en-GB" => "English (United Kingdom)",
            "en-US" => "English (United States)",
            "eo" => "Esperanto - esperanto",
            "et" => "Estonian - eesti",
            "fo" => "Faroese - føroyskt",
            "fil" => "Filipino",
            "fi" => "Finnish - suomi",
            "fr" => "French - français",
            "fr-CA" => "French (Canada) - français (Canada)",
            "fr-FR" => "French (France) - français (France)",
            "fr-CH" => "French (Switzerland) - français (Suisse)",
            "gl" => "Galician - galego",
            "ka" => "Georgian - ქართული",
            "de" => "German - Deutsch",
            "de-AT" => "German (Austria) - Deutsch (Österreich)",
            "de-DE" => "German (Germany) - Deutsch (Deutschland)",
            "de-LI" => "German (Liechtenstein) - Deutsch (Liechtenstein)",
            "de-CH" => "German (Switzerland) - Deutsch (Schweiz)",
            "el" => "Greek - Ελληνικά",
            "gn" => "Guarani",
            "gu" => "Gujarati - ગુજરાતી",
            "ha" => "Hausa",
            "haw" => "Hawaiian - ʻŌlelo Hawaiʻi",
            "he" => "Hebrew - עברית",
            "hi" => "Hindi - हिन्दी",
            "hu" => "Hungarian - magyar",
            "is" => "Icelandic - íslenska",
            "id" => "Indonesian - Indonesia",
            "ia" => "Interlingua",
            "ga" => "Irish - Gaeilge",
            "it" => "Italian - italiano",
            "it-IT" => "Italian (Italy) - italiano (Italia)",
            "it-CH" => "Italian (Switzerland) - italiano (Svizzera)",
            "ja" => "Japanese - 日本語",
            "kn" => "Kannada - ಕನ್ನಡ",
            "kk" => "Kazakh - қазақ тілі",
            "km" => "Khmer - ខ្មែរ",
            "ko" => "Korean - 한국어",
            "ku" => "Kurdish - Kurdî",
            "ky" => "Kyrgyz - кыргызча",
            "lo" => "Lao - ລາວ",
            "la" => "Latin",
            "lv" => "Latvian - latviešu",
            "ln" => "Lingala - lingála",
            "lt" => "Lithuanian - lietuvių",
            "mk" => "Macedonian - македонски",
            "ms" => "Malay - Bahasa Melayu",
            "ml" => "Malayalam - മലയാളം",
            "mt" => "Maltese - Malti",
            "mr" => "Marathi - मराठी",
            "mn" => "Mongolian - монгол",
            "ne" => "Nepali - नेपाली",
            "no" => "Norwegian - norsk",
            "nb" => "Norwegian Bokmål - norsk bokmål",
            "nn" => "Norwegian Nynorsk - nynorsk",
            "oc" => "Occitan",
            "or" => "Oriya - ଓଡ଼ିଆ",
            "om" => "Oromo - Oromoo",
            "ps" => "Pashto - پښتو",
            "fa" => "Persian - فارسی",
            "pl" => "Polish - polski",
            "pt" => "Portuguese - português",
            "pt-BR" => "Portuguese (Brazil) - português (Brasil)",
            "pt-PT" => "Portuguese (Portugal) - português (Portugal)",
            "pa" => "Punjabi - ਪੰਜਾਬੀ",
            "qu" => "Quechua",
            "ro" => "Romanian - română",
            "mo" => "Romanian (Moldova) - română (Moldova)",
            "rm" => "Romansh - rumantsch",
            "ru" => "Russian - русский",
            "gd" => "Scottish Gaelic",
            "sr" => "Serbian - српски",
            "sh" => "Serbo-Croatian - Srpskohrvatski",
            "sn" => "Shona - chiShona",
            "sd" => "Sindhi",
            "si" => "Sinhala - සිංහල",
            "sk" => "Slovak - slovenčina",
            "sl" => "Slovenian - slovenščina",
            "so" => "Somali - Soomaali",
            "st" => "Southern Sotho",
            "es" => "Spanish - español",
            "es-AR" => "Spanish (Argentina) - español (Argentina)",
            "es-419" => "Spanish (Latin America) - español (Latinoamérica)",
            "es-MX" => "Spanish (Mexico) - español (México)",
            "es-ES" => "Spanish (Spain) - español (España)",
            "es-US" => "Spanish (United States) - español (Estados Unidos)",
            "su" => "Sundanese",
            "sw" => "Swahili - Kiswahili",
            "sv" => "Swedish - svenska",
            "tg" => "Tajik - тоҷикӣ",
            "ta" => "Tamil - தமிழ்",
            "tt" => "Tatar",
            "te" => "Telugu - తెలుగు",
            "th" => "Thai - ไทย",
            "ti" => "Tigrinya - ትግርኛ",
            "to" => "Tongan - lea fakatonga",
            "tr" => "Turkish - Türkçe",
            "tk" => "Turkmen",
            "tw" => "Twi",
            "uk" => "Ukrainian - українська",
            "ur" => "Urdu - اردو",
            "ug" => "Uyghur",
            "uz" => "Uzbek - o‘zbek",
            "vi" => "Vietnamese - Tiếng Việt",
            "wa" => "Walloon - wa",
            "cy" => "Welsh - Cymraeg",
            "fy" => "Western Frisian",
            "xh" => "Xhosa",
            "yi" => "Yiddish",
            "yo" => "Yoruba - Èdè Yorùbá",
            "zu" => "Zulu - isiZulu",
        );
        return array_key_exists($key, $languages) ? $languages[$key] : $key;
    }

    public static function get_view_keys()
    {
        $keys = BusinessSetting::whereIn('key', ['toggle_veg_non_veg', 'toggle_dm_registration', 'toggle_store_registration'])->get();
        $data = [];
        foreach ($keys as $key) {
            $data[$key->key] = (bool)$key->value;
        }
        return $data;
    }

    public static function default_lang()
    {
        if (strpos(url()->current(), '/api')) {
            $lang = App::getLocale();
        } elseif ( request()->is('admin*') && auth('admin')?->check() && session()->has('local')) {
            $lang = session('local');
        }elseif (request()->is('store-panel/*') && (auth('vendor_employee')?->check() || auth('vendor')?->check()) && session()->has('vendor_local')) {
            $lang = session('vendor_local');
        }
        elseif (session()->has('landing_local')) {
            $lang = session('landing_local');
        }
        elseif (session()->has('local')) {
            $lang = session('local');
        } else {
            $data = Helpers::get_business_settings('language');
            $code = 'en';
            $direction = 'ltr';
            foreach ($data as $ln) {
                if (is_array($ln) && array_key_exists('default', $ln) && $ln['default']) {
                    $code = $ln['code'];
                    if (array_key_exists('direction', $ln)) {
                        $direction = $ln['direction'];
                    }
                }
            }
            session()->put('local', $code);
            $lang = $code;
        }
        return $lang;
    }

    public static function system_default_language()
    {
        $languages = json_decode(\App\Models\BusinessSetting::where('key', 'system_language')->first()?->value);
        $lang = 'en';

        foreach ($languages as $key => $language) {
            if($language->default){
                $lang = $language->code;
            }
        }
        return $lang;
    }
    public static function system_default_direction()
    {
        $languages = json_decode(\App\Models\BusinessSetting::where('key', 'system_language')->first()?->value);
        $lang = 'en';

        foreach ($languages as $key => $language) {
            if($language->default){
                $lang = $language->direction;
            }
        }
        return $lang;
    }

    //Mail Config Check
    public static function remove_invalid_charcaters($str)
    {
        return str_ireplace(['\'', '"', ';', '<', '>'], ' ', $str);
    }

    //Generate referer code

    public static function generate_referer_code() {
        $ref_code = strtoupper(Str::random(10));

        if (self::referer_code_exists($ref_code)) {
            return self::generate_referer_code();
        }

        return $ref_code;
    }

    public static function referer_code_exists($ref_code) {
        return User::where('ref_code', '=', $ref_code)->exists();
    }


    public static function generate_reset_password_code() {
        $code = strtoupper(Str::random(15));

        if (self::reset_password_code_exists($code)) {
            return self::generate_reset_password_code();
        }

        return $code;
    }

    public static function reset_password_code_exists($code) {
        return DB::table('password_resets')->where('token', '=', $code)->exists();
    }

    public static function number_format_short( $n ) {
        if ($n < 900) {
            // 0 - 900
            $n = $n;
            $suffix = '';
        } else if ($n < 900000) {
            // 0.9k-850k
            $n = $n / 1000;
            $suffix = 'K';
        } else if ($n < 900000000) {
            // 0.9m-850m
            $n = $n / 1000000;
            $suffix = 'M';
        } else if ($n < 900000000000) {
            // 0.9b-850b
            $n = $n / 1000000000;
            $suffix = 'B';
        } else {
            // 0.9t+
            $n = $n / 1000000000000;
            $suffix = 'T';
        }

        if(!session()->has('currency_symbol_position')){
            $currency_symbol_position = BusinessSetting::where(['key' => 'currency_symbol_position'])->first()->value;
            session()->put('currency_symbol_position',$currency_symbol_position);
        }
        $currency_symbol_position = session()->get('currency_symbol_position');

        return $currency_symbol_position == 'right' ? number_format($n, config('round_up_to_digit')).$suffix . ' ' . self::currency_symbol() : self::currency_symbol() . ' ' . number_format($n, config('round_up_to_digit')).$suffix;
    }
    // public static function export_attributes($collection){
    //     $data = [];
    //     foreach($collection as $key=>$item){
    //         $data[] = [
    //             'SL'=>$key+1,
    //              translate('messages.id') => $item['id'],
    //              translate('messages.name') => $item['name'],
    //         ];
    //     }
    //     return $data;
    // }


    public static function export_store_withdraw($collection){
        $data = [];
        $status = ['pending','approved','denied'];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.amount') => $item->amount,
                translate('messages.store') => isset($item->vendor) ? $item->vendor->stores[0]->name : '',
                translate('messages.request_time') => date('Y-m-d '.config('timeformat'),strtotime($item->created_at)),
                translate('messages.status') => isset($status[$item->approved])?translate("messages.".$status[$item->approved]):"",
            ];
        }
        return $data;
    }

    public static function export_account_transaction($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.collect_from') => $item->store ? $item->store?->name : ($item->deliveryman ? $item->deliveryman->f_name . ' ' . $item->deliveryman->l_name : translate('messages.not_found')),
                translate('messages.type') => $item->from_type,
                translate('messages.received_at') => $item->created_at->format('Y-m-d '.config('timeformat')),
                translate('messages.amount') => $item->amount,
                translate('messages.reference') => $item->ref,
            ];
        }
        return $data;
    }

    public static function export_dm_earning($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.name') => isset($item->delivery_man) ? $item->delivery_man->f_name.' '.$item->delivery_man->l_name : translate('messages.not_found'),
                translate('messages.received_at') => $item->created_at->format('Y-m-d '.config('timeformat')),
                translate('messages.amount') => $item->amount,
                translate('messages.method') => $item->method,
                translate('messages.reference') => $item->ref,
            ];
        }
        return $data;
    }

    public static function export_items($foods,$module_type){
        $storage = [];
        foreach($foods as $item)
        {
            $category_id = 0;
            $sub_category_id = 0;
            foreach(json_decode($item->category_ids, true) as $key=>$category)
            {
                if($key==0)
                {
                    $category_id = $category['id'];
                }
                else if($key==1)
                {
                    $sub_category_id = $category['id'];
                }
            }
            $storage[] = [
                'Id'=>$item->id,
                'Name'=>$item->name,
                'Description'=>$item->description,
                'Image'=>$item->image,
                'Images'=>$item->images,
                'CategoryId'=>$category_id,
                'SubCategoryId'=>$sub_category_id,
                'UnitId'=>$item->unit_id,
                'Stock'=>$item->stock,
                'Price'=>$item->price,
                'Discount'=>$item->discount,
                'DiscountType'=>$item->discount_type,
                'AvailableTimeStarts'=>$item->available_time_starts,
                'AvailableTimeEnds'=>$item->available_time_ends,
                'Variations'=>$module_type == 'food'?$item->food_variations:$item->variations,
                'AddOns'=>str_replace(['"','[',']'],'',$item->add_ons),
                'Attributes'=>str_replace(['"','[',']'],'',$item->attributes),
                'StoreId'=>$item->store_id,
                'ModuleId'=>$item->module_id,
                'Status'=>$item->status == 1 ? 'active' : 'inactive',
                'Veg'=>$item->veg == 1 ? 'yes' : 'no',
                'Recommended'=>$item->recommended == 1 ? 'yes' : 'no',
            ];
        }

        return $storage;
    }

    public static function export_store_item($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.id') => $item['id'],
                translate('messages.name') => $item['name'],
                translate('messages.type') => $item->category?$item->category->name:'',
                translate('messages.price') => $item['price'],
                translate('messages.status') => $item['status'],
            ];
        }
        return $data;
    }

    public static function export_stores($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'id'=>$item->id,
                'ownerId'=>$item->vendor->id,
                'ownerFirstName'=>$item->vendor->f_name,
                'ownerLastName'=>$item->vendor->l_name,
                'storeName'=>$item->name,
                'phone'=>$item->vendor->phone,
                'email'=>$item->vendor->email,
                'logo'=>$item->logo,
                'CoverPhoto'=>$item->cover_photo,
                'latitude'=>$item->latitude,
                'longitude'=>$item->longitude,
                'Address'=>$item->address ?? null,
                'zone_id'=>$item->zone_id,
                'module_id'=>$item->module_id,
                'MinimumOrderAmount'=>$item->minimum_order,
                'Comission'=>$item->comission ?? 0,
                'Tax'=>$item->tax ?? 0,
                'DeliveryTime'=>$item->delivery_time ?? '20-30',
                'MinimumDeliveryFee'=>$item->minimum_shipping_charge ?? 0,
                'PerKmDeliveryFee'=>$item->per_km_shipping_charge ?? 0,
                'MaximumDeliveryFee'=>$item->maximum_shipping_charge ?? 0,
                'ScheduleOrder'=> $item->schedule_order == 1 ? 'yes' : 'no',
                'Status'=> $item->status == 1 ? 'active' : 'inactive',
                'SelfDeliverySystem'=> $item->self_delivery_system == 1 ? 'active' : 'inactive',
                'Veg'=> $item->veg == 1 ? 'yes' : 'no',
                'NonVeg'=> $item->non_veg == 1 ? 'yes' : 'no',
                'FreeDelivery'=> $item->free_delivery == 1 ? 'yes' : 'no',
                'TakeAway'=> $item->take_away == 1 ? 'yes' : 'no',
                'Delivery'=> $item->delivery == 1 ? 'yes' : 'no',
                'ReviewsSection'=> $item->reviews_section == 1 ? 'active' : 'inactive',
                'PosSystem'=> $item->pos_system == 1 ? 'active' : 'inactive',
                'storeOpen'=> $item->active == 1 ? 'yes' : 'no',
                'FeaturedStore'=> $item->featured == 1 ? 'yes' : 'no',
            ];
        }
        return $data;
    }

    public static function export_units($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.id') => $item['id'],
                translate('messages.unit') => $item['unit'],
            ];
        }
        return $data;
    }

    public static function export_customers($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.id') => $item['id'],
                translate('messages.name') => $item->f_name.' '.$item->l_name,
                translate('messages.phone') => $item['phone'],
                translate('messages.email') => $item['email'],
                translate('messages.total_order') => $item['order_count'],
                translate('messages.status') => $item['status'],
            ];
        }
        return $data;
    }

    public static function export_day_wise_report($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.order_id') => $item['order_id'],
                translate('messages.store')=>$item->order->store?$item->order->store->name:translate('messages.invalid'),
                translate('messages.customer_name')=>$item->order->customer?$item->order->customer['f_name'].' '.$item->order->customer['l_name']:translate('messages.invalid_customer_data'),
                translate('total_item_amount')=>\App\CentralLogics\Helpers::format_currency($item->order['order_amount'] - $item->order['dm_tips']-$item->order['delivery_charge'] - $item['tax'] + $item->order['coupon_discount_amount'] + $item->order['store_discount_amount']),
                translate('item_discount')=>\App\CentralLogics\Helpers::format_currency($item->order->details->sum('discount_on_item')),
                translate('coupon_discount')=>\App\CentralLogics\Helpers::format_currency($item->order['coupon_discount_amount']),
                translate('discounted_amount')=>\App\CentralLogics\Helpers::format_currency($item->order['coupon_discount_amount'] + $item->order['store_discount_amount']),
                translate('messages.tax')=>\App\CentralLogics\Helpers::format_currency($item->order['total_tax_amount']),
                translate('messages.delivery_charge')=>\App\CentralLogics\Helpers::format_currency($item['delivery_charge']),
                translate('messages.total_order_amount') => \App\CentralLogics\Helpers::format_currency($item['order_amount']),
                translate('messages.admin_discount') => \App\CentralLogics\Helpers::format_currency($item['admin_expense']),
                translate('messages.store_discount') => \App\CentralLogics\Helpers::format_currency($item->order['store_discount_amount']),
                translate('messages.admin_commission') => \App\CentralLogics\Helpers::format_currency(($item->admin_commission + $item->admin_expense) - $item->delivery_fee_comission),
                translate('Comission on delivery fee') => \App\CentralLogics\Helpers::format_currency($item['delivery_fee_comission']),
                translate('admin_net_income') => \App\CentralLogics\Helpers::format_currency($item['admin_commission']),
                translate('store_net_income') => \App\CentralLogics\Helpers::format_currency($item['store_amount'] - $item['tax']),
                translate('messages.amount_received_by') => $item['received_by'],
                translate('messages.payment_method')=>translate(str_replace('_', ' ', $item->order['payment_method'])),
                translate('messages.payment_status') => $item->status ? translate("messages.refunded") : translate("messages.completed"),
            ];
        }
        return $data;
    }


    public static function export_expense_wise_report($collection){
        $data = [];
        foreach($collection as $key=>$item){
            if(isset($item->order->customer)){
                $customer_name= $item->order->customer->f_name.' '.$item->order->customer->l_name;
            }
            $data[] = [
                'SL'=>$key+1,
                translate('messages.order_id') => $item['order_id'],
                translate('messages.expense_date') =>  $item['created_at'],
                // translate('messages.expense_date') =>  $item->created_at->format('Y-m-d '.config('timeformat')),
                translate('messages.type') => str::title( str_replace('_', ' ',  $item['type'])),
                translate('messages.customer_name') => $customer_name,
                translate('messages.amount') => $item['amount'],
            ];
        }
        return $data;
    }

    public static function export_item_wise_report($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.id') => $item['id'],
                translate('messages.name') => $item['name'],
                translate('messages.module') =>$item->module ? $item->module->module_name : '',
                translate('messages.store') => $item->store ? $item->store?->name : '',
                translate('messages.order') => $item->orders_count,
                translate('messages.price') => \App\CentralLogics\Helpers::format_currency($item->price),
                translate('messages.total_amount_sold') => \App\CentralLogics\Helpers::format_currency($item->orders_sum_price),
                translate('messages.total_discount_given') => \App\CentralLogics\Helpers::format_currency($item->orders_sum_discount_on_item),
                translate('messages.average_sale_value') => $item->orders_count>0? \App\CentralLogics\Helpers::format_currency(($item->orders_sum_price-$item->orders_sum_discount_on_item)/$item->orders_count):0 ,
                translate('messages.average_ratings') => round($item->avg_rating,1),
            ];
        }
        return $data;
    }

    public static function export_stock_wise_report($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.id') => $item['id'],
                translate('messages.name') => $item['name'],
                translate('messages.store') => $item->store?$item->store?->name : '',
                translate('messages.zone') => ($item->store && $item->store?->zone) ? $item->store?->zone->name:'',
                translate('messages.stock') => $item['stock'],
            ];
        }
        return $data;
    }

    public static function export_delivery_men($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.id') => $item['id'],
                translate('messages.name') => $item->f_name.' '.$item->l_name,
                translate('messages.phone') => $item['phone'],
                translate('messages.zone') => $item->zone?$item->zone->name:'',
                translate('messages.total_order') => $item['order_count'],
                translate('messages.currently_assigned_orders') => (int) $item['current_orders'],
                translate('messages.status') => $item['status'],
            ];
        }
        return $data;
    }

    public static function hex_to_rbg($color){
        list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
        $output = "$r, $g, $b";
        return $output;
    }

    public static function expenseCreate($amount,$type,$datetime,$created_by,$order_id=null,$store_id=null,$description='',$delivery_man_id=null,$user_id=null)
    {
        $expense = new Expense();
        $expense->amount = $amount;
        $expense->type = $type;
        $expense->order_id = $order_id;
        $expense->created_by = $created_by;
        $expense->store_id = $store_id;
        $expense->delivery_man_id = $delivery_man_id;
        $expense->user_id = $user_id;
        $expense->description = $description;
        $expense->created_at = now();
        $expense->updated_at = now();
        return $expense->save();
    }

    public static function get_varient(array $product_variations, $variations)
    {
        $result = [];
        $variation_price = 0;

        foreach($variations as $k=> $variation){
            foreach($product_variations as  $product_variation){
                if( isset($variation['values']) && isset($product_variation['values']) && $product_variation['name'] == $variation['name']  ){
                    $result[$k] = $product_variation;
                    $result[$k]['values'] = [];
                    foreach($product_variation['values'] as $key=> $option){
                        if(in_array($option['label'], $variation['values']['label'])){
                            $result[$k]['values'][] = $option;
                            $variation_price += $option['optionPrice'];
                        }
                    }
                }
            }
        }

        return ['price'=>$variation_price,'variations'=>$result];
    }

    public static function food_variation_price($product, $variations)
    {
        // $match = json_decode($variations, true)[0];
        $match = $variations;
        $result = 0;
        // foreach (json_decode($product['variations'], true) as $property => $value) {
        //     if ($value['type'] == $match['type']) {
        //         $result = $value['price'];
        //     }
        // }
        foreach($product as $product_variation){
            foreach($product_variation['values'] as $option){
                foreach($match as $variation){
                    if($product_variation['name'] == $variation['name'] && isset($variation['values']) && in_array($option['label'], $variation['values']['label'])){
                        $result += $option['optionPrice'];
                    }
                }
            }
        }

        return $result;
    }

    public static function gen_mpdf($view, $file_prefix, $file_postfix)
    {
        $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/../../storage/tmp','default_font' => 'Inter', 'mode' => 'utf-8', 'format' => [190, 250]]);
        /* $mpdf->AddPage('XL', '', '', '', '', 10, 10, 10, '10', '270', '');*/
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $mpdf_view = $view;
        $mpdf_view = $mpdf_view->render();
        $mpdf->WriteHTML($mpdf_view);
        $mpdf->Output($file_prefix . $file_postfix . '.pdf', 'D');
    }

    public static function auto_translator($q, $sl, $tl)
    {
        $res = file_get_contents("https://translate.googleapis.com/translate_a/single?client=gtx&ie=UTF-8&oe=UTF-8&dt=bd&dt=ex&dt=ld&dt=md&dt=qca&dt=rw&dt=rm&dt=ss&dt=t&dt=at&sl=" . $sl . "&tl=" . $tl . "&hl=hl&q=" . urlencode($q), $_SERVER['DOCUMENT_ROOT'] . "/transes.html");
        $res = json_decode($res);
        return str_replace('_',' ',$res[0][0][0]);
    }

    public static function getLanguageCode(string $country_code): string
    {
        $locales = array(
            'en-English(default)',
            'af-Afrikaans',
            'sq-Albanian - shqip',
            'am-Amharic - አማርኛ',
            'ar-Arabic - العربية',
            'an-Aragonese - aragonés',
            'hy-Armenian - հայերեն',
            'ast-Asturian - asturianu',
            'az-Azerbaijani - azərbaycan dili',
            'eu-Basque - euskara',
            'be-Belarusian - беларуская',
            'bn-Bengali - বাংলা',
            'bs-Bosnian - bosanski',
            'br-Breton - brezhoneg',
            'bg-Bulgarian - български',
            'ca-Catalan - català',
            'ckb-Central Kurdish - کوردی (دەستنوسی عەرەبی)',
            'zh-Chinese - 中文',
            'zh-HK-Chinese (Hong Kong) - 中文（香港）',
            'zh-CN-Chinese (Simplified) - 中文（简体）',
            'zh-TW-Chinese (Traditional) - 中文（繁體）',
            'co-Corsican',
            'hr-Croatian - hrvatski',
            'cs-Czech - čeština',
            'da-Danish - dansk',
            'nl-Dutch - Nederlands',
            'en-AU-English (Australia)',
            'en-CA-English (Canada)',
            'en-IN-English (India)',
            'en-NZ-English (New Zealand)',
            'en-ZA-English (South Africa)',
            'en-GB-English (United Kingdom)',
            'en-US-English (United States)',
            'eo-Esperanto - esperanto',
            'et-Estonian - eesti',
            'fo-Faroese - føroyskt',
            'fil-Filipino',
            'fi-Finnish - suomi',
            'fr-French - français',
            'fr-CA-French (Canada) - français (Canada)',
            'fr-FR-French (France) - français (France)',
            'fr-CH-French (Switzerland) - français (Suisse)',
            'gl-Galician - galego',
            'ka-Georgian - ქართული',
            'de-German - Deutsch',
            'de-AT-German (Austria) - Deutsch (Österreich)',
            'de-DE-German (Germany) - Deutsch (Deutschland)',
            'de-LI-German (Liechtenstein) - Deutsch (Liechtenstein)
            ',
            'de-CH-German (Switzerland) - Deutsch (Schweiz)',
            'el-Greek - Ελληνικά',
            'gn-Guarani',
            'gu-Gujarati - ગુજરાતી',
            'ha-Hausa',
            'haw-Hawaiian - ʻŌlelo Hawaiʻi',
            'he-Hebrew - עברית',
            'hi-Hindi - हिन्दी',
            'hu-Hungarian - magyar',
            'is-Icelandic - íslenska',
            'id-Indonesian - Indonesia',
            'ia-Interlingua',
            'ga-Irish - Gaeilge',
            'it-Italian - italiano',
            'it-IT-Italian (Italy) - italiano (Italia)',
            'it-CH-Italian (Switzerland) - italiano (Svizzera)',
            'ja-Japanese - 日本語',
            'kn-Kannada - ಕನ್ನಡ',
            'kk-Kazakh - қазақ тілі',
            'km-Khmer - ខ្មែរ',
            'ko-Korean - 한국어',
            'ku-Kurdish - Kurdî',
            'ky-Kyrgyz - кыргызча',
            'lo-Lao - ລາວ',
            'la-Latin',
            'lv-Latvian - latviešu',
            'ln-Lingala - lingála',
            'lt-Lithuanian - lietuvių',
            'mk-Macedonian - македонски',
            'ms-Malay - Bahasa Melayu',
            'ml-Malayalam - മലയാളം',
            'mt-Maltese - Malti',
            'mr-Marathi - मराठी',
            'mn-Mongolian - монгол',
            'ne-Nepali - नेपाली',
            'no-Norwegian - norsk',
            'nb-Norwegian Bokmål - norsk bokmål',
            'nn-Norwegian Nynorsk - nynorsk',
            'oc-Occitan',
            'or-Oriya - ଓଡ଼ିଆ',
            'om-Oromo - Oromoo',
            'ps-Pashto - پښتو',
            'fa-Persian - فارسی',
            'pl-Polish - polski',
            'pt-Portuguese - português',
            'pt-BR-Portuguese (Brazil) - português (Brasil)',
            'pt-PT-Portuguese (Portugal) - português (Portugal)',
            'pa-Punjabi - ਪੰਜਾਬੀ',
            'qu-Quechua',
            'ro-Romanian - română',
            'mo-Romanian (Moldova) - română (Moldova)',
            'rm-Romansh - rumantsch',
            'ru-Russian - русский',
            'gd-Scottish Gaelic',
            'sr-Serbian - српски',
            'sh-Serbo-Croatian - Srpskohrvatski',
            'sn-Shona - chiShona',
            'sd-Sindhi',
            'si-Sinhala - සිංහල',
            'sk-Slovak - slovenčina',
            'sl-Slovenian - slovenščina',
            'so-Somali - Soomaali',
            'st-Southern Sotho',
            'es-Spanish - español',
            'es-AR-Spanish (Argentina) - español (Argentina)',
            'es-419-Spanish (Latin America) - español (Latinoamérica)
            ',
            'es-MX-Spanish (Mexico) - español (México)',
            'es-ES-Spanish (Spain) - español (España)',
            'es-US-Spanish (United States) - español (Estados Unidos)
            ',
            'su-Sundanese',
            'sw-Swahili - Kiswahili',
            'sv-Swedish - svenska',
            'tg-Tajik - тоҷикӣ',
            'ta-Tamil - தமிழ்',
            'tt-Tatar',
            'te-Telugu - తెలుగు',
            'th-Thai - ไทย',
            'ti-Tigrinya - ትግርኛ',
            'to-Tongan - lea fakatonga',
            'tr-Turkish - Türkçe',
            'tk-Turkmen',
            'tw-Twi',
            'uk-Ukrainian - українська',
            'ur-Urdu - اردو',
            'ug-Uyghur',
            'uz-Uzbek - o‘zbek',
            'vi-Vietnamese - Tiếng Việt',
            'wa-Walloon - wa',
            'cy-Welsh - Cymraeg',
            'fy-Western Frisian',
            'xh-Xhosa',
            'yi-Yiddish',
            'yo-Yoruba - Èdè Yorùbá',
            'zu-Zulu - isiZulu',
        );

        foreach ($locales as $locale) {
            $locale_region = explode('-',$locale);
            if ($country_code == $locale_region[0]) {
                return $locale_region[0];
            }
        }

        return "en";
    }


    public static function pagination_limit()
    {
        $pagination_limit = BusinessSetting::where('key', 'pagination_limit')->first();
        if ($pagination_limit != null) {
            return $pagination_limit->value;
        } else {
            return 25;
        }
    }

    public static function language_load()
    {
        if (\session()->has('language_settings')) {
            $language = \session('language_settings');
        } else {
            $language = BusinessSetting::where('key', 'system_language')->first();
            \session()->put('language_settings', $language);
        }
        return $language;
    }

    public static function vendor_language_load()
    {
        if (\session()->has('vendor_language_settings')) {
            $language = \session('vendor_language_settings');
        } else {
            $language = BusinessSetting::where('key', 'system_language')->first();
            \session()->put('vendor_language_settings', $language);
        }
        return $language;
    }

    public static function landing_language_load()
    {
        if (\session()->has('landing_language_settings')) {
            $language = \session('landing_language_settings');
        } else {
            $language = BusinessSetting::where('key', 'system_language')->first();
            \session()->put('landing_language_settings', $language);
        }
        return $language;
    }


    public static function product_tax($price , $tax, $is_include=false){
        $price_tax = ($price * $tax) / (100 + ($is_include?$tax:0)) ;
        return $price_tax;
    }

    public static function apple_client_secret(){
        // Set up the necessary variables
        $keyId = 'U7KA7F82UM';
        $teamId = '7WSYLQ8Y87';
        $clientId = 'com.sixamtech.sixamMartApp';
        $privateKey = file_get_contents('AuthKey_U7KA7F82UM.p8'); // Should be a string containing the contents of the private key file.

        // Create the JWT header
        $header = [
            'alg' => 'ES256',
            'kid' => $keyId,
        ];

        // Create the JWT payload
        $payload = [
            'iss' => $teamId,
            'iat' => time(),
            'exp' => time() + 86400 * 180, // 180 days in seconds
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ];

        // Encode the JWT header and payload
        $base64Header = base64_encode(json_encode($header));
        $base64Payload = base64_encode(json_encode($payload));

        // Create the signature using the private key and the SHA-256 algorithm
        $dataToSign = $base64Header . '.' . $base64Payload;
        $signature = '';
        openssl_sign($dataToSign, $signature, $privateKey, 'sha256');

        // Encode the signature
        $base64Signature = base64_encode($signature);

        // Create the Apple Client Secret key
        $clientSecret = $base64Header . '.' . $base64Payload . '.' . $base64Signature;

        // Output the key
        return $clientSecret;
    }

    public static function error_formater($key, $mesage, $errors = [])
    {
        $errors[] = ['code' => $key, 'message' => $mesage];

        return $errors;
    }

    public static function Export_generator($datas) {
        foreach ($datas as $data) {
            yield $data;
        }
        return true;
    }

    public static function export_addons($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'Id'=>$item->id,
                'Name'=>$item->name,
                'Price'=>$item->price,
                'StoreId'=>$item->store_id,
                'Status'=>$item->status == 1 ? 'active' : 'inactive'
            ];
        }
        return $data;
    }
    public static function export_categories($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'Id'=>$item->id,
                'Name'=>$item->name,
                'Image'=>$item->image,
                'ParentId'=>$item->parent_id,
                'Position'=>$item->position,
                'Priority'=>$item->priority,
                'Status'=>$item->status == 1 ? 'active' : 'inactive',
            ];
        }
        return $data;
    }

    public static function get_mail_status($name)
    {
        $status = BusinessSetting::where('key', $name)->first()?->value ?? 0;
        return $status;
    }

    public static function text_variable_data_format($value,$user_name=null,$store_name=null,$delivery_man_name=null,$transaction_id=null,$order_id=null,$add_id= null)
    {
        $data = $value;
        if ($value) {
            if($user_name){
                $data =  str_replace("{userName}", $user_name, $data);
            }

            if($store_name){
                $data =  str_replace("{storeName}", $store_name, $data);
            }

            if($delivery_man_name){
                $data =  str_replace("{deliveryManName}", $delivery_man_name, $data);
            }

            if($transaction_id){
                $data =  str_replace("{transactionId}", $transaction_id, $data);
            }

            if($order_id){
                $data =  str_replace("{orderId}", $order_id, $data);
            }
            if($add_id){
                $data =  str_replace("{advertisementId}", $add_id, $data);
            }
        }

        return $data;
    }

    public static function get_login_url($type){
        $data=DataSetting::whereIn('key',['store_employee_login_url','store_login_url','admin_employee_login_url','admin_login_url'
        ])->pluck('key','value')->toArray();

        return array_search($type,$data);
    }

    public static function react_activation_check($react_domain, $react_license_code){
        $scheme = str_contains($react_domain, 'localhost')?'http://':'https://';
        $url = empty(parse_url($react_domain)['scheme']) ? $scheme . ltrim($react_domain, '/') : $react_domain;
        $response = Http::post('https://store.6amtech.com/api/v1/customer/license-check', [
            'domain_name' => str_ireplace('www.', '', parse_url($url, PHP_URL_HOST)),
            'license_code' => $react_license_code
        ]);
        return ($response->successful() && isset($response->json('content')['is_active']) && $response->json('content')['is_active']);
    }

    public static function activation_submit($purchase_key)
    {
        $post = [
            'purchase_key' => $purchase_key
        ];
        $live = 'https://check.6amtech.com';
        $ch = curl_init($live . '/api/v1/software-check');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $response = curl_exec($ch);

        curl_close($ch);
        $response_body = json_decode($response, true);

        try {
            if ($response_body['is_valid'] && $response_body['result']['item']['id'] == env('REACT_APP_KEY')) {
                $previous_active = json_decode(BusinessSetting::where('key', 'app_activation')->first()->value ?? '[]');
                $found = 0;
                foreach ($previous_active as $key => $item) {
                    if ($item->software_id == env('REACT_APP_KEY')) {
                        $found = 1;
                    }
                }
                if (!$found) {
                    $previous_active[] = [
                        'software_id' => env('REACT_APP_KEY'),
                        'is_active' => 1
                    ];
                    DB::table('business_settings')->updateOrInsert(['key' => 'app_activation'], [
                        'value' => json_encode($previous_active)
                    ]);
                }
                return true;
            }

        } catch (\Exception $exception) {
            info($exception->getMessage());

            $previous_active[] = [
                'software_id' => env('REACT_APP_KEY'),
                'is_active' => 1
            ];
            DB::table('business_settings')->updateOrInsert(['key' => 'app_activation'], [
                'value' => json_encode($previous_active)
            ]);

            return true;
        }
        return false;
    }

    public static function react_domain_status_check(){
        $data = self::get_business_settings('react_setup');
        if($data && isset($data['react_domain']) && isset($data['react_license_code'])){
            if(isset($data['react_platform']) && $data['react_platform'] == 'codecanyon'){
                $data['status'] = (int)self::activation_submit($data['react_license_code']);
            }elseif(!self::react_activation_check($data['react_domain'], $data['react_license_code'])){
                $data['status']=0;
            }elseif($data['status'] != 1){
                $data['status']=1;
            }
            DB::table('business_settings')->updateOrInsert(['key' => 'react_setup'], [
                'value' => json_encode($data)
            ]);
        }
    }

    public static function export_order_transaction_report($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.id') => $item['id'],
                translate('messages.vendor_id') => $item['vendor_id'],
                translate('messages.delivery_man_id') => $item['delivery_man_id'],
                translate('messages.order_id') => $item['order_id'],
                translate('messages.order_amount') => $item['order_amount'],
                translate('messages.store_amount') => $item['store_amount']-$item['tax'],
                translate('messages.admin_commission') => $item['admin_commission'],
                translate('messages.received_by') => $item['received_by'],
                translate('messages.status') => $item['status'],
                translate('messages.created_at') => $item['created_at'],
                translate('messages.updated_at') => $item['updated_at'],
                translate('messages.delivery_charge') => $item['delivery_charge'],
                translate('messages.original_delivery_charge') => $item['original_delivery_charge'],
                translate('messages.tax') => $item['tax'],
                translate('messages.zone_id') => $item['zone_id'],
                translate('messages.module_id') => $item['module_id'],
                translate('messages.parcel_catgory_id') => $item['parcel_catgory_id'],
                translate('messages.dm_tips') => $item['dm_tips'],
                translate('messages.delivery_fee_comission') => $item['delivery_fee_comission'],
                translate('messages.admin_expense') => $item['admin_expense'],
                translate('messages.store_expense') => $item['store_expense'],
                translate('messages.discount_amount_by_store') => $item['discount_amount_by_store'],
            ];
        }
        return $data;
    }

    public static function get_zones_name($zones){
        if(is_array($zones)){
            $data = Zone::whereIn('id',$zones)->pluck('name')->toArray();
        }else{
            $data = Zone::where('id',$zones)->pluck('name')->toArray();
        }
        $data = implode(', ', $data);
        return $data;
    }

    public static function get_stores_name($stores){
        if(is_array($stores)){
            $data = Store::whereIn('id',$stores)->pluck('name')->toArray();
        }else{
            $data = Store::where('id',$stores)->pluck('name')->toArray();
        }
        $data = implode(', ', $data);
        return $data;
    }

    public static function get_category_name($id){
        $id=Json_decode($id,true);
        $id=data_get($id,'0.id','NA');
        return Category::where('id',$id)->first()?->name;
    }
    public static function get_sub_category_name($id){
        $id=Json_decode($id,true);
        $id=data_get($id,'1.id','NA');
        return Category::where('id',$id)->first()?->name;
    }
    public static function get_attributes($choice_options){
        try{
            $data=[];
            foreach((array)json_decode($choice_options) as $key => $choice){
                $data[$choice->title] =$choice->options;
            }
            return str_ireplace(['\'', '"', '{','}', '[',']', ';', '<', '>', '?'], ' ',json_encode($data));
        } catch (\Exception $ex) {
            info(["line___{$ex->getLine()}",$ex->getMessage()]);
            return 0;
        }
    }

    public static function get_module_name($id){
        return Module::where('id',$id)->first()?->module_name;
    }

    public static function get_food_variations($variations){
        try{
            $data=[];
            $data2=[];
            foreach((array)json_decode($variations,true) as $key => $choice){
                foreach($choice['values'] as $k => $v){
                    $data2[$k] =  $v['label'];
                    // if(!next($choice['values'] )) {
                    //     $data2[$k] =  $v['label'].";";
                    // }
                }
                $data[$choice['name']] = $data2;
            }
            return str_ireplace(['\'', '"', '{','}', '[',']', '<', '>', '?'], ' ',json_encode($data));
        } catch (\Exception $ex) {
            info(["line___{$ex->getLine()}",$ex->getMessage()]);
            return 0;
        }

    }

    public static function get_customer_name($id){
        $user = User::where('id',$id)->first();

        return $user->f_name.' '.$user->l_name;
    }
    public static function get_addon_data($id){
        try{
            $data=[];
            $addon= AddOn::whereIn('id',json_decode($id, true))->get(['name','price'])->toArray();
            foreach($addon as $key => $value){
                $data[$key]= $value['name'] .' - ' .\App\CentralLogics\Helpers::format_currency($value['price']);
            }
            return str_ireplace(['\'', '"', '{','}', '[',']', '<', '>', '?'], ' ',json_encode($data, JSON_UNESCAPED_UNICODE));
        } catch (\Exception $ex) {
            info(["line___{$ex->getLine()}",$ex->getMessage()]);
            return 0;
        }
    }



    public static function add_or_update_translations($request, $key_data,$name_field ,$model_name, $data_id,$data_value ){
        try{
            $model = 'App\\Models\\'.$model_name;
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->{$name_field}[$index])) {
                    if ($key != 'default') {
                        Translation::updateorcreate(
                            [
                                'translationable_type' =>  $model,
                                'translationable_id' => $data_id,
                                'locale' => $key,
                                'key' => $key_data
                            ],
                            ['value' => $data_value]
                        );
                    }
                } else {
                    if ($request->{$name_field}[$index] && $key != 'default') {
                        Translation::updateorcreate(
                            [
                                'translationable_type' => $model,
                                'translationable_id' => $data_id,
                                'locale' => $key,
                                'key' => $key_data
                            ],
                            ['value' => $request->{$name_field}[$index]]
                        );
                    }
                }
            }
            return true;
        } catch(\Exception $e){
            info(["line___{$e->getLine()}",$e->getMessage()]);
            return false;
        }
    }

    public static function offline_payment_formater($user_data){
        $userInputs = [];

        $user_inputes=  json_decode($user_data->payment_info, true);
        $method_name= $user_inputes['method_name'];
        $method_id= $user_inputes['method_id'];

        foreach ($user_inputes as $key => $value) {
            if(!in_array($key,['method_name','method_id'])){
                $userInput = [
                    'user_input' => $key,
                    'user_data' => $value,
                ];
                $userInputs[] = $userInput;
            }
        }

        $data = [
            'status' => $user_data->status,
            'method_id' => $method_id,
            'method_name' => $method_name,
            'customer_note' => $user_data->customer_note,
            'admin_note' => $user_data->note,
        ];

        $result = [
            'input' => $userInputs,
            'data' => $data,
            'method_fields' =>json_decode($user_data->method_fields ,true),
        ];

        return $result;
    }

    public static function time_date_format($data){
        $time=config('timeformat') ?? 'H:i';
        return  Carbon::parse($data)->locale(app()->getLocale())->translatedFormat('d M Y ' . $time);
    }
    public static function date_format($data){
        return  Carbon::parse($data)->locale(app()->getLocale())->translatedFormat('d M Y');
    }
    public static function time_format($data){
        $time=config('timeformat') ?? 'H:i';
        return  Carbon::parse($data)->locale(app()->getLocale())->translatedFormat($time);
    }

    public static function get_full_url($path,$data,$type,$placeholder = null){
        $place_holders = [
            'default' => asset('public/assets/admin/img/100x100/2.jpg'),
            'business' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'contact_us_image' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'profile' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'product' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'order' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'refund' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'delivery-man' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'admin' => asset('public/assets/admin/img/160x160/img1.jpg'),
            'conversation' => asset('public/assets/admin/img/160x160/img1.jpg'),
            'banner' => asset('public/assets/admin/img/900x400/img1.jpg'),
            'campaign' => asset('public/assets/admin/img/900x400/img1.jpg'),
            'notification' => asset('public/assets/admin/img/900x400/img1.jpg'),
            'category' => asset('public/assets/admin/img/100x100/2.jpg'),
            'store' => asset('public/assets/admin/img/160x160/img1.jpg'),
            'vendor' => asset('public/assets/admin/img/160x160/img1.jpg'),
            'brand' => asset('public/assets/admin/img/100x100/2.jpg'),
            'upload_image' => asset('public/assets/admin/img/upload-img.png'),
            'store/cover' => asset('public/assets/admin/img/100x100/2.jpg'),
            'upload_image_4' => asset('/public/assets/admin/img/upload-4.png'),
            'promotional_banner' => asset('public/assets/admin/img/100x100/2.jpg'),
            'admin_feature' => asset('public/assets/admin/img/100x100/2.jpg'),
            'aspect_1' => asset('/public/assets/admin/img/aspect-1.png'),
            'special_criteria' => asset('public/assets/admin/img/100x100/2.jpg'),
            'download_user_app_image' => asset('public/assets/admin/img/100x100/2.jpg'),
            'reviewer_image' => asset('public/assets/admin/img/100x100/2.jpg'),
            'fixed_header_image' => asset('/public/assets/admin/img/aspect-1.png'),
            'header_icon' => asset('/public/assets/admin/img/aspect-1.png'),
            'why_choose' => asset('/public/assets/admin/img/aspect-1.png'),
            'header_banner' => asset('/public/assets/admin/img/aspect-1.png'),
            'reviewer_company_image' => asset('public/assets/admin/img/100x100/2.jpg'),
            'module' => asset('public/assets/admin/img/100x100/2.jpg'),
            'parcel_category' => asset('/public/assets/admin/img/400x400/img2.jpg'),
            'favicon' => asset('/public/assets/admin/img/favicon.png'),
            'seller' => asset('public/assets/back-end/img/160x160/img1.jpg'),
            'upload_placeholder' => asset('/public/assets/admin/img/upload-placeholder.png'),
            'payment_modules/gateway_image' => asset('/public/assets/admin/img/payment/placeholder.png'),
            'email_template' => asset('/public/assets/admin/img/blank1.png'),
        ];

        try {
            if ($data && $type == 's3' && Storage::disk('s3')->exists($path .'/'. $data)) {
                return Storage::disk('s3')->url($path .'/'. $data);
//                $awsUrl = config('filesystems.disks.s3.url');
//                $awsBucket = config('filesystems.disks.s3.bucket');
//                return rtrim($awsUrl, '/') . '/' . ltrim($awsBucket . '/' . $path . '/' . $data, '/');
            }
        } catch (\Exception $e){
        }

        if ($data && Storage::disk('public')->exists($path .'/'. $data)) {
            return asset('storage/app/public') . '/' . $path . '/' . $data;
        }

        if (request()->is('api/*')) {
            return null;
        }

        if(isset($placeholder) && array_key_exists($placeholder, $place_holders)){
            return $place_holders[$placeholder];
        }elseif(array_key_exists($path, $place_holders)){
            return $place_holders[$path];
        }else{
            return $place_holders['default'];
        }

        return 'def.png';
    }



    public static function create_storage($model,$data_id){
        $config=self::get_business_settings('local_storage');
        $value = isset($config)?($config==0?'s3':'public'):'public';
           return DB::table('storages')->updateOrInsert(['data_type' => $model,'data_id' => $data_id], [
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }


    public static function getCalculatedCashBackAmount($amount,$customer_id){
        $data=[
            'calculated_amount'=> (float) 0,
            'cashback_amount'=>0,
            'cashback_type'=>'',
            'min_purchase'=>0,
            'max_discount'=>0,
            'id'=>0,
        ];

        try {
            $percent_bonus = CashBack::active()
            ->where('cashback_type', 'percentage')
            ->Running()
            ->where('min_purchase', '<=', $amount)
            ->where(function($query) use ($customer_id) {
                $query->whereJsonContains('customer_id', [$customer_id])->orWhereJsonContains('customer_id', ['all']);
            })
                ->when(is_numeric($customer_id), function($q) use ($customer_id){
                $q->where('same_user_limit', '>', function($query) use ($customer_id) {
                    $query->select(DB::raw('COUNT(*)'))
                            ->from('cash_back_histories')
                            ->where('user_id', $customer_id)
                            ->whereColumn('cash_back_id', 'cash_backs.id');
                    });
                })

            ->orderBy('cashback_amount', 'desc')
            ->first();

            $amount_bonus = CashBack::active()->where('cashback_type','amount')
            ->Running()
            ->where(function($query)use($customer_id){
                $query->whereJsonContains('customer_id', [$customer_id])->orWhereJsonContains('customer_id', ['all']);
            })
            ->where('min_purchase','<=',$amount )
            ->when(is_numeric($customer_id), function($q) use ($customer_id){
                $q->where('same_user_limit', '>', function($query) use ($customer_id) {
                    $query->select(DB::raw('COUNT(*)'))
                            ->from('cash_back_histories')
                            ->where('user_id', $customer_id)
                            ->whereColumn('cash_back_id', 'cash_backs.id');
                    });
                })
            ->orderBy('cashback_amount','desc')->first();

            if($percent_bonus && ($amount >=$percent_bonus->min_purchase)){
                $p_bonus = ($amount  * $percent_bonus->cashback_amount)/100;
                $p_bonus = $p_bonus > $percent_bonus->max_discount ? $percent_bonus->max_discount : $p_bonus;
                $p_bonus = round($p_bonus,config('round_up_to_digit'));
            }else{
                $p_bonus = 0;
            }

            if($amount_bonus && ($amount >=$amount_bonus->min_purchase)){
                $a_bonus = $amount_bonus?$amount_bonus->cashback_amount: 0;
                $a_bonus = round($a_bonus,config('round_up_to_digit'));
            }else{
                $a_bonus = 0;
            }

            $cashback_amount = max([$p_bonus,$a_bonus]);

            if($p_bonus ==  $cashback_amount){
                $data=[
                    'calculated_amount'=> (float)$cashback_amount,
                    'cashback_amount'=>$percent_bonus?->cashback_amount ?? 0,
                    'cashback_type'=>$percent_bonus?->cashback_type ?? '',
                    'min_purchase'=>$percent_bonus?->min_purchase ?? 0,
                    'max_discount'=>$percent_bonus?->max_discount ?? 0,
                    'id'=>$percent_bonus?->id,
                ];

            } elseif($a_bonus == $cashback_amount){
                $data=[
                    'calculated_amount'=> (float)$cashback_amount,
                    'cashback_amount'=>$amount_bonus?->cashback_amount ?? 0,
                    'cashback_type'=>$amount_bonus?->cashback_type ?? '',
                    'min_purchase'=>$amount_bonus?->min_purchase ?? 0,
                    'max_discount'=>$amount_bonus?->max_discount ?? 0,
                    'id'=>$amount_bonus?->id,
                ];
            }

            return $data ;
        } catch (\Exception $exception) {
            info([$exception->getFile(),$exception->getLine(),$exception->getMessage()]);
            return $data ;
        }

    }



     public static function getCusromerFirstOrderDiscount($order_count, $user_creation_date,$refby, $price = null){

        $data=[
            'is_valid' => false,
            'discount_amount' => 0,
            'discount_amount_type' => '',
            'validity' => '',
            'calculated_amount' => 0,
        ];
        if($order_count > 0 || !$refby){
            return $data?? [];
        }
    $settings =  array_column(BusinessSetting::whereIn('key',['new_customer_discount_status','new_customer_discount_amount','new_customer_discount_amount_type','new_customer_discount_amount_validity','new_customer_discount_validity_type',])->get()->toArray(), 'value', 'key');

        $validity_value = data_get($settings,'new_customer_discount_amount_validity');
        $validity_unit = data_get($settings,'new_customer_discount_validity_type');

        if($validity_unit == 'day'){
            $validity_end_date = (new DateTime($user_creation_date))->modify("+$validity_value day");

        } elseif($validity_unit == 'month'){
            $validity_end_date = (new DateTime($user_creation_date))->modify("+$validity_value month");

        } elseif($validity_unit == 'year'){
            $validity_end_date = (new DateTime($user_creation_date))->modify("+$validity_value year");
        }
        else{
            $validity_end_date = (new DateTime($user_creation_date))->modify("-1 day");
        }

        $is_valid=false;
        $current_date = new DateTime();
        if($validity_end_date >= $current_date){
        $is_valid=true;
        }



    if($order_count == 0 && $is_valid && data_get($settings,'new_customer_discount_status' ) == 1 && data_get($settings,'new_customer_discount_amount' ) > 0 ){
        $calculated_amount=0;
        if(data_get($settings,'new_customer_discount_amount_type') == 'percentage' && isset($price)){
            $calculated_amount= ($price / 100) * data_get($settings,'new_customer_discount_amount');
        } else{
            $calculated_amount=data_get($settings,'new_customer_discount_amount');
        }

        $data=[
            'is_valid' => $is_valid,
            'discount_amount' => data_get($settings,'new_customer_discount_amount'),
            'discount_amount_type' => data_get($settings,'new_customer_discount_amount_type'),
            'validity' => data_get($settings,'new_customer_discount_amount_validity') .' '. translate(Str::plural((data_get($settings,'new_customer_discount_validity_type') ?? 'day'),data_get($settings,'new_customer_discount_amount_validity'))),
            'calculated_amount' => round($calculated_amount,config('round_up_to_digit')),
        ];
    }

    return $data?? [];
    }


    public static function send_push_notif_for_demo_reset($data, $topic, $type,)
    {
        $postData = [
            'message' => [
                "topic" => $topic,
                "data" => [
                    "title" => (string)$data['title'],
                    "body" => (string)$data['description'],
                    "type" => (string)$type,
                    "image" => (string)$data['image'],
                    "body_loc_key" => (string)$type,
                ]
            ]
        ];

        return self::sendNotificationToHttp($postData);
    }


    public static function subscriptionConditionsCheck($store_id ,$package_id,){
        $store=Store::findOrFail($store_id);
        $package = SubscriptionPackage::withoutGlobalScope('translate')->find($package_id);

        $total_food= $store->items()->withoutGlobalScope(\App\Scopes\StoreScope::class)->count();
        if ($package->max_product != 'unlimited' &&  $total_food >= $package->max_product  ){
            return ['disable_item_count' => $total_food - $package->max_product];
            // return 'downgrade_error';
        }
        return null;
    }
    public static function subscription_plan_chosen($store_id ,$package_id, $payment_method  ,$discount = 0,$pending_bill =0,$reference=null ,$type=null){
        $store=Store::find($store_id);
        $package = SubscriptionPackage::withoutGlobalScope('translate')->find($package_id);
        $add_days=0;
        $add_orders=0;

        try {
            $store_subscription=$store->store_sub;
            if (isset($store_subscription) && $type == 'renew') {
                $store_subscription->total_package_renewed= $store_subscription->total_package_renewed + 1;

                $day_left=$store_subscription->expiry_date_parsed->format('Y-m-d');
                if (Carbon::now()->diffInDays($day_left, false) > 0 && $store_subscription->is_canceled != 1) {
                    $add_days= Carbon::now()->subDays(1)->diffInDays($day_left, false);
                }
                if ($store_subscription->max_order != 'unlimited' && $store_subscription->max_order > 0) {
                    $add_orders=$store_subscription->max_order;
                }

            }
            elseif($store->store_sub_update_application && $store->store_sub_update_application->package_id == $package->id && $type == 'renew' ){
                $store_subscription=$store->store_sub_update_application;
                $store_subscription->total_package_renewed= $store_subscription->total_package_renewed + 1;
            }

            else{
                self::calculateSubscriptionRefundAmount($store);
                StoreSubscription::where('store_id',$store->id)->update([
                    'status' => 0,
                ]);
                $store_subscription =new StoreSubscription();
                $store_subscription->total_package_renewed= 0;

                }

            $store_subscription->is_trial= 0;
            $store_subscription->renewed_at=now();
            $store_subscription->package_id=$package->id;
            $store_subscription->store_id=$store->id;
            if ($payment_method  == 'free_trial' ) {

                $free_trial_period= BusinessSetting::where(['key' => 'subscription_free_trial_days'])->first()?->value ?? 1;

                $store_subscription->expiry_date= Carbon::now()->addDays($free_trial_period)->format('Y-m-d');
                $store_subscription->validity= $free_trial_period;
            }
            else{
                $store_subscription->expiry_date= Carbon::now()->addDays($package->validity+$add_days)->format('Y-m-d');
                $store_subscription->validity=$package->validity+$add_days;
            }
            if($package->max_order != 'unlimited'){
                $store_subscription->max_order=$package->max_order + $add_orders;
            } else{
                $store_subscription->max_order=$package->max_order;
            }


            $store_subscription->max_product=$package->max_product;
            $store_subscription->pos=$package->pos;
            $store_subscription->mobile_app=$package->mobile_app;
            $store_subscription->chat=$package->chat;
            $store_subscription->review=$package->review;
            $store_subscription->self_delivery=$package->self_delivery;
            $store_subscription->is_canceled=0;
            $store_subscription->canceled_by='none';

            $store->item_section= 1;
            $store->pos_system= 1;
            if ($type == 'new_join' && $store->vendor?->status == 0 ) {
                $store->status= 0;
                $store_subscription->status= 0;

            }else{
                $store->status= 1;
                $store_subscription->status= 1;

            }

            // For Store Free Delivery
            if($store->free_delivery == 1 && $package->self_delivery == 1){
                $store->free_delivery = 1 ;
            } else{
                $store->free_delivery = 0 ;
                $store->coupon()->where('created_by','vendor')->where('coupon_type','free_delivery')->delete();
            }


            $store->reviews_section= 1;
            $store->self_delivery_system= 1;
            $store->store_business_model= 'subscription';

            $subscription_transaction= new SubscriptionTransaction();

            $subscription_transaction->package_id=$package->id;
            $subscription_transaction->store_id=$store->id;
            $subscription_transaction->price=$package->price;

            $subscription_transaction->validity=$package->validity;
            $subscription_transaction->paid_amount= $package->price - (($package->price*$discount)/100) + $pending_bill;

            $subscription_transaction->payment_status = 'success';
            $subscription_transaction->created_by=  in_array($payment_method,['wallet_payment_by_admin','manual_payment_by_admin' ,'plan_shift_by_admin'] )?'Admin': 'Store';

            if ($payment_method  == 'free_trial') {
                $subscription_transaction->validity= $free_trial_period;
                $subscription_transaction->paid_amount= 0;
                $subscription_transaction->is_trial= 1;
                $store_subscription->is_trial= 1;
            }
            elseif($payment_method  == 'pay_now'){
                $subscription_transaction->payment_status ='on_hold';
                $subscription_transaction->transaction_status = 0;
                $store_subscription->status= 0;
            }



            $subscription_transaction->payment_method=$payment_method;
            $subscription_transaction->reference=$reference ?? null;
            $subscription_transaction->discount=$discount ?? 0;
            if(in_array($type ,['renew','free_trial'])){
                $subscription_transaction->plan_type=$type;
            } elseif(StoreSubscription::where('store_id',$store->id)->where('is_trial',0)->count() > 0 || $reference == 'plan_shift_by_admin'){
                $subscription_transaction->plan_type='new_plan';
            }


            $subscription_transaction->package_details=[
                'pos'=>$package->pos,
                'review'=>$package->review,
                'self_delivery'=>$package->self_delivery,
                'chat'=>$package->chat,
                'mobile_app'=>$package->mobile_app,
                'max_order'=>$package->max_order,
                'max_product'=>$package->max_product,
            ];
            DB::beginTransaction();
            $store->save();
            $subscription_transaction->save();
            $store_subscription->save();
            DB::commit();
            $subscription_transaction->store_subscription_id= $store_subscription->id;
            $subscription_transaction->save();

            SubscriptionBillingAndRefundHistory::where(['store_id'=>$store->id,
            'transaction_type'=>'pending_bill', 'is_success' =>0])->update([
                'is_success'=> 1,
                'reference'=> 'payment_via_'.$payment_method.' _transaction_id_'.$subscription_transaction->id
            ]);

            if($reference == 'plan_shift_by_admin'){
                $billing= new SubscriptionBillingAndRefundHistory();
                $billing->store_id= $store->id;
                $billing->subscription_id= $store_subscription->id;
                $billing->package_id= $store_subscription->package_id;
                $billing->transaction_type= 'pending_bill';
                $billing->is_success= 0;
                $billing->amount= $package->price;
                $billing->save();
            }


        } catch(\Exception $e){
            DB::rollBack();
            info(["line___{$e->getLine()}",$e->getMessage()]);
            return false;
        }




        if(data_get(self::subscriptionConditionsCheck(store_id:$store->id,package_id:$package->id) , 'disable_item_count') > 0){
            $disable_item_count=data_get(Helpers::subscriptionConditionsCheck(store_id:$store->id,package_id:$package->id) , 'disable_item_count');
            $store->item_section= 0;
            $store->save();

            Item::where('store_id',$store->id)->oldest()->take($disable_item_count)->update([
                'status' => 0
            ]);
        }


        try {

            if($type == 'renew'){
                $push_notification_status=Helpers::getNotificationStatusData('store','store_subscription_renew','push_notification_status',$store->id);
                $title=translate('subscription_renewed');
                $des=translate('Your_subscription_successfully_renewed');
                } elseif($type != 'renew'){
                    $des=translate('Your_subscription_successfully_shifted');
                    $title=translate('subscription_shifted');
                    $push_notification_status=Helpers::getNotificationStatusData('store','store_subscription_shift','push_notification_status',$store->id);


            }
            if($push_notification_status  &&  $store?->vendor?->firebase_token){
                $data = [
                    'title' => $title ?? '',
                    'description' => $des ?? '',
                    'order_id' => '',
                    'image' => '',
                    'type' => 'subscription',
                    'order_status' => '',
                ];
                Helpers::send_push_notif_to_device($store?->vendor?->firebase_token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'vendor_id' => $store?->vendor_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }


            if (config('mail.status') && Helpers::get_mail_status('subscription_renew_mail_status_store') == '1' && $type == 'renew' && Helpers::getNotificationStatusData('store','store_subscription_renew','mail_status',$store->id)) {
                Mail::to($store->email)->send(new SubscriptionRenewOrShift($type,$store->name));
            }
            if (config('mail.status') && Helpers::get_mail_status('subscription_shift_mail_status_store') == '1' && $type != 'renew'  && Helpers::getNotificationStatusData('store','store_subscription_shift','mail_status',$store->id)) {
                Mail::to($store->email)->send(new SubscriptionRenewOrShift($type,$store->name));
            }
            if (config('mail.status') && Helpers::get_mail_status('subscription_successful_mail_status_store') == '1' && Helpers::getNotificationStatusData('store','store_subscription_success','mail_status',$store->id) ) {
                $url=route('subscription_invoice',['id' => base64_encode($subscription_transaction->id)]);
                Mail::to($store->email)->send(new SubscriptionSuccessful($store->name,$url));
            }


            if( Helpers::getNotificationStatusData('store','store_subscription_success','push_notification_status',$store->id)  &&  $store?->vendor?->firebase_token){
                $data = [
                    'title' => translate('subscription_successful'),
                    'description' => translate('You_are_successfully_subscribed'),
                    'order_id' => '',
                    'image' => '',
                    'type' => 'subscription',
                    'order_status' => '',
                ];
                Helpers::send_push_notif_to_device($store?->vendor?->firebase_token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'vendor_id' => $store?->vendor_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }


        } catch (\Exception $ex) {
            info($ex->getMessage());
        }

        return  $subscription_transaction->id;
    }
    public static function subscriptionPayment($store_id,$package_id,$payment_gateway,$url,$pending_bill=0,$type='payment',$payment_platform='web'){
        $store = Store::where('id',$store_id)->first();
        $package = SubscriptionPackage::where('id',$package_id)->first();
        $type == null ? 'payment' :$type ;

        $payer = new Payer(
            $store->name ,
            $store->email,
            $store->phone,
            ''
        );
        $store_logo= BusinessSetting::where(['key' => 'logo'])->first();
        $additional_data = [
            'business_name' => BusinessSetting::where(['key'=>'business_name'])->first()?->value,
            'business_logo' => \App\CentralLogics\Helpers::get_full_url('business',$store_logo?->value,$store_logo?->storage[0]?->value ?? 'public')
        ];
        $payment_info = new PaymentInfo(
            success_hook: 'sub_success',
            failure_hook: 'sub_fail',
            currency_code: Helpers::currency_code(),
            payment_method: $payment_gateway,
            payment_platform: $payment_platform,
            payer_id: $store->id,
            receiver_id:  $package->id,
            additional_data: $additional_data,
            payment_amount: $package->price + $pending_bill,
            external_redirect_link: $url,
            attribute: 'store_subscription_'.$type,
            attribute_id: $package->id,
        );
        $receiver_info = new Receiver('Admin','example.png');
        $redirect_link = Payment::generate_link($payer, $payment_info, $receiver_info);

        return $redirect_link;
    }

    public Static function subscription_check()
    {
        $subscription_business_model=  BusinessSetting::where(['key'=>'subscription_business_model'])->first()?->value ?? null;
        if($subscription_business_model == null ){
            Helpers::insert_business_settings_key('subscription_business_model', '1');
            $subscription_business_model=  BusinessSetting::where(['key'=>'subscription_business_model'])->first()?->value ?? null;
        }
        return $subscription_business_model ?? 1;

    }
    public Static function commission_check()
    {
        $commission_business_model=  BusinessSetting::where(['key'=>'commission_business_model'])->first()?->value ?? null;
        if($commission_business_model == null ){
            Helpers::insert_business_settings_key('commission_business_model', '1');
            $commission_business_model=  BusinessSetting::where(['key'=>'commission_business_model'])->first()?->value ?? null;
        }
        return $commission_business_model ?? 1;
    }

    public static function calculateSubscriptionRefundAmount($store,$return_data=null){

        $store_subscription=$store->store_sub;
        if($store_subscription && $store_subscription?->is_canceled === 0 && $store_subscription?->is_trial === 0 ){
            $day_left=$store_subscription->expiry_date_parsed->format('Y-m-d');
            if (Carbon::now()->diffInDays($day_left, false) > 0) {
                $add_days= Carbon::now()->diffInDays($day_left, false);
                $validity=$store_subscription?->validity;
                $subscription_usage_max_time=BusinessSetting::where('key', 'subscription_usage_max_time')->first()?->value ?? 50 ;
                $subscription_usage_max_time=  ($validity * $subscription_usage_max_time) /100 ;

                if(($validity - $add_days) < $subscription_usage_max_time ){
                        $per_day= $store->store_sub_trans->price / $store->store_sub_trans->validity;
                        $back_amount= $per_day *  $add_days;

                        if($return_data == true){
                            return ['back_amount' => $back_amount, 'days'=> $add_days];
                        }

                        $vendorWallet = StoreWallet::firstOrNew(
                            ['vendor_id' => $store->vendor_id]
                        );
                        $vendorWallet->total_earning = $vendorWallet->total_earning+$back_amount;
                        $vendorWallet->save();

                        $refund=new SubscriptionBillingAndRefundHistory();
                        $refund->store_id= $store->id;
                        $refund->subscription_id= $store_subscription->id;
                        $refund->package_id= $store_subscription->package_id;
                        $refund->transaction_type= 'refund';
                        $refund->is_success= 1;
                        $refund->amount= $back_amount;
                        $refund->reference= 'validity_left_'.$add_days ;
                        $refund->save();

                    }
            }

        }

        return true;
    }
    public static function increment_order_count($store){
        $store_sub=$store->store_sub;
        if ( $store->store_business_model == 'subscription' && isset($store_sub) && $store_sub->max_order != "unlimited") {
            $store_sub->increment('max_order', 1);
        }
        return true;
    }

    public static function getDefaultPaymentMethods()
    {
        if (!Schema::hasTable('addon_settings')) {
            return [];
        }

        $digital_payment=\App\CentralLogics\Helpers::get_business_settings('digital_payment');

        if($digital_payment && $digital_payment['status']==0){
            return [];
        }

        $methods = DB::table('addon_settings')->where('is_active',1)->whereIn('settings_type', ['payment_config'])->whereIn('key_name', ['ssl_commerz','paypal','stripe','razor_pay','senang_pay','paytabs','paystack','paymob_accept','paytm','flutterwave','liqpay','bkash','mercadopago'])->get();
        $env = env('APP_ENV') == 'live' ? 'live' : 'test';
        $credentials = $env . '_values';

        $data = [];
        foreach ($methods as $method) {
            $credentialsData = json_decode($method->$credentials);
            $additional_data = json_decode($method->additional_data);
            if ($credentialsData->status == 1) {
                $data[] = [
                    'gateway' => $method->key_name,
                    'gateway_title' => $additional_data?->gateway_title,
                    'gateway_image' => $additional_data?->gateway_image,
                    'storage' => $additional_data?->storage ?? 'public'
                ];
            }
        }
        return $data;
    }

    public static function getNotificationStatusData($user_type,$key,$notification_type, $store_id= null){
        $data= NotificationSetting::where('type',$user_type)->where('key',$key)->select($notification_type)->first();
        $data= $data?->{$notification_type} === 'active' ? 1 : 0;

        if($store_id && $user_type == 'store' && $data === 1){
            $data= self::getStoreNotificationStatusData(store_id:$store_id,key:$key ,notification_type: $notification_type);
            $data= $data?->{$notification_type} === 'active' ? 1 : 0;
        }

        return $data;
    }
        public static function getNotificationStatusDataAdmin($user_type,$key){
            $data= NotificationSetting::where('type',$user_type)->where('key',$key)->select(['mail_status','push_notification_status','sms_status'])->first();
            return $data ?? null ;
        }

    public static function notificationDataSetup(){

        $data=self::getAdminNotificationSetupData();
        $data = NotificationSetting::upsert($data,['key','type'],['title','mail_status','sms_status','push_notification_status','sub_title']);
        return true;
    }
    public static function storeNotificationDataSetup($id){
        $data=self::getStoreNotificationSetupData($id);
        $data = StoreNotificationSetting::upsert($data,['key','store_id'],['title','mail_status','sms_status','push_notification_status','sub_title']);
        return true;
    }
    public static function updateAdminNotificationSetupDataSetup(){
        self::updateAdminNotificationSetupData();
    return true;
    }
    public static function getStoreNotificationStatusData($store_id,$key,$notification_type){
        $data= StoreNotificationSetting::where('store_id',$store_id)->where('key',$key)->select($notification_type)->first();
        if(!$data){
            self::storeNotificationDataSetup($store_id);
            $data= StoreNotificationSetting::where('store_id',$store_id)->where('key',$key)->select($notification_type)->first();
        }
        return $data ?? null ;
    }
    public static function add_fund_push_notification($user_id){
        $customer_push_notification_status=self::getNotificationStatusData('customer','customer_add_fund_to_wallet','push_notification_status' );

        $user= User::where('id',$user_id)->first();
        if ($customer_push_notification_status && $user?->cm_firebase_token) {
            $data = [
                'title' => translate('messages.Fund_added'),
                'description' => translate('messages.Fund_added_to_your_wallet'),
                'order_id' => '',
                'image' => '',
                'type' => 'add_fund',
                'order_status' =>'',
            ];
            self::send_push_notif_to_device($user?->cm_firebase_token, $data);

            DB::table('user_notifications')->insert([
                'data' => json_encode($data),
                'user_id' => $user_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        return true;
    }


    public static function getActivePaymentGateways(){

        if (!Schema::hasTable('addon_settings')) {
            return [];
        }

        $published_status = 0;
        $payment_published_status = config('get_payment_publish_status');
        if (isset($payment_published_status[0]['is_published'])) {
            $published_status = $payment_published_status[0]['is_published'];
        }


        if($published_status == 1){
            $methods = DB::table('addon_settings')->where('is_active',1)->where('settings_type', 'payment_config')->get();
            $env = env('APP_ENV') == 'live' ? 'live' : 'test';
            $credentials = $env . '_values';

        } else{
            $methods = DB::table('addon_settings')->where('is_active',1)->whereIn('settings_type', ['payment_config'])->whereIn('key_name', ['ssl_commerz','paypal','stripe','razor_pay','senang_pay','paytabs','paystack','paymob_accept','paytm','flutterwave','liqpay','bkash','mercadopago'])->get();
            $env = env('APP_ENV') == 'live' ? 'live' : 'test';
            $credentials = $env . '_values';

        }

            $data = [];
            foreach ($methods as $method) {
                $credentialsData = json_decode($method->$credentials);
                $additional_data = json_decode($method->additional_data);
                if ($credentialsData?->status == 1) {
                    $data[] = [
                        'gateway' => $method->key_name,
                        'gateway_title' => $additional_data?->gateway_title,
                        'gateway_image' => $additional_data?->gateway_image,
                        'gateway_image_full_url' => Helpers::get_full_url('payment_modules/gateway_image',$additional_data?->gateway_image,$additional_data?->storage ?? 'public')
                    ];
                }
            }
            return $data;

    }



    public static function checkCurrency($data , $type= null){

        $digital_payment=self::get_business_settings('digital_payment');

        if($digital_payment && $digital_payment['status']==1){
            if($type === null){
                if(is_array(self::getActivePaymentGateways())){
                    foreach(self::getActivePaymentGateways() as $payment_gateway){

                        if(!empty(self::getPaymentGatewaySupportedCurrencies($payment_gateway['gateway'])) && !array_key_exists($data,self::getPaymentGatewaySupportedCurrencies($payment_gateway['gateway']))    ){
                            return  $payment_gateway['gateway'];
                        }
                    }
                }
            }
            elseif($type == 'payment_gateway'){
                $currency=  BusinessSetting::where('key','currency')->first()?->value;
                    if(!empty(self::getPaymentGatewaySupportedCurrencies($data)) && !array_key_exists($currency,self::getPaymentGatewaySupportedCurrencies($data))    ){
                        return  $data;
                    }
            }
        }

        return true;
        }


    public static function updateStorageTable($dataType, $dataId, $image)
    {
        $value = Helpers::getDisk();
        DB::table('storages')->updateOrInsert([
            'data_type' => $dataType,
            'data_id' => $dataId,
            'key' => 'image',
        ], [
            'value' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    public static function getNextOpeningTime($schedule) {
        $currentTime =now()->format('H:i');
        if ($schedule) {
            foreach($schedule as $entry) {
                if ($entry['day'] == now()->format('w')) {
                        if ($currentTime >= $entry['opening_time'] && $currentTime <= $entry['closing_time']) {
                            return $entry['opening_time'];
                        } elseif($currentTime < $entry['opening_time']){
                            return $entry['opening_time'];
                        }
                }
            }
        }
            return 'closed';
        }

    public static function checkExternalConfiguration($externalBaseUrl, $externalTokem, $martToken)
    {
        $activationMode = ExternalConfiguration::where('key', 'activation_mode')->first()?->value;
        $driveMondBaseUrl = ExternalConfiguration::where('key', 'drivemond_base_url')->first()?->value;
        $driveMondToken = ExternalConfiguration::where('key', 'drivemond_token')->first()?->value;
        $systemSelfToken = ExternalConfiguration::where('key', 'system_self_token')->first()?->value;
        return $activationMode == 1 && $driveMondBaseUrl == $externalBaseUrl && $driveMondToken == $externalTokem && $systemSelfToken == $martToken;
    }

    public static function checkSelfExternalConfiguration()
    {
        $activationMode = ExternalConfiguration::where('key', 'activation_mode')->first()?->value;
        $driveMondBaseUrl = ExternalConfiguration::where('key', 'drivemond_base_url')->first()?->value;
        $driveMondToken = ExternalConfiguration::where('key', 'drivemond_token')->first()?->value;
        $systemSelfToken = ExternalConfiguration::where('key', 'system_self_token')->first()?->value;
        return $activationMode == 1 && $driveMondBaseUrl != null && $driveMondToken != null && $systemSelfToken != null;
    }
}

