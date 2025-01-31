<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\Cart;
use App\Models\ExternalConfiguration;
use App\Models\User;
use App\Models\Guest;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Carbon;
use App\Mail\EmailVerification;
use App\Mail\LoginVerification;
use App\Models\BusinessSetting;
use App\CentralLogics\SMS_module;
use App\Models\WalletTransaction;
use App\Models\EmailVerifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Gateways\Traits\SmsGateway;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CustomerAuthController extends Controller
{
    public function verify_phone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|min:11|max:14',
            'otp' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $user = User::where('phone', $request->phone)->first();
        if ($user) {
            if ($user->is_phone_verified) {
                return response()->json([
                    'message' => translate('messages.phone_number_is_already_varified')
                ], 200);
            }

            if (env('APP_MODE') == 'demo') {
                if ($request['otp'] == "123456") {
                    $user->is_phone_verified = 1;
                    $user->save();

                    return response()->json([
                        'message' => translate('messages.phone_number_varified_successfully'),
                        'otp' => 'inactive'
                    ], 200);
                }
                return response()->json([
                    'message' => translate('messages.phone_number_and_otp_not_matched')
                ], 404);
            }

            $data = DB::table('phone_verifications')->where([
                'phone' => $request['phone'],
                'token' => $request['otp'],
            ])->first();

            if ($data) {
                DB::table('phone_verifications')->where([
                    'phone' => $request['phone'],
                    'token' => $request['otp'],
                ])->delete();

                $user->is_phone_verified = 1;
                $user->save();

                return response()->json([
                    'message' => translate('messages.phone_number_varified_successfully'),
                    'otp' => 'inactive'
                ], 200);
            } else {
                $max_otp_hit = 5;
                $max_otp_hit_time = 60; // seconds
                $temp_block_time = 600; // seconds

                $verification_data = DB::table('phone_verifications')->where('phone', $request['phone'])->first();

                if (isset($verification_data)) {

                    if (isset($verification_data->temp_block_time) && Carbon::parse($verification_data->temp_block_time)->DiffInSeconds() <= $temp_block_time) {
                        $time = $temp_block_time - Carbon::parse($verification_data->temp_block_time)->DiffInSeconds();

                        $errors = [];
                        array_push($errors, ['code' => 'otp_block_time',
                            'message' => translate('messages.please_try_again_after_') . CarbonInterval::seconds($time)->cascade()->forHumans()
                        ]);
                        return response()->json([
                            'errors' => $errors
                        ], 405);
                    }

                    if ($verification_data->is_temp_blocked == 1 && Carbon::parse($verification_data->updated_at)->DiffInSeconds() >= $max_otp_hit_time) {
                        DB::table('phone_verifications')->updateOrInsert(['phone' => $request['phone']],
                            [
                                'otp_hit_count' => 0,
                                'is_temp_blocked' => 0,
                                'temp_block_time' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }

                    if ($verification_data->otp_hit_count >= $max_otp_hit && Carbon::parse($verification_data->updated_at)->DiffInSeconds() < $max_otp_hit_time && $verification_data->is_temp_blocked == 0) {

                        DB::table('phone_verifications')->updateOrInsert(['phone' => $request['phone']],
                            [
                                'is_temp_blocked' => 1,
                                'temp_block_time' => now(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        $errors = [];
                        array_push($errors, ['code' => 'otp_temp_blocked', 'message' => translate('messages.Too_many_attemps')]);
                        return response()->json([
                            'errors' => $errors
                        ], 405);
                    }

                }


                DB::table('phone_verifications')->updateOrInsert(['phone' => $request['phone']],
                    [
                        'otp_hit_count' => DB::raw('otp_hit_count + 1'),
                        'updated_at' => now(),
                        'temp_block_time' => null,
                    ]);

                return response()->json([
                    'message' => translate('messages.phone_number_and_otp_not_matched')
                ], 404);
            }
        }
        return response()->json([
            'message' => translate('messages.not_found')
        ], 404);
    }

    public function check_email(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|unique:users'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }


        if (BusinessSetting::where(['key' => 'email_verification'])->first()->value) {
            $token = rand(100000, 999999);
            DB::table('email_verifications')->insert([
                'email' => $request['email'],
                'token' => $token,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $mail_status = Helpers::get_mail_status('registration_otp_mail_status_user');
            if (config('mail.status') && $mail_status == '1' && Helpers::getNotificationStatusData('customer', 'customer_registration_otp', 'mail_status')) {
                $user = User::where('email', $request['email'])->first();
                Mail::to($request['email'])->send(new EmailVerification($token, $user->f_name));
            }
            return response()->json([
                'message' => 'Email is ready to register',
                'token' => 'active'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Email is ready to register',
                'token' => 'inactive'
            ], 200);
        }
    }

    public function verify_email(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $verify = EmailVerifications::where(['email' => $request['email'], 'token' => $request['token']])->first();

        if (isset($verify)) {
            $verify->delete();
            return response()->json([
                'message' => translate('messages.token_varified'),
            ], 200);
        }

        $errors = [];
        array_push($errors, ['code' => 'token', 'message' => translate('messages.token_not_found')]);
        return response()->json(
            ['errors' => $errors],
            404
        );
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => 'required|unique:users',
            'phone' => 'required|unique:users',
            'password' => ['required', Password::min(8)],
        ], [
            'f_name.required' => 'The first name field is required.',
            'l_name.required' => 'The last name field is required.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $ref_by = null;
        $customer_verification = BusinessSetting::where('key', 'customer_verification')->first()->value;
        $firebase_otp_verification = BusinessSetting::where('key', 'firebase_otp_verification')->first()->value??0;

        if ($request->ref_code) {
            $ref_status = BusinessSetting::where('key', 'ref_earning_status')->first()->value;
            if ($ref_status != '1') {
                return response()->json(['errors' => Helpers::error_formater('ref_code', translate('messages.referer_disable'))], 403);
            }

            $referar_user = User::where('ref_code', '=', $request->ref_code)->first();
            if (!$referar_user || !$referar_user->status) {
                return response()->json(['errors' => Helpers::error_formater('ref_code', translate('messages.referer_code_not_found'))], 405);
            }

            if (WalletTransaction::where('reference', $request->phone)->first()) {
                return response()->json(['errors' => Helpers::error_formater('phone', translate('Referrer code already used'))], 203);
            }


            $notification_data = [
                'title' => translate('messages.Your_referral_code_is_used_by') . ' ' . $request->f_name . ' ' . $request->l_name,
                'description' => translate('Be prepare to receive when they complete there first purchase'),
                'order_id' => 1,
                'image' => '',
                'type' => 'referral_code',
            ];

            if (Helpers::getNotificationStatusData('customer', 'customer_new_referral_join', 'push_notification_status') && $referar_user?->cm_firebase_token) {
                Helpers::send_push_notif_to_device($referar_user?->cm_firebase_token, $notification_data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($notification_data),
                    'user_id' => $referar_user?->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }


            $ref_by = $referar_user->id;
        }

        $user = User::create([
            'f_name' => $request->f_name,
            'l_name' => $request->l_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'ref_by' => $ref_by,
            'password' => bcrypt($request->password),
        ]);
        $user->ref_code = Helpers::generate_referer_code($user);
        $user->save();


        $token = $user->createToken('RestaurantCustomerAuth')->accessToken;

        if($customer_verification && !$firebase_otp_verification && env('APP_MODE') !='demo')
        {
            $otp_interval_time= 60; //seconds
            $verification_data= DB::table('phone_verifications')->where('phone', $request['phone'])->first();

            if (isset($verification_data) && Carbon::parse($verification_data->updated_at)->DiffInSeconds() < $otp_interval_time) {
                $time = $otp_interval_time - Carbon::parse($verification_data->updated_at)->DiffInSeconds();
                $errors = [];
                array_push($errors, ['code' => 'otp', 'message' => translate('messages.please_try_again_after_') . $time . ' ' . translate('messages.seconds')]);
                return response()->json([
                    'errors' => $errors
                ], 405);
            }

            $otp = rand(100000, 999999);
            DB::table('phone_verifications')->updateOrInsert(['phone' => $request['phone']],
                [
                    'token' => $otp,
                    'otp_hit_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);


            try {
                $mailResponse = null;
                if (config('mail.status') && Helpers::get_mail_status('registration_otp_mail_status_user') == '1' && Helpers::getNotificationStatusData('customer', 'customer_registration_otp', 'mail_status')) {
                    Mail::to($request['email'])->send(new EmailVerification($otp, $request->f_name));
                    $mailResponse = 'success';
                }

            } catch (\Exception $ex) {
                info($ex->getMessage());
                $mailResponse = null;
            }

            //for payment and sms gateway addon
            $response = null;
            if (Helpers::getNotificationStatusData('customer', 'customer_registration_otp', 'sms_status')) {
                $published_status = addon_published_status('Gateways');
                if ($published_status == 1) {
                    $response = SmsGateway::send($request['phone'], $otp);
                } else {
                    $response = SMS_module::send($request['phone'], $otp);
                }
            }
//            if(!$user->cm_firebase_token || $user->cm_firebase_token == '@'  ){
//                $user->cm_firebase_token = $request->cm_firebase_token;
//                $user->save();
//                }
//            if(Helpers::getNotificationStatusData('customer','customer_registration_otp','push_notification_status')){
//
//                if (isset($user->cm_firebase_token)) {
//                    $data = [
//                        'title' => translate('messages.verification_otp'),
//                        'description' => translate('messages.your_verification_otp_is').' '.$otp,
//                        'order_id' => '',
//                        'image' => '',
//                        'type' => 'otp'
//                    ];
//                    Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);
//
//                    DB::table('user_notifications')->insert([
//                        'data' => json_encode($data),
//                        'user_id' => $user->id,
//                        'created_at' => now(),
//                        'updated_at' => now()
//                    ]);
//                    $response = 'success';
//                }
//            }

            if ($response !== 'success' && $mailResponse !== 'success') {
                $errors = [];
                array_push($errors, ['code' => 'otp', 'message' => translate('messages.failed_to_send_sms_&_mail')]);
                return response()->json([
                    'errors' => $errors
                ], 405);
            }
        }
        try {
            $mail_status = Helpers::get_mail_status('registration_mail_status_user');
            if (config('mail.status') && $request->email && $mail_status == '1' && Helpers::getNotificationStatusData('customer', 'customer_registration', 'mail_status')) {
                Mail::to($request->email)->send(new \App\Mail\CustomerRegistration($request->f_name . ' ' . $request->l_name));
            }
        } catch (\Exception $ex) {
            info($ex->getMessage());
        }
        if ($request->guest_id && isset($user->id)) {

            $userStoreIds = Cart::where('user_id', $request->guest_id)
                ->join('items', 'carts.item_id', '=', 'items.id')
                ->pluck('items.store_id')
                ->toArray();

            Cart::where('user_id', $user->id)
                ->whereHas('item', function ($query) use ($userStoreIds) {
                    $query->whereNotIn('store_id', $userStoreIds);
                })
                ->delete();

            Cart::where('user_id', $request->guest_id)->update(['user_id' => $user->id, 'is_guest' => 0]);
        }
        return response()->json(['token' => $token, 'is_phone_verified' => 0, 'phone_verify_end_url' => "api/v1/auth/verify-phone"], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $data = [
            'phone' => $request->phone,
            'password' => $request->password
        ];
        $customer_verification = BusinessSetting::where('key', 'customer_verification')->first()->value;
        $firebase_otp_verification = BusinessSetting::where('key', 'firebase_otp_verification')->first()->value??0;
        if (auth()->attempt($data)) {
            $token = auth()->user()->createToken('RestaurantCustomerAuth')->accessToken;
            if (!auth()->user()->status) {
                $errors = [];
                array_push($errors, ['code' => 'auth-003', 'message' => translate('messages.your_account_is_blocked')]);
                return response()->json([
                    'errors' => $errors
                ], 403);
            }
            $user = auth()->user();
            if($customer_verification && !$firebase_otp_verification && !auth()->user()->is_phone_verified && env('APP_MODE') != 'demo')
            {
                $otp_interval_time= 60; //seconds

                $verification_data = DB::table('phone_verifications')->where('phone', $request['phone'])->first();

                if (isset($verification_data) && Carbon::parse($verification_data->updated_at)->DiffInSeconds() < $otp_interval_time) {

                    $time = $otp_interval_time - Carbon::parse($verification_data->updated_at)->DiffInSeconds();
                    $errors = [];
                    array_push($errors, ['code' => 'otp', 'message' => translate('messages.please_try_again_after_') . $time . ' ' . translate('messages.seconds')]);
                    return response()->json([
                        'errors' => $errors
                    ], 405);
                }

                $otp = rand(100000, 999999);
                DB::table('phone_verifications')->updateOrInsert(['phone' => $request['phone']],
                    [
                        'token' => $otp,
                        'otp_hit_count' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                try {
                    $mailResponse = null;
                    if (config('mail.status') && Helpers::get_mail_status('login_otp_mail_status_user') == '1' && Helpers::getNotificationStatusData('customer', 'customer_login_otp', 'mail_status')) {
                        Mail::to($user['email'])->send(new LoginVerification($otp, $user->f_name));
                        $mailResponse = 'success';
                    }
                } catch (\Exception $ex) {
                    $mailResponse = null;
                    info($ex->getMessage());
                }

                $response = null;
                if (Helpers::getNotificationStatusData('customer', 'customer_login_otp', 'sms_status')) {
                    $published_status = addon_published_status('Gateways');
                    if ($published_status == 1) {
                        //for payment and sms gateway addon
                        $response = SmsGateway::send($request['phone'], $otp);
                    } else {
                        $response = SMS_module::send($request['phone'], $otp);
                    }
                }

//                        if(!$user->cm_firebase_token || $user->cm_firebase_token == '@' ){
//                            $user->cm_firebase_token = $request->cm_firebase_token;
//                        DB::table('users')->where('id', $user->id)->update(['cm_firebase_token' => $request->cm_firebase_token]);
//                        }
//                    if(Helpers::getNotificationStatusData('customer','customer_login_otp','push_notification_status')){
//                        if (isset($user->cm_firebase_token)) {
//                            $data = [
//                                'title' => translate('messages.verification_otp'),
//                                'description' => translate('messages.your_verification_otp_is').' '.$otp,
//                                'order_id' => '',
//                                'image' => '',
//                                'type' => 'otp'
//                                ];
//                                Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);
//
//                                DB::table('user_notifications')->insert([
//                                    'data' => json_encode($data),
//                                    'user_id' => $user->id,
//                                    'created_at' => now(),
//                                    'updated_at' => now()
//                                ]);
//                                $response = 'success';
//                            }
//
//                        }


                if ($response !== 'success' && $mailResponse !== 'success') {
                    $errors = [];
                    array_push($errors, ['code' => 'otp', 'message' => translate('messages.failed_to_send_sms_&_mail')]);
                    return response()->json([
                        'errors' => $errors
                    ], 405);
                }


            }
            if ($user->ref_code == null && isset($user->id)) {
                $ref_code = Helpers::generate_referer_code($user);
                DB::table('users')->where('phone', $user->phone)->update(['ref_code' => $ref_code]);
            }
            if ($request->guest_id && isset($user->id)) {

                $userStoreIds = Cart::where('user_id', $request->guest_id)
                    ->join('items', 'carts.item_id', '=', 'items.id')
                    ->pluck('items.store_id')
                    ->toArray();

                Cart::where('user_id', $user->id)
                    ->whereHas('item', function ($query) use ($userStoreIds) {
                        $query->whereNotIn('store_id', $userStoreIds);
                    })
                    ->delete();

                Cart::where('user_id', $request->guest_id)->update(['user_id' => $user->id, 'is_guest' => 0]);
            }
            return response()->json(['token' => $token, 'is_phone_verified' => auth()->user()->is_phone_verified], 200);
        } else {
            $errors = [];
            array_push($errors, ['code' => 'auth-001', 'message' => translate('messages.Unauthorized')]);
            return response()->json([
                'errors' => $errors
            ], 401);
        }
    }
    public function guest_request(Request $request)
    {
        $guest = new Guest();
        $guest->ip_address = $request->ip();
        $guest->fcm_token = $request->fcm_token;

        if ($guest->save()) {
            return response()->json([
                'message' => translate('messages.guest_varified'),
                'guest_id' => $guest->id,
            ], 200);
        }

        return response()->json([
            'message' => translate('messages.failed')
        ], 404);
    }

    public function firebase_auth_verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sessionInfo' => 'required',
            'phoneNumber' => 'required',
            'code' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }


        $webApiKey = BusinessSetting::where('key', 'firebase_web_api_key')->first()->value??'';

//        $firebaseOTPVerification = Helpers::get_business_settings('firebase_otp_verification');
//        $webApiKey = $firebaseOTPVerification ? $firebaseOTPVerification['web_api_key'] : '';

        $response = Http::post('https://identitytoolkit.googleapis.com/v1/accounts:signInWithPhoneNumber?key='. $webApiKey, [
            'sessionInfo' => $request->sessionInfo,
            'phoneNumber' => $request->phoneNumber,
            'code' => $request->code,
        ]);

        $responseData = $response->json();

        if (isset($responseData['error'])) {
            $errors = [];
            $errors[] = ['code' => "403", 'message' => $responseData['error']['message']];
            return response()->json(['errors' => $errors], 403);
        }

        $user = User::Where(['phone' => $request->phoneNumber])->first();

        if (isset($user)){
            if ($request['is_reset_token'] == 1){
                DB::table('password_resets')->updateOrInsert(['email' => $user->email],
                    [
                        'token' => $request->code,
                        'created_at' => now(),
                    ]);
                return response()->json(['message'=>"OTP found, you can proceed"], 200);
            }else{
                if ($user->is_phone_verified) {
                    return response()->json([
                        'message' => translate('messages.phone_number_is_already_varified')
                    ], 200);
                }
                $user->is_phone_verified = 1;
                $user->save();

                return response()->json([
                    'message' => translate('messages.phone_number_varified_successfully'),
                    'otp' => 'inactive'
                ], 200);
            }
        }

        return response()->json([
            'message' => translate('messages.not_found')
        ], 404);
    }




    #handshake
    public function customerLoginFromDrivemond(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'token' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user = User::where('phone', $request->phone)->first();

        if (Helpers::checkSelfExternalConfiguration()) {
            $driveMondBaseUrl = ExternalConfiguration::where('key', 'drivemond_base_url')->first()->value;
            $driveMondToken = ExternalConfiguration::where('key', 'drivemond_token')->first()->value;
            $systemSelfToken = ExternalConfiguration::where('key', 'system_self_token')->first()->value;
            if (!$user){
                $response = Http::withToken($request->token)->post($driveMondBaseUrl . '/api/customer/get-data',
                    [
                        'token' => $driveMondToken,
                        'external_base_url' => url('/'),
                        'external_token' => $systemSelfToken,
                    ]);
                if ($response->successful()) {
                    $drivemondCustomerResponse = $response->json();
                    if ($drivemondCustomerResponse['status']) {
                        $drivemondCustomer = $drivemondCustomerResponse['data'];
                        if (User::where('email', $drivemondCustomer['email'])->first()) {
                            $errors = [];
                            array_push($errors, ['code' => 'email_unique_402', 'message' => translate('messages.Email already exists, Please update mart email and switch 6ammart')]);
                            return response()->json([
                                'errors' => $errors
                            ], 403);
                        }
                        $user = User::create([
                            'f_name' => $drivemondCustomer['first_name'],
                            'l_name' => $drivemondCustomer['last_name'],
                            'email' => $drivemondCustomer['email'],
                            'phone' => $drivemondCustomer['phone'],
                            'password' => $drivemondCustomer['password'],
                            'is_phone_verified' => $drivemondCustomer['phone_verified_at'] ? 1 : 0,
                        ]);
                        $user->ref_code = Helpers::generate_referer_code($user);
                        $user->save();
                    } else {
                        $drivemondCustomer = $drivemondCustomerResponse['data'];
                        if ($drivemondCustomer['error_code'] == 402) {
                            $errors = [];
                            array_push($errors, ['code' => 'drivemond_external_configuration_402', 'message' => translate('messages.Drivemond external authentication failed')]);
                            return response()->json([
                                'errors' => $errors
                            ], 403);
                        }
                    }

                } else {
                    $errors = [];
                    array_push($errors, ['code' => 'drivemond_user_404', 'message' => translate('messages.Drivemond user not found')]);
                    return response()->json([
                        'errors' => $errors
                    ], 403);
                }
            }


            if (Auth::loginUsingId($user->id)) {
                $token = auth()->user()->createToken('RestaurantCustomerAuth')->accessToken;
                if (!auth()->user()->status) {
                    $errors = [];
                    array_push($errors, ['code' => 'auth-003', 'message' => translate('messages.your_account_is_blocked')]);
                    return response()->json([
                        'errors' => $errors
                    ], 403);
                }
                $user = auth()->user();
                if ($user->ref_code == null && isset($user->id)) {
                    $ref_code = Helpers::generate_referer_code($user);
                    DB::table('users')->where('phone', $user->phone)->update(['ref_code' => $ref_code]);
                }
                return response()->json(['token' => $token, 'is_phone_verified' => auth()->user()->is_phone_verified], 200);
            }

            $errors = [];
            array_push($errors, ['code' => 'auth-001', 'message' => translate('messages.Unauthorized')]);
            return response()->json([
                'errors' => $errors
            ], 401);

        }
        $errors = [];
        array_push($errors, ['code' => 'external_config_404', 'message' => translate('messages.External configuration not found')]);
        return response()->json([
            'errors' => $errors
        ], 403);
    }

}
