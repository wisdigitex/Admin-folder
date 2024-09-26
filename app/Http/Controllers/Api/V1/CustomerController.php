<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ExternalConfiguration;
use App\Models\Item;
use App\Models\User;
use App\Models\Zone;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\OrderReference;
use Illuminate\Support\Carbon;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use MatanYadaev\EloquentSpatial\Objects\Point;

class CustomerController extends Controller
{
    public function address_list(Request $request)
    {
        $limit = $request['limit'] ?? 10;
        $offset = $request['offset'] ?? 1;

        $addresses = CustomerAddress::where('user_id', $request->user()->id)->latest()->paginate($limit, ['*'], 'page', $offset);

        $data = [
            'total_size' => $addresses->total(),
            'limit' => $limit,
            'offset' => $offset,
            'addresses' => Helpers::address_data_formatting($addresses->items())
        ];
        return response()->json($data, 200);
    }

    public function add_new_address(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required',
            'longitude' => 'required',
            'latitude' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $zone = Zone::whereContains('coordinates', new Point($request->latitude, $request->longitude, POINT_SRID))->get(['id']);
        if (count($zone) == 0) {
            $errors = [];
            array_push($errors, ['code' => 'coordinates', 'message' => translate('messages.service_not_available_in_this_area')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $address = [
            'user_id' => $request->user()->id,
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'zone_id' => $zone[0]->id,
            'created_at' => now(),
            'updated_at' => now()
        ];
        DB::table('customer_addresses')->insert($address);
        return response()->json(['message' => translate('messages.successfully_added'), 'zone_ids' => array_column($zone->toArray(), 'id')], 200);
    }

    public function update_address(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required',
            'longitude' => 'required',
            'latitude' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone = Zone::whereContains('coordinates', new Point($request->latitude, $request->longitude, POINT_SRID))->get(['id']);
        if (!$zone) {
            $errors = [];
            array_push($errors, ['code' => 'coordinates', 'message' => translate('messages.service_not_available_in_this_area')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $address = [
            'user_id' => $request->user()->id,
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'zone_id' => $zone[0]->id,
            'created_at' => now(),
            'updated_at' => now()
        ];
        DB::table('customer_addresses')->where('id', $id)->update($address);
        return response()->json(['message' => translate('messages.updated_successfully'), 'zone_id' => $zone[0]->id], 200);
    }

    public function delete_address(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (DB::table('customer_addresses')->where(['id' => $request['address_id'], 'user_id' => $request->user()->id])->first()) {
            DB::table('customer_addresses')->where(['id' => $request['address_id'], 'user_id' => $request->user()->id])->delete();
            return response()->json(['message' => translate('messages.successfully_removed')], 200);
        }
        return response()->json(['message' => translate('messages.not_found')], 404);
    }

    public function get_order_list(Request $request)
    {
        $orders = Order::where(['user_id' => $request->user()->id])->get();
        return response()->json($orders, 200);
    }

    public function get_order_details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $details = OrderDetail::where(['order_id' => $request['order_id']])->get();
        foreach ($details as $det) {
            $det['product_details'] = json_decode($det['product_details'], true);
        }

        return response()->json($details, 200);
    }

    public function info(Request $request)
    {
        if (!$request->hasHeader('X-localization')) {

            $errors = [];
            array_push($errors, ['code' => 'current_language_key', 'message' => translate('messages.current_language_key_required')]);
            return response()->json([
                'errors' => $errors
            ], 200);
        }

        // Current Language
        $current_language = $request->header('X-localization');
        $user = User::findOrFail($request->user()->id);
        $user->current_language_key = $current_language;
        $user->save();

        $data = $request->user();
        $data['userinfo'] = $data->userinfo;
        $data['order_count'] = (integer)$request->user()->orders->count();
        $data['member_since_days'] = (integer)$request->user()->created_at->diffInDays();
        $data['selected_modules_for_interest'] = $request->user()?->module_ids ? json_decode($user?->module_ids, true) : [];
        $discount_data = Helpers::getCusromerFirstOrderDiscount(order_count: $data['order_count'], user_creation_date: $request->user()->created_at, refby: $request->user()->ref_by);
        $data['is_valid_for_discount'] = data_get($discount_data, 'is_valid');
        $data['discount_amount'] = (float)data_get($discount_data, 'discount_amount');
        $data['discount_amount_type'] = data_get($discount_data, 'discount_amount_type');
        $data['validity'] = (string)data_get($discount_data, 'validity');

        unset($data['orders']);
        return response()->json($data, 200);
    }


    public function update_interest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'interest' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user = User::where(['id' => $request->user()->id])->first();
        $module_ids = $user?->module_ids ? json_decode($user?->module_ids, true) : [];
        array_push($module_ids, $request->header('moduleId'));
        $module_ids = array_unique($module_ids);

        $interest = $user?->interest ? json_decode($user?->interest, true) : [];
        $interest = array_unique(array_merge($interest, $request->interest));
        $user->interest = json_encode(array_values($interest));

        $user->module_ids = json_encode($module_ids);
        $user->save();

        return response()->json(['message' => translate('messages.interest_updated_successfully')], 200);
    }

    public function update_cm_firebase_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cm_firebase_token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        DB::table('users')->where('id', $request->user()->id)->update([
            'cm_firebase_token' => $request['cm_firebase_token']
        ]);

        return response()->json(['message' => translate('messages.updated_successfully')], 200);
    }

    public function get_suggested_item(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => 'Zone id is required!']);
            return response()->json([
                'errors' => $errors
            ], 403);
        }


        $zone_id = $request->header('zoneId');

        $interest = $request->user()->interest;
        $interest = isset($interest) ? json_decode($interest) : null;

        $products = Item::active()->whereHas('store', function ($q) use ($zone_id) {
            $q->whereIn('zone_id', json_decode($zone_id, true));
        })
            ->when(isset($interest), function ($q) use ($interest) {
                $q->where(function ($query) use ($interest) {
                    foreach ($interest as $id) {
                        $query->orWhereJsonContains('category_ids', ['id' => (string)$id]);
                    }
                });
            })
            ->whereHas('module.zones', function ($query) use ($zone_id) {
                $query->whereIn('zones.id', json_decode($zone_id, true));
            })
            ->whereHas('store', function ($query) use ($zone_id) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })->whereIn('zone_id', json_decode($zone_id, true));
            })
            ->when($interest == null, function ($q) {
                return $q->popular();
            })
            ->limit(5)->get();
        $products = Helpers::product_data_formatting($products, true, false, app()->getLocale());
        return response()->json($products, 200);
    }

    public function update_zone(Request $request)
    {
        if (!$request->hasHeader('zoneId') && is_numeric($request->header('zoneId'))) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $customer = $request->user();
        $customer->zone_id = (integer)$request->header('zoneId');
        $customer->save();
        return response()->json([], 200);
    }

    public function remove_account(Request $request)
    {
        $user = $request->user();

        if (Order::where('user_id', $user->id)->whereIn('order_status', ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'])->count()) {
            return response()->json(['errors' => [['code' => 'on-going', 'message' => translate('messages.Please_complete_your_ongoing_and_accepted_orders')]]], 203);
        }
        $request->user()->token()->revoke();
        if ($user->userinfo) {
            $user->userinfo->delete();
        }
        $user->delete();
        return response()->json([]);
    }

    public function review_reminder(Request $request)
    {
        $order = Order::wherehas('OrderReference', function ($query) {
            $query->where('is_reviewed', 0)->where('is_review_canceled', 0);
        })
            ->where('user_id', $request->user()->id)->where('order_status', 'delivered')->where('is_guest', 0)->latest()->select('id')->with('details:id,order_id,item_details')->first();

        if ($order?->details) {
            $images = collect($order->details)->pluck('item_details')->map(function ($itemDetail) {
                $decodeditemDetail = json_decode($itemDetail, true);
                $product = Item::where(['id' => $decodeditemDetail['id']])->first();
                return $product->image_full_url ?? null;
            })->filter();
        }

        return response()->json(['order_id' => $order?->id ?? null,
            'images' => $images ?? []], 200);

    }

    public function review_reminder_cancel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        OrderReference::where('order_id', $request->order_id)->update([
            'is_review_canceled' => 1
        ]);
        return response()->json('success', 200);
    }


    #handshake
    public function update_profile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => 'required|unique:users,email,' . $request->user()->id,
            'password' => ['nullable', Password::min(8)],
        ], [
            'f_name.required' => 'First name is required!',
            'l_name.required' => 'Last name is required!',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image = $request->file('image');

        if ($request->has('image')) {
            $imageName = Helpers::update('profile/', $request->user()->image, 'png', $request->file('image'));
        } else {
            $imageName = $request->user()->image;
        }

        if ($request['password'] != null && strlen($request['password']) > 5) {
            $pass = bcrypt($request['password']);
        } else {
            $pass = $request->user()->password;
        }
        $user = User::where(['id' => $request->user()->id])->first();
        $user->f_name = $request->f_name;
        $user->l_name = $request->l_name;
        $user->email = $request->email;
        $user->image = $imageName;
        $user->password = $pass;
        $user->save();

        if ($user->userinfo) {
            $userinfo = $user->userinfo;
            $userinfo->f_name = $request->f_name;
            $userinfo->l_name = $request->l_name;
            $userinfo->email = $request->email;
            $userinfo->image = $imageName;
            $userinfo->save();
        }
        if (Helpers::checkSelfExternalConfiguration()) {
            $driveMondBaseUrl = ExternalConfiguration::where('key', 'drivemond_base_url')->first()?->value;
            $driveMondToken = ExternalConfiguration::where('key', 'drivemond_token')->first()?->value;
            $systemSelfToken = ExternalConfiguration::where('key', 'system_self_token')->first()?->value;
            $response = Http::asForm()->post($driveMondBaseUrl . '/api/customer/external-update-data',
                [
                    'bearer_token' => $request->bearerToken(),
                    'token' => $driveMondToken,
                    'external_base_url' => url('/'),
                    'external_token' => $systemSelfToken,
                ]);
        }
        return response()->json(['message' => translate('messages.successfully_updated')], 200);
    }


    public function getCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'external_base_url' => 'required',
            'external_token' => 'required',
        ]);
        if ($validator->fails()) {
            $data = [
                'status' => false,
                'errors' => Helpers::error_processor($validator),
            ];
            return response()->json($data);
        }
        if (Helpers::checkExternalConfiguration($request->external_base_url, $request->external_token, $request->token)) {
            $user = DB::table('users')->where('id', Auth::id())->first();
            if (!$user) {
                $data = [
                    'status' => false,
                    'data' => ['error_code' => 404, 'message' => "User not found"]
                ];
                return response()->json($data);
            }
            $data = [
                'status' => true,
                'data' => $user
            ];
            return response()->json($data);
        }
        $data = [
            'status' => false,
            'data' => ['error_code' => 402, 'message' => "Invalid token"]
        ];
        return response()->json($data);

    }

    public function externalUpdateCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bearer_token' => 'required',
            'token' => 'required',
            'external_base_url' => 'required',
            'external_token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if (Helpers::checkSelfExternalConfiguration() && Helpers::checkExternalConfiguration($request->external_base_url, $request->external_token, $request->token)) {
            $driveMondBaseUrl = ExternalConfiguration::where('key', 'drivemond_base_url')->first()?->value;
            $driveMondToken = ExternalConfiguration::where('key', 'drivemond_token')->first()?->value;
            $systemSelfToken = ExternalConfiguration::where('key', 'system_self_token')->first()?->value;
            $response = Http::withToken($request->bearer_token)->post($driveMondBaseUrl . '/api/customer/get-data',
                [
                    'token' => $driveMondToken,
                    'external_base_url' => url('/'),
                    'external_token' => $systemSelfToken,
                ]);
            if ($response->successful()) {
                $drivemondCustomerResponse = $response->json();
                if ($drivemondCustomerResponse['status']) {
                    $drivemondCustomer = $drivemondCustomerResponse['data'];
                    $user = User::where(['phone' => $drivemondCustomer['phone']])->first();
                    if ($user) {
                        $user->f_name = $drivemondCustomer['first_name'];
                        $user->l_name = $drivemondCustomer['last_name'];
                        $user->email = $drivemondCustomer['email'];
                        $user->password = $drivemondCustomer['password'];
                        $user->save();

                        if ($user->userinfo) {
                            $userinfo = $user->userinfo;
                            $userinfo->f_name = $drivemondCustomer['first_name'];
                            $userinfo->l_name = $drivemondCustomer['last_name'];
                            $userinfo->email = $drivemondCustomer['email'];
                            $userinfo->save();
                        }
                        $data = [
                            'status' => true,
                            'data' => $user
                        ];
                        return response()->json($data);
                    }
                }
            }
            $drivemondCustomer = $drivemondCustomerResponse['data'];
            if ($drivemondCustomer['error_code'] == 402) {
                $data = [
                    'status' => false,
                    'data' => ['error_code' => 402, 'message' => "Drivemond user not found"]
                ];
                return response()->json($data);
            }

        }
        $data = [
            'status' => false,
            'data' => ['error_code' => 402, 'message' => "Invalid token"]
        ];
        return response()->json($data);


    }
}
